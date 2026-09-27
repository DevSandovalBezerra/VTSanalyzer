<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
$path='';$method='CLI';
require __DIR__.'/../src/media.php';
require __DIR__.'/../src/gemini.php';

query("UPDATE ai_analyses SET status='failed',error='O processador foi reiniciado. Inicie outra análise.',updated_at=now() WHERE status='processing'");
while(true){
    try {
        $job=query("UPDATE ai_analyses SET status='processing',updated_at=now() WHERE id=(SELECT id FROM ai_analyses WHERE status='queued' ORDER BY created_at FOR UPDATE SKIP LOCKED LIMIT 1) RETURNING *")->fetch(PDO::FETCH_ASSOC);
        if(!$job){sleep(2);continue;}
        try {gemini_execute($job);}
        catch(Throwable $e){
            // Only curated messages from our own exceptions reach the UI. No provider body or key is logged.
            $message=$e instanceof RuntimeException?$e->getMessage():'Falha interna ao analisar o vídeo. Consulte o diagnóstico.';
            query("UPDATE ai_analyses SET status='failed',error=?,updated_at=now() WHERE id=? AND status='processing'",[$message,$job['id']]);
            error_log('gemini_analysis_failed id='.$job['id'].' type='.get_class($e));
        }
    }catch(Throwable $e){error_log('gemini_worker_error type='.get_class($e));sleep(5);}
}
