import json

from worker_validator import PythonValidatorWorker


class FakeRedis:
    def __init__(self, payload):
        self.payload = payload
        self.rpush_calls = []
        self.heartbeats = []

    def setex(self, key, ttl, value):
        self.heartbeats.append((key, ttl, value))

    def blpop(self, queue, timeout=0):
        if self.payload is None:
            return None
        payload = self.payload
        self.payload = None
        return (queue, payload)

    def rpush(self, queue, value):
        self.rpush_calls.append((queue, value))


def test_worker_validator_consumes_queue_and_pushes_result():
    payload = json.dumps({
        "candidate_id": "candidate-1",
        "selectors": {
            "post_container": "article",
            "author_name": "strong",
            "text_content": "div",
            "timestamp": "abbr"
        },
        "sample_html": "<article><strong>A</strong><div>x</div><abbr></abbr></article>"
    })
    redis_client = FakeRedis(payload)
    worker = PythonValidatorWorker(redis_client=redis_client)

    processed = worker.process_one_task()

    assert processed is True
    assert redis_client.rpush_calls[0][0] == "queue:parser_validation:results:candidate-1"
    result = json.loads(redis_client.rpush_calls[0][1])
    assert result["candidate_id"] == "candidate-1"
    assert result["is_valid"] is True
