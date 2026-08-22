import re

# Fix the test file
with open("python_scraper/tests/test_worker_http.py", "r") as f:
    code = f.read()

code = code.replace('options=Options(limit=10, request_fingerprint="test_fp")', 'options=Options(limit=10), request_fingerprint="test_fp"')
code = code.replace('options=Options(limit=5, request_fingerprint="test_fp")', 'options=Options(limit=5), request_fingerprint="test_fp"')

with open("python_scraper/tests/test_worker_http.py", "w") as f:
    f.write(code)

# Fix db.py
with open("python_scraper/db.py", "r") as f:
    code2 = f.read()

old_db = """        try:
            import psycopg2"""

new_db = """        if os.environ.get("APP_ENV") == "testing":
            print("Testing mode: skipping fallback DB connection")
            raise e

        try:
            import psycopg2"""

code2 = code2.replace(old_db, new_db)

with open("python_scraper/db.py", "w") as f:
    f.write(code2)

