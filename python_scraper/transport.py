import urllib.request
import urllib.parse
import urllib.error
import ssl
import time
import socket
import ipaddress
import redis
import os
from typing import Dict, Any, Optional, Tuple
from circuit_breaker import CircuitBreaker

ALLOWED_FACEBOOK_HOSTS = {
    "facebook.com",
    "www.facebook.com",
    "m.facebook.com",
    "mbasic.facebook.com",
    "touch.facebook.com"
}

REDIS_HOST = os.environ.get("REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.environ.get("REDIS_PORT", 6379))

class SafeRedirectHandler(urllib.request.HTTPRedirectHandler):
    """
    Custom Redirect Handler that validates each redirect hop against SSRF & host rules
    BEFORE making the request to the redirected destination.
    """
    def __init__(self, validator_fn):
        super().__init__()
        self.validator_fn = validator_fn

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        is_safe, err = self.validator_fn(newurl)
        if not is_safe:
            raise urllib.error.HTTPError(
                newurl, 403, "SSRF_REDIRECT_REJECTED", headers, fp
            )
        return super().redirect_request(req, fp, code, msg, headers, newurl)

class FacebookHttpTransport:
    """
    Self-hosted HTTP transport for Facebook public content.
    Includes strict ipaddress-based SSRF protection, pre-request redirect hop validation,
    exact response byte ceiling, sanitized errors, and accurate response classification.
    """
    MAX_RESPONSE_BYTES = 2 * 1024 * 1024  # 2MB max response bound
    DEFAULT_TIMEOUT = 10  # seconds

    def is_safe_destination(self, url: str) -> Tuple[bool, Optional[str]]:
        if not url or not isinstance(url, str):
            return False, "Invalid URL format"
        url = url.strip()
        if not url.startswith("http://") and not url.startswith("https://"):
            return False, "Invalid protocol scheme"

        parsed = urllib.parse.urlparse(url)
        if parsed.scheme.lower() not in ("http", "https"):
            return False, "Unsupported protocol"

        host = (parsed.hostname or "").lower()
        if not host or host not in ALLOWED_FACEBOOK_HOSTS:
            return False, "Host not in allowed Facebook whitelist"

        # DNS resolution & IP safety check
        try:
            addr_info = socket.getaddrinfo(host, parsed.port or (443 if parsed.scheme == "https" else 80), proto=socket.IPPROTO_TCP)
            for item in addr_info:
                ip_str = item[4][0]
                ip_obj = ipaddress.ip_address(ip_str)
                if ip_obj.is_loopback or ip_obj.is_private or ip_obj.is_link_local or ip_obj.is_multicast or ip_obj.is_reserved or ip_obj.is_unspecified:
                    return False, "Target resolved to private or reserved IP address"
        except socket.gaierror:
            return False, "DNS resolution failed"
        except Exception:
            return False, "Target address validation failed"

        return True, None

    def validate_and_normalize_url(self, target: str) -> Optional[str]:
        if not target or not isinstance(target, str):
            return None
        target = target.strip()
        if not target.startswith("http://") and not target.startswith("https://"):
            if target.isalnum() or target.replace("_", "").replace(".", "").replace("-", "").isalnum():
                target = f"https://www.facebook.com/{target}"
            else:
                return None

        is_safe, _ = self.is_safe_destination(target)
        return target if is_safe else None

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
        
        # Check Circuit Breaker & Rate Limiter
        if hasattr(self, 'cb') and not self.cb.allow_request():
            return {
                "success": False,
                "classification": "CIRCUIT_OPEN",
                "status_code": 503,
                "requested_url": target,
                "final_url": target,
                "transport_mode": "HTTP",
                "elapsed_ms": 0,
                "error_code": "CIRCUIT_OPEN",
                "error_message": "Platform circuit breaker is OPEN due to repeated failures.",
                "body": None,
                "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
            }

        if hasattr(self, 'limiter') and not self.limiter.allow_request():
            return {
                "success": False,
                "classification": "PLATFORM_RATE_LIMITED",
                "status_code": 429,
                "requested_url": target,
                "final_url": target,
                "transport_mode": "HTTP",
                "elapsed_ms": 0,
                "error_code": "PLATFORM_RATE_LIMITED",
                "error_message": "Outbound rate limit to Facebook exceeded.",
                "body": None,
                "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
            }

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
                "error_message": "Target host is not an allowed Facebook public host or resolves to unsafe IP.",
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
            handlers = [SafeRedirectHandler(self.is_safe_destination)]

            if proxy_url:
                handlers.append(urllib.request.ProxyHandler({'http': proxy_url, 'https': proxy_url}))

            ctx = ssl.create_default_context()
            handlers.append(urllib.request.HTTPSHandler(context=ctx))
            opener = urllib.request.build_opener(*handlers)

            with opener.open(req, timeout=timeout) as response:
                final_url = response.geturl()
                status_code = response.getcode()

                content_len = response.headers.get('Content-Length')
                if content_len and int(content_len) > self.MAX_RESPONSE_BYTES:
                    return {
                        "success": False,
                        "classification": "RESPONSE_TOO_LARGE",
                        "status_code": status_code,
                        "requested_url": target,
                        "final_url": final_url,
                        "transport_mode": "HTTP",
                        "elapsed_ms": int((time.time() - start_time) * 1000),
                        "error_code": "RESPONSE_TOO_LARGE",
                        "error_message": f"Response header exceeded {self.MAX_RESPONSE_BYTES} bytes.",
                        "body": None,
                        "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
                    }

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
                        "error_message": f"Response stream exceeded {self.MAX_RESPONSE_BYTES} bytes bound.",
                        "body": None,
                        "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
                    }

                body_str = raw_bytes.decode('utf-8', errors='replace')
                elapsed_ms = int((time.time() - start_time) * 1000)
                classification = self.classify_response(status_code, body_str)

                if hasattr(self, 'cb'):
                    if classification == "SUCCESS" or classification == "NOT_FOUND":
                        self.cb.record_success()
                    elif classification == "RATE_LIMITED" or classification == "BLOCKED":
                        self.cb.record_failure()

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
            if hasattr(self, 'cb'):
                if classification == "NOT_FOUND":
                    self.cb.record_success()
                else:
                    self.cb.record_failure()

            return {
                "success": False,
                "classification": classification,
                "status_code": e.code,
                "requested_url": target,
                "final_url": target,
                "transport_mode": "HTTP",
                "elapsed_ms": elapsed_ms,
                "error_code": classification,
                "error_message": f"HTTP response error {e.code}",
                "body": err_body,
                "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
            }

        except (urllib.error.URLError, socket.timeout):
            if hasattr(self, 'cb'):
                self.cb.record_failure()
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
                "error_message": "Network connection error during upstream transport.",
                "body": None,
                "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
            }
        except Exception:
            elapsed_ms = int((time.time() - start_time) * 1000)
            return {
                "success": False,
                "classification": "NETWORK_ERROR",
                "status_code": 0,
                "requested_url": target,
                "final_url": target,
                "transport_mode": "HTTP",
                "elapsed_ms": elapsed_ms,
                "error_code": "TRANSPORT_ERROR",
                "error_message": "Internal transport failure during execution.",
                "body": None,
                "fetched_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
            }
