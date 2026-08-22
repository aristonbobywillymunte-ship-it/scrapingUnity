import pytest
from datetime import datetime
from pydantic import ValidationError
from contracts import (
    ExecutionContract, PlatformEnum, OperationEnum, TargetTypeEnum,
    NormalizedItem, Author, Target
)
from validators import (
    validate_username, validate_url, validate_id,
    validate_keyword, validate_hashtag, validate_ssrf
)
from parser import OfflineFacebookParser, ErrorClassification
from core import (
    deduplicate_items, redact_secrets, PaginationState,
    should_retry, calculate_backoff, compute_item_hash
)
import json
import os

# --- 1. Target Validation & SSRF Tests ---
def test_target_validation():
    assert validate_username("validuser") == True
    assert validate_username("user\x00name") == False # Control char
    assert validate_url("https://facebook.com/example") == True
    assert validate_url("http://facebook.com/example") == False # Must be HTTPS
    assert validate_url("https://evilfacebook.com/example") == False # Deceptive
    assert validate_id("123456789") == True
    assert validate_keyword("IKN") == True
    assert validate_hashtag("#IKN") == "IKN"
    assert validate_hashtag("IKN") == "IKN"

def test_ssrf_validation():
    assert validate_ssrf("https://facebook.com/profile") == True
    assert validate_ssrf("https://127.0.0.1/admin") == False
    assert validate_ssrf("https://169.254.169.254/metadata") == False
    assert validate_ssrf("https://user:pass@facebook.com/") == False

# --- 2. Execution Contract Tests ---
def test_execution_contract():
    # Valid Contract
    contract = ExecutionContract(
        execution_id="123",
        platform=PlatformEnum.FACEBOOK,
        operation=OperationEnum.PROFILE,
        target=Target(type=TargetTypeEnum.USERNAME, value="example"),
        options={"limit": 10, "mode": "http"}
    )
    assert contract.platform == "facebook"

    # Invalid Target Type for Operation
    with pytest.raises(ValidationError):
        ExecutionContract(
            execution_id="123",
            platform=PlatformEnum.FACEBOOK,
            operation=OperationEnum.PROFILE,
            target=Target(type=TargetTypeEnum.KEYWORD, value="example"),
            options={"limit": 10, "mode": "http"}
        )

# --- 3. Parser Fixture & Matrix Tests ---
@pytest.fixture
def parser():
    return OfflineFacebookParser()

def load_fixture(name):
    path = os.path.join(os.path.dirname(__file__), "fixtures/facebook", name)
    with open(path, "r") as f:
        return json.load(f)

def test_parser_matrix_profile(parser):
    fixture = load_fixture("profile_success.json")
    status, result = parser.parse_fixture(fixture)
    assert status == ErrorClassification.NORMAL
    assert len(result["items"]) == 1

def test_parser_matrix_auth_required(parser):
    fixture = load_fixture("profile_auth_required.json")
    status, result = parser.parse_fixture(fixture)
    assert status == ErrorClassification.AUTH_REQUIRED

def test_parser_matrix_single_post(parser):
    fixture = load_fixture("single_post_success.json")
    status, result = parser.parse_fixture(fixture)
    assert status == ErrorClassification.NORMAL
    assert result["items"][0]["post_id"] == "98765"

# --- 4. Normalization (Zero/Null & Media) Tests ---
def test_normalization_zero_null():
    item = NormalizedItem(
        platform="facebook",
        external_id="ext_test_123",
        canonical_url="https://facebook.com/posts/123",
        content_type="post",
        author=Author(external_id="123"),
        metrics={"likes": 50, "shares": 0, "comments": None, "invalid": "NaN"},
        collected_at=datetime.utcnow().isoformat(),
        parser_version="1.0.0"
    )
    assert item.metrics["likes"] == 50
    assert item.metrics["shares"] == 0
    assert item.metrics["comments"] is None
    assert item.metrics["invalid"] is None # Cast to null

# --- 5. Pagination & Deduplication Tests ---
def test_pagination_state():
    state = PaginationState(limit=10, max_pages=3)
    assert state.next_page("cursor1", 5) == True
    assert state.next_page("cursor1", 0) == False # Duplicate cursor

    state2 = PaginationState(limit=10)
    assert state2.next_page("cursorA", 10) == False # Limit reached

def test_deduplication():
    items = [
        {"external_id": "1", "text": "A"},
        {"external_id": "1", "text": "B"}, # Duplicate ID
        {"canonical_url": "url", "text": "C"},
        {"canonical_url": "url", "text": "D"} # Duplicate URL
    ]
    deduped = deduplicate_items(items)
    assert len(deduped) == 2

# --- 6. Error/Stop/Retry Tests ---
def test_retry_logic():
    assert should_retry(ErrorClassification.NETWORK_ERROR, attempts=1) == True
    assert should_retry(ErrorClassification.AUTH_REQUIRED, attempts=1) == False
    assert should_retry(ErrorClassification.NETWORK_ERROR, attempts=3) == False # Max attempts

    backoff = calculate_backoff(2)
    assert backoff == 4.0

# --- 7. Secret Redaction Tests ---
def test_secret_redaction():
    payload = "Authorization: Bearer SECRET_TOKEN\nCookie: session_id=12345\nhttps://user:pass@facebook.com"
    redacted = redact_secrets(payload)
    assert "SECRET_TOKEN" not in redacted
    assert "session_id" not in redacted
    assert "user:pass" not in redacted
    assert "[REDACTED]@[REDACTED]@" in redacted

# --- 8. Network Guard ---
def test_no_network_access():
    import socket
    original_socket = socket.socket

    def guard(*args, **kwargs):
        raise RuntimeError("External network access forbidden in Phase B!")

    socket.socket = guard
    try:
        # Simulate test running safely without sockets
        assert validate_ssrf("https://facebook.com") == True
    finally:
        socket.socket = original_socket

