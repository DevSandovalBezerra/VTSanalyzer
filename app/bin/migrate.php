<?php
require __DIR__.'/../src/bootstrap.php';
db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version text PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now())');
query("SELECT pg_advisory_lock(830019)");
try {
    foreach (glob(__DIR__.'/../migrations/*.sql') as $file) {
        $version=basename($file);
        if(query('SELECT 1 FROM schema_migrations WHERE version=?',[$version])->fetchColumn()) continue;
        db()->beginTransaction();
        try { db()->exec(file_get_contents($file)); query('INSERT INTO schema_migrations(version) VALUES (?)',[$version]); db()->commit(); echo "Applied $version\n"; }
        catch(Throwable $e) { db()->rollBack(); throw $e; }
    }
} finally { query('SELECT pg_advisory_unlock(830019)'); }
