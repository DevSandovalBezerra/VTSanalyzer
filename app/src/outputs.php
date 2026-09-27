<?php
if($path==='/api/outputs' && $method==='GET') {
    $rows=query("SELECT p.output_directory,p.id AS project_id,p.name AS project_name,p.status AS project_status,
        v.id AS video_id,v.title,v.original_name,v.status AS video_status,v.error AS video_error,
        r.id AS run_id,r.version,r.status,r.error,r.created_at,
        COALESCE((SELECT a.status FROM ai_analyses a WHERE a.run_id=r.id ORDER BY a.created_at DESC LIMIT 1),'not_started') AS ai_status,
        COALESCE((SELECT json_agg(json_build_object('name',s.name,'status',s.status,'updated_at',s.updated_at,'error',s.error)) FROM stages s WHERE s.run_id=r.id),'[]'::json) AS stages
        FROM projects p JOIN videos v ON v.project_id=p.id LEFT JOIN runs r ON r.video_id=v.id
        WHERE p.owner_id=? ORDER BY COALESCE(r.created_at,v.created_at) DESC",[$_SESSION['user']['id']])->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){$row['outputs']=$row['run_id']?run_output_availability([...$row,'id'=>$row['run_id']],json_decode($row['stages'],true)):[];unset($row['output_directory']);}unset($row);json_response($rows);
}

// Fixed catalog: callers select a key, never a filesystem path.
function output_documents(string $rid): array {
    $documents=[
        'transcript-txt'=>['Transcrição','transcricao/transcricao.txt'],
        'transcript-srt'=>['Legendas SRT','transcricao/transcricao.srt'],
        'transcript-json'=>['Transcrição estruturada','transcricao/transcricao.json'],
        'ocr'=>['Texto das telas · OCR','ocr.json'],
        'screens'=>['Telas identificadas','telas.json'],
        'analysis'=>['Análise e conclusões','analise.json'],
        'history'=>['Histórico de revisão','historico.json'],
        'frames'=>['Índice dos frames','frames.json'],
        'audio'=>['Informações do áudio','audio.json'],
        'exports'=>['Exportações','exportacoes.json'],
        'index'=>['Índice de arquivos','INDICE.json'],
    ];
    foreach(query("SELECT id,created_at FROM ai_analyses WHERE run_id=? AND status='completed' ORDER BY created_at DESC",[$rid])->fetchAll(PDO::FETCH_ASSOC) as $a)
        $documents['ai-'.$a['id']]=['Análise por IA · Gemini · '.$a['created_at'],'gemini/'.$a['id'].'.md'];
    foreach(query('SELECT id,created_at FROM snapshots WHERE run_id=? ORDER BY created_at DESC',[$rid])->fetchAll(PDO::FETCH_ASSOC) as $s)
        $documents['snapshot-'.$s['id']]=['Versão aprovada · '.$s['created_at'],'snapshots/'.$s['id'].'.json'];
    return $documents;
}
function available_output(string $folder,string $relative): ?string {
    $base=realpath($folder);$file=realpath($folder.'/'.$relative);
    return $base && $file && str_starts_with($file,$base.'/') && is_file($file) ? $file : null;
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/outputs(?:/(text|audio))?$#',$path,$m)&&$method==='GET'){
    $r=owned_run($m[1]);$documents=output_documents($r['id']);$folder=$r['output_directory']?run_output_path($r):'';
    $stageMap=output_stage_map(query('SELECT name,status,updated_at FROM stages WHERE run_id=?',[$r['id']])->fetchAll(PDO::FETCH_ASSOC));
    $action=$m[2]??'';
    if($action==='text'){
        $key=$_GET['file']??'';
        if(!is_string($key)||!isset($documents[$key]))json_response(['error'=>'Documento não encontrado.'],404);
        $requiredStage=output_document_stage($key);
        if($requiredStage && !output_stage_file_ready($r,$stageMap,$requiredStage,$documents[$key][1]))json_response(['error'=>'Este resultado ainda não está pronto. Acompanhe o processamento.'],409);
        $file=$folder?available_output($folder,$documents[$key][1]):null;
        if(!$file)json_response(['error'=>'O arquivo ainda não foi gerado. Atualize após o processamento.'],404);
        header('X-Content-Type-Options: nosniff');
        if(($_GET['download']??'')==='1')stream_file($file,'text/plain; charset=utf-8',true);
        $limit=2*1024*1024;$content=file_get_contents($file,false,null,0,$limit);
        // Keep the preview valid UTF-8 when the byte limit bisects a character.
        for($n=0;$n<4 && preg_match('//u',$content)!==1;$n++)$content=substr($content,0,-1);
        json_response(['key'=>$key,'content'=>$content,'truncated'=>filesize($file)>$limit,'bytes'=>filesize($file)]);
    }
    if($action==='audio'){
        if(!output_stage_file_ready($r,$stageMap,'audio','audio.wav'))json_response(['error'=>'O áudio ainda não está pronto.'],409);
        $file=$folder?available_output($folder,'audio.wav'):null;
        if(!$file)json_response(['error'=>'Áudio ainda indisponível.'],404);
        stream_file($file,'audio/wav');
    }
    $files=[];
    foreach($documents as $key=>[$label,$relative]){
        $requiredStage=output_document_stage($key);if($requiredStage&&!output_stage_file_ready($r,$stageMap,$requiredStage,$relative))continue;
        $file=$folder?available_output($folder,$relative):null;
        if($file)$files[]=['key'=>$key,'label'=>$label,'path'=>$relative,'bytes'=>filesize($file),'modified'=>gmdate('c',filemtime($file)),'format'=>pathinfo($relative,PATHINFO_EXTENSION)];
    }
    $stage=query("SELECT result FROM stages WHERE run_id=? AND name='frames' AND status='completed'",[$r['id']])->fetchColumn();
    $frames=[];
    foreach(($stage?json_decode($stage,true)['frames']??[]:[]) as $frame){
        if($folder && output_stage_file_ready($r,$stageMap,'frames','frames.json') && available_output($folder,'frames/'.$frame['id'].'.jpg'))$frames[]=$frame;
    }
    $sync=query("SELECT status,error,updated_at FROM jobs WHERE project_id=? AND kind='outputs' ORDER BY created_at DESC LIMIT 1",[$r['project_id']])->fetch(PDO::FETCH_ASSOC)?:null;
    json_response(['files'=>$files,'frames'=>$frames,'audio'=>output_stage_file_ready($r,$stageMap,'audio','audio.wav'),'sync'=>$sync]);
}
