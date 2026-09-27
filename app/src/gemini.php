<?php
function gemini_default_prompt(): string {return trim(file_get_contents(__DIR__.'/../prompts/gemini-analysis.md'));}
function gemini_settings_row(): array {
    $uid=$_SESSION['user']['id'];
    query('INSERT INTO ai_settings(user_id) VALUES (?) ON CONFLICT DO NOTHING',[$uid]);
    return query('SELECT * FROM ai_settings WHERE user_id=?',[$uid])->fetch(PDO::FETCH_ASSOC);
}
function gemini_public_settings(array $s): array {
    return ['has_key'=>!empty($s['gemini_key_encrypted']),'key_suffix'=>$s['key_suffix'],
        'validated_at'=>$s['validated_at'],'models'=>json_decode($s['available_models'],true),
        'model'=>$s['model'],'prompt'=>$s['prompt_draft']?:gemini_default_prompt(),
        'default_prompt'=>gemini_default_prompt(),'draft_version'=>(int)$s['draft_version'],
        'prompt_status'=>'ready','analysis_enabled'=>true];
}
function gemini_master_key(): string {
    $dir='/data/secrets';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Secret storage unavailable');
    $file=$dir.'/gemini-master.key';$f=fopen($file,'c+b');if(!$f)throw new RuntimeException('Secret storage unavailable');
    try {
        if(!flock($f,LOCK_EX))throw new RuntimeException('Secret storage unavailable');
        chmod($file,0600);$key=stream_get_contents($f);
        if($key===''){$key=random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);if(fwrite($f,$key)!==strlen($key))throw new RuntimeException('Secret storage unavailable');fflush($f);}
        if(strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new RuntimeException('Invalid secret storage');
        return $key;
    } finally {flock($f,LOCK_UN);fclose($f);}
}
function gemini_seal_key(string $key,string $uid): string {
    $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $derived=sodium_crypto_generichash($uid,gemini_master_key(),SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    return base64_encode($nonce.sodium_crypto_secretbox($key,$nonce,$derived));
}
function gemini_open_key(string $encrypted,string $uid): string {
    $raw=base64_decode($encrypted,true);
    if($raw===false||strlen($raw)<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new RuntimeException('Invalid stored key');
    $derived=sodium_crypto_generichash($uid,gemini_master_key(),SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    $value=sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$derived);
    if($value===false)throw new RuntimeException('Invalid stored key');return $value;
}
function gemini_models_request(string $key,string $pageToken=''): array {
    $url='https://generativelanguage.googleapis.com/v1beta/models?pageSize=1000';
    if($pageToken!=='')$url.='&pageToken='.rawurlencode($pageToken);
    $curl=curl_init($url);$body='';
    curl_setopt_array($curl,[CURLOPT_HTTPHEADER=>['x-goog-api-key: '.$key,'Accept: application/json'],
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION=>function($handle,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>2000000)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if($ok===false)throw new RuntimeException('Não foi possível conectar ao Gemini. Tente novamente.');
    if(in_array($code,[400,401,403],true))throw new RuntimeException('A chave foi recusada ou não tem acesso à API Gemini. Confira a chave e as permissões no Google AI Studio.');
    if($code===429)throw new RuntimeException('O Gemini informou limite de uso. Confira a cota da sua conta e tente novamente.');
    if($code!==200)throw new RuntimeException('O Gemini não respondeu como esperado. Tente novamente mais tarde.');
    $data=json_decode($body,true);
    if(!is_array($data)||!isset($data['models'])||!is_array($data['models']))throw new RuntimeException('Resposta de modelos inválida.');
    return $data;
}
function gemini_list_models(string $key,?callable $transport=null): array {
    $transport??='gemini_models_request';$models=[];$token='';
    for($page=0;$page<5;$page++){
        $response=$transport($key,$token);
        foreach($response['models']??[] as $m){
            $id=$m['name']??'';
            if(!is_string($id)||!preg_match('#^models/gemini-[a-zA-Z0-9._-]+$#D',$id)||!in_array('generateContent',$m['supportedGenerationMethods']??[],true)||preg_match('/image|tts|live|embedding/i',$id))continue;
            $models[$id]=['id'=>substr($id,7),'label'=>substr((string)($m['displayName']??$id),0,160),
                'input_token_limit'=>(int)($m['inputTokenLimit']??0),'output_token_limit'=>(int)($m['outputTokenLimit']??0)];
        }
        $token=$response['nextPageToken']??'';if(!is_string($token)||strlen($token)>4096)throw new RuntimeException('Paginação de modelos inválida.');if($token==='')break;
    }
    if(!$models)throw new RuntimeException('Nenhum modelo de análise de conteúdo disponível para esta chave.');
    return array_values($models);
}
if($path==='/api/ai/gemini'&&$method==='GET')json_response(gemini_public_settings(gemini_settings_row()));
if($path==='/api/ai/gemini/key'&&$method==='POST'){
    $v=input();$key=is_string($v['api_key']??null)?trim($v['api_key']):'';
    if(!preg_match('/^[\x21-\x7e]{20,512}$/D',$key))json_response(['error'=>'Informe uma chave API válida do Google AI Studio.'],422);
    gemini_settings_row();$cipher=gemini_seal_key($key,$_SESSION['user']['id']);
    query("UPDATE ai_settings SET gemini_key_encrypted=?,key_suffix=?,key_version=key_version+1,validated_at=NULL,available_models='[]',model='',updated_at=now() WHERE user_id=?",[$cipher,substr($key,-4),$_SESSION['user']['id']]);
    sodium_memzero($key);audit('gemini.key_saved');json_response(gemini_public_settings(gemini_settings_row()));
}
if($path==='/api/ai/gemini/key'&&$method==='DELETE'){
    gemini_settings_row();query("UPDATE ai_settings SET gemini_key_encrypted=NULL,key_suffix=NULL,key_version=key_version+1,validated_at=NULL,available_models='[]',model='',updated_at=now() WHERE user_id=?",[$_SESSION['user']['id']]);
    audit('gemini.key_removed');json_response(gemini_public_settings(gemini_settings_row()));
}
if($path==='/api/ai/gemini/test'&&$method==='POST'){
    $s=gemini_settings_row();if(empty($s['gemini_key_encrypted']))json_response(['error'=>'Salve sua chave antes de testar a conexão.'],422);
    $key=gemini_open_key($s['gemini_key_encrypted'],$s['user_id']);
    try{$models=gemini_list_models($key);}catch(RuntimeException $e){sodium_memzero($key);query('UPDATE ai_settings SET validated_at=NULL WHERE user_id=? AND key_version=?',[$s['user_id'],$s['key_version']]);json_response(['error'=>$e->getMessage()],502);}
    sodium_memzero($key);$selected='';
    foreach($models as $model)if($model['id']===$s['model'])$selected=$model['id'];
    if($selected==='')foreach($models as $model)if(str_contains($model['id'],'flash')&&!preg_match('/preview|experimental|exp-/',$model['id'])){$selected=$model['id'];break;}
    $selected=$selected?:$models[0]['id'];
    $changed=query('UPDATE ai_settings SET available_models=?,model=?,validated_at=now(),updated_at=now() WHERE user_id=? AND key_version=?',[json_encode($models),$selected,$s['user_id'],$s['key_version']])->rowCount();
    if(!$changed)json_response(['error'=>'A chave mudou durante o teste. Teste novamente.'],409);
    audit('gemini.connection_tested');json_response(gemini_public_settings(gemini_settings_row()));
}
if($path==='/api/ai/gemini/model'&&$method==='PATCH'){
    $s=gemini_settings_row();$v=input();$model=$v['model']??null;
    $available=json_decode($s['available_models'],true)?:[];
    if(!$s['validated_at']||!is_string($model)||!in_array($model,array_column($available,'id'),true))
        json_response(['error'=>'Teste a conexão e escolha um dos modelos disponíveis.'],422);
    query('UPDATE ai_settings SET model=?,updated_at=now() WHERE user_id=?',[$model,$s['user_id']]);
    audit('gemini.model_saved');json_response(gemini_public_settings(gemini_settings_row()));
}
if($path==='/api/ai/gemini/draft'&&$method==='PATCH'){
    $s=gemini_settings_row();$v=input();$prompt=$v['prompt']??($s['prompt_draft']?:gemini_default_prompt());$model=$v['model']??$s['model'];
    if(!is_string($prompt)||trim($prompt)===''||strlen($prompt)>24000)json_response(['error'=>'O prompt deve conter texto e ter até 24.000 bytes.'],422);
    if(!is_string($model)||($model!==''&&!in_array($model,array_column(json_decode($s['available_models'],true),'id'),true)))json_response(['error'=>'Teste a conexão e escolha um dos modelos disponíveis.'],422);
    query('UPDATE ai_settings SET prompt_draft=?,model=?,draft_version=draft_version+1,updated_at=now() WHERE user_id=?',[trim($prompt),$model,$s['user_id']]);
    audit('gemini.draft_saved');json_response(gemini_public_settings(gemini_settings_row()));
}
require_once __DIR__.'/gemini_engine.php';
function gemini_job_public(array $job): array {
    return ['id'=>$job['id'],'status'=>$job['status'],'model'=>$job['model'],
        'created_at'=>$job['created_at'],'updated_at'=>$job['updated_at'],
        'progress_done'=>(int)$job['progress_done'],'progress_total'=>(int)$job['progress_total'],
        'error'=>$job['error'],'warnings'=>json_decode($job['warnings'],true),
        'report'=>$job['status']==='completed'?$job['result_text']:null];
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/ai/gemini(?:/([a-f0-9]{32})/(cancel|download))?$#',$path,$m)){
    $run=owned_run($m[1]);$jobId=$m[2]??null;$action=$m[3]??null;
    if($action==='cancel'&&$method==='POST'){
        $changed=query("UPDATE ai_analyses SET status='cancelled',updated_at=now() WHERE id=? AND run_id=? AND user_id=? AND status IN ('queued','processing')",[$jobId,$run['id'],$_SESSION['user']['id']])->rowCount();
        if(!$changed)json_response(['error'=>'A análise não está ativa.'],409);
        audit('gemini.analysis_cancelled',$jobId);json_response(['ok'=>true],202);
    }
    if($action==='download'&&$method==='GET'){
        $job=query("SELECT id FROM ai_analyses WHERE id=? AND run_id=? AND user_id=? AND status='completed'",[$jobId,$run['id'],$_SESSION['user']['id']])->fetchColumn();
        if(!$job)json_response(['error'=>'Relatório indisponível.'],404);
        stream_file(run_output_path($run).'/gemini/'.$job.'.md','text/markdown; charset=utf-8',true);
    }
    if($jobId!==null)json_response(['error'=>'Recurso não encontrado.'],404);
    if($method==='GET'){
        $job=query('SELECT * FROM ai_analyses WHERE run_id=? AND user_id=? ORDER BY created_at DESC LIMIT 1',[$run['id'],$_SESSION['user']['id']])->fetch(PDO::FETCH_ASSOC);
        json_response($job?gemini_job_public($job):['status'=>'not_started']);
    }
    if($method==='POST'){
        if(!in_array($run['status'],['review','approved'],true))json_response(['error'=>'Aguarde o processamento local terminar antes de iniciar a análise Gemini.'],409);
        $settings=gemini_settings_row();
        if(!$settings['gemini_key_encrypted']||!$settings['validated_at'])json_response(['error'=>'Salve sua chave Gemini e teste a conexão antes de analisar.'],422);
        $models=json_decode($settings['available_models'],true);
        if(!in_array($settings['model'],array_column($models,'id'),true))json_response(['error'=>'Escolha um modelo disponível e salve a escolha.'],422);
        $prompt=$settings['prompt_draft']?:gemini_default_prompt();
        foreach(['{{TITULO}}','{{CONTEXTO}}','{{OBJETIVO_PROJETO}}','{{COBERTURA_ENVIADA}}','{{TRANSCRICAO}}','{{TELAS}}'] as $marker)
            if(!str_contains($prompt,$marker))json_response(['error'=>'O prompt precisa conservar os seis marcadores do material. Restaure o prompt-base e salve novamente.'],422);
        $stages=query('SELECT name,status,updated_at FROM stages WHERE run_id=?',[$run['id']])->fetchAll(PDO::FETCH_ASSOC);
        $ready=run_output_availability($run,$stages);
        if(!$ready['screens'])json_response(['error'=>'Aguarde a transcrição e as telas serem concluídas e salvas antes de analisar.'],409);
        $project=owned_project($run['project_id']);
        if(empty($project['output_directory']))json_response(['error'=>'A pasta de outputs ainda não está pronta.'],409);
        $id=id();
        try{query('INSERT INTO ai_analyses(id,run_id,user_id,model,prompt_snapshot,draft_version,key_version,stage_versions) VALUES (?,?,?,?,?,?,?,?)',
            [$id,$run['id'],$_SESSION['user']['id'],$settings['model'],$prompt,$settings['draft_version'],$settings['key_version'],json_encode(gemini_stage_versions($run['id']),JSON_THROW_ON_ERROR)]);}
        catch(PDOException $e){if($e->getCode()==='23505')json_response(['error'=>'Já existe uma análise em andamento para este vídeo.'],409);throw $e;}
        audit('gemini.analysis_queued',$id);
        json_response(gemini_job_public(query('SELECT * FROM ai_analyses WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC)),202);
    }
}
