import json
import os
import sys
import time
from typing import Any, Dict, Optional

import redis

from validator import validate_candidate_selectors

REDIS_HOST = os.environ.get("REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.environ.get("REDIS_PORT", 6379))
REQUEST_QUEUE = os.environ.get("PARSER_VALIDATION_QUEUE", "queue:parser_validation")
RESULT_QUEUE_PREFIX = os.environ.get("PARSER_VALIDATION_RESULT_PREFIX", "queue:parser_validation:results:")
HEARTBEAT_KEY = os.environ.get("PARSER_VALIDATION_HEARTBEAT_KEY", "worker:heartbeat:python_validator_1")
APP_ENV = os.environ.get("APP_ENV", "production")


class PythonValidatorWorker:
    def __init__(self, redis_client: Optional[redis.Redis] = None):
        self.r = redis_client or redis.Redis(host=REDIS_HOST, port=REDIS_PORT, db=0, decode_responses=True)
        self.running = True

    def send_heartbeat(self) -> None:
        payload = {
            "worker_id": "python_validator_worker_1",
            "worker_type": "VALIDATOR",
            "status": "HEALTHY",
            "queue": REQUEST_QUEUE,
            "last_heartbeat": time.time(),
        }
        try:
            self.r.setex(HEARTBEAT_KEY, 30, json.dumps(payload))
        except Exception as exc:
            print(f"validator heartbeat failed: {exc}", file=sys.stderr)

    def _load_sample_html(self, request: Dict[str, Any]) -> str:
        sample_html = request.get("sample_html") or ""
        sample_path = request.get("sample_path") or ""
        if sample_html.strip():
            return sample_html
        if sample_path:
            with open(sample_path, "r", encoding="utf-8") as handle:
                return handle.read()
        return ""

    def process_one_task(self, timeout: int = 1) -> bool:
        self.send_heartbeat()
        item = self.r.blpop(REQUEST_QUEUE, timeout=timeout)
        if not item:
            return False

        _, raw_payload = item
        candidate_id = None
        started = time.time()
        try:
            request = json.loads(raw_payload)
            candidate_id = str(request["candidate_id"])
            selectors = request.get("selectors")
            if not isinstance(selectors, dict):
                raise ValueError("selectors must be an object")

            sample_html = self._load_sample_html(request)
            validation = validate_candidate_selectors(json.dumps(selectors), sample_html)
            validation["candidate_id"] = candidate_id
            validation["elapsed_ms"] = int((time.time() - started) * 1000)
            validation["proven"] = bool(sample_html.strip())

            if not sample_html.strip():
                validation["is_valid"] = False
                validation["coverage_score"] = 0.0
                validation["error"] = "No sample HTML provided"

            result_key = f"{RESULT_QUEUE_PREFIX}{candidate_id}"
            self.r.rpush(result_key, json.dumps(validation))
            return True
        except Exception as exc:
            error_payload = {
                "candidate_id": candidate_id,
                "is_valid": False,
                "coverage_score": 0.0,
                "error": f"VALIDATOR_FAILED: {exc}",
                "elapsed_ms": int((time.time() - started) * 1000),
                "proven": False,
            }
            if candidate_id:
                self.r.rpush(f"{RESULT_QUEUE_PREFIX}{candidate_id}", json.dumps(error_payload))
            print(f"validator worker error: {exc}", file=sys.stderr)
            return False

    def run(self) -> None:
        print(f"Python Validator Worker listening on Redis queue: {REQUEST_QUEUE}")
        while self.running:
            self.process_one_task(timeout=2)


if __name__ == "__main__":
    worker = PythonValidatorWorker()
    worker.run()
