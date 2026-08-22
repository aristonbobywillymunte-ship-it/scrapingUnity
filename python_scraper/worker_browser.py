import json
import time
import os
import sys
import redis
from typing import Dict, Any, Optional

from contracts import (
    PlatformEnum,
    OperationEnum,
    ExecutionContract
)
from core import deduplicate_items, redact_secrets
from facebook_parser import FacebookHtmlParser
from transport import FacebookHttpTransport

REDIS_HOST = os.environ.get("REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.environ.get("REDIS_PORT", 6379))
REDIS_BROWSER_QUEUE = os.environ.get("REDIS_BROWSER_QUEUE", "scrape:executions:browser")
HEARTBEAT_KEY = "worker:heartbeat:python_browser_1"
MAX_DOM_SIZE_BYTES = 2 * 1024 * 1024  # 2MB strict DOM size ceiling

class PythonBrowserWorker:
    """
    Dedicated Python Browser Worker (Playwright / Chromium).
    Enforces maximum concurrency <= 1.
    Strictly forbids CAPTCHA/Login bypass, fake accounts, or fingerprint evasion.
    Applies pre-navigation SSRF URL validation, redirect boundary checks, and DOM body ceiling.
    Routes operations accurately to operation-specific parser methods.
    Never exposes raw exception strings in public error payloads.
    """
    def __init__(self, redis_client: Optional[redis.Redis] = None):
        self.r = redis_client or redis.Redis(host=REDIS_HOST, port=REDIS_PORT, db=0, decode_responses=True)
        self.parser = FacebookHtmlParser()
        self.transport = FacebookHttpTransport()
        from circuit_breaker import CircuitBreaker
        from outbound_limiter import OutboundLimiter
        self.transport.cb = CircuitBreaker("facebook", self.r)
        self.transport.limiter = OutboundLimiter("facebook", self.r, 500)
        self.running = True

    def send_heartbeat(self):
        try:
            self.r.setex(HEARTBEAT_KEY, 30, json.dumps({
                "worker_id": "python_browser_worker_1",
                "worker_type": "BROWSER",
                "status": "HEALTHY",
                "concurrency_limit": 1,
                "last_heartbeat": time.time()
            }))
        except Exception:
            pass

    def fetch_with_browser(self, target_url: str, options: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        options = options or {}
        proxy_url = options.get("proxy_url")
        start_time = time.time()

        if hasattr(self.transport, 'cb') and not self.transport.cb.allow_request():
            return {
                "success": False,
                "classification": "CIRCUIT_OPEN",
                "status_code": 503,
                "requested_url": target_url,
                "final_url": target_url,
                "transport_mode": "BROWSER",
                "elapsed_ms": 0,
                "body": None,
                "error_message": "Platform circuit breaker is OPEN due to repeated failures."
            }

        if hasattr(self.transport, 'limiter') and not self.transport.limiter.allow_request():
            return {
                "success": False,
                "classification": "PLATFORM_RATE_LIMITED",
                "status_code": 429,
                "requested_url": target_url,
                "final_url": target_url,
                "transport_mode": "BROWSER",
                "elapsed_ms": 0,
                "body": None,
                "error_message": "Outbound rate limit to Facebook exceeded."
            }

        # 1. Pre-navigation SSRF & Whitelist URL validation
        is_safe, err = self.transport.is_safe_destination(target_url)
        if not is_safe:
            return {
                "success": False,
                "classification": "INVALID_TARGET",
                "status_code": 400,
                "requested_url": target_url,
                "final_url": target_url,
                "transport_mode": "BROWSER",
                "elapsed_ms": 0,
                "body": None,
                "error_message": "Target URL is not in allowed Facebook whitelist or resolves to unsafe IP."
            }

        try:
            from playwright.sync_api import sync_playwright
            with sync_playwright() as p:
                launch_args = {"headless": True}
                if proxy_url:
                    launch_args["proxy"] = {"server": proxy_url}

                browser = p.chromium.launch(**launch_args)
                context = browser.new_context(viewport={"width": 1280, "height": 800})
                page = context.new_page()

                # Navigation with networkidle
                page.goto(target_url, wait_until="networkidle", timeout=15000)
                final_url = page.url

                # Verify final destination didn't redirect to an illegal SSRF target
                final_safe, _ = self.transport.is_safe_destination(final_url)
                if not final_safe:
                    context.close()
                    browser.close()
                    return {
                        "success": False,
                        "classification": "INVALID_TARGET",
                        "status_code": 400,
                        "requested_url": target_url,
                        "final_url": final_url,
                        "transport_mode": "BROWSER",
                        "elapsed_ms": int((time.time() - start_time) * 1000),
                        "body": None,
                        "error_message": "Navigation redirected to unsafe or non-whitelisted host."
                    }

                content = page.content()
                context.close()
                browser.close()

                # 2. Strict DOM body size bound
                content_bytes = len(content.encode('utf-8'))
                if content_bytes > MAX_DOM_SIZE_BYTES:
                    return {
                        "success": False,
                        "classification": "RESPONSE_TOO_LARGE",
                        "status_code": 200,
                        "requested_url": target_url,
                        "final_url": final_url,
                        "transport_mode": "BROWSER",
                        "elapsed_ms": int((time.time() - start_time) * 1000),
                        "body": None,
                        "error_message": f"Browser DOM content size {content_bytes} bytes exceeded {MAX_DOM_SIZE_BYTES} bytes ceiling."
                    }

                elapsed_ms = int((time.time() - start_time) * 1000)
                lower_content = content.lower()

                if "checkpoint" in lower_content or "security check" in lower_content or "captcha" in lower_content:
                    classification = "CHALLENGE"
                elif "login_form" in lower_content or "log into facebook" in lower_content:
                    classification = "LOGIN_REQUIRED"
                else:
                    classification = "SUCCESS"

                if hasattr(self.transport, 'cb'):
                    if classification == "SUCCESS":
                        self.transport.cb.record_success()
                    elif classification in ("CHALLENGE", "LOGIN_REQUIRED"):
                        self.transport.cb.record_failure()

                return {
                    "success": (classification == "SUCCESS"),
                    "classification": classification,
                    "status_code": 200,
                    "requested_url": target_url,
                    "final_url": final_url,
                    "transport_mode": "BROWSER",
                    "elapsed_ms": elapsed_ms,
                    "body": content,
                    "error_message": None if classification == "SUCCESS" else f"Browser encountered {classification}"
                }

        except Exception:
            if hasattr(self.transport, 'cb'):
                self.transport.cb.record_failure()
            elapsed_ms = int((time.time() - start_time) * 1000)
            return {
                "success": False,
                "classification": "NETWORK_ERROR",
                "status_code": 0,
                "requested_url": target_url,
                "final_url": target_url,
                "transport_mode": "BROWSER",
                "elapsed_ms": elapsed_ms,
                "body": None,
                "error_message": "Network or Playwright failure during browser execution."
            }

    def process_one_task(self, timeout: int = 1) -> bool:
        self.send_heartbeat()
        item = self.r.blpop(REDIS_BROWSER_QUEUE, timeout=timeout)
        if not item:
            return False

        _, raw_payload = item
        try:
            data = json.loads(raw_payload)
            contract = ExecutionContract(**data)
            target_val = contract.target.value
            target_url = target_val if target_val.startswith("http") else f"https://www.facebook.com/{target_val}"
            op = contract.operation
            limit = contract.options.limit or 20
            max_items = min(limit, 100)

            # Search / Hashtag requires authenticated session
            if op == OperationEnum.SEARCH_POSTS:
                result = {
                    "status": "FAILED",
                    "classification": "UNSUPPORTED",
                    "status_code": 403,
                    "transport_mode": "BROWSER",
                    "elapsed_ms": 0,
                    "error": "Unauthenticated public search is unsupported on Facebook browser edge.",
                    "items": [],
                    "count": 0
                }
            else:
                res = self.fetch_with_browser(target_url, {
                    "proxy_url": getattr(contract.options, "proxy_url", None)
                })

                if not res["success"]:
                    result = {
                        "status": "FAILED",
                        "classification": res["classification"],
                        "status_code": res["status_code"],
                        "transport_mode": "BROWSER",
                        "elapsed_ms": res["elapsed_ms"],
                        "error": res["error_message"],
                        "items": [],
                        "count": 0
                    }
                else:
                    body_html = res["body"] or ""
                    final_url = res["final_url"]

                    if op == OperationEnum.PROFILE:
                        parsed_records = self.parser.parse_profile(body_html, final_url)
                    elif op == OperationEnum.REPLIES:
                        parsed_records = self.parser.parse_comments(body_html, final_url)
                    else:
                        parsed_records = self.parser.parse_posts(body_html, final_url)

                    parsed_records = parsed_records[:max_items]
                    result = {
                        "status": "COMPLETED" if parsed_records else "PARTIAL",
                        "classification": "SUCCESS",
                        "status_code": 200,
                        "transport_mode": "BROWSER",
                        "elapsed_ms": res["elapsed_ms"],
                        "items": deduplicate_items(parsed_records),
                        "count": len(parsed_records)
                    }

            import db
            import hashlib
            fingerprint_payload = [
                contract.platform.value,
                contract.operation.value,
                contract.target.type.value,
                contract.target.value,
                contract.options.model_dump(exclude_none=True) if contract.options else {}
            ]
            fingerprint = hashlib.sha256(json.dumps(fingerprint_payload).encode('utf-8')).hexdigest()
            db.persist_execution_result(contract.execution_id, fingerprint, result)

            result_key = f"execution:result:{contract.execution_id}"
            self.r.setex(result_key, 3600, json.dumps(result))
            return True

        except Exception as e:
            redacted_err = redact_secrets(str(e))
            print(f"Error processing browser execution: {redacted_err}", file=sys.stderr)
            return False

    def run(self):
        print("Python Browser Worker listening on Redis queue:", REDIS_BROWSER_QUEUE)
        while self.running:
            self.process_one_task(timeout=2)

if __name__ == "__main__":
    worker = PythonBrowserWorker()
    worker.run()
