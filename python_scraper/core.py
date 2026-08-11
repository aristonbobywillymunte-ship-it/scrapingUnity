import hashlib
from typing import Dict, Any, List
import re

def compute_item_hash(item: Dict[str, Any]) -> str:
    """
    Offline implementation-neutral fallback fingerprint 
    using external_id, canonical_url, or text content.
    """
    if item.get("external_id"):
        base = item["external_id"]
    elif item.get("canonical_url"):
        base = item["canonical_url"]
    else:
        base = item.get("text", "")
        
    return hashlib.sha256(str(base).encode('utf-8')).hexdigest()

def deduplicate_items(items: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    seen = set()
    deduped = []
    for item in items:
        h = compute_item_hash(item)
        if h not in seen:
            seen.add(h)
            deduped.append(item)
    return deduped

def redact_secrets(payload: str) -> str:
    """
    Redact cookies, auth tokens, and passwords from logs/diagnostics
    """
    if not payload:
        return payload
    
    # Redact Authorization header values
    payload = re.sub(r'(?i)(authorization:\s*)[^\n]+', r'\1[REDACTED]', payload)
    
    # Redact Cookies
    payload = re.sub(r'(?i)(cookie:\s*)[^\n]+', r'\1[REDACTED]', payload)
    
    # Redact URL credentials (http://user:pass@host)
    payload = re.sub(r'(https?://)[^@]+@', r'\1[REDACTED]@[REDACTED]@', payload)
    
    # Redact Laravel APP_KEY
    payload = re.sub(r'(?i)(app_key[=:])\s*[a-zA-Z0-9+/=]+', r'\1[REDACTED]', payload)
    
    return payload

class PaginationState:
    def __init__(self, limit: int, max_pages: int = 5):
        self.limit = limit
        self.max_pages = max_pages
        self.current_page = 0
        self.items_collected = 0
        self.seen_cursors = set()
        
    def next_page(self, cursor: str, new_items_count: int) -> bool:
        """Returns True if should continue, False if stop"""
        self.current_page += 1
        self.items_collected += new_items_count
        
        if self.items_collected >= self.limit:
            return False
            
        if self.current_page >= self.max_pages:
            return False
            
        if not cursor:
            return False
            
        if cursor in self.seen_cursors:
            return False # Duplicate cursor detected
            
        self.seen_cursors.add(cursor)
        return True

def calculate_backoff(attempt: int) -> float:
    """Mock exponential backoff with jitter limits"""
    # 2^attempt, capped to some max
    return min(2 ** attempt, 30.0)

def should_retry(classification: str, attempts: int, max_attempts: int = 3) -> bool:
    from parser import ErrorClassification
    
    # Hard stops
    if classification in [
        ErrorClassification.AUTH_REQUIRED,
        ErrorClassification.CHALLENGE_PRESENT,
        ErrorClassification.ACCESS_RESTRICTED,
        ErrorClassification.PARSING_FAILED,
        ErrorClassification.UPSTREAM_ERROR
    ]:
        return False
        
    # Retryable transient
    if classification in [
        ErrorClassification.NETWORK_ERROR,
        ErrorClassification.RATE_LIMITED,
        ErrorClassification.PROXY_UNAVAILABLE
    ]:
        if attempts < max_attempts:
            return True
            
    return False
