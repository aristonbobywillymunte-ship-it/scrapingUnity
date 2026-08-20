ALTER TABLE task_attempts DROP CONSTRAINT IF EXISTS task_attempts_proxy_lease_id_fkey;
ALTER TABLE task_attempts DROP CONSTRAINT IF EXISTS task_attempts_account_lease_id_fkey;

DROP TABLE IF EXISTS proxy_leases;
DROP TABLE IF EXISTS account_leases;
DROP TABLE IF EXISTS proxies;
DROP TABLE IF EXISTS social_sessions;
DROP TABLE IF EXISTS social_accounts;
DROP TABLE IF EXISTS proxy_pools;
DROP TABLE IF EXISTS resource_pools;
DROP TYPE IF EXISTS resource_health_status;
