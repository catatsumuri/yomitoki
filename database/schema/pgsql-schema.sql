--
-- PostgreSQL database dump
--

\restrict 4NKq7VPHLGWgykPoTaITiKi6ZUUp1kKiALs21Sycbh5IiedG5CMpU4MrO1Du13U

-- Dumped from database version 17.10 (Debian 17.10-1.pgdg12+1)
-- Dumped by pg_dump version 18.4 (Ubuntu 18.4-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: vector; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA public;


--
-- Name: EXTENSION vector; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION vector IS 'vector data type and ivfflat and hnsw access methods';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: agent_conversation_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_conversation_messages (
    id character varying(36) NOT NULL,
    conversation_id character varying(36) NOT NULL,
    user_id bigint,
    agent character varying(255) NOT NULL,
    role character varying(25) NOT NULL,
    content text NOT NULL,
    attachments text NOT NULL,
    tool_calls text NOT NULL,
    tool_results text NOT NULL,
    usage text NOT NULL,
    meta text NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: agent_conversations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_conversations (
    id character varying(36) NOT NULL,
    user_id bigint,
    title character varying(255) NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: ai_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_runs (
    id bigint NOT NULL,
    user_id bigint,
    target_type character varying(255),
    target_id bigint,
    result_document_id bigint,
    run_type character varying(255) NOT NULL,
    agent_name character varying(255),
    conversation_id character varying(36),
    provider character varying(255),
    model character varying(255),
    provider_run_id character varying(255),
    status character varying(255) DEFAULT 'queued'::character varying NOT NULL,
    prompt text,
    input_payload json,
    output_payload json,
    usage json,
    error_message text,
    started_at timestamp(0) with time zone,
    completed_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: ai_runs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_runs_id_seq OWNED BY public.ai_runs.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: document_scraps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_scraps (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    scrap_id bigint NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    role character varying(255) DEFAULT 'source'::character varying NOT NULL,
    excerpt text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: document_scraps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.document_scraps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: document_scraps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.document_scraps_id_seq OWNED BY public.document_scraps.id;


--
-- Name: documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.documents (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    document_type character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    content_markdown text NOT NULL,
    summary text,
    outline json,
    meta json,
    published_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: documents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.documents_id_seq OWNED BY public.documents.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: passkeys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.passkeys (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    credential_id character varying(255) NOT NULL,
    credential json NOT NULL,
    last_used_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: passkeys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.passkeys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: passkeys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.passkeys_id_seq OWNED BY public.passkeys.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) with time zone
);


--
-- Name: scrap_relations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scrap_relations (
    id bigint NOT NULL,
    from_scrap_id bigint NOT NULL,
    to_scrap_id bigint NOT NULL,
    relation_type character varying(255) NOT NULL,
    confidence smallint,
    meta json,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: scrap_relations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.scrap_relations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: scrap_relations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.scrap_relations_id_seq OWNED BY public.scrap_relations.id;


--
-- Name: scrap_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scrap_sources (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    source_type character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    external_id character varying(255),
    uri text,
    channel character varying(255),
    meta json,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: scrap_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.scrap_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: scrap_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.scrap_sources_id_seq OWNED BY public.scrap_sources.id;


--
-- Name: scraps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scraps (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    parent_id bigint,
    scrap_source_id bigint,
    source_type character varying(255) NOT NULL,
    source_reference character varying(255),
    title character varying(255),
    slug character varying(255),
    content text NOT NULL,
    content_markdown text,
    summary text,
    status character varying(255) DEFAULT 'raw'::character varying NOT NULL,
    language character varying(12),
    occurred_at timestamp(0) with time zone,
    processed_at timestamp(0) with time zone,
    extracted_data json,
    meta json,
    embedding public.vector(1024),
    embedding_model character varying(255),
    embedding_generated_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: scraps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.scraps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: scraps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.scraps_id_seq OWNED BY public.scraps.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) with time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: ai_runs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_runs ALTER COLUMN id SET DEFAULT nextval('public.ai_runs_id_seq'::regclass);


--
-- Name: document_scraps id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_scraps ALTER COLUMN id SET DEFAULT nextval('public.document_scraps_id_seq'::regclass);


--
-- Name: documents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents ALTER COLUMN id SET DEFAULT nextval('public.documents_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: passkeys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys ALTER COLUMN id SET DEFAULT nextval('public.passkeys_id_seq'::regclass);


--
-- Name: scrap_relations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_relations ALTER COLUMN id SET DEFAULT nextval('public.scrap_relations_id_seq'::regclass);


--
-- Name: scrap_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_sources ALTER COLUMN id SET DEFAULT nextval('public.scrap_sources_id_seq'::regclass);


--
-- Name: scraps id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraps ALTER COLUMN id SET DEFAULT nextval('public.scraps_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: agent_conversation_messages agent_conversation_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_conversation_messages
    ADD CONSTRAINT agent_conversation_messages_pkey PRIMARY KEY (id);


--
-- Name: agent_conversations agent_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_conversations
    ADD CONSTRAINT agent_conversations_pkey PRIMARY KEY (id);


--
-- Name: ai_runs ai_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_runs
    ADD CONSTRAINT ai_runs_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: document_scraps document_scraps_document_id_scrap_id_role_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_scraps
    ADD CONSTRAINT document_scraps_document_id_scrap_id_role_unique UNIQUE (document_id, scrap_id, role);


--
-- Name: document_scraps document_scraps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_scraps
    ADD CONSTRAINT document_scraps_pkey PRIMARY KEY (id);


--
-- Name: documents documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: passkeys passkeys_credential_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_credential_id_unique UNIQUE (credential_id);


--
-- Name: passkeys passkeys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: scrap_relations scrap_relations_from_scrap_id_to_scrap_id_relation_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_relations
    ADD CONSTRAINT scrap_relations_from_scrap_id_to_scrap_id_relation_type_unique UNIQUE (from_scrap_id, to_scrap_id, relation_type);


--
-- Name: scrap_relations scrap_relations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_relations
    ADD CONSTRAINT scrap_relations_pkey PRIMARY KEY (id);


--
-- Name: scrap_sources scrap_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_sources
    ADD CONSTRAINT scrap_sources_pkey PRIMARY KEY (id);


--
-- Name: scrap_sources scrap_sources_user_id_source_type_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_sources
    ADD CONSTRAINT scrap_sources_user_id_source_type_name_unique UNIQUE (user_id, source_type, name);


--
-- Name: scraps scraps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraps
    ADD CONSTRAINT scraps_pkey PRIMARY KEY (id);


--
-- Name: scraps scraps_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraps
    ADD CONSTRAINT scraps_slug_unique UNIQUE (slug);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: agent_conversation_messages_conversation_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_conversation_messages_conversation_id_index ON public.agent_conversation_messages USING btree (conversation_id);


--
-- Name: agent_conversation_messages_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_conversation_messages_user_id_index ON public.agent_conversation_messages USING btree (user_id);


--
-- Name: agent_conversations_user_id_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_conversations_user_id_updated_at_index ON public.agent_conversations USING btree (user_id, updated_at);


--
-- Name: ai_runs_conversation_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_runs_conversation_id_index ON public.ai_runs USING btree (conversation_id);


--
-- Name: ai_runs_run_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_runs_run_type_status_index ON public.ai_runs USING btree (run_type, status);


--
-- Name: ai_runs_target_type_target_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_runs_target_type_target_id_index ON public.ai_runs USING btree (target_type, target_id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: conversation_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX conversation_index ON public.agent_conversation_messages USING btree (conversation_id, user_id, updated_at);


--
-- Name: document_scraps_document_id_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_scraps_document_id_position_index ON public.document_scraps USING btree (document_id, "position");


--
-- Name: documents_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_status_index ON public.documents USING btree (status);


--
-- Name: documents_user_id_document_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_user_id_document_type_index ON public.documents USING btree (user_id, document_type);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: passkeys_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX passkeys_user_id_index ON public.passkeys USING btree (user_id);


--
-- Name: scrap_relations_to_scrap_id_relation_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scrap_relations_to_scrap_id_relation_type_index ON public.scrap_relations USING btree (to_scrap_id, relation_type);


--
-- Name: scrap_sources_user_id_source_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scrap_sources_user_id_source_type_index ON public.scrap_sources USING btree (user_id, source_type);


--
-- Name: scraps_embedding_vectorindex; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scraps_embedding_vectorindex ON public.scraps USING hnsw (embedding public.vector_cosine_ops);


--
-- Name: scraps_parent_id_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scraps_parent_id_occurred_at_index ON public.scraps USING btree (parent_id, occurred_at);


--
-- Name: scraps_source_reference_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scraps_source_reference_index ON public.scraps USING btree (source_reference);


--
-- Name: scraps_source_type_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scraps_source_type_occurred_at_index ON public.scraps USING btree (source_type, occurred_at);


--
-- Name: scraps_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scraps_user_id_status_index ON public.scraps USING btree (user_id, status);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: ai_runs ai_runs_result_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_runs
    ADD CONSTRAINT ai_runs_result_document_id_foreign FOREIGN KEY (result_document_id) REFERENCES public.documents(id) ON DELETE SET NULL;


--
-- Name: ai_runs ai_runs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_runs
    ADD CONSTRAINT ai_runs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: document_scraps document_scraps_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_scraps
    ADD CONSTRAINT document_scraps_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_scraps document_scraps_scrap_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_scraps
    ADD CONSTRAINT document_scraps_scrap_id_foreign FOREIGN KEY (scrap_id) REFERENCES public.scraps(id) ON DELETE CASCADE;


--
-- Name: documents documents_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: passkeys passkeys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: scrap_relations scrap_relations_from_scrap_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_relations
    ADD CONSTRAINT scrap_relations_from_scrap_id_foreign FOREIGN KEY (from_scrap_id) REFERENCES public.scraps(id) ON DELETE CASCADE;


--
-- Name: scrap_relations scrap_relations_to_scrap_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_relations
    ADD CONSTRAINT scrap_relations_to_scrap_id_foreign FOREIGN KEY (to_scrap_id) REFERENCES public.scraps(id) ON DELETE CASCADE;


--
-- Name: scrap_sources scrap_sources_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scrap_sources
    ADD CONSTRAINT scrap_sources_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: scraps scraps_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraps
    ADD CONSTRAINT scraps_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.scraps(id) ON DELETE SET NULL;


--
-- Name: scraps scraps_scrap_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraps
    ADD CONSTRAINT scraps_scrap_source_id_foreign FOREIGN KEY (scrap_source_id) REFERENCES public.scrap_sources(id) ON DELETE SET NULL;


--
-- Name: scraps scraps_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraps
    ADD CONSTRAINT scraps_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict 4NKq7VPHLGWgykPoTaITiKi6ZUUp1kKiALs21Sycbh5IiedG5CMpU4MrO1Du13U

--
-- PostgreSQL database dump
--

\restrict AbbkhBg3MYnidPNQE3HY9QWtuM27ykxbNrwZyN0dEqMOnwC5PmZ2sLGdWTGmU3y

-- Dumped from database version 17.10 (Debian 17.10-1.pgdg12+1)
-- Dumped by pg_dump version 18.4 (Ubuntu 18.4-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	0001_01_01_000003_enable_pgvector_extension	1
5	2024_01_01_000000_create_passkeys_table	1
6	2026_05_14_140329_create_scrap_sources_table	1
7	2026_05_14_140330_create_scraps_table	1
8	2026_05_14_140331_create_scrap_relations_table	1
9	2026_05_14_140332_create_documents_table	1
10	2026_05_14_140333_create_ai_runs_table	1
11	2026_05_14_140334_create_document_scraps_table	1
12	2026_05_14_140453_create_agent_conversations_table	1
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 12, true);


--
-- PostgreSQL database dump complete
--

\unrestrict AbbkhBg3MYnidPNQE3HY9QWtuM27ykxbNrwZyN0dEqMOnwC5PmZ2sLGdWTGmU3y

