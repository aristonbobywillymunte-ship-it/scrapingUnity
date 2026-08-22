import pytest
from worker_browser import PythonBrowserWorker

def test_browser_pre_navigation_ssrf():
    worker = PythonBrowserWorker()
    res = worker.fetch_with_browser("http://127.0.0.1:8000/admin")
    assert res["success"] is False
    assert res["classification"] == "INVALID_TARGET"
    assert "not in allowed Facebook whitelist" in res["error_message"]

    res_local = worker.fetch_with_browser("http://localhost:8080")
    assert res_local["success"] is False
    assert res_local["classification"] == "INVALID_TARGET"

    res_meta = worker.fetch_with_browser("http://169.254.169.254/latest/meta-data")
    assert res_meta["success"] is False
    assert res_meta["classification"] == "INVALID_TARGET"

def test_browser_concurrency_heartbeat():
    worker = PythonBrowserWorker()
    assert worker.running is True
