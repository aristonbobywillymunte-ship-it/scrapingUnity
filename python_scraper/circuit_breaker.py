import time
import os
import redis

class CircuitBreaker:
    """
    Redis-backed Circuit Breaker for outbound platform requests.
    States: CLOSED (normal), OPEN (blocking), HALF_OPEN (testing recovery)
    """
    def __init__(self, platform_name: str, redis_client: redis.Redis, failure_threshold: int = 5, recovery_timeout: int = 30):
        self.platform = platform_name
        self.r = redis_client
        self.failure_threshold = failure_threshold
        self.recovery_timeout = recovery_timeout

    def _state_key(self):
        return f"circuit_breaker:{self.platform}:state"

    def _failure_count_key(self):
        return f"circuit_breaker:{self.platform}:failures"

    def _last_failure_time_key(self):
        return f"circuit_breaker:{self.platform}:last_failure"

    def get_state(self) -> str:
        state = self.r.get(self._state_key())
        if not state:
            return "CLOSED"
        
        if state == "OPEN":
            last_failure = float(self.r.get(self._last_failure_time_key()) or 0)
            if time.time() - last_failure > self.recovery_timeout:
                self.r.set(self._state_key(), "HALF_OPEN")
                return "HALF_OPEN"
        return state

    def allow_request(self) -> bool:
        state = self.get_state()
        if state == "OPEN":
            return False
        return True

    def record_success(self):
        state = self.get_state()
        if state == "HALF_OPEN" or state == "CLOSED":
            self.r.delete(self._failure_count_key())
            self.r.set(self._state_key(), "CLOSED")

    def record_failure(self):
        failures = self.r.incr(self._failure_count_key())
        self.r.set(self._last_failure_time_key(), time.time())
        if failures >= self.failure_threshold:
            self.r.set(self._state_key(), "OPEN")
