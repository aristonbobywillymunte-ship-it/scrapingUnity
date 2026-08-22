import urllib.request
import urllib.parse
import urllib.error
import ssl
import time
import socket
import re
from typing import Dict, Any, Optional

ALLOWED_FACEBOOK_HOSTS = {
    "facebook.com",
    "www.facebook.com",
    "m.facebook.com",
    "mbasic.facebook.com",
    "touch.facebook.com"
}

DISALLOWED_IP_PATTERNS = [
    re.compile(r"^127\."),
    re.compile(r"^10\."),
    re.compile(r"^172\.(1[6-9]|2[0-9]|3[0-1])\."),
    re.compile(r"^192\.168\."),
    re.compile(r"^169\.254\."),
    re.compile(r"^0\."),
    re.compile(r"^::1$"),
    re.compile(r"^localhost$")
]

class FacebookHttpTransport:
    """
    Self-hosted HTTP transport for Facebook public content.
    Includes strict SSRF protection, redirect verification, response bounds, and classification.
    """
    MAX_RESPONSE_BYTES = 2 * 1024 * 1024  # 2MB max response bound
    DEFAULT_TIMEOUT = 10  # seconds

    def validate_and_normalize_url(self, target: str) -> Optional[str]:
        if not target or not isinstance(target, str):
            return None
        target = target.strip()
        if not target.startswith("http://") and not target.startswith("https://"):
            if re.match(r"^[a-zA-Z0-9._-]+$", target):
                target = f"https://www.facebook.com/{target}"
            else:
                return None

        parsed = urllib.parse.urlparse(target)
        if parsed.scheme.lower() not in ("http", "https"):
            return None

        host = (parsed.hostname or "").lower()
        if host not in ALLOWED_FACEBOOK_HOSTS:
            return None

        for pat in DISALLOWED_IP_PATTERNS:
            if pat.match(host):
                return None

        return target

    def classify_response(self, status_code: int, body_str: str) -> str:
        if status_code == 404:
            return "NOT_FOUND"
        if status_code == 429:
            return "RATE_LIMITED"
        if status_code in (401, 403):
            return "BLOCKED"

        lower_body = body_str.lower()
        if "checkpoint" in lower_body or "security check" in lower_body or "captcha" in lower_body:
            return "CHALLENGE"
        if "login_form" in lower_body or ("log into facebook" in lower_body and 'content="profile"' not in lower_body):
            return "LOGIN_REQUIRED"
        if "this content isn't available right now" in lower_body or "the link you followed may be broken" in lower_body:
            return "NOT_FOUND"
        if "temporarily blocked" in lower_body or "rate limit exceeded" in lower_body:
            return "RATE_LIMITED"

        if 200 <= status_code < 300:
            return "SUCCESS"
        return "BLOCKED"

    def fetch(self, target: str, options: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        options = options or {}
        validated_url = self.validate_and_normalize_url(target)

        if not validated_url:
            return {
                "success": False,
                "classification": "INVALID_TARGET",
                "status_code": 400,
                "requested_url": target,
                "final_url": target,
                "transport_mode": "HTTP",
                "elapsed_ms": 0,
                "error_code": "SSRF_REJECTED",
                "error_message": "Target host is not an allowed Facebook public host.",
                "body": None,
                "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
            }

        start_time = time.time()
        timeout = min(15, options.get("timeout", self.DEFAULT_TIMEOUT))
        proxy_url = options.get("proxy_url")

        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language": "en-US,en;q=0.9,id;q=0.8",
            "Sec-Fetch-Dest": "document",
            "Sec-Fetch-Mode": "navigate",
            "Sec-Fetch-Site": "none",
            "Sec-Fetch-User": "?1",
            "Upgrade-Insecure-Requests": "1"
        }

        try:
            req = urllib.request.Request(validated_url, headers=headers)
            handlers = []

            if proxy_url:
                handlers.append(urllib.request.ProxyHandler({'http': proxy_url, 'https': proxy_url}))

            ctx = ssl.create_default_context()
            handlers.append(urllib.request.HTTPSHandler(context=ctx))
            opener = urllib.request.build_opener(*handlers)

            with opener.open(req, timeout=timeout) as response:
                final_url = response.geturl()
                # Re-validate redirect destination
                if not self.validate_and_normalize_url(final_url):
                    return {
                        "success": False,
                        "classification": "BLOCKED",
                        "status_code": 403,
                        "requested_url": target,
                        "final_url": final_url,
                        "transport_mode": "HTTP",
                        "elapsed_ms": int((time.time() - start_time) * 1000),
                        "error_code": "REDIRECT_SSRF_REJECTED",
                        "error_message": "Redirected to forbidden host.",
                        "body": None,
                        "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
                    }

                status_code = response.getcode()
                raw_bytes = response.read(self.MAX_RESPONSE_BYTES + 1)
                if len(raw_bytes) > self.MAX_RESPONSE_BYTES:
                    return {
                        "success": False,
                        "classification": "RESPONSE_TOO_LARGE",
                        "status_code": status_code,
                        "requested_url": target,
                        "final_url": final_url,
                        "transport_mode": "HTTP",
                        "elapsed_ms": int((time.time() - start_time) * 1000),
                        "error_code": "RESPONSE_TOO_LARGE",
                        "error_message": f"Response exceeded {self.MAX_RESPONSE_BYTES} byte bound.",
                        "body": None,
                        "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
                    }

                body_str = raw_bytes.decode('utf-8', errors='replace')
                elapsed_ms = int((time.time() - start_time) * 1000)
                classification = self.classify_response(status_code, body_str)

                return {
                    "success": (classification == "SUCCESS"),
                    "classification": classification,
                    "status_code": status_code,
                    "requested_url": target,
                    "final_url": final_url,
                    "transport_mode": "HTTP",
                    "elapsed_ms": elapsed_ms,
                    "error_code": None if classification == "SUCCESS" else classification,
                    "error_message": None if classification == "SUCCESS" else f"Facebook response classified as {classification}",
                    "body": body_str,
                    "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
                }

        except urllib.error.HTTPError as e:
            elapsed_ms = int((time.time() - start_time) * 1000)
            err_body = e.read(self.MAX_RESPONSE_BYTES).decode('utf-8', errors='replace') if e.fp else ""
            classification = self.classify_response(e.code, err_body)
            return {
                "success": False,
                "classification": classification,
                "status_code": e.code,
                "requested_url": target,
                "final_url": target,
                "transport_mode": "HTTP",
                "elapsed_ms": elapsed_ms,
                "error_code": classification,
                "error_message": f"HTTP error {e.code}: {e.reason}",
                "body": err_body,
                "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
            }

        except (urllib.error.URLError, socket.timeout) as e:
            elapsed_ms = int((time.time() - start_time) * 1000)
            return {
                "success": False,
                "classification": "NETWORK_ERROR",
                "status_code": 0,
                "requested_url": target,
                "final_url": target,
                "transport_mode": "HTTP",
                "elapsed_ms": elapsed_ms,
                "error_code": "NETWORK_ERROR",
                "error_message": f"Connection error: {str(e)}",
                "body": None,
                "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
            }
