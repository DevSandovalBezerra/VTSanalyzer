CREATE TABLE ai_analyses (
    id text PRIMARY KEY,
    run_id text NOT NULL REFERENCES runs(id),
    user_id text NOT NULL REFERENCES users(id),
    status text NOT NULL DEFAULT 'queued' CHECK(status IN ('queued','processing','completed','failed','cancelled')),
    model text NOT NULL,
    prompt_snapshot text NOT NULL,
    draft_version integer NOT NULL,
    key_version integer NOT NULL,
    stage_versions jsonb NOT NULL,
    evidence_manifest jsonb,
    result_text text,
    warnings jsonb NOT NULL DEFAULT '[]',
    error text,
    progress_done integer NOT NULL DEFAULT 0,
    progress_total integer NOT NULL DEFAULT 0,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ai_analyses_one_active ON ai_analyses(run_id) WHERE status IN ('queued','processing');
CREATE INDEX ai_analyses_by_run ON ai_analyses(run_id,created_at DESC);
