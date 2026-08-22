import re
with open("python_scraper/tests/test_worker_http.py", "r") as f:
    code = f.read()

code = code.replace('test_exec_id = f"test_exec_{int(time.time())}"', 'import uuid\n    test_exec_id = str(uuid.uuid4())')

with open("python_scraper/tests/test_worker_http.py", "w") as f:
    f.write(code)
