<?php
function owned_video(string $vid): array {
    $v=query('SELECT v.* FROM videos v JOIN projects p ON v.project_id=p.id WHERE v.id=? AND p.owner_id=?',[$vid,$_SESSION['user']['id']])->fetch(PDO::FETCH_ASSOC);
    if(!$v)json_response(['error'=>'Vídeo não encontrado.'],404);return $v;
}
function owned_run(string $rid): array {
    $r=query('SELECT r.*,v.project_id,v.title,v.sensitivity FROM runs r JOIN videos v ON r.video_id=v.id JOIN projects p ON v.project_id=p.id WHERE r.id=? AND p.owner_id=?',[$rid,$_SESSION['user']['id']])->fetch(PDO::FETCH_ASSOC);
    if(!$r)json_response(['error'=>'Execução não encontrada.'],404);return $r;
}
function enqueue(string $kind,string $pid,array $payload): string { $jid=id();query('INSERT INTO jobs(id,project_id,kind,payload) VALUES (?,?,?,?)',[$jid,$pid,$kind,json_encode($payload,JSON_THROW_ON_ERROR)]);return $jid; }
function stage_result(string $rid,string $name): array { $s=query('SELECT result FROM stages WHERE run_id=? AND name=?',[$rid,$name])->fetchColumn();return $s?json_decode($s,true,64,JSON_THROW_ON_ERROR):[]; }
function stream_file(string $file,string $mime,bool $download=false): never {
    if(!is_file($file))json_response(['error'=>'Arquivo indisponível.'],404);
    session_write_close();$size=filesize($file);$start=0;$end=$size-1;
    header('Content-Type: '.$mime);header('Accept-Ranges: bytes');
    if($download)header('Content-Disposition: attachment; filename="'.basename($file).'"');
    if(isset($_SERVER['HTTP_RANGE'])){
        if(!preg_match('/^bytes=(\d*)-(\d*)$/',$_SERVER['HTTP_RANGE'],$range)||($range[1]===''&&$range[2]==='')) {http_response_code(416);header("Content-Range: bytes */$size");exit;}
        if($range[1]===''){$start=max(0,$size-(int)$range[2]);}else{$start=(int)$range[1];if($range[2]!=='')$end=min($end,(int)$range[2]);}
        if($start>$end||$start>=$size){http_response_code(416);header("Content-Range: bytes */$size");exit;}
        http_response_code(206);header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: '.($end-$start+1));$f=fopen($file,'rb');fseek($f,$start);$remaining=$end-$start+1;
    while($remaining>0&&!feof($f)&&!connection_aborted()){$chunk=fread($f,min(65536,$remaining));echo $chunk;$remaining-=strlen($chunk);}fclose($f);exit;
}
if(preg_match('#^/api/projects/([a-f0-9]{32})/videos$#',$path,$m)){
    $p=owned_project($m[1]);
    if($method==='GET')json_response(query('SELECT v.*,(SELECT json_agg(r ORDER BY r.created_at DESC) FROM runs r WHERE r.video_id=v.id) AS runs FROM videos v WHERE project_id=? ORDER BY created_at DESC',[$p['id']])->fetchAll(PDO::FETCH_ASSOC));
    if($method==='POST'){
        $v=input();$name=required($v,'name',255);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$size=filter_var($v['size']??0,FILTER_VALIDATE_INT);
        if(!in_array($ext,['mp4','mov','mkv','webm'],true))json_response(['error'=>'Envie MP4, MOV, MKV ou WebM.'],422);
        $max=(int)(getenv('MAX_UPLOAD_BYTES')?:4294967296);if(!$size||$size>$max)json_response(['error'=>'Tamanho fora do limite de upload.'],422);
        if(disk_free_space('/data')<$size*3+1073741824)json_response(['error'=>'Espaço insuficiente para vídeo e derivados.'],422);
        $sensitivity=$v['sensitivity']??'internal';if(!in_array($sensitivity,['public','internal','sensitive'],true))json_response(['error'=>'Classificação inválida.'],422);
        $vid=id();mkdir('/data/uploads/'.$vid,0770,true);
        query('INSERT INTO videos(id,project_id,title,original_name,extension,size,origin,context,sensitivity) VALUES (?,?,?,?,?,?,?,?,?)',[$vid,$p['id'],required($v,'title'),$name,$ext,$size,substr((string)($v['origin']??''),0,1000),substr((string)($v['context']??''),0,8000),$sensitivity]);audit('video.upload_started',$vid);json_response(['id'=>$vid,'chunk_size'=>8388608],201);
    }
}
if(preg_match('#^/api/uploads/([a-f0-9]{32})$#',$path,$m)&&$method==='GET') { $v=owned_video($m[1]);json_response(['video'=>$v,'chunks'=>query('SELECT number,size,sha256 FROM upload_chunks WHERE video_id=? ORDER BY number',[$v['id']])->fetchAll(PDO::FETCH_ASSOC)]); }
if(preg_match('#^/api/uploads/([a-f0-9]{32})/chunks/(\d+)$#',$path,$m)&&$method==='PUT'){
    $v=owned_video($m[1]);$number=(int)$m[2];$count=(int)ceil($v['size']/8388608);
    if($v['status']!=='uploading'||$number>=$count)json_response(['error'=>'Bloco fora do upload ativo.'],409);
    $expected=$number===$count-1?(int)$v['size']-$number*8388608:8388608;
    $raw=file_get_contents('php://input',false,null,0,8388609);if(strlen($raw)!==$expected)json_response(['error'=>'Tamanho do bloco inválido.'],422);
    $hash=hash('sha256',$raw);db()->beginTransaction();query('SELECT id FROM videos WHERE id=? FOR UPDATE',[$v['id']]);
    $fresh=owned_video($v['id']);if($fresh['status']!=='uploading'){db()->rollBack();json_response(['error'=>'Upload já finalizado.'],409);}
    $existing=query('SELECT sha256 FROM upload_chunks WHERE video_id=? AND number=?',[$v['id'],$number])->fetchColumn();
    if($existing&&$existing!==$hash){db()->rollBack();json_response(['error'=>'Conteúdo do bloco diverge do já recebido.'],409);}
    $file='/data/uploads/'.$v['id'].'/'.$number.'.part';if(!$existing){file_put_contents($file.'.tmp',$raw,LOCK_EX);rename($file.'.tmp',$file);query('INSERT INTO upload_chunks(video_id,number,size,sha256) VALUES (?,?,?,?)',[$v['id'],$number,$expected,$hash]);}db()->commit();json_response(['sha256'=>$hash]);
}
if(preg_match('#^/api/uploads/([a-f0-9]{32})/complete$#',$path,$m)&&$method==='POST'){
    $v=owned_video($m[1]);db()->beginTransaction();query('SELECT id FROM videos WHERE id=? FOR UPDATE',[$v['id']]);$v=owned_video($v['id']);
    if($v['status']!=='uploading'){db()->rollBack();json_response(['error'=>'O upload já foi finalizado.'],409);}
    $sum=(int)query('SELECT COALESCE(sum(size),0) FROM upload_chunks WHERE video_id=?',[$v['id']])->fetchColumn();if($sum!==(int)$v['size']){db()->rollBack();json_response(['error'=>'Ainda existem blocos pendentes.'],422);}
    query("UPDATE videos SET status='validating' WHERE id=?",[$v['id']]);enqueue('ingest',$v['project_id'],['video_id'=>$v['id']]);audit('video.validation_queued',$v['id']);db()->commit();json_response(['status'=>'validating'],202);
}
if(preg_match('#^/api/videos/([a-f0-9]{32})/stream$#',$path,$m)&&$method==='GET'){$v=owned_video($m[1]);stream_file('/data/videos/'.$v['id'].'.'.$v['extension'],['mp4'=>'video/mp4','mov'=>'video/quicktime','mkv'=>'video/x-matroska','webm'=>'video/webm'][$v['extension']]);}
if(preg_match('#^/api/videos/([a-f0-9]{32})/runs$#',$path,$m)&&$method==='POST'){
    $v=owned_video($m[1]);if($v['status']!=='ready')json_response(['error'=>'Aguarde a validação técnica do vídeo.'],422);$data=input();
    $caps=json_decode(queue()->get('worker:capabilities')?:'{}',true);$metadata=json_decode($v['metadata'],true);
    if(!queue()->exists('worker:heartbeat')||empty($caps['ffmpeg'])||empty($caps['ocr']))json_response(['error'=>'Worker, FFmpeg ou OCR indisponível. Execute o diagnóstico.'],422);
    if(!empty($metadata['audio_codec'])&&empty($caps['transcription_model_cached']))json_response(['error'=>'Instale o modelo de transcrição local com scripts/download-model.sh antes de iniciar vídeos com áudio.'],422);
    $profile=$data['profile']??'balanced';$presets=['quick'=>[15,.5,640],'balanced'=>[5,.3,1280],'detailed'=>[2,.15,1600]];if(!isset($presets[$profile]))json_response(['error'=>'Perfil inválido.'],422);
    $interval=filter_var($data['interval']??$presets[$profile][0],FILTER_VALIDATE_FLOAT);$scene=filter_var($data['scene']??$presets[$profile][1],FILTER_VALIDATE_FLOAT);
    if($interval===false||$interval<1||$interval>120||$scene===false||$scene<.05||$scene>.95)json_response(['error'=>'Intervalo ou sensibilidade inválidos.'],422);
    $config=['profile'=>$profile,'interval'=>$interval,'scene'=>$scene,'width'=>$presets[$profile][2],'ocr_language'=>'por+eng','language'=>'pt','model_profile'=>'astra','external_analysis'=>false,'max_frames'=>600];
    $rid=id();db()->beginTransaction();query('SELECT id FROM videos WHERE id=? FOR UPDATE',[$v['id']]);$version=1+(int)query('SELECT COALESCE(max(version),0) FROM runs WHERE video_id=?',[$v['id']])->fetchColumn();query('INSERT INTO runs(id,video_id,config,version) VALUES (?,?,?,?)',[$rid,$v['id'],json_encode($config),$version]);
    foreach(['audio','transcript','frames','ocr','screens'] as $s)query('INSERT INTO stages(run_id,name) VALUES (?,?)',[$rid,$s]);enqueue('pipeline',$v['project_id'],['run_id'=>$rid]);audit('run.queued',$rid);db()->commit();json_response(['id'=>$rid],202);
}
if(preg_match('#^/api/runs/([a-f0-9]{32})$#',$path,$m)&&$method==='GET'){
    $r=owned_run($m[1]);$r['config']=json_decode($r['config'],true);$r['stages']=query('SELECT * FROM stages WHERE run_id=? ORDER BY updated_at',[$r['id']])->fetchAll(PDO::FETCH_ASSOC);foreach($r['stages'] as &$s)$s['result']=$s['result']?json_decode($s['result'],true):null;unset($s);
    $r['claims']=query('SELECT * FROM claims WHERE run_id=? ORDER BY created_at',[$r['id']])->fetchAll(PDO::FETCH_ASSOC);foreach($r['claims'] as &$c)$c['evidence']=json_decode($c['evidence'],true);unset($c);json_response($r);
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/(cancel|resume)$#',$path,$m)&&$method==='POST'){
    $r=owned_run($m[1]);db()->beginTransaction();query('SELECT id FROM runs WHERE id=? FOR UPDATE',[$r['id']]);$r=owned_run($r['id']);
    if($m[2]==='cancel'){
        if(!in_array($r['status'],['queued','processing'],true)){db()->rollBack();json_response(['error'=>'Execução não está ativa.'],409);}
        query("UPDATE runs SET status='cancelled',updated_at=now() WHERE id=?",[$r['id']]);
    }else{
        if(!in_array($r['status'],['failed','cancelled','review'],true)){db()->rollBack();json_response(['error'=>'Aguarde o término da execução.'],409);}
        if(!empty(json_decode($r['config'],true)['redaction_pending'])){db()->rollBack();json_response(['error'=>'Há uma ocultação pendente. Repita a ocultação antes de retomar o processamento.'],409);}
        $active=(int)query("SELECT count(*) FROM jobs WHERE kind='pipeline' AND payload->>'run_id'=? AND status IN ('queued','processing')",[$r['id']])->fetchColumn();if($active){db()->rollBack();json_response(['error'=>'Aguarde o worker confirmar o cancelamento.'],409);}
        $v=input();$from=$v['stage']??null;$order=['audio','transcript','frames','ocr','screens'];
        if($from!==null&&!in_array($from,$order,true)){db()->rollBack();json_response(['error'=>'Etapa inválida.'],422);}
        $masked=array_filter(stage_result($r['id'],'frames')['frames']??[],fn($f)=>!empty($f['redactions']));if($from!==null&&array_search($from,$order,true)<=2&&$masked){db()->rollBack();json_response(['error'=>'Esta versão contém ocultações. Crie uma nova análise para refazer a captura, preservando esta versão protegida.'],409);}
        if($from!==null){foreach(array_slice($order,array_search($from,$order,true)) as $s)query("UPDATE stages SET status='pending',error=NULL WHERE run_id=? AND name=?",[$r['id'],$s]);}
        query("UPDATE runs SET status='queued',error=NULL,approved_at=NULL,updated_at=now() WHERE id=?",[$r['id']]);enqueue('pipeline',$r['project_id'],['run_id'=>$r['id']]);
    }audit('run.'.$m[2],$r['id']);db()->commit();json_response(['ok'=>true],202);
}
require __DIR__.'/knowledge.php';
if(preg_match('#^/api/runs/([a-f0-9]{32})/frames/([a-f0-9]{32})$#',$path,$m)&&$method==='GET'){
    $r=owned_run($m[1]);$frames=stage_result($r['id'],'frames')['frames']??[];$matches=array_values(array_filter($frames,fn($f)=>$f['id']===$m[2]));if(!$matches)json_response(['error'=>'Frame não encontrado.'],404);stream_file('/data/runs/'.$r['id'].'/frames/'.$m[2].'.jpg','image/jpeg');
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/transcript/([a-f0-9]{32})$#',$path,$m)&&$method==='PATCH'){
    $r=owned_run($m[1]);if($r['status']!=='review')json_response(['error'=>'Edite somente quando a execução estiver em revisão.'],409);$v=input();$text=required($v,'text',8000);
    db()->beginTransaction();query("SELECT name FROM stages WHERE run_id=? AND name='transcript' FOR UPDATE",[$r['id']]);$result=stage_result($r['id'],'transcript');$found=false;
    foreach($result['segments'] as &$s)if($s['id']===$m[2]){$before=$s;$s['text']=$text;$s['edited']=true;$found=true;query('INSERT INTO revisions(run_id,kind,item_id,before_value,after_value,actor_id) VALUES (?,?,?,?,?,?)',[$r['id'],'transcript',$s['id'],json_encode($before),json_encode($s),$_SESSION['user']['id']]);}unset($s);
    if(!$found){db()->rollBack();json_response(['error'=>'Segmento não encontrado.'],404);}query("UPDATE stages SET result=? WHERE run_id=? AND name='transcript'",[json_encode($result),$r['id']]);audit('transcript.edited',$r['id']);db()->commit();json_response(['ok'=>true]);
}
