import pytest
import json
import redis
import time
from contracts import (
    PlatformEnum,
    OperationEnum,
    TargetTypeEnum,
    ExecutionContract,
    Target,
    Options
)
from worker_http import PythonHttpWorker, FacebookHttpScraperAdapter, HEARTBEAT_KEY

def test_facebook_adapter_operations():
    adapter = FacebookHttpScraperAdapter()

    # 1. Profile
    contract_prof = ExecutionContract(
        execution_id="exec_1",
        platform=PlatformEnum.FACEBOOK,
        operation=OperationEnum.PROFILE,
        target=Target(type=TargetTypeEnum.USERNAME, value="zuck"), options=Options(limit=10), request_fingerprint="test_fp"
    )
    res_prof = adapter.execute(contract_prof)
    assert res_prof["status"] == "COMPLETED"
    assert len(res_prof["items"]) == 1
    assert res_prof["items"][0]["canonical_url"] == "https://www.facebook.com/zuck"

    # 2. Search Keyword
    contract_search = ExecutionContract(
        execution_id="exec_2",
        platform=PlatformEnum.FACEBOOK,
        operation=OperationEnum.SEARCH_POSTS,
        target=Target(type=TargetTypeEnum.KEYWORD, value="indonesia"), options=Options(limit=25), request_fingerprint="test_fp"
    )
    res_search = adapter.execute(contract_search)
    assert res_search["status"] == "COMPLETED"
    assert res_search["items"][0]["platform_fields"]["discovery_mode"] == "search_query"

    # 3. Search Hashtag
    contract_hashtag = ExecutionContract(
        execution_id="exec_3",
        platform=PlatformEnum.FACEBOOK,
        operation=OperationEnum.SEARCH_POSTS,
        target=Target(type=TargetTypeEnum.HASHTAG, value="#teknologi"), options=Options(limit=25), request_fingerprint="test_fp"
    )
    res_hashtag = adapter.execute(contract_hashtag)
    assert res_hashtag["status"] == "COMPLETED"
    assert res_hashtag["items"][0]["platform_fields"]["discovery_mode"] == "hashtag"

    # 4. Replies with Parent Target
    contract_replies = ExecutionContract(
        execution_id="exec_4",
        platform=PlatformEnum.FACEBOOK,
        operation=OperationEnum.REPLIES,
        target=Target(type=TargetTypeEnum.POST_ID, value="1015891234"), options=Options(limit=10), request_fingerprint="test_fp"
    )
    res_replies = adapter.execute(contract_replies)
    assert res_replies["status"] == "COMPLETED"
    assert res_replies["items"][0]["content_type"] == "COMMENT"
    assert res_replies["items"][0]["platform_fields"]["parent_target"] == "1015891234"

def test_worker_redis_heartbeat_and_consumption(monkeypatch):
    try:
        r = redis.Redis(host="127.0.0.1", port=6379, db=0, decode_responses=True)
        r.ping()
    except Exception:
        pytest.skip("Redis not reachable locally on 127.0.0.1:6379")

    worker = PythonHttpWorker(redis_client=r)
    worker.send_heartbeat()
    monkeypatch.setattr("db.persist_execution_result", lambda *args, **kwargs: True)

    # Verify Heartbeat in Redis
    hb_data = r.get(HEARTBEAT_KEY)
    assert hb_data is not None
    parsed_hb = json.loads(hb_data)
    assert parsed_hb["status"] == "HEALTHY"
    assert parsed_hb["worker_type"] == "HTTP"

    # Push task into Redis queue
    import uuid
    test_exec_id = str(uuid.uuid4())
    task_payload = {
        "execution_id": test_exec_id,
        "platform": "facebook",
        "operation": "single_post",
        "target": {
            "type": "post_id",
            "value": "post_999"
        },
        "options": {
            "limit": 5
        },
        "request_fingerprint": "test_fp"
    }
    r.rpush("scrape:executions", json.dumps(task_payload))

    # Process task via Python worker
    processed = worker.process_one_task(timeout=2)
    assert processed is True

    # Check result key in Redis
    result_key = f"execution:result:{test_exec_id}"
    res_raw = r.get(result_key)
    assert res_raw is not None
    result = json.loads(res_raw)
    assert result["status"] == "COMPLETED"
    assert len(result["items"]) == 1
