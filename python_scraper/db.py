import os
import psycopg2
import json
import uuid
import sys
import time
from typing import Dict, Any, List

DB_HOST = os.environ.get("DB_HOST", "127.0.0.1")
DB_PORT = os.environ.get("DB_PORT", "5432")
DB_DATABASE = os.environ.get("DB_DATABASE", "app_db")
DB_USERNAME = os.environ.get("DB_USERNAME", "root")
DB_PASSWORD = os.environ.get("DB_PASSWORD", "")
INTERNAL_API_TOKEN = os.environ.get("INTERNAL_API_TOKEN")
LARAVEL_INTERNAL_URL = os.environ.get("LARAVEL_INTERNAL_URL")

def get_connection():
    return psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_DATABASE,
        user=DB_USERNAME,
        password=DB_PASSWORD
    )

def persist_execution_result(execution_id: str, fingerprint: str, result: Dict[str, Any]):
    """
    Durably persist execution result to PostgreSQL:
    1. Update scrape_executions
    2. Insert scraping_items
    3. Update subscribed scraping_jobs statuses
    4. Insert usage_ledger entries
    5. Trigger any necessary webhook events (in next phase, or Laravel can do it)
    """
    if "status" not in result:
        return

    items = result.get("items", [])
    count = result.get("count", len(items))
    status = result["status"]
    classification = result.get("classification")
    elapsed_ms = result.get("elapsed_ms")
    transport_mode = result.get("transport_mode")
    error = result.get("error")

    conn = None
    try:
        conn = get_connection()
        conn.autocommit = False
        cur = conn.cursor()

        # Update scrape_executions
        cur.execute("""
            UPDATE scrape_executions
            SET status = %s, classification = %s, elapsed_ms = %s, 
                transport_mode = %s, items_count = %s, error_message = %s, 
                completed_at = NOW(), updated_at = NOW()
            WHERE id = %s
        """, (status, classification, elapsed_ms, transport_mode, count, error, execution_id))

        # Insert items
        for item in items:
            cur.execute("""
                INSERT INTO scraping_items 
                (id, platform, content_type, external_id, canonical_url, request_fingerprint, 
                 author, text, published_at, media, metrics, platform_fields, 
                 collected_at, parser_version, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                ON CONFLICT (external_id, request_fingerprint) DO NOTHING
            """, (
                str(uuid.uuid4()),
                item.get("platform"),
                item.get("content_type"),
                item.get("external_id"),
                item.get("canonical_url"),
                fingerprint,
                json.dumps(item.get("author", {})),
                item.get("text"),
                item.get("published_at"),
                json.dumps(item.get("media", [])),
                json.dumps(item.get("metrics", {})),
                json.dumps(item.get("platform_fields", {})),
                item.get("collected_at"),
                item.get("parser_version")
            ))

        # Update ALL subscribed customer jobs
        cur.execute("""
            UPDATE scraping_jobs
            SET status = %s, updated_at = NOW()
            WHERE scrape_execution_id = %s AND status IN ('QUEUED', 'PROCESSING')
            RETURNING id, user_id, platform, operation
        """, (status, execution_id))
        
        updated_jobs = cur.fetchall()

        # Insert usage ledger for each job if there are items
        if count > 0 and status in ('COMPLETED', 'PARTIAL'):
            for job in updated_jobs:
                job_id, user_id, platform, operation = job
                cur.execute("""
                    INSERT INTO usage_ledger
                    (id, user_id, job_id, platform, operation, records_delivered, resolution, recorded_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                """, (
                    str(uuid.uuid4()),
                    user_id,
                    job_id,
                    platform,
                    operation,
                    count,
                    'upstream'
                ))

        conn.commit()
        cur.close()
        conn.close()

        # Dispatch Webhooks via Laravel internal API
        import requests
        for job in updated_jobs:
            job_id = job[0]
            try:
                if not LARAVEL_INTERNAL_URL or not INTERNAL_API_TOKEN:
                    raise RuntimeError("Missing LARAVEL_INTERNAL_URL or INTERNAL_API_TOKEN")
                requests.post(
                    f"{LARAVEL_INTERNAL_URL}/api/internal/webhook-dispatch",
                    json={"job_id": job_id},
                    headers={"X-Internal-Token": INTERNAL_API_TOKEN},
                    timeout=2
                )
            except Exception as inner_e:
                print(f"Webhook dispatch failed for {job_id}: {inner_e}", file=sys.stderr)
                
        return True

    except Exception as e:
        print(f"Error persisting result to Postgres: {e}", file=sys.stderr)
        try:
            if conn:
                conn.rollback()
                conn.close()
            conn_fallback = psycopg2.connect(
                dbname=DB_DATABASE,
                user=DB_USERNAME,
                password=DB_PASSWORD,
                host=DB_HOST,
                port=DB_PORT
            )
            cur_fallback = conn_fallback.cursor()
            cur_fallback.execute("UPDATE scrape_executions SET status = 'FAILED', updated_at = NOW() WHERE id = %s", (execution_id,))
            cur_fallback.execute("UPDATE scraping_jobs SET status = 'FAILED', updated_at = NOW() WHERE scrape_execution_id = %s", (execution_id,))
            conn_fallback.commit()
            cur_fallback.close()
            conn_fallback.close()
        except Exception as fallback_error:
            print(f"Fallback failure while marking execution {execution_id} failed: {fallback_error}", file=sys.stderr)
        raise e
