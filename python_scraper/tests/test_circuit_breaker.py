import pytest
import time
import fakeredis
from circuit_breaker import CircuitBreaker

def test_circuit_breaker_flow():
    r = fakeredis.FakeRedis(decode_responses=True)
    cb = CircuitBreaker("facebook", r, failure_threshold=2, recovery_timeout=1)

    assert cb.get_state() == "CLOSED"
    assert cb.allow_request() is True

    # 1st failure
    cb.record_failure()
    assert cb.get_state() == "CLOSED"

    # 2nd failure trips it
    cb.record_failure()
    assert cb.get_state() == "OPEN"
    assert cb.allow_request() is False

    # Wait for recovery
    time.sleep(1.1)
    
    # State transitions to HALF_OPEN when queried
    assert cb.get_state() == "HALF_OPEN"
    assert cb.allow_request() is True

    # Success in HALF_OPEN resets to CLOSED
    cb.record_success()
    assert cb.get_state() == "CLOSED"
    assert int(r.get(cb._failure_count_key()) or 0) == 0

