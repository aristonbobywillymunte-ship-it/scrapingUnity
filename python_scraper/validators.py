import re
from typing import Optional
from urllib.parse import urlparse

def validate_username(username: str) -> bool:
    if not username or not username.strip():
        return False
    if re.search(r'[\x00-\x1F]', username):  # No control chars
        return False
    if len(username) > 100: # Bounded length
        return False
    return True

def validate_url(url: str) -> bool:
    if not url:
        return False
    parsed = urlparse(url)
    if parsed.scheme != "https":
        return False
    if parsed.hostname not in ["facebook.com", "www.facebook.com"]:
        return False
    if parsed.username or parsed.password:
        return False
    if parsed.fragment and not parsed.path: # Just fragment trick
        return False
    return True

def validate_id(id_val: str) -> bool:
    if not id_val or not id_val.strip():
        return False
    if len(id_val) > 100:
        return False
    return True

def validate_keyword(keyword: str) -> bool:
    if not keyword or not keyword.strip():
        return False
    if re.search(r'[\x00-\x1F]', keyword):
        return False
    if len(keyword) > 200:
        return False
    return True

def validate_hashtag(hashtag: str) -> str:
    """Returns canonical hashtag or empty string if invalid"""
    if not hashtag or not hashtag.strip():
        return ""
    hashtag = hashtag.strip()
    if hashtag.startswith("#"):
        hashtag = hashtag[1:]
    if not hashtag or len(hashtag) > 100:
        return ""
    return hashtag

def validate_ssrf(url: str) -> bool:
    """
    Deterministic SSRF test (No real DNS for Phase B)
    """
    if not url:
        return False
    parsed = urlparse(url)
    
    # Must be HTTPS
    if parsed.scheme != "https":
        return False
    
    # No credentials
    if parsed.username or parsed.password:
        return False
        
    hostname = parsed.hostname
    if not hostname:
        return False
        
    # Check for raw IPs or loopback
    ip_patterns = [
        r"^127\.",
        r"^10\.",
        r"^192\.168\.",
        r"^172\.(1[6-9]|2[0-9]|3[0-1])\.",
        r"^169\.254\.", # Link local
        r"\[::1\]", # IPv6 loopback
    ]
    if any(re.search(p, hostname) for p in ip_patterns):
        return False
        
    # Deceptive suffix/subdomains
    if hostname not in ["facebook.com", "www.facebook.com"]:
        return False
        
    return True
