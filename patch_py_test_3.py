import re
with open("python_scraper/tests/test_worker_http.py", "r") as f:
    code = f.read()

code = code.replace('options=Options(limit=25, request_fingerprint="test_fp")', 'options=Options(limit=25), request_fingerprint="test_fp"')

with open("python_scraper/tests/test_worker_http.py", "w") as f:
    f.write(code)
