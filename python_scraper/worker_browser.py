import json
import time
import os
import sys
import redis
from typing import Dict, Any, Optional

from contracts import (
    PlatformEnum,
    OperationEnum,
    ExecutionContract,
    NormalizedItem,
    Author
)
from core import deduplicate_items, redact_secrets
from facebook_parser import FacebookHtmlParser

REDIS_HOST = os.environ.get("REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.environ.get("REDIS_PORT", 6379))
REDIS_BROWSER_QUEUE = os.environ.get("REDIS_BROWSER_QUEUE", "scrape:executions:browser")
HEARTBEAT_KEY = "worker:heartbeat:python_browser_1"
APP_ENV = os.environ.get("APP_ENV", "production")

class PythonBrowserWorker:
    """
    Dedicated Python Browser Worker (Playwright / Chromium).
    Enforces maximum concurrency <= 1.
    Strictly forbids CAPTCHA/Login bypass, fake accounts, or fingerprint evasion.
    If challenge/login is detected, safely classifies and returns the state without synthetic output.
    """
    def __init__(self, redis_client: Optional[redis.Redis] = None):
        self.r = redis_client or redis.Redis(host=REDIS_HOST, port=REDIS_PORT, db=0, decode_responses=True)
        self.parser = FacebookHtmlParser()
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

        try:
            from playwright.sync_api import sync_playwright
            with sync_playwright() as p:
                launch_args = {"headless": True}
                if proxy_url:
                    launch_args["proxy"] = {"server": proxy_url}

                browser = p.chromium.launch(**launch_args)
                context = browser.new_context(viewport={"width": 1280, "height": 800})
                page = context.new_page()

                page.goto(target_url, wait_until="networkidle", timeout=15000)
                content = page.content()
                final_url = page.url
                context.close()
                browser.close()

                elapsed_ms = int((time.time() - start_time) * 1000)
                lower_content = content.lower()

                if "checkpoint" in lower_content or "security check" in lower_content or "captcha" in lower_content:
                    classification = "CHALLENGE"
                elif "login_form" in lower_content or "log into facebook" in lower_content:
                    classification = "LOGIN_REQUIRED"
                else:
                    classification = "SUCCESS"

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

        except Exception as e:
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
                "error_message": f"Browser navigation error: {str(e)}"
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
                items = self.parser.parse_posts(res["body"] or "", res["final_url"])
                result = {
                    "status": "COMPLETED" if items else "PARTIAL",
                    "classification": "SUCCESS",
                    "status_code": 200,
                    "transport_mode": "BROWSER",
                    "elapsed_ms": res["elapsed_ms"],
                    "items": deduplicate_items(items),
                    "count": len(items)
                }

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
