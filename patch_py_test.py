import re

def fix_file(filepath):
    with open(filepath, "r") as f:
        code = f.read()

    code = re.sub(r'(target=.*?,)\s*(options=.*?)\s*\)', r'\1 \2, request_fingerprint="test_fp")', code)
    code = re.sub(r'("options": {\s*"limit": 5\s*})', r'\1,\n        "request_fingerprint": "test_fp"', code)

    with open(filepath, "w") as f:
        f.write(code)

fix_file("python_scraper/tests/test_offline_poc.py")
fix_file("python_scraper/tests/test_worker_http.py")
