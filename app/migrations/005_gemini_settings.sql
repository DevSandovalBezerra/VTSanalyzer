CREATE TABLE ai_settings (
    user_id text PRIMARY KEY REFERENCES users(id),
    gemini_key_encrypted text,
    key_suffix text,
    key_version integer NOT NULL DEFAULT 0,
    validated_at timestamptz,
    available_models jsonb NOT NULL DEFAULT '[]',
    model text NOT NULL DEFAULT '',
    prompt_draft text NOT NULL DEFAULT '',
    draft_version integer NOT NULL DEFAULT 0,
    updated_at timestamptz NOT NULL DEFAULT now()
);
