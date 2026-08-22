import time
import redis

class OutboundLimiter:
    """
    Redis-backed Outbound Platform Limiter.
    Enforces a strict global maximum RPM to a specific platform (e.g., Facebook).
    Uses a simple sliding window or fixed window counter.
    """
    def __init__(self, platform_name: str, redis_client: redis.Redis, rpm_limit: int = 500):
        self.platform = platform_name
        self.r = redis_client
        self.rpm_limit = rpm_limit

    def _key(self):
        # Minute window key
        current_minute = int(time.time() / 60)
        return f"outbound_limit:{self.platform}:{current_minute}"

    def allow_request(self) -> bool:
        key = self._key()
        count = self.r.incr(key)
        if count == 1:
            self.r.expire(key, 120)
        
        if count > self.rpm_limit:
            return False
        return True
