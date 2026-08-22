import pytest
from transport import FacebookHttpTransport

def test_validate_and_normalize_url():
    t = FacebookHttpTransport()
    assert t.validate_and_normalize_url("zuck") == "https://www.facebook.com/zuck"
    assert t.validate_and_normalize_url("https://www.facebook.com/zuck") == "https://www.facebook.com/zuck"
    assert t.validate_and_normalize_url("https://m.facebook.com/zuck") == "https://m.facebook.com/zuck"

def test_reject_ssrf():
    t = FacebookHttpTransport()
    assert t.validate_and_normalize_url("http://127.0.0.1:8000") is None
    assert t.validate_and_normalize_url("http://localhost:8000") is None
    assert t.validate_and_normalize_url("http://169.254.169.254/latest/meta-data") is None
    assert t.validate_and_normalize_url("https://evil.com/facebook.com") is None

    res = t.fetch("http://127.0.0.1:8000")
    assert res["success"] is False
    assert res["classification"] == "INVALID_TARGET"
    assert res["error_code"] == "SSRF_REJECTED"

def test_classify_response():
    t = FacebookHttpTransport()
    assert t.classify_response(404, "") == "NOT_FOUND"
    assert t.classify_response(429, "") == "RATE_LIMITED"
    assert t.classify_response(403, "") == "BLOCKED"
    assert t.classify_response(200, "Please complete the security check captcha") == "CHALLENGE"
    assert t.classify_response(200, "Log into Facebook to see this") == "LOGIN_REQUIRED"
    assert t.classify_response(200, "<html><head><meta property=\"og:title\" content=\"Zuck\"></head></html>") == "SUCCESS"
