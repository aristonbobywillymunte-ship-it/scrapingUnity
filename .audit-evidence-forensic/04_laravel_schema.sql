--
-- PostgreSQL database dump
--

\restrict gBvQY9LJDqYXPI1VZN7kXAQm0CMAmmg4diQQFkknt3VABeEHe6ZuaH0wgW0dBx0

-- Dumped from database version 15.19 (Debian 15.19-1.pgdg13+2)
-- Dumped by pg_dump version 15.19 (Debian 15.19-1.pgdg13+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: canonical_entity_type; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.canonical_entity_type AS ENUM (
    'PROFILE',
    'POST',
    'VIDEO',
    'ARTICLE',
    'COMMENT',
    'REPLY',
    'PAGE'
);


ALTER TYPE public.canonical_entity_type OWNER TO root;

--
-- Name: credit_transaction_type; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.credit_transaction_type AS ENUM (
    'PACKAGE_CREDIT',
    'PURCHASE',
    'RESERVE',
    'RELEASE',
    'USAGE',
    'REFUND',
    'BONUS',
    'ADJUSTMENT',
    'EXPIRED'
);


ALTER TYPE public.credit_transaction_type OWNER TO root;

--
-- Name: error_category; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.error_category AS ENUM (
    'invalid_input',
    'authentication_session',
    'account_restricted',
    'proxy_network',
    'target_rate_limit',
    'target_unavailable',
    'selector_parse',
    'content_not_found',
    'resource_exhausted',
    'worker_timeout',
    'worker_crash',
    'internal_system',
    'billing_quota',
    'cancelled'
);


ALTER TYPE public.error_category OWNER TO root;

--
-- Name: export_status; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.export_status AS ENUM (
    'QUEUED',
    'PROCESSING',
    'READY',
    'EXPIRED',
    'FAILED'
);


ALTER TYPE public.export_status OWNER TO root;

--
-- Name: notification_delivery_status; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.notification_delivery_status AS ENUM (
    'QUEUED',
    'SENDING',
    'DELIVERED',
    'FAILED'
);


ALTER TYPE public.notification_delivery_status OWNER TO root;

--
-- Name: refund_status; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.refund_status AS ENUM (
    'PENDING',
    'APPROVED',
    'REJECTED'
);


ALTER TYPE public.refund_status OWNER TO root;

--
-- Name: resource_health_status; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.resource_health_status AS ENUM (
    'HEALTHY',
    'DEGRADED',
    'UNHEALTHY'
);


ALTER TYPE public.resource_health_status OWNER TO root;

--
-- Name: run_status; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.run_status AS ENUM (
    'QUEUED',
    'RUNNING',
    'COMPLETED',
    'PARTIAL',
    'FAILED',
    'CANCELLED'
);


ALTER TYPE public.run_status OWNER TO root;

--
-- Name: selector_version_status; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.selector_version_status AS ENUM (
    'DRAFT',
    'TESTING',
    'ACTIVE',
    'INACTIVE',
    'DEPRECATED'
);


ALTER TYPE public.selector_version_status OWNER TO root;

--
-- Name: task_status; Type: TYPE; Schema: public; Owner: root
--

CREATE TYPE public.task_status AS ENUM (
    'QUEUED',
    'LEASED',
    'RUNNING',
    'RETRY_WAIT',
    'COMPLETED',
    'FAILED',
    'CANCELLED'
);


ALTER TYPE public.task_status OWNER TO root;

--
-- Name: prevent_audit_logs_modification(); Type: FUNCTION; Schema: public; Owner: root
--

CREATE FUNCTION public.prevent_audit_logs_modification() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs is append-only';
END;
$$;


ALTER FUNCTION public.prevent_audit_logs_modification() OWNER TO root;

--
-- Name: prevent_credit_ledger_modification(); Type: FUNCTION; Schema: public; Owner: root
--

CREATE FUNCTION public.prevent_credit_ledger_modification() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    RAISE EXCEPTION 'credit_ledger is append-only';
END;
$$;


ALTER FUNCTION public.prevent_credit_ledger_modification() OWNER TO root;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: access_reviews; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.access_reviews (
    id uuid NOT NULL,
    target_user_id uuid NOT NULL,
    reviewer_id uuid NOT NULL,
    status character varying(50) NOT NULL,
    reviewed_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.access_reviews OWNER TO root;

--
-- Name: account_leases; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.account_leases (
    id uuid NOT NULL,
    account_id uuid NOT NULL,
    task_id uuid NOT NULL,
    worker_identity character varying(255) NOT NULL,
    acquired_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    heartbeat_at timestamp with time zone,
    released_at timestamp with time zone,
    status character varying(50) DEFAULT 'ACQUIRED'::character varying NOT NULL,
    release_reason character varying(255)
);


ALTER TABLE public.account_leases OWNER TO root;

--
-- Name: ai_conversations; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.ai_conversations (
    id uuid NOT NULL,
    actor_id uuid NOT NULL,
    channel character varying(100) NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    safe_metadata jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.ai_conversations OWNER TO root;

--
-- Name: ai_messages; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.ai_messages (
    id uuid NOT NULL,
    conversation_id uuid NOT NULL,
    role character varying(50) NOT NULL,
    content_text text,
    idempotency_key character varying(255),
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.ai_messages OWNER TO root;

--
-- Name: ai_tool_audits; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.ai_tool_audits (
    id uuid NOT NULL,
    message_id uuid NOT NULL,
    tool_name character varying(255) NOT NULL,
    safe_arguments jsonb,
    safe_result jsonb,
    execution_latency_ms integer,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.ai_tool_audits OWNER TO root;

--
-- Name: ai_usage; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.ai_usage (
    id uuid NOT NULL,
    message_id uuid NOT NULL,
    provider character varying(100) NOT NULL,
    model character varying(100) NOT NULL,
    prompt_tokens integer NOT NULL,
    completion_tokens integer NOT NULL,
    internal_cost_cents bigint NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.ai_usage OWNER TO root;

--
-- Name: api_idempotency_keys; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.api_idempotency_keys (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    actor_identity character varying(255) NOT NULL,
    operation_id character varying(255) NOT NULL,
    key_hash character varying(255) NOT NULL,
    request_fingerprint character varying(255) NOT NULL,
    status character varying(50) DEFAULT 'PROCESSING'::character varying NOT NULL,
    response_reference jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL
);


ALTER TABLE public.api_idempotency_keys OWNER TO root;

--
-- Name: api_keys; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.api_keys (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    created_by uuid,
    key_hash character varying(255) NOT NULL,
    key_prefix character varying(50) NOT NULL,
    scopes jsonb,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    last_used_at timestamp with time zone,
    revoked_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.api_keys OWNER TO root;

--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.audit_logs (
    id uuid NOT NULL,
    actor_id uuid,
    actor_type character varying(50) NOT NULL,
    organization_id uuid,
    action character varying(255) NOT NULL,
    target character varying(255),
    request_id character varying(255),
    safe_metadata jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.audit_logs OWNER TO root;

--
-- Name: auth_logs; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.auth_logs (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    event_type character varying(100) NOT NULL,
    ip_address inet,
    device_metadata jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.auth_logs OWNER TO root;

--
-- Name: auth_sessions; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.auth_sessions (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    token_hash character varying(255) NOT NULL,
    device_metadata jsonb,
    ip_address inet,
    expires_at timestamp with time zone NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    revoked_at timestamp with time zone
);


ALTER TABLE public.auth_sessions OWNER TO root;

--
-- Name: billing_reservations; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.billing_reservations (
    id uuid NOT NULL,
    run_id uuid NOT NULL,
    organization_id uuid NOT NULL,
    estimated bigint NOT NULL,
    reserved bigint NOT NULL,
    settled bigint DEFAULT 0 NOT NULL,
    released bigint DEFAULT 0 NOT NULL,
    status character varying(50) NOT NULL,
    pricing_version_id uuid,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT billing_reservations_check CHECK (((settled + released) <= reserved)),
    CONSTRAINT billing_reservations_estimated_check CHECK ((estimated >= 0)),
    CONSTRAINT billing_reservations_released_check CHECK ((released >= 0)),
    CONSTRAINT billing_reservations_reserved_check CHECK ((reserved >= 0)),
    CONSTRAINT billing_reservations_settled_check CHECK ((settled >= 0))
);


ALTER TABLE public.billing_reservations OWNER TO root;

--
-- Name: break_glass_activations; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.break_glass_activations (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    reason text NOT NULL,
    starts_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    revoked_at timestamp with time zone,
    audit_reference character varying(255),
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.break_glass_activations OWNER TO root;

--
-- Name: canonical_articles; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.canonical_articles (
    canonical_entity_id uuid NOT NULL,
    entity_type public.canonical_entity_type NOT NULL,
    title character varying(512),
    canonical_url text,
    text_content text,
    published_at timestamp with time zone,
    author_name character varying(255),
    safe_metadata jsonb,
    CONSTRAINT canonical_articles_entity_type_check CHECK ((entity_type = 'ARTICLE'::public.canonical_entity_type))
);


ALTER TABLE public.canonical_articles OWNER TO root;

--
-- Name: canonical_comments; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.canonical_comments (
    canonical_entity_id uuid NOT NULL,
    entity_type public.canonical_entity_type NOT NULL,
    parent_content_id uuid NOT NULL,
    parent_entity_type public.canonical_entity_type NOT NULL,
    author_profile_id uuid,
    text_content text,
    published_at timestamp with time zone,
    safe_metadata jsonb,
    CONSTRAINT canonical_comments_entity_type_check CHECK ((entity_type = 'COMMENT'::public.canonical_entity_type)),
    CONSTRAINT canonical_comments_parent_entity_type_check CHECK ((parent_entity_type = ANY (ARRAY['POST'::public.canonical_entity_type, 'VIDEO'::public.canonical_entity_type, 'ARTICLE'::public.canonical_entity_type, 'PAGE'::public.canonical_entity_type])))
);


ALTER TABLE public.canonical_comments OWNER TO root;

--
-- Name: canonical_entities; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.canonical_entities (
    id uuid NOT NULL,
    platform character varying(50) NOT NULL,
    entity_type public.canonical_entity_type NOT NULL,
    stable_source_id character varying(255),
    normalized_url text,
    identity_hash character varying(255) NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.canonical_entities OWNER TO root;

--
-- Name: canonical_pages; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.canonical_pages (
    canonical_entity_id uuid NOT NULL,
    entity_type public.canonical_entity_type NOT NULL,
    title character varying(512),
    url text,
    text_content text,
    safe_metadata jsonb,
    CONSTRAINT canonical_pages_entity_type_check CHECK ((entity_type = 'PAGE'::public.canonical_entity_type))
);


ALTER TABLE public.canonical_pages OWNER TO root;

--
-- Name: canonical_posts; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.canonical_posts (
    canonical_entity_id uuid NOT NULL,
    entity_type public.canonical_entity_type NOT NULL,
    author_profile_id uuid,
    text_content text,
    published_at timestamp with time zone,
    safe_metadata jsonb,
    CONSTRAINT canonical_posts_entity_type_check CHECK ((entity_type = 'POST'::public.canonical_entity_type))
);


ALTER TABLE public.canonical_posts OWNER TO root;

--
-- Name: canonical_profiles; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.canonical_profiles (
    canonical_entity_id uuid NOT NULL,
    entity_type public.canonical_entity_type NOT NULL,
    username character varying(255),
    display_name character varying(255),
    safe_metadata jsonb,
    CONSTRAINT canonical_profiles_entity_type_check CHECK ((entity_type = 'PROFILE'::public.canonical_entity_type))
);


ALTER TABLE public.canonical_profiles OWNER TO root;

--
-- Name: canonical_replies; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.canonical_replies (
    canonical_entity_id uuid NOT NULL,
    entity_type public.canonical_entity_type NOT NULL,
    root_content_id uuid NOT NULL,
    root_entity_type public.canonical_entity_type NOT NULL,
    parent_comment_id uuid,
    author_profile_id uuid,
    text_content text,
    published_at timestamp with time zone,
    safe_metadata jsonb,
    CONSTRAINT canonical_replies_entity_type_check CHECK ((entity_type = 'REPLY'::public.canonical_entity_type)),
    CONSTRAINT canonical_replies_root_entity_type_check CHECK ((root_entity_type = ANY (ARRAY['POST'::public.canonical_entity_type, 'VIDEO'::public.canonical_entity_type, 'ARTICLE'::public.canonical_entity_type, 'PAGE'::public.canonical_entity_type])))
);


ALTER TABLE public.canonical_replies OWNER TO root;

--
-- Name: canonical_videos; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.canonical_videos (
    canonical_entity_id uuid NOT NULL,
    entity_type public.canonical_entity_type NOT NULL,
    author_profile_id uuid,
    text_content text,
    published_at timestamp with time zone,
    safe_metadata jsonb,
    CONSTRAINT canonical_videos_entity_type_check CHECK ((entity_type = 'VIDEO'::public.canonical_entity_type))
);


ALTER TABLE public.canonical_videos OWNER TO root;

--
-- Name: channel_bindings; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.channel_bindings (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    channel character varying(50) NOT NULL,
    external_identity character varying(255) NOT NULL,
    verified_at timestamp with time zone,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    revoked_at timestamp with time zone,
    safe_metadata jsonb,
    CONSTRAINT channel_bindings_channel_check CHECK (((channel)::text = ANY ((ARRAY['WHATSAPP'::character varying, 'TELEGRAM'::character varying])::text[])))
);


ALTER TABLE public.channel_bindings OWNER TO root;

--
-- Name: credit_allocations; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.credit_allocations (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    payment_id uuid NOT NULL,
    credit_lot_id uuid NOT NULL,
    quantity bigint NOT NULL,
    allocation_reference character varying(255) NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT credit_allocations_quantity_check CHECK ((quantity >= 0))
);


ALTER TABLE public.credit_allocations OWNER TO root;

--
-- Name: credit_ledger; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.credit_ledger (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    event_idempotency_key character varying(255) NOT NULL,
    transaction_type public.credit_transaction_type NOT NULL,
    credit_lot_id uuid NOT NULL,
    reservation_id uuid,
    reservation_allocation_id uuid,
    run_id uuid,
    quantity bigint NOT NULL,
    economic_value_reference jsonb,
    actor_id uuid,
    reason character varying(255),
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.credit_ledger OWNER TO root;

--
-- Name: credit_lots; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.credit_lots (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    source character varying(50) NOT NULL,
    source_reference character varying(255),
    original_quantity bigint NOT NULL,
    remaining_quantity bigint NOT NULL,
    effective_monetary_value_cents bigint DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    CONSTRAINT credit_lots_check CHECK ((remaining_quantity <= original_quantity)),
    CONSTRAINT credit_lots_original_quantity_check CHECK ((original_quantity >= 0)),
    CONSTRAINT credit_lots_remaining_quantity_check CHECK ((remaining_quantity >= 0)),
    CONSTRAINT credit_lots_source_check CHECK (((source)::text = ANY ((ARRAY['SUBSCRIPTION'::character varying, 'TOP_UP'::character varying, 'BONUS'::character varying, 'ADJUSTMENT'::character varying, 'REFUND'::character varying])::text[])))
);


ALTER TABLE public.credit_lots OWNER TO root;

--
-- Name: credit_reservation_allocations; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.credit_reservation_allocations (
    id uuid NOT NULL,
    reservation_id uuid NOT NULL,
    organization_id uuid NOT NULL,
    credit_lot_id uuid NOT NULL,
    reserved_quantity bigint NOT NULL,
    settled_quantity bigint DEFAULT 0 NOT NULL,
    released_quantity bigint DEFAULT 0 NOT NULL,
    economic_value_snapshot jsonb,
    allocation_order integer NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT credit_reservation_allocations_check CHECK (((settled_quantity + released_quantity) <= reserved_quantity)),
    CONSTRAINT credit_reservation_allocations_released_quantity_check CHECK ((released_quantity >= 0)),
    CONSTRAINT credit_reservation_allocations_reserved_quantity_check CHECK ((reserved_quantity >= 0)),
    CONSTRAINT credit_reservation_allocations_settled_quantity_check CHECK ((settled_quantity >= 0))
);


ALTER TABLE public.credit_reservation_allocations OWNER TO root;

--
-- Name: dead_letter_queue_records; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.dead_letter_queue_records (
    id uuid NOT NULL,
    task_id uuid NOT NULL,
    run_id uuid NOT NULL,
    attempt_id uuid NOT NULL,
    error_category public.error_category,
    error_code character varying(255),
    safe_diagnostics jsonb,
    retry_exhausted boolean DEFAULT false NOT NULL,
    failed_at timestamp with time zone DEFAULT now() NOT NULL,
    operator_replay_reference character varying(255),
    reconciled_at timestamp with time zone,
    resolution character varying(255)
);


ALTER TABLE public.dead_letter_queue_records OWNER TO root;

--
-- Name: exports; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.exports (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    requested_by uuid NOT NULL,
    format character varying(50) NOT NULL,
    status public.export_status DEFAULT 'QUEUED'::public.export_status NOT NULL,
    request_snapshot jsonb,
    retention_policy_snapshot jsonb,
    storage_reference character varying(255),
    download_metadata jsonb,
    ready_at timestamp with time zone,
    expires_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.exports OWNER TO root;

--
-- Name: in_app_notifications; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.in_app_notifications (
    id uuid NOT NULL,
    recipient_id uuid NOT NULL,
    logical_notification_id uuid NOT NULL,
    content jsonb NOT NULL,
    read_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.in_app_notifications OWNER TO root;

--
-- Name: internal_costs; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.internal_costs (
    id uuid NOT NULL,
    event_idempotency_key character varying(255) NOT NULL,
    run_id uuid,
    task_id uuid,
    category character varying(100) NOT NULL,
    provider_reference character varying(255),
    amount_cents bigint NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT internal_costs_amount_cents_check CHECK ((amount_cents >= 0))
);


ALTER TABLE public.internal_costs OWNER TO root;

--
-- Name: internal_user_assignments; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.internal_user_assignments (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    role_id character varying(50) NOT NULL,
    role_is_internal boolean DEFAULT true NOT NULL,
    CONSTRAINT internal_user_assignments_role_is_internal_check CHECK ((role_is_internal = true))
);


ALTER TABLE public.internal_user_assignments OWNER TO root;

--
-- Name: invoices; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.invoices (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    total_cents bigint NOT NULL,
    currency character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    status character varying(50) NOT NULL,
    issued_at timestamp with time zone DEFAULT now() NOT NULL,
    due_at timestamp with time zone,
    paid_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT invoices_total_cents_check CHECK ((total_cents >= 0))
);


ALTER TABLE public.invoices OWNER TO root;

--
-- Name: jit_privilege_grants; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.jit_privilege_grants (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    requested_by uuid NOT NULL,
    approved_by uuid NOT NULL,
    permission_id character varying(100) NOT NULL,
    organization_id uuid,
    reason text NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    starts_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    revoked_at timestamp with time zone,
    audit_reference character varying(255),
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.jit_privilege_grants OWNER TO root;

--
-- Name: logical_notifications; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.logical_notifications (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    event_id uuid NOT NULL,
    recipient_id uuid NOT NULL,
    channel character varying(50) NOT NULL,
    status public.notification_delivery_status DEFAULT 'QUEUED'::public.notification_delivery_status NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.logical_notifications OWNER TO root;

--
-- Name: migrations; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO root;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: root
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.migrations_id_seq OWNER TO root;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: root
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notification_deliveries; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.notification_deliveries (
    id uuid NOT NULL,
    logical_notification_id uuid NOT NULL,
    status public.notification_delivery_status DEFAULT 'QUEUED'::public.notification_delivery_status NOT NULL,
    provider_reference character varying(255),
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.notification_deliveries OWNER TO root;

--
-- Name: notification_delivery_attempts; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.notification_delivery_attempts (
    id uuid NOT NULL,
    delivery_id uuid NOT NULL,
    attempt_number integer NOT NULL,
    provider_instance_id uuid,
    provider_event_id character varying(255),
    runtime_phase character varying(100),
    safe_error jsonb,
    latency_ms integer,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.notification_delivery_attempts OWNER TO root;

--
-- Name: notification_events; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.notification_events (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    event_type character varying(255) NOT NULL,
    event_version character varying(50) NOT NULL,
    dedupe_key character varying(255) NOT NULL,
    safe_payload jsonb,
    occurred_at timestamp with time zone DEFAULT now() NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.notification_events OWNER TO root;

--
-- Name: notification_rules; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.notification_rules (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    event_type character varying(255) NOT NULL,
    channels jsonb NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.notification_rules OWNER TO root;

--
-- Name: notification_templates; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.notification_templates (
    id uuid NOT NULL,
    organization_id uuid,
    channel character varying(50) NOT NULL,
    event_type character varying(255) NOT NULL,
    template_content text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.notification_templates OWNER TO root;

--
-- Name: organization_memberships; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.organization_memberships (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    user_id uuid NOT NULL,
    role_id character varying(50) NOT NULL,
    role_is_internal boolean DEFAULT false NOT NULL,
    CONSTRAINT organization_memberships_role_is_internal_check CHECK ((role_is_internal = false))
);


ALTER TABLE public.organization_memberships OWNER TO root;

--
-- Name: organizations; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.organizations (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone
);


ALTER TABLE public.organizations OWNER TO root;

--
-- Name: otp_rate_buckets; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.otp_rate_buckets (
    user_id uuid NOT NULL,
    channel character varying(50) NOT NULL,
    bucket_date date NOT NULL,
    request_count integer DEFAULT 1 NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT otp_rate_buckets_channel_check CHECK (((channel)::text = ANY ((ARRAY['EMAIL'::character varying, 'WHATSAPP'::character varying])::text[]))),
    CONSTRAINT otp_rate_buckets_request_count_check CHECK ((request_count <= 3))
);


ALTER TABLE public.otp_rate_buckets OWNER TO root;

--
-- Name: otp_requests; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.otp_requests (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    channel character varying(50) NOT NULL,
    otp_hash character varying(255) NOT NULL,
    purpose character varying(100) NOT NULL,
    attempt_count integer DEFAULT 0 NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    used_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT otp_requests_attempt_count_check CHECK ((attempt_count <= 5)),
    CONSTRAINT otp_requests_channel_check CHECK (((channel)::text = ANY ((ARRAY['EMAIL'::character varying, 'WHATSAPP'::character varying])::text[])))
);


ALTER TABLE public.otp_requests OWNER TO root;

--
-- Name: outgoing_webhooks; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.outgoing_webhooks (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    events jsonb NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    endpoint_url text NOT NULL,
    encrypted_secret character varying(2048) NOT NULL,
    key_reference character varying(255) NOT NULL,
    encryption_version character varying(50) NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.outgoing_webhooks OWNER TO root;

--
-- Name: package_entitlements; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.package_entitlements (
    id uuid NOT NULL,
    package_id uuid NOT NULL,
    capability character varying(100) NOT NULL,
    limits jsonb DEFAULT '{}'::jsonb NOT NULL
);


ALTER TABLE public.package_entitlements OWNER TO root;

--
-- Name: packages; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.packages (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    is_custom boolean DEFAULT false NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    duration_days integer,
    retention_days integer,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.packages OWNER TO root;

--
-- Name: payment_webhook_events; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.payment_webhook_events (
    id uuid NOT NULL,
    provider character varying(100) NOT NULL,
    provider_event_id character varying(255) NOT NULL,
    payment_id uuid,
    provider_transaction_reference character varying(255),
    event_type character varying(100) NOT NULL,
    received_at timestamp with time zone DEFAULT now() NOT NULL,
    processed_at timestamp with time zone,
    processing_status character varying(50) NOT NULL,
    safe_payload_metadata jsonb,
    safe_error jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.payment_webhook_events OWNER TO root;

--
-- Name: payments; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.payments (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    invoice_id uuid NOT NULL,
    provider character varying(100) NOT NULL,
    provider_transaction_id character varying(255) NOT NULL,
    currency character varying(3) NOT NULL,
    amount_cents bigint NOT NULL,
    status character varying(50) NOT NULL,
    paid_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT payments_amount_cents_check CHECK ((amount_cents >= 0))
);


ALTER TABLE public.payments OWNER TO root;

--
-- Name: permissions; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.permissions (
    id character varying(100) NOT NULL
);


ALTER TABLE public.permissions OWNER TO root;

--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO root;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: root
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.personal_access_tokens_id_seq OWNER TO root;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: root
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: pricing_versions; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.pricing_versions (
    id uuid NOT NULL,
    capability character varying(100) NOT NULL,
    credits_per_result bigint NOT NULL,
    valid_from timestamp with time zone DEFAULT now() NOT NULL,
    valid_until timestamp with time zone,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT pricing_versions_credits_per_result_check CHECK ((credits_per_result >= 0))
);


ALTER TABLE public.pricing_versions OWNER TO root;

--
-- Name: provider_configs; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.provider_configs (
    id uuid NOT NULL,
    provider_name character varying(100) NOT NULL,
    provider_type character varying(100) NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    safe_metadata jsonb,
    encrypted_credentials character varying(2048) NOT NULL,
    key_reference character varying(255) NOT NULL,
    encryption_version character varying(50) NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.provider_configs OWNER TO root;

--
-- Name: proxies; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.proxies (
    id uuid NOT NULL,
    pool_id uuid,
    host character varying(255) NOT NULL,
    port integer NOT NULL,
    health_status public.resource_health_status DEFAULT 'HEALTHY'::public.resource_health_status NOT NULL,
    operational_state character varying(50) DEFAULT 'AVAILABLE'::character varying NOT NULL,
    cooldown_until timestamp with time zone,
    max_concurrency integer DEFAULT 1 NOT NULL,
    encrypted_credentials character varying(2048),
    key_reference character varying(255),
    encryption_version character varying(50),
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone,
    CONSTRAINT proxies_port_check CHECK (((port >= 1) AND (port <= 65535)))
);


ALTER TABLE public.proxies OWNER TO root;

--
-- Name: proxy_leases; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.proxy_leases (
    id uuid NOT NULL,
    proxy_id uuid NOT NULL,
    task_id uuid NOT NULL,
    worker_identity character varying(255) NOT NULL,
    acquired_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    heartbeat_at timestamp with time zone,
    released_at timestamp with time zone,
    status character varying(50) DEFAULT 'ACQUIRED'::character varying NOT NULL,
    release_reason character varying(255)
);


ALTER TABLE public.proxy_leases OWNER TO root;

--
-- Name: proxy_pools; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.proxy_pools (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    max_concurrency integer,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone
);


ALTER TABLE public.proxy_pools OWNER TO root;

--
-- Name: reconciliation_findings; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.reconciliation_findings (
    id uuid NOT NULL,
    reconciliation_run_id uuid NOT NULL,
    finding_type character varying(100) NOT NULL,
    object_reference character varying(255) NOT NULL,
    status character varying(50) NOT NULL,
    safe_details jsonb,
    detected_at timestamp with time zone DEFAULT now() NOT NULL,
    resolved_at timestamp with time zone,
    resolution character varying(255)
);


ALTER TABLE public.reconciliation_findings OWNER TO root;

--
-- Name: reconciliation_runs; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.reconciliation_runs (
    id uuid NOT NULL,
    type character varying(100) NOT NULL,
    status character varying(50) NOT NULL,
    actor_reference character varying(255),
    started_at timestamp with time zone DEFAULT now() NOT NULL,
    completed_at timestamp with time zone,
    safe_details jsonb
);


ALTER TABLE public.reconciliation_runs OWNER TO root;

--
-- Name: refund_approvals; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.refund_approvals (
    id uuid NOT NULL,
    maker_id uuid NOT NULL,
    checker_id uuid NOT NULL,
    status public.refund_status NOT NULL,
    reason character varying(255),
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    reviewed_at timestamp with time zone,
    CONSTRAINT refund_approvals_check CHECK ((maker_id <> checker_id))
);


ALTER TABLE public.refund_approvals OWNER TO root;

--
-- Name: refunds; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.refunds (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    payment_id uuid,
    run_id uuid,
    approval_id uuid NOT NULL,
    amount_cents bigint,
    credit_quantity bigint,
    status public.refund_status NOT NULL,
    reason character varying(255) NOT NULL,
    idempotency_key character varying(255) NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT refunds_amount_cents_check CHECK ((amount_cents >= 0)),
    CONSTRAINT refunds_check CHECK (((COALESCE(amount_cents, (0)::bigint) > 0) OR (COALESCE(credit_quantity, (0)::bigint) > 0))),
    CONSTRAINT refunds_credit_quantity_check CHECK ((credit_quantity >= 0))
);


ALTER TABLE public.refunds OWNER TO root;

--
-- Name: resource_pools; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.resource_pools (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    platform character varying(50),
    max_concurrency integer,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone
);


ALTER TABLE public.resource_pools OWNER TO root;

--
-- Name: role_permissions; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.role_permissions (
    role_id character varying(50) NOT NULL,
    permission_id character varying(100) NOT NULL
);


ALTER TABLE public.role_permissions OWNER TO root;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.roles (
    id character varying(50) NOT NULL,
    is_internal_role boolean NOT NULL,
    description character varying(255)
);


ALTER TABLE public.roles OWNER TO root;

--
-- Name: run_requests; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.run_requests (
    run_id uuid NOT NULL,
    target_type character varying(100),
    target_url text,
    normalized_target_url text,
    source_canonical_identity_id uuid,
    parent_canonical_identity_id uuid,
    capability character varying(100),
    limit_value integer,
    options jsonb,
    reference_id character varying(255),
    request_snapshot jsonb,
    scraper_contract_version character varying(50),
    payload_version character varying(50)
);


ALTER TABLE public.run_requests OWNER TO root;

--
-- Name: run_results; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.run_results (
    id uuid NOT NULL,
    run_id uuid NOT NULL,
    organization_id uuid NOT NULL,
    canonical_entity_id uuid NOT NULL,
    source_task_id uuid NOT NULL,
    billable_status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.run_results OWNER TO root;

--
-- Name: runs; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.runs (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    actor_id uuid,
    origin character varying(50),
    capability character varying(100) NOT NULL,
    scraper_contract_version character varying(50),
    request_id character varying(255),
    reference_id character varying(255),
    status public.run_status DEFAULT 'QUEUED'::public.run_status NOT NULL,
    pricing_snapshot_id uuid,
    counters jsonb,
    error_category public.error_category,
    safe_error_metadata jsonb,
    started_at timestamp with time zone,
    completed_at timestamp with time zone,
    cancel_requested_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.runs OWNER TO root;

--
-- Name: search_indexing_states; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.search_indexing_states (
    id uuid NOT NULL,
    index_name character varying(255) NOT NULL,
    last_checkpoint character varying(255),
    status character varying(50) NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.search_indexing_states OWNER TO root;

--
-- Name: security_events; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.security_events (
    id uuid NOT NULL,
    event_type character varying(255) NOT NULL,
    actor_id uuid,
    organization_id uuid,
    safe_context jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.security_events OWNER TO root;

--
-- Name: selector_versions; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.selector_versions (
    id uuid NOT NULL,
    selector_id uuid NOT NULL,
    status public.selector_version_status DEFAULT 'DRAFT'::public.selector_version_status NOT NULL,
    version_tag character varying(50) NOT NULL,
    selector_data jsonb NOT NULL,
    test_metadata jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.selector_versions OWNER TO root;

--
-- Name: selectors; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.selectors (
    id uuid NOT NULL,
    platform character varying(50) NOT NULL,
    scraper character varying(100) NOT NULL,
    source character varying(100) NOT NULL,
    page_type character varying(100) NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.selectors OWNER TO root;

--
-- Name: service_actor_permissions; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.service_actor_permissions (
    service_actor_id uuid NOT NULL,
    permission_id character varying(100) NOT NULL
);


ALTER TABLE public.service_actor_permissions OWNER TO root;

--
-- Name: service_actors; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.service_actors (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    credentials_hash character varying(255) NOT NULL,
    purpose character varying(255) NOT NULL,
    owner_user_id uuid,
    environment character varying(50) NOT NULL,
    credential_rotation_reference character varying(255),
    last_rotated_at timestamp with time zone,
    audit_reference character varying(255),
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone
);


ALTER TABLE public.service_actors OWNER TO root;

--
-- Name: social_accounts; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.social_accounts (
    id uuid NOT NULL,
    platform character varying(50) NOT NULL,
    pool_id uuid,
    health_status public.resource_health_status DEFAULT 'HEALTHY'::public.resource_health_status NOT NULL,
    operational_state character varying(50) DEFAULT 'AVAILABLE'::character varying NOT NULL,
    cooldown_until timestamp with time zone,
    affinity_metadata jsonb,
    max_concurrency integer DEFAULT 1 NOT NULL,
    encrypted_credentials character varying(2048) NOT NULL,
    key_reference character varying(255) NOT NULL,
    encryption_version character varying(50) NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone
);


ALTER TABLE public.social_accounts OWNER TO root;

--
-- Name: social_sessions; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.social_sessions (
    id uuid NOT NULL,
    account_id uuid NOT NULL,
    encrypted_session character varying(4096) NOT NULL,
    key_reference character varying(255) NOT NULL,
    encryption_version character varying(50) NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    expires_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    revoked_at timestamp with time zone
);


ALTER TABLE public.social_sessions OWNER TO root;

--
-- Name: subscription_snapshots; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.subscription_snapshots (
    id uuid NOT NULL,
    subscription_id uuid NOT NULL,
    snapshot_data jsonb NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.subscription_snapshots OWNER TO root;

--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.subscriptions (
    id uuid NOT NULL,
    organization_id uuid NOT NULL,
    package_id uuid NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    starts_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.subscriptions OWNER TO root;

--
-- Name: system_maintenance; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.system_maintenance (
    id uuid NOT NULL,
    scope character varying(255) NOT NULL,
    reason text NOT NULL,
    actor_id uuid,
    starts_at timestamp with time zone NOT NULL,
    ends_at timestamp with time zone NOT NULL,
    status character varying(50) NOT NULL,
    config jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.system_maintenance OWNER TO root;

--
-- Name: task_attempts; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.task_attempts (
    id uuid NOT NULL,
    task_id uuid NOT NULL,
    run_id uuid NOT NULL,
    organization_id uuid NOT NULL,
    attempt_number integer NOT NULL,
    worker_identity character varying(255),
    account_lease_id uuid,
    proxy_lease_id uuid,
    outcome character varying(50),
    error_category public.error_category,
    error_code character varying(255),
    safe_diagnostics jsonb,
    started_at timestamp with time zone,
    completed_at timestamp with time zone
);


ALTER TABLE public.task_attempts OWNER TO root;

--
-- Name: task_leases; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.task_leases (
    id uuid NOT NULL,
    task_id uuid NOT NULL,
    worker_identity character varying(255) NOT NULL,
    acquired_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    heartbeat_at timestamp with time zone,
    released_at timestamp with time zone,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    release_reason character varying(255)
);


ALTER TABLE public.task_leases OWNER TO root;

--
-- Name: tasks; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.tasks (
    id uuid NOT NULL,
    run_id uuid NOT NULL,
    organization_id uuid NOT NULL,
    capability character varying(100),
    payload_version character varying(50),
    scraper_contract_version character varying(50),
    status public.task_status DEFAULT 'QUEUED'::public.task_status NOT NULL,
    attempt_count integer DEFAULT 0 NOT NULL,
    max_attempts_reference character varying(50),
    next_retry_at timestamp with time zone,
    active_lease_id uuid,
    lease_expires_at timestamp with time zone,
    heartbeat_at timestamp with time zone,
    worker_identity character varying(255),
    queued_at timestamp with time zone DEFAULT now() NOT NULL,
    started_at timestamp with time zone,
    completed_at timestamp with time zone,
    error_category public.error_category,
    error_code character varying(255),
    safe_error_metadata jsonb
);


ALTER TABLE public.tasks OWNER TO root;

--
-- Name: temporary_access_grants; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.temporary_access_grants (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    role_id character varying(50) NOT NULL,
    approver_id uuid NOT NULL,
    organization_id uuid,
    reason text NOT NULL,
    starts_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    revoked_at timestamp with time zone,
    audit_reference character varying(255),
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.temporary_access_grants OWNER TO root;

--
-- Name: users; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.users (
    id uuid NOT NULL,
    email character varying(255) NOT NULL,
    password_hash character varying(255),
    mfa_enabled boolean DEFAULT false NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone
);


ALTER TABLE public.users OWNER TO root;

--
-- Name: wa_instances; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.wa_instances (
    id uuid NOT NULL,
    pool_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    health_state character varying(50) DEFAULT 'HEALTHY'::character varying NOT NULL,
    provider_config_id uuid,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.wa_instances OWNER TO root;

--
-- Name: wa_pools; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.wa_pools (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    status character varying(50) DEFAULT 'ACTIVE'::character varying NOT NULL,
    concurrency_config jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone
);


ALTER TABLE public.wa_pools OWNER TO root;

--
-- Name: webhook_deliveries; Type: TABLE; Schema: public; Owner: root
--

CREATE TABLE public.webhook_deliveries (
    id uuid NOT NULL,
    webhook_id uuid NOT NULL,
    organization_id uuid NOT NULL,
    event_id uuid NOT NULL,
    status public.notification_delivery_status NOT NULL,
    response_code integer,
    safe_error jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.webhook_deliveries OWNER TO root;

--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: access_reviews access_reviews_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.access_reviews
    ADD CONSTRAINT access_reviews_pkey PRIMARY KEY (id);


--
-- Name: account_leases account_leases_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.account_leases
    ADD CONSTRAINT account_leases_pkey PRIMARY KEY (id);


--
-- Name: ai_conversations ai_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.ai_conversations
    ADD CONSTRAINT ai_conversations_pkey PRIMARY KEY (id);


--
-- Name: ai_messages ai_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.ai_messages
    ADD CONSTRAINT ai_messages_pkey PRIMARY KEY (id);


--
-- Name: ai_tool_audits ai_tool_audits_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.ai_tool_audits
    ADD CONSTRAINT ai_tool_audits_pkey PRIMARY KEY (id);


--
-- Name: ai_usage ai_usage_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.ai_usage
    ADD CONSTRAINT ai_usage_pkey PRIMARY KEY (id);


--
-- Name: api_idempotency_keys api_idempotency_keys_organization_id_actor_identity_operati_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.api_idempotency_keys
    ADD CONSTRAINT api_idempotency_keys_organization_id_actor_identity_operati_key UNIQUE (organization_id, actor_identity, operation_id, key_hash);


--
-- Name: api_idempotency_keys api_idempotency_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.api_idempotency_keys
    ADD CONSTRAINT api_idempotency_keys_pkey PRIMARY KEY (id);


--
-- Name: api_keys api_keys_key_hash_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_key_hash_key UNIQUE (key_hash);


--
-- Name: api_keys api_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: auth_logs auth_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.auth_logs
    ADD CONSTRAINT auth_logs_pkey PRIMARY KEY (id);


--
-- Name: auth_sessions auth_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.auth_sessions
    ADD CONSTRAINT auth_sessions_pkey PRIMARY KEY (id);


--
-- Name: auth_sessions auth_sessions_token_hash_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.auth_sessions
    ADD CONSTRAINT auth_sessions_token_hash_key UNIQUE (token_hash);


--
-- Name: billing_reservations billing_reservations_id_organization_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.billing_reservations
    ADD CONSTRAINT billing_reservations_id_organization_id_key UNIQUE (id, organization_id);


--
-- Name: billing_reservations billing_reservations_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.billing_reservations
    ADD CONSTRAINT billing_reservations_pkey PRIMARY KEY (id);


--
-- Name: billing_reservations billing_reservations_run_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.billing_reservations
    ADD CONSTRAINT billing_reservations_run_id_key UNIQUE (run_id);


--
-- Name: break_glass_activations break_glass_activations_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.break_glass_activations
    ADD CONSTRAINT break_glass_activations_pkey PRIMARY KEY (id);


--
-- Name: canonical_articles canonical_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_articles
    ADD CONSTRAINT canonical_articles_pkey PRIMARY KEY (canonical_entity_id);


--
-- Name: canonical_comments canonical_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_comments
    ADD CONSTRAINT canonical_comments_pkey PRIMARY KEY (canonical_entity_id);


--
-- Name: canonical_entities canonical_entities_id_entity_type_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_entities
    ADD CONSTRAINT canonical_entities_id_entity_type_key UNIQUE (id, entity_type);


--
-- Name: canonical_entities canonical_entities_identity_hash_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_entities
    ADD CONSTRAINT canonical_entities_identity_hash_key UNIQUE (identity_hash);


--
-- Name: canonical_entities canonical_entities_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_entities
    ADD CONSTRAINT canonical_entities_pkey PRIMARY KEY (id);


--
-- Name: canonical_pages canonical_pages_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_pages
    ADD CONSTRAINT canonical_pages_pkey PRIMARY KEY (canonical_entity_id);


--
-- Name: canonical_posts canonical_posts_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_posts
    ADD CONSTRAINT canonical_posts_pkey PRIMARY KEY (canonical_entity_id);


--
-- Name: canonical_profiles canonical_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_profiles
    ADD CONSTRAINT canonical_profiles_pkey PRIMARY KEY (canonical_entity_id);


--
-- Name: canonical_replies canonical_replies_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_replies
    ADD CONSTRAINT canonical_replies_pkey PRIMARY KEY (canonical_entity_id);


--
-- Name: canonical_videos canonical_videos_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_videos
    ADD CONSTRAINT canonical_videos_pkey PRIMARY KEY (canonical_entity_id);


--
-- Name: channel_bindings channel_bindings_channel_external_identity_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.channel_bindings
    ADD CONSTRAINT channel_bindings_channel_external_identity_key UNIQUE (channel, external_identity);


--
-- Name: channel_bindings channel_bindings_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.channel_bindings
    ADD CONSTRAINT channel_bindings_pkey PRIMARY KEY (id);


--
-- Name: credit_allocations credit_allocations_allocation_reference_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_allocations
    ADD CONSTRAINT credit_allocations_allocation_reference_key UNIQUE (allocation_reference);


--
-- Name: credit_allocations credit_allocations_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_allocations
    ADD CONSTRAINT credit_allocations_pkey PRIMARY KEY (id);


--
-- Name: credit_ledger credit_ledger_event_idempotency_key_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_ledger
    ADD CONSTRAINT credit_ledger_event_idempotency_key_key UNIQUE (event_idempotency_key);


--
-- Name: credit_ledger credit_ledger_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_ledger
    ADD CONSTRAINT credit_ledger_pkey PRIMARY KEY (id);


--
-- Name: credit_lots credit_lots_id_organization_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_lots
    ADD CONSTRAINT credit_lots_id_organization_id_key UNIQUE (id, organization_id);


--
-- Name: credit_lots credit_lots_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_lots
    ADD CONSTRAINT credit_lots_pkey PRIMARY KEY (id);


--
-- Name: credit_reservation_allocations credit_reservation_allocations_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_reservation_allocations
    ADD CONSTRAINT credit_reservation_allocations_pkey PRIMARY KEY (id);


--
-- Name: credit_reservation_allocations credit_reservation_allocations_reservation_id_credit_lot_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_reservation_allocations
    ADD CONSTRAINT credit_reservation_allocations_reservation_id_credit_lot_id_key UNIQUE (reservation_id, credit_lot_id);


--
-- Name: dead_letter_queue_records dead_letter_queue_records_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.dead_letter_queue_records
    ADD CONSTRAINT dead_letter_queue_records_pkey PRIMARY KEY (id);


--
-- Name: exports exports_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.exports
    ADD CONSTRAINT exports_pkey PRIMARY KEY (id);


--
-- Name: in_app_notifications in_app_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.in_app_notifications
    ADD CONSTRAINT in_app_notifications_pkey PRIMARY KEY (id);


--
-- Name: internal_costs internal_costs_event_idempotency_key_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.internal_costs
    ADD CONSTRAINT internal_costs_event_idempotency_key_key UNIQUE (event_idempotency_key);


--
-- Name: internal_costs internal_costs_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.internal_costs
    ADD CONSTRAINT internal_costs_pkey PRIMARY KEY (id);


--
-- Name: internal_user_assignments internal_user_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.internal_user_assignments
    ADD CONSTRAINT internal_user_assignments_pkey PRIMARY KEY (id);


--
-- Name: internal_user_assignments internal_user_assignments_user_id_role_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.internal_user_assignments
    ADD CONSTRAINT internal_user_assignments_user_id_role_id_key UNIQUE (user_id, role_id);


--
-- Name: invoices invoices_id_organization_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_id_organization_id_key UNIQUE (id, organization_id);


--
-- Name: invoices invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);


--
-- Name: jit_privilege_grants jit_privilege_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.jit_privilege_grants
    ADD CONSTRAINT jit_privilege_grants_pkey PRIMARY KEY (id);


--
-- Name: logical_notifications logical_notifications_event_id_recipient_id_channel_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.logical_notifications
    ADD CONSTRAINT logical_notifications_event_id_recipient_id_channel_key UNIQUE (event_id, recipient_id, channel);


--
-- Name: logical_notifications logical_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.logical_notifications
    ADD CONSTRAINT logical_notifications_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notification_deliveries notification_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_pkey PRIMARY KEY (id);


--
-- Name: notification_delivery_attempts notification_delivery_attempts_delivery_id_attempt_number_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_delivery_attempts
    ADD CONSTRAINT notification_delivery_attempts_delivery_id_attempt_number_key UNIQUE (delivery_id, attempt_number);


--
-- Name: notification_delivery_attempts notification_delivery_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_delivery_attempts
    ADD CONSTRAINT notification_delivery_attempts_pkey PRIMARY KEY (id);


--
-- Name: notification_events notification_events_id_organization_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_events
    ADD CONSTRAINT notification_events_id_organization_id_key UNIQUE (id, organization_id);


--
-- Name: notification_events notification_events_organization_id_dedupe_key_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_events
    ADD CONSTRAINT notification_events_organization_id_dedupe_key_key UNIQUE (organization_id, dedupe_key);


--
-- Name: notification_events notification_events_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_events
    ADD CONSTRAINT notification_events_pkey PRIMARY KEY (id);


--
-- Name: notification_rules notification_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_rules
    ADD CONSTRAINT notification_rules_pkey PRIMARY KEY (id);


--
-- Name: notification_templates notification_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_pkey PRIMARY KEY (id);


--
-- Name: organization_memberships organization_memberships_organization_id_user_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_organization_id_user_id_key UNIQUE (organization_id, user_id);


--
-- Name: organization_memberships organization_memberships_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_pkey PRIMARY KEY (id);


--
-- Name: organizations organizations_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_pkey PRIMARY KEY (id);


--
-- Name: otp_rate_buckets otp_rate_buckets_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.otp_rate_buckets
    ADD CONSTRAINT otp_rate_buckets_pkey PRIMARY KEY (user_id, channel, bucket_date);


--
-- Name: otp_requests otp_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.otp_requests
    ADD CONSTRAINT otp_requests_pkey PRIMARY KEY (id);


--
-- Name: outgoing_webhooks outgoing_webhooks_id_organization_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.outgoing_webhooks
    ADD CONSTRAINT outgoing_webhooks_id_organization_id_key UNIQUE (id, organization_id);


--
-- Name: outgoing_webhooks outgoing_webhooks_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.outgoing_webhooks
    ADD CONSTRAINT outgoing_webhooks_pkey PRIMARY KEY (id);


--
-- Name: package_entitlements package_entitlements_package_id_capability_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.package_entitlements
    ADD CONSTRAINT package_entitlements_package_id_capability_key UNIQUE (package_id, capability);


--
-- Name: package_entitlements package_entitlements_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.package_entitlements
    ADD CONSTRAINT package_entitlements_pkey PRIMARY KEY (id);


--
-- Name: packages packages_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.packages
    ADD CONSTRAINT packages_pkey PRIMARY KEY (id);


--
-- Name: payment_webhook_events payment_webhook_events_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.payment_webhook_events
    ADD CONSTRAINT payment_webhook_events_pkey PRIMARY KEY (id);


--
-- Name: payment_webhook_events payment_webhook_events_provider_provider_event_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.payment_webhook_events
    ADD CONSTRAINT payment_webhook_events_provider_provider_event_id_key UNIQUE (provider, provider_event_id);


--
-- Name: payments payments_id_organization_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_id_organization_id_key UNIQUE (id, organization_id);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: payments payments_provider_provider_transaction_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_provider_provider_transaction_id_key UNIQUE (provider, provider_transaction_id);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: pricing_versions pricing_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.pricing_versions
    ADD CONSTRAINT pricing_versions_pkey PRIMARY KEY (id);


--
-- Name: provider_configs provider_configs_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.provider_configs
    ADD CONSTRAINT provider_configs_pkey PRIMARY KEY (id);


--
-- Name: proxies proxies_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.proxies
    ADD CONSTRAINT proxies_pkey PRIMARY KEY (id);


--
-- Name: proxy_leases proxy_leases_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.proxy_leases
    ADD CONSTRAINT proxy_leases_pkey PRIMARY KEY (id);


--
-- Name: proxy_pools proxy_pools_name_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.proxy_pools
    ADD CONSTRAINT proxy_pools_name_key UNIQUE (name);


--
-- Name: proxy_pools proxy_pools_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.proxy_pools
    ADD CONSTRAINT proxy_pools_pkey PRIMARY KEY (id);


--
-- Name: reconciliation_findings reconciliation_findings_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.reconciliation_findings
    ADD CONSTRAINT reconciliation_findings_pkey PRIMARY KEY (id);


--
-- Name: reconciliation_runs reconciliation_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.reconciliation_runs
    ADD CONSTRAINT reconciliation_runs_pkey PRIMARY KEY (id);


--
-- Name: refund_approvals refund_approvals_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.refund_approvals
    ADD CONSTRAINT refund_approvals_pkey PRIMARY KEY (id);


--
-- Name: refunds refunds_idempotency_key_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_idempotency_key_key UNIQUE (idempotency_key);


--
-- Name: refunds refunds_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_pkey PRIMARY KEY (id);


--
-- Name: resource_pools resource_pools_name_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.resource_pools
    ADD CONSTRAINT resource_pools_name_key UNIQUE (name);


--
-- Name: resource_pools resource_pools_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.resource_pools
    ADD CONSTRAINT resource_pools_pkey PRIMARY KEY (id);


--
-- Name: role_permissions role_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_pkey PRIMARY KEY (role_id, permission_id);


--
-- Name: roles roles_id_is_internal_role_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_id_is_internal_role_key UNIQUE (id, is_internal_role);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: run_requests run_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_requests
    ADD CONSTRAINT run_requests_pkey PRIMARY KEY (run_id);


--
-- Name: run_results run_results_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_results
    ADD CONSTRAINT run_results_pkey PRIMARY KEY (id);


--
-- Name: run_results run_results_run_id_canonical_entity_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_results
    ADD CONSTRAINT run_results_run_id_canonical_entity_id_key UNIQUE (run_id, canonical_entity_id);


--
-- Name: runs runs_id_organization_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.runs
    ADD CONSTRAINT runs_id_organization_id_key UNIQUE (id, organization_id);


--
-- Name: runs runs_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.runs
    ADD CONSTRAINT runs_pkey PRIMARY KEY (id);


--
-- Name: search_indexing_states search_indexing_states_index_name_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.search_indexing_states
    ADD CONSTRAINT search_indexing_states_index_name_key UNIQUE (index_name);


--
-- Name: search_indexing_states search_indexing_states_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.search_indexing_states
    ADD CONSTRAINT search_indexing_states_pkey PRIMARY KEY (id);


--
-- Name: security_events security_events_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.security_events
    ADD CONSTRAINT security_events_pkey PRIMARY KEY (id);


--
-- Name: selector_versions selector_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.selector_versions
    ADD CONSTRAINT selector_versions_pkey PRIMARY KEY (id);


--
-- Name: selectors selectors_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.selectors
    ADD CONSTRAINT selectors_pkey PRIMARY KEY (id);


--
-- Name: service_actor_permissions service_actor_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.service_actor_permissions
    ADD CONSTRAINT service_actor_permissions_pkey PRIMARY KEY (service_actor_id, permission_id);


--
-- Name: service_actors service_actors_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.service_actors
    ADD CONSTRAINT service_actors_pkey PRIMARY KEY (id);


--
-- Name: social_accounts social_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.social_accounts
    ADD CONSTRAINT social_accounts_pkey PRIMARY KEY (id);


--
-- Name: social_sessions social_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.social_sessions
    ADD CONSTRAINT social_sessions_pkey PRIMARY KEY (id);


--
-- Name: subscription_snapshots subscription_snapshots_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.subscription_snapshots
    ADD CONSTRAINT subscription_snapshots_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: system_maintenance system_maintenance_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.system_maintenance
    ADD CONSTRAINT system_maintenance_pkey PRIMARY KEY (id);


--
-- Name: task_attempts task_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.task_attempts
    ADD CONSTRAINT task_attempts_pkey PRIMARY KEY (id);


--
-- Name: task_attempts task_attempts_task_id_attempt_number_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.task_attempts
    ADD CONSTRAINT task_attempts_task_id_attempt_number_key UNIQUE (task_id, attempt_number);


--
-- Name: task_leases task_leases_id_task_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.task_leases
    ADD CONSTRAINT task_leases_id_task_id_key UNIQUE (id, task_id);


--
-- Name: task_leases task_leases_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.task_leases
    ADD CONSTRAINT task_leases_pkey PRIMARY KEY (id);


--
-- Name: tasks tasks_id_run_id_organization_id_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_id_run_id_organization_id_key UNIQUE (id, run_id, organization_id);


--
-- Name: tasks tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_pkey PRIMARY KEY (id);


--
-- Name: temporary_access_grants temporary_access_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.temporary_access_grants
    ADD CONSTRAINT temporary_access_grants_pkey PRIMARY KEY (id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: wa_instances wa_instances_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.wa_instances
    ADD CONSTRAINT wa_instances_pkey PRIMARY KEY (id);


--
-- Name: wa_pools wa_pools_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.wa_pools
    ADD CONSTRAINT wa_pools_pkey PRIMARY KEY (id);


--
-- Name: webhook_deliveries webhook_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.webhook_deliveries
    ADD CONSTRAINT webhook_deliveries_pkey PRIMARY KEY (id);


--
-- Name: idx_account_leases_active; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_account_leases_active ON public.account_leases USING btree (account_id) WHERE (released_at IS NULL);


--
-- Name: idx_account_leases_recovery; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_account_leases_recovery ON public.account_leases USING btree (expires_at, released_at) WHERE (released_at IS NULL);


--
-- Name: idx_account_leases_task; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_account_leases_task ON public.account_leases USING btree (task_id);


--
-- Name: idx_auth_logs_created_at; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_auth_logs_created_at ON public.auth_logs USING btree (created_at);


--
-- Name: idx_auth_sessions_expires_at; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_auth_sessions_expires_at ON public.auth_sessions USING btree (expires_at);


--
-- Name: idx_canonical_comments_author; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_comments_author ON public.canonical_comments USING btree (author_profile_id) WHERE (author_profile_id IS NOT NULL);


--
-- Name: idx_canonical_comments_parent; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_comments_parent ON public.canonical_comments USING btree (parent_content_id);


--
-- Name: idx_canonical_entities_identity_hash; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_entities_identity_hash ON public.canonical_entities USING btree (identity_hash);


--
-- Name: idx_canonical_entities_normalized_url; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_entities_normalized_url ON public.canonical_entities USING btree (normalized_url) WHERE (normalized_url IS NOT NULL);


--
-- Name: idx_canonical_entities_platform_type; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_entities_platform_type ON public.canonical_entities USING btree (platform, entity_type);


--
-- Name: idx_canonical_entities_source_id; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_entities_source_id ON public.canonical_entities USING btree (stable_source_id) WHERE (stable_source_id IS NOT NULL);


--
-- Name: idx_canonical_posts_author; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_posts_author ON public.canonical_posts USING btree (author_profile_id) WHERE (author_profile_id IS NOT NULL);


--
-- Name: idx_canonical_replies_author; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_replies_author ON public.canonical_replies USING btree (author_profile_id) WHERE (author_profile_id IS NOT NULL);


--
-- Name: idx_canonical_replies_parent_comment; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_replies_parent_comment ON public.canonical_replies USING btree (parent_comment_id) WHERE (parent_comment_id IS NOT NULL);


--
-- Name: idx_canonical_replies_root; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_replies_root ON public.canonical_replies USING btree (root_content_id);


--
-- Name: idx_canonical_videos_author; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_canonical_videos_author ON public.canonical_videos USING btree (author_profile_id) WHERE (author_profile_id IS NOT NULL);


--
-- Name: idx_cra_reservation_order; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_cra_reservation_order ON public.credit_reservation_allocations USING btree (reservation_id, allocation_order);


--
-- Name: idx_credit_lots_fefo; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_credit_lots_fefo ON public.credit_lots USING btree (organization_id, expires_at) WHERE (remaining_quantity > 0);


--
-- Name: idx_credit_lots_org; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_credit_lots_org ON public.credit_lots USING btree (organization_id);


--
-- Name: idx_otp_requests_user_created; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_otp_requests_user_created ON public.otp_requests USING btree (user_id, created_at);


--
-- Name: idx_pricing_versions_active_capability; Type: INDEX; Schema: public; Owner: root
--

CREATE UNIQUE INDEX idx_pricing_versions_active_capability ON public.pricing_versions USING btree (capability) WHERE ((status)::text = 'ACTIVE'::text);


--
-- Name: idx_proxies_pool; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_proxies_pool ON public.proxies USING btree (pool_id);


--
-- Name: idx_proxy_leases_active; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_proxy_leases_active ON public.proxy_leases USING btree (proxy_id) WHERE (released_at IS NULL);


--
-- Name: idx_proxy_leases_recovery; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_proxy_leases_recovery ON public.proxy_leases USING btree (expires_at, released_at) WHERE (released_at IS NULL);


--
-- Name: idx_proxy_leases_task; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_proxy_leases_task ON public.proxy_leases USING btree (task_id);


--
-- Name: idx_selector_versions_active; Type: INDEX; Schema: public; Owner: root
--

CREATE UNIQUE INDEX idx_selector_versions_active ON public.selector_versions USING btree (selector_id) WHERE (status = 'ACTIVE'::public.selector_version_status);


--
-- Name: idx_social_accounts_platform; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_social_accounts_platform ON public.social_accounts USING btree (platform);


--
-- Name: idx_social_accounts_pool; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_social_accounts_pool ON public.social_accounts USING btree (pool_id);


--
-- Name: idx_social_sessions_account; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX idx_social_sessions_account ON public.social_sessions USING btree (account_id);


--
-- Name: idx_task_leases_one_active; Type: INDEX; Schema: public; Owner: root
--

CREATE UNIQUE INDEX idx_task_leases_one_active ON public.task_leases USING btree (task_id) WHERE ((released_at IS NULL) AND ((status)::text = 'ACTIVE'::text));


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: root
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: audit_logs trg_audit_logs_append_only; Type: TRIGGER; Schema: public; Owner: root
--

CREATE TRIGGER trg_audit_logs_append_only BEFORE DELETE OR UPDATE ON public.audit_logs FOR EACH ROW EXECUTE FUNCTION public.prevent_audit_logs_modification();


--
-- Name: credit_ledger trg_credit_ledger_append_only; Type: TRIGGER; Schema: public; Owner: root
--

CREATE TRIGGER trg_credit_ledger_append_only BEFORE DELETE OR UPDATE ON public.credit_ledger FOR EACH ROW EXECUTE FUNCTION public.prevent_credit_ledger_modification();


--
-- Name: access_reviews access_reviews_reviewer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.access_reviews
    ADD CONSTRAINT access_reviews_reviewer_id_fkey FOREIGN KEY (reviewer_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: access_reviews access_reviews_target_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.access_reviews
    ADD CONSTRAINT access_reviews_target_user_id_fkey FOREIGN KEY (target_user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: account_leases account_leases_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.account_leases
    ADD CONSTRAINT account_leases_account_id_fkey FOREIGN KEY (account_id) REFERENCES public.social_accounts(id) ON DELETE RESTRICT;


--
-- Name: account_leases account_leases_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.account_leases
    ADD CONSTRAINT account_leases_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE RESTRICT;


--
-- Name: ai_conversations ai_conversations_actor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.ai_conversations
    ADD CONSTRAINT ai_conversations_actor_id_fkey FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: ai_messages ai_messages_conversation_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.ai_messages
    ADD CONSTRAINT ai_messages_conversation_id_fkey FOREIGN KEY (conversation_id) REFERENCES public.ai_conversations(id) ON DELETE RESTRICT;


--
-- Name: ai_tool_audits ai_tool_audits_message_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.ai_tool_audits
    ADD CONSTRAINT ai_tool_audits_message_id_fkey FOREIGN KEY (message_id) REFERENCES public.ai_messages(id) ON DELETE RESTRICT;


--
-- Name: ai_usage ai_usage_message_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.ai_usage
    ADD CONSTRAINT ai_usage_message_id_fkey FOREIGN KEY (message_id) REFERENCES public.ai_messages(id) ON DELETE RESTRICT;


--
-- Name: api_idempotency_keys api_idempotency_keys_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.api_idempotency_keys
    ADD CONSTRAINT api_idempotency_keys_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: api_keys api_keys_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: api_keys api_keys_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.api_keys
    ADD CONSTRAINT api_keys_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: audit_logs audit_logs_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: auth_logs auth_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.auth_logs
    ADD CONSTRAINT auth_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: auth_sessions auth_sessions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.auth_sessions
    ADD CONSTRAINT auth_sessions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: billing_reservations billing_reservations_pricing_version_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.billing_reservations
    ADD CONSTRAINT billing_reservations_pricing_version_id_fkey FOREIGN KEY (pricing_version_id) REFERENCES public.pricing_versions(id) ON DELETE RESTRICT;


--
-- Name: billing_reservations billing_reservations_run_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.billing_reservations
    ADD CONSTRAINT billing_reservations_run_id_organization_id_fkey FOREIGN KEY (run_id, organization_id) REFERENCES public.runs(id, organization_id) ON DELETE RESTRICT;


--
-- Name: break_glass_activations break_glass_activations_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.break_glass_activations
    ADD CONSTRAINT break_glass_activations_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: canonical_articles canonical_articles_canonical_entity_id_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_articles
    ADD CONSTRAINT canonical_articles_canonical_entity_id_entity_type_fkey FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: canonical_comments canonical_comments_author_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_comments
    ADD CONSTRAINT canonical_comments_author_profile_id_fkey FOREIGN KEY (author_profile_id) REFERENCES public.canonical_profiles(canonical_entity_id) ON DELETE RESTRICT;


--
-- Name: canonical_comments canonical_comments_canonical_entity_id_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_comments
    ADD CONSTRAINT canonical_comments_canonical_entity_id_entity_type_fkey FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: canonical_comments canonical_comments_parent_content_id_parent_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_comments
    ADD CONSTRAINT canonical_comments_parent_content_id_parent_entity_type_fkey FOREIGN KEY (parent_content_id, parent_entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: canonical_pages canonical_pages_canonical_entity_id_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_pages
    ADD CONSTRAINT canonical_pages_canonical_entity_id_entity_type_fkey FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: canonical_posts canonical_posts_author_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_posts
    ADD CONSTRAINT canonical_posts_author_profile_id_fkey FOREIGN KEY (author_profile_id) REFERENCES public.canonical_profiles(canonical_entity_id) ON DELETE RESTRICT;


--
-- Name: canonical_posts canonical_posts_canonical_entity_id_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_posts
    ADD CONSTRAINT canonical_posts_canonical_entity_id_entity_type_fkey FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: canonical_profiles canonical_profiles_canonical_entity_id_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_profiles
    ADD CONSTRAINT canonical_profiles_canonical_entity_id_entity_type_fkey FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: canonical_replies canonical_replies_author_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_replies
    ADD CONSTRAINT canonical_replies_author_profile_id_fkey FOREIGN KEY (author_profile_id) REFERENCES public.canonical_profiles(canonical_entity_id) ON DELETE RESTRICT;


--
-- Name: canonical_replies canonical_replies_canonical_entity_id_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_replies
    ADD CONSTRAINT canonical_replies_canonical_entity_id_entity_type_fkey FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: canonical_replies canonical_replies_parent_comment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_replies
    ADD CONSTRAINT canonical_replies_parent_comment_id_fkey FOREIGN KEY (parent_comment_id) REFERENCES public.canonical_comments(canonical_entity_id) ON DELETE RESTRICT;


--
-- Name: canonical_replies canonical_replies_root_content_id_root_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_replies
    ADD CONSTRAINT canonical_replies_root_content_id_root_entity_type_fkey FOREIGN KEY (root_content_id, root_entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: canonical_videos canonical_videos_author_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_videos
    ADD CONSTRAINT canonical_videos_author_profile_id_fkey FOREIGN KEY (author_profile_id) REFERENCES public.canonical_profiles(canonical_entity_id) ON DELETE RESTRICT;


--
-- Name: canonical_videos canonical_videos_canonical_entity_id_entity_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.canonical_videos
    ADD CONSTRAINT canonical_videos_canonical_entity_id_entity_type_fkey FOREIGN KEY (canonical_entity_id, entity_type) REFERENCES public.canonical_entities(id, entity_type) ON DELETE RESTRICT;


--
-- Name: channel_bindings channel_bindings_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.channel_bindings
    ADD CONSTRAINT channel_bindings_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: credit_allocations credit_allocations_credit_lot_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_allocations
    ADD CONSTRAINT credit_allocations_credit_lot_id_organization_id_fkey FOREIGN KEY (credit_lot_id, organization_id) REFERENCES public.credit_lots(id, organization_id) ON DELETE RESTRICT;


--
-- Name: credit_allocations credit_allocations_payment_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_allocations
    ADD CONSTRAINT credit_allocations_payment_id_organization_id_fkey FOREIGN KEY (payment_id, organization_id) REFERENCES public.payments(id, organization_id) ON DELETE RESTRICT;


--
-- Name: credit_ledger credit_ledger_actor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_ledger
    ADD CONSTRAINT credit_ledger_actor_id_fkey FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: credit_ledger credit_ledger_credit_lot_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_ledger
    ADD CONSTRAINT credit_ledger_credit_lot_id_fkey FOREIGN KEY (credit_lot_id) REFERENCES public.credit_lots(id) ON DELETE RESTRICT;


--
-- Name: credit_ledger credit_ledger_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_ledger
    ADD CONSTRAINT credit_ledger_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: credit_ledger credit_ledger_reservation_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_ledger
    ADD CONSTRAINT credit_ledger_reservation_id_organization_id_fkey FOREIGN KEY (reservation_id, organization_id) REFERENCES public.billing_reservations(id, organization_id) ON DELETE RESTRICT;


--
-- Name: credit_ledger credit_ledger_run_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_ledger
    ADD CONSTRAINT credit_ledger_run_id_organization_id_fkey FOREIGN KEY (run_id, organization_id) REFERENCES public.runs(id, organization_id) ON DELETE RESTRICT;


--
-- Name: credit_lots credit_lots_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_lots
    ADD CONSTRAINT credit_lots_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: credit_reservation_allocations credit_reservation_allocation_credit_lot_id_organization_i_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_reservation_allocations
    ADD CONSTRAINT credit_reservation_allocation_credit_lot_id_organization_i_fkey FOREIGN KEY (credit_lot_id, organization_id) REFERENCES public.credit_lots(id, organization_id) ON DELETE RESTRICT;


--
-- Name: credit_reservation_allocations credit_reservation_allocation_reservation_id_organization__fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.credit_reservation_allocations
    ADD CONSTRAINT credit_reservation_allocation_reservation_id_organization__fkey FOREIGN KEY (reservation_id, organization_id) REFERENCES public.billing_reservations(id, organization_id) ON DELETE RESTRICT;


--
-- Name: dead_letter_queue_records dead_letter_queue_records_attempt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.dead_letter_queue_records
    ADD CONSTRAINT dead_letter_queue_records_attempt_id_fkey FOREIGN KEY (attempt_id) REFERENCES public.task_attempts(id) ON DELETE RESTRICT;


--
-- Name: dead_letter_queue_records dead_letter_queue_records_run_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.dead_letter_queue_records
    ADD CONSTRAINT dead_letter_queue_records_run_id_fkey FOREIGN KEY (run_id) REFERENCES public.runs(id) ON DELETE RESTRICT;


--
-- Name: dead_letter_queue_records dead_letter_queue_records_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.dead_letter_queue_records
    ADD CONSTRAINT dead_letter_queue_records_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE RESTRICT;


--
-- Name: exports exports_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.exports
    ADD CONSTRAINT exports_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: exports exports_requested_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.exports
    ADD CONSTRAINT exports_requested_by_fkey FOREIGN KEY (requested_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: in_app_notifications in_app_notifications_logical_notification_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.in_app_notifications
    ADD CONSTRAINT in_app_notifications_logical_notification_id_fkey FOREIGN KEY (logical_notification_id) REFERENCES public.logical_notifications(id) ON DELETE RESTRICT;


--
-- Name: in_app_notifications in_app_notifications_recipient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.in_app_notifications
    ADD CONSTRAINT in_app_notifications_recipient_id_fkey FOREIGN KEY (recipient_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: internal_costs internal_costs_run_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.internal_costs
    ADD CONSTRAINT internal_costs_run_id_fkey FOREIGN KEY (run_id) REFERENCES public.runs(id) ON DELETE RESTRICT;


--
-- Name: internal_costs internal_costs_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.internal_costs
    ADD CONSTRAINT internal_costs_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE RESTRICT;


--
-- Name: internal_user_assignments internal_user_assignments_role_id_role_is_internal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.internal_user_assignments
    ADD CONSTRAINT internal_user_assignments_role_id_role_is_internal_fkey FOREIGN KEY (role_id, role_is_internal) REFERENCES public.roles(id, is_internal_role) ON DELETE RESTRICT;


--
-- Name: internal_user_assignments internal_user_assignments_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.internal_user_assignments
    ADD CONSTRAINT internal_user_assignments_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: invoices invoices_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: jit_privilege_grants jit_privilege_grants_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.jit_privilege_grants
    ADD CONSTRAINT jit_privilege_grants_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: jit_privilege_grants jit_privilege_grants_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.jit_privilege_grants
    ADD CONSTRAINT jit_privilege_grants_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: jit_privilege_grants jit_privilege_grants_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.jit_privilege_grants
    ADD CONSTRAINT jit_privilege_grants_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE RESTRICT;


--
-- Name: jit_privilege_grants jit_privilege_grants_requested_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.jit_privilege_grants
    ADD CONSTRAINT jit_privilege_grants_requested_by_fkey FOREIGN KEY (requested_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: jit_privilege_grants jit_privilege_grants_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.jit_privilege_grants
    ADD CONSTRAINT jit_privilege_grants_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: logical_notifications logical_notifications_event_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.logical_notifications
    ADD CONSTRAINT logical_notifications_event_id_organization_id_fkey FOREIGN KEY (event_id, organization_id) REFERENCES public.notification_events(id, organization_id) ON DELETE RESTRICT;


--
-- Name: logical_notifications logical_notifications_recipient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.logical_notifications
    ADD CONSTRAINT logical_notifications_recipient_id_fkey FOREIGN KEY (recipient_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: notification_deliveries notification_deliveries_logical_notification_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_logical_notification_id_fkey FOREIGN KEY (logical_notification_id) REFERENCES public.logical_notifications(id) ON DELETE RESTRICT;


--
-- Name: notification_delivery_attempts notification_delivery_attempts_delivery_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_delivery_attempts
    ADD CONSTRAINT notification_delivery_attempts_delivery_id_fkey FOREIGN KEY (delivery_id) REFERENCES public.notification_deliveries(id) ON DELETE RESTRICT;


--
-- Name: notification_delivery_attempts notification_delivery_attempts_provider_instance_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_delivery_attempts
    ADD CONSTRAINT notification_delivery_attempts_provider_instance_id_fkey FOREIGN KEY (provider_instance_id) REFERENCES public.wa_instances(id) ON DELETE RESTRICT;


--
-- Name: notification_events notification_events_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_events
    ADD CONSTRAINT notification_events_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: notification_rules notification_rules_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_rules
    ADD CONSTRAINT notification_rules_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: notification_templates notification_templates_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organization_memberships organization_memberships_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: organization_memberships organization_memberships_role_id_role_is_internal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_role_id_role_is_internal_fkey FOREIGN KEY (role_id, role_is_internal) REFERENCES public.roles(id, is_internal_role) ON DELETE RESTRICT;


--
-- Name: organization_memberships organization_memberships_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: otp_rate_buckets otp_rate_buckets_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.otp_rate_buckets
    ADD CONSTRAINT otp_rate_buckets_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: otp_requests otp_requests_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.otp_requests
    ADD CONSTRAINT otp_requests_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: outgoing_webhooks outgoing_webhooks_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.outgoing_webhooks
    ADD CONSTRAINT outgoing_webhooks_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: package_entitlements package_entitlements_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.package_entitlements
    ADD CONSTRAINT package_entitlements_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.packages(id) ON DELETE RESTRICT;


--
-- Name: payment_webhook_events payment_webhook_events_payment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.payment_webhook_events
    ADD CONSTRAINT payment_webhook_events_payment_id_fkey FOREIGN KEY (payment_id) REFERENCES public.payments(id) ON DELETE RESTRICT;


--
-- Name: payments payments_invoice_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_invoice_id_organization_id_fkey FOREIGN KEY (invoice_id, organization_id) REFERENCES public.invoices(id, organization_id) ON DELETE RESTRICT;


--
-- Name: proxies proxies_pool_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.proxies
    ADD CONSTRAINT proxies_pool_id_fkey FOREIGN KEY (pool_id) REFERENCES public.proxy_pools(id) ON DELETE RESTRICT;


--
-- Name: proxy_leases proxy_leases_proxy_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.proxy_leases
    ADD CONSTRAINT proxy_leases_proxy_id_fkey FOREIGN KEY (proxy_id) REFERENCES public.proxies(id) ON DELETE RESTRICT;


--
-- Name: proxy_leases proxy_leases_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.proxy_leases
    ADD CONSTRAINT proxy_leases_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE RESTRICT;


--
-- Name: reconciliation_findings reconciliation_findings_reconciliation_run_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.reconciliation_findings
    ADD CONSTRAINT reconciliation_findings_reconciliation_run_id_fkey FOREIGN KEY (reconciliation_run_id) REFERENCES public.reconciliation_runs(id) ON DELETE RESTRICT;


--
-- Name: refund_approvals refund_approvals_checker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.refund_approvals
    ADD CONSTRAINT refund_approvals_checker_id_fkey FOREIGN KEY (checker_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: refund_approvals refund_approvals_maker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.refund_approvals
    ADD CONSTRAINT refund_approvals_maker_id_fkey FOREIGN KEY (maker_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: refunds refunds_approval_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_approval_id_fkey FOREIGN KEY (approval_id) REFERENCES public.refund_approvals(id) ON DELETE RESTRICT;


--
-- Name: refunds refunds_payment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_payment_id_fkey FOREIGN KEY (payment_id) REFERENCES public.payments(id) ON DELETE RESTRICT;


--
-- Name: refunds refunds_run_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_run_id_organization_id_fkey FOREIGN KEY (run_id, organization_id) REFERENCES public.runs(id, organization_id) ON DELETE RESTRICT;


--
-- Name: role_permissions role_permissions_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_permissions role_permissions_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: run_requests run_requests_parent_canonical_identity_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_requests
    ADD CONSTRAINT run_requests_parent_canonical_identity_id_fkey FOREIGN KEY (parent_canonical_identity_id) REFERENCES public.canonical_entities(id) ON DELETE RESTRICT;


--
-- Name: run_requests run_requests_run_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_requests
    ADD CONSTRAINT run_requests_run_id_fkey FOREIGN KEY (run_id) REFERENCES public.runs(id) ON DELETE RESTRICT;


--
-- Name: run_requests run_requests_source_canonical_identity_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_requests
    ADD CONSTRAINT run_requests_source_canonical_identity_id_fkey FOREIGN KEY (source_canonical_identity_id) REFERENCES public.canonical_entities(id) ON DELETE RESTRICT;


--
-- Name: run_results run_results_canonical_entity_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_results
    ADD CONSTRAINT run_results_canonical_entity_id_fkey FOREIGN KEY (canonical_entity_id) REFERENCES public.canonical_entities(id) ON DELETE RESTRICT;


--
-- Name: run_results run_results_run_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_results
    ADD CONSTRAINT run_results_run_id_organization_id_fkey FOREIGN KEY (run_id, organization_id) REFERENCES public.runs(id, organization_id) ON DELETE RESTRICT;


--
-- Name: run_results run_results_source_task_id_run_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.run_results
    ADD CONSTRAINT run_results_source_task_id_run_id_organization_id_fkey FOREIGN KEY (source_task_id, run_id, organization_id) REFERENCES public.tasks(id, run_id, organization_id) ON DELETE RESTRICT;


--
-- Name: runs runs_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.runs
    ADD CONSTRAINT runs_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: runs runs_pricing_snapshot_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.runs
    ADD CONSTRAINT runs_pricing_snapshot_id_fkey FOREIGN KEY (pricing_snapshot_id) REFERENCES public.pricing_versions(id) ON DELETE RESTRICT;


--
-- Name: security_events security_events_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.security_events
    ADD CONSTRAINT security_events_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: selector_versions selector_versions_selector_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.selector_versions
    ADD CONSTRAINT selector_versions_selector_id_fkey FOREIGN KEY (selector_id) REFERENCES public.selectors(id) ON DELETE RESTRICT;


--
-- Name: service_actor_permissions service_actor_permissions_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.service_actor_permissions
    ADD CONSTRAINT service_actor_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: service_actor_permissions service_actor_permissions_service_actor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.service_actor_permissions
    ADD CONSTRAINT service_actor_permissions_service_actor_id_fkey FOREIGN KEY (service_actor_id) REFERENCES public.service_actors(id) ON DELETE CASCADE;


--
-- Name: service_actors service_actors_owner_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.service_actors
    ADD CONSTRAINT service_actors_owner_user_id_fkey FOREIGN KEY (owner_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: social_accounts social_accounts_pool_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.social_accounts
    ADD CONSTRAINT social_accounts_pool_id_fkey FOREIGN KEY (pool_id) REFERENCES public.resource_pools(id) ON DELETE RESTRICT;


--
-- Name: social_sessions social_sessions_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.social_sessions
    ADD CONSTRAINT social_sessions_account_id_fkey FOREIGN KEY (account_id) REFERENCES public.social_accounts(id) ON DELETE RESTRICT;


--
-- Name: subscription_snapshots subscription_snapshots_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.subscription_snapshots
    ADD CONSTRAINT subscription_snapshots_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE RESTRICT;


--
-- Name: subscriptions subscriptions_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: subscriptions subscriptions_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.packages(id) ON DELETE RESTRICT;


--
-- Name: system_maintenance system_maintenance_actor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.system_maintenance
    ADD CONSTRAINT system_maintenance_actor_id_fkey FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: task_attempts task_attempts_account_lease_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.task_attempts
    ADD CONSTRAINT task_attempts_account_lease_id_fkey FOREIGN KEY (account_lease_id) REFERENCES public.account_leases(id) ON DELETE RESTRICT;


--
-- Name: task_attempts task_attempts_proxy_lease_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.task_attempts
    ADD CONSTRAINT task_attempts_proxy_lease_id_fkey FOREIGN KEY (proxy_lease_id) REFERENCES public.proxy_leases(id) ON DELETE RESTRICT;


--
-- Name: task_attempts task_attempts_task_id_run_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.task_attempts
    ADD CONSTRAINT task_attempts_task_id_run_id_organization_id_fkey FOREIGN KEY (task_id, run_id, organization_id) REFERENCES public.tasks(id, run_id, organization_id) ON DELETE RESTRICT;


--
-- Name: task_leases task_leases_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.task_leases
    ADD CONSTRAINT task_leases_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE RESTRICT;


--
-- Name: tasks tasks_active_lease_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_active_lease_id_fkey FOREIGN KEY (active_lease_id, id) REFERENCES public.task_leases(id, task_id) ON DELETE RESTRICT;


--
-- Name: tasks tasks_run_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_run_id_organization_id_fkey FOREIGN KEY (run_id, organization_id) REFERENCES public.runs(id, organization_id) ON DELETE RESTRICT;


--
-- Name: temporary_access_grants temporary_access_grants_approver_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.temporary_access_grants
    ADD CONSTRAINT temporary_access_grants_approver_id_fkey FOREIGN KEY (approver_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: temporary_access_grants temporary_access_grants_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.temporary_access_grants
    ADD CONSTRAINT temporary_access_grants_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE RESTRICT;


--
-- Name: temporary_access_grants temporary_access_grants_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.temporary_access_grants
    ADD CONSTRAINT temporary_access_grants_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE RESTRICT;


--
-- Name: temporary_access_grants temporary_access_grants_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.temporary_access_grants
    ADD CONSTRAINT temporary_access_grants_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: wa_instances wa_instances_pool_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.wa_instances
    ADD CONSTRAINT wa_instances_pool_id_fkey FOREIGN KEY (pool_id) REFERENCES public.wa_pools(id) ON DELETE RESTRICT;


--
-- Name: wa_instances wa_instances_provider_config_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.wa_instances
    ADD CONSTRAINT wa_instances_provider_config_id_fkey FOREIGN KEY (provider_config_id) REFERENCES public.provider_configs(id) ON DELETE RESTRICT;


--
-- Name: webhook_deliveries webhook_deliveries_event_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.webhook_deliveries
    ADD CONSTRAINT webhook_deliveries_event_id_organization_id_fkey FOREIGN KEY (event_id, organization_id) REFERENCES public.notification_events(id, organization_id) ON DELETE RESTRICT;


--
-- Name: webhook_deliveries webhook_deliveries_webhook_id_organization_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: root
--

ALTER TABLE ONLY public.webhook_deliveries
    ADD CONSTRAINT webhook_deliveries_webhook_id_organization_id_fkey FOREIGN KEY (webhook_id, organization_id) REFERENCES public.outgoing_webhooks(id, organization_id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict gBvQY9LJDqYXPI1VZN7kXAQm0CMAmmg4diQQFkknt3VABeEHe6ZuaH0wgW0dBx0

