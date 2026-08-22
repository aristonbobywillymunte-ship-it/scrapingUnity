import pytest

import db


class FakeCursor:
    def __init__(self, fail_on_main=False):
        self.fail_on_main = fail_on_main
        self.executed = []
        self._rows = [("job-1", "user-1", "facebook", "posts")]

    def execute(self, sql, params=None):
        self.executed.append((sql, params))
        if self.fail_on_main and "UPDATE scrape_executions" in sql:
            raise RuntimeError("primary persistence failed")

    def fetchall(self):
        return self._rows

    def close(self):
        pass


class FakeConnection:
    def __init__(self, fail_on_main=False):
        self.fail_on_main = fail_on_main
        self.committed = False
        self.rolled_back = False
        self.cursor_obj = FakeCursor(fail_on_main=fail_on_main)

    def cursor(self):
        return self.cursor_obj

    def commit(self):
        self.committed = True

    def rollback(self):
        self.rolled_back = True

    def close(self):
        pass


def test_persist_execution_result_marks_failed_on_primary_error(monkeypatch):
    calls = []

    def fake_connect(**kwargs):
        calls.append(kwargs)
        if len(calls) == 1:
            return FakeConnection(fail_on_main=True)
        return FakeConnection()

    monkeypatch.setattr(db.psycopg2, "connect", fake_connect)
    monkeypatch.setattr(db, "INTERNAL_API_TOKEN", None)
    monkeypatch.setattr(db, "LARAVEL_INTERNAL_URL", None)

    with pytest.raises(RuntimeError, match="primary persistence failed"):
        db.persist_execution_result("exec-1", "fingerprint-1", {"status": "COMPLETED", "items": []})

    assert len(calls) == 2
