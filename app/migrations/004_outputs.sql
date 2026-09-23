ALTER TABLE projects ADD COLUMN output_directory text UNIQUE;
CREATE UNIQUE INDEX one_queued_outputs_per_project ON jobs(project_id) WHERE kind='outputs' AND status='queued';

-- Transactional outbox: review edits also update the local files, including after a restart.
CREATE FUNCTION enqueue_project_outputs() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE pid text;
BEGIN
    IF TG_TABLE_NAME='projects' THEN pid:=NEW.id;
    ELSIF TG_TABLE_NAME IN ('videos','target_inventories') THEN pid:=NEW.project_id;
    ELSIF TG_TABLE_NAME='runs' THEN SELECT project_id INTO pid FROM videos WHERE id=NEW.video_id;
    ELSE SELECT v.project_id INTO pid FROM runs r JOIN videos v ON v.id=r.video_id WHERE r.id=NEW.run_id;
    END IF;
    INSERT INTO jobs(id,project_id,kind,payload)
    VALUES(replace(gen_random_uuid()::text,'-',''),pid,'outputs',jsonb_build_object('project_id',pid))
    ON CONFLICT(project_id) WHERE kind='outputs' AND status='queued' DO NOTHING;
    RETURN NEW;
END;
$$;
CREATE TRIGGER outputs_project AFTER INSERT OR UPDATE OF name,objective,domain,notes,status ON projects FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
CREATE TRIGGER outputs_video AFTER INSERT OR UPDATE ON videos FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
CREATE TRIGGER outputs_run AFTER INSERT OR UPDATE ON runs FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
CREATE TRIGGER outputs_stage AFTER INSERT OR UPDATE ON stages FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
CREATE TRIGGER outputs_claim AFTER INSERT OR UPDATE ON claims FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
CREATE TRIGGER outputs_revision AFTER INSERT ON revisions FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
CREATE TRIGGER outputs_snapshot AFTER INSERT ON snapshots FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
CREATE TRIGGER outputs_artifact AFTER INSERT OR UPDATE ON artifacts FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
CREATE TRIGGER outputs_inventory AFTER INSERT OR UPDATE ON target_inventories FOR EACH ROW EXECUTE FUNCTION enqueue_project_outputs();
