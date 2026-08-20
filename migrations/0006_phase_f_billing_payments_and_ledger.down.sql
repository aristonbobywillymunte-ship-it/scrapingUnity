DROP TABLE IF EXISTS internal_costs;
DROP TABLE IF EXISTS refunds;
DROP TABLE IF EXISTS refund_approvals;
DROP TABLE IF EXISTS credit_allocations;
DROP TABLE IF EXISTS payment_webhook_events;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoices;
DROP TRIGGER IF EXISTS trg_credit_ledger_append_only ON credit_ledger;
DROP FUNCTION IF EXISTS prevent_credit_ledger_modification();
DROP TABLE IF EXISTS credit_ledger;
DROP TABLE IF EXISTS credit_reservation_allocations;
DROP TABLE IF EXISTS billing_reservations;

ALTER TABLE runs DROP CONSTRAINT IF EXISTS runs_pricing_snapshot_id_fkey;

DROP TYPE IF EXISTS refund_status;
DROP TYPE IF EXISTS credit_transaction_type;
