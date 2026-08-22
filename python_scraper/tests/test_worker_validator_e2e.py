import json

import pytest
import redis

from worker_validator import PythonValidatorWorker


def test_worker_validator_real_redis_e2e():
    try:
        r = redis.Redis(host="127.0.0.1", port=6379, db=0, decode_responses=True)
        r.ping()
    except Exception:
        pytest.skip("Redis not reachable locally on 127.0.0.1:6379")

    candidate_id = "candidate-e2e"
    request = {
        "candidate_id": candidate_id,
        "selectors": {
            "post_container": "article",
            "author_name": "strong",
            "text_content": "div[data-ad-preview='message']",
            "timestamp": "abbr[data-utime]",
        },
        "sample_html": """
            <html>
              <body>
                <article>
                  <strong>Alice</strong>
                  <div data-ad-preview="message">Hello</div>
                  <abbr data-utime="1710000000"></abbr>
                </article>
              </body>
            </html>
        """,
    }

    request_queue = "queue:parser_validation"
    result_queue = f"queue:parser_validation:results:{candidate_id}"
    r.delete(result_queue)
    r.rpush(request_queue, json.dumps(request))

    worker = PythonValidatorWorker(redis_client=r)
    processed = worker.process_one_task(timeout=2)

    assert processed is True
    result_raw = r.lpop(result_queue)
    assert result_raw is not None
    result = json.loads(result_raw)
    assert result["candidate_id"] == candidate_id
    assert result["is_valid"] is True
