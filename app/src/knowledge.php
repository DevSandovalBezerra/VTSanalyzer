<?php
function evidence_catalog(string $rid): array {
    $catalog=[];
    foreach(stage_result($rid,'frames')['frames']??[] as $f)$catalog[$f['id']]=['id'=>$f['id'],'type'=>'frame','timestamp'=>$f['timestamp'],'reference'=>'evidence/frames/'.$f['id'].'.jpg'];
    foreach(stage_result($rid,'transcript')['segments']??[] as $s)$catalog[$s['id']]=['id'=>$s['id'],'type'=>'transcript','timestamp'=>$s['start'],'reference'=>'TRANSCRIPT.md#'.$s['id']];
    return $catalog;
}
function validated_claim(array $v,string $rid): array {
    $type=$v['type']??'';$class=$v['classification']??'';$review=$v['review_status']??'pending';$confidence=filter_var($v['confidence']??null,FILTER_VALIDATE_FLOAT);
    if(!in_array($type,['business_rule','domain_entity','ux_pattern','event','flow','screen','glossary','gap'],true)||!in_array($class,['observed','narrated','inferred','unknown'],true)||!in_array($review,['pending','approved','rejected','confirm'],true)||$confidence===false||!is_finite($confidence)||$confidence<0||$confidence>1)json_response(['error'=>'Tipo, classificação, confiança ou revisão inválidos.'],422);
    $evidence=[];$catalog=evidence_catalog($rid);foreach($v['evidence']??[] as $e){$key=is_array($e)?($e['id']??''):$e;if(!is_string($key)||!isset($catalog[$key]))json_response(['error'=>'Evidência não pertence à execução.'],422);$evidence[$key]=$catalog[$key];}
    if(!$evidence)json_response(['error'=>'Selecione pelo menos uma evidência.'],422);
    return ['type'=>$type,'title'=>required($v,'title'),'description'=>required($v,'description',8000),'classification'=>$class,'confidence'=>$confidence,'review_status'=>$review,'evidence'=>array_values($evidence)];
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/claims(?:/([a-f0-9]{32}))?$#',$path,$m)&&in_array($method,['POST','PATCH'],true)){
    $r=owned_run($m[1]);if($r['status']!=='review')json_response(['error'=>'A execução precisa estar em revisão.'],409);$v=input();$c=validated_claim($v,$r['id']);$cid=$m[2]??id();db()->beginTransaction();query('SELECT id FROM runs WHERE id=? FOR UPDATE',[$r['id']]);$r=owned_run($r['id']);if($r['status']!=='review'){db()->rollBack();json_response(['error'=>'A execução mudou de estado.'],409);}
    if($method==='POST')query('INSERT INTO claims(id,run_id,type,title,description,classification,confidence,review_status,evidence,origin,prompt_version,model_profile) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$cid,$r['id'],$c['type'],$c['title'],$c['description'],$c['classification'],$c['confidence'],'pending',json_encode($c['evidence']),'human','manual-v1','human']);
    else{$old=query('SELECT * FROM claims WHERE id=? AND run_id=?',[$cid,$r['id']])->fetch(PDO::FETCH_ASSOC);if(!$old){db()->rollBack();json_response(['error'=>'Item não encontrado.'],404);}query('INSERT INTO revisions(run_id,kind,item_id,before_value,after_value,actor_id) VALUES (?,?,?,?,?,?)',[$r['id'],'claim',$cid,json_encode($old),json_encode($c),$_SESSION['user']['id']]);query('UPDATE claims SET type=?,title=?,description=?,classification=?,confidence=?,review_status=?,evidence=? WHERE id=?',[$c['type'],$c['title'],$c['description'],$c['classification'],$c['confidence'],$c['review_status'],json_encode($c['evidence']),$cid]);}
    audit($method==='POST'?'claim.created':'claim.reviewed',$cid);db()->commit();json_response(['id'=>$cid]);
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/approve$#',$path,$m)&&$method==='POST'){
    $r=owned_run($m[1]);db()->beginTransaction();query('SELECT id FROM runs WHERE id=? FOR UPDATE',[$r['id']]);$r=owned_run($r['id']);
    $pending=(int)query("SELECT count(*) FROM claims WHERE run_id=? AND review_status IN ('pending','confirm')",[$r['id']])->fetchColumn();$claims=query("SELECT * FROM claims WHERE run_id=? AND review_status='approved' ORDER BY created_at",[$r['id']])->fetchAll(PDO::FETCH_ASSOC);
    if($r['status']!=='review'||$pending||!$claims){db()->rollBack();json_response(['error'=>'Resolva todas as pendências e aprove pelo menos um item antes de fechar a revisão.'],422);}
    foreach($claims as &$claim){$claim['evidence']=json_decode($claim['evidence'],true);$claim['history']=query('SELECT before_value,after_value,created_at FROM revisions WHERE run_id=? AND item_id=? ORDER BY id',[$r['id'],$claim['id']])->fetchAll(PDO::FETCH_ASSOC);}unset($claim);
    $snapshot=['run'=>['id'=>$r['id'],'video_id'=>$r['video_id'],'version'=>$r['version'],'config'=>json_decode($r['config'],true),'title'=>$r['title'],'sensitivity'=>$r['sensitivity']],'claims'=>$claims,'transcript'=>stage_result($r['id'],'transcript'),'frames'=>stage_result($r['id'],'frames'),'ocr'=>stage_result($r['id'],'ocr'),'evidence'=>evidence_catalog($r['id']),'application_version'=>getenv('APP_VERSION')];
    $sid=id();query('INSERT INTO snapshots(id,run_id,payload) VALUES (?,?,?)',[$sid,$r['id'],json_encode($snapshot,JSON_THROW_ON_ERROR)]);query("UPDATE runs SET status='approved',approved_at=now(),updated_at=now() WHERE id=?",[$r['id']]);audit('knowledge.approved',$sid);db()->commit();json_response(['snapshot_id'=>$sid],201);
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/exports$#',$path,$m)){
    $r=owned_run($m[1]);if($method==='GET')json_response(query('SELECT * FROM artifacts WHERE run_id=? ORDER BY created_at DESC',[$r['id']])->fetchAll(PDO::FETCH_ASSOC));
    if($method==='POST'){$s=query('SELECT id FROM snapshots WHERE run_id=? ORDER BY created_at DESC LIMIT 1',[$r['id']])->fetchColumn();if(!$s||$r['status']!=='approved')json_response(['error'=>'Aprove uma versão do conhecimento antes de exportar.'],422);$aid=id();db()->beginTransaction();query('INSERT INTO artifacts(id,run_id,snapshot_id) VALUES (?,?,?)',[$aid,$r['id'],$s]);enqueue('export',$r['project_id'],['artifact_id'=>$aid]);audit('export.queued',$aid);db()->commit();json_response(['id'=>$aid],202);}
}
if(preg_match('#^/api/exports/([a-f0-9]{32})$#',$path,$m)&&$method==='GET'){
    $a=query('SELECT * FROM artifacts WHERE id=?',[$m[1]])->fetch(PDO::FETCH_ASSOC);if(!$a)json_response(['error'=>'Pacote não encontrado.'],404);owned_run($a['run_id']);if($a['status']!=='completed')json_response(['error'=>'Aguarde a geração do pacote.'],409);stream_file('/data/exports/'.$a['id'].'.zip','application/zip',true);
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/redact$#',$path,$m)&&$method==='POST'){
    $r=owned_run($m[1]);if(!in_array($r['status'],['review','failed'],true))json_response(['error'=>'A ocultação exige execução em revisão ou falha.'],409);$v=input();$frames=stage_result($r['id'],'frames')['frames']??[];$frame=null;foreach($frames as $f)if($f['id']===($v['frame_id']??null))$frame=$f;
    if(!$frame)json_response(['error'=>'Frame não encontrado.'],404);$box=[];foreach(['x','y','width','height'] as $key){$n=filter_var($v[$key]??null,FILTER_VALIDATE_INT);if($n===false||$n<0)json_response(['error'=>'Coordenadas inválidas.'],422);$box[$key]=$n;}
    if($box['width']<1||$box['height']<1||$box['x']+$box['width']>$frame['width']||$box['y']+$box['height']>$frame['height'])json_response(['error'=>'A região precisa estar dentro do frame.'],422);
    $pending=json_decode($r['config'],true)['redaction_pending']??null;if($pending&&($pending['frame_id']!==$frame['id']||$pending['box']!=$box))json_response(['error'=>'Conclua a ocultação pendente antes de solicitar uma região diferente.'],409);
    db()->beginTransaction();query('SELECT id FROM runs WHERE id=? FOR UPDATE',[$r['id']]);if(!in_array(owned_run($r['id'])['status'],['review','failed'],true)){db()->rollBack();json_response(['error'=>'A execução mudou de estado.'],409);}$config=json_decode($r['config'],true);$config['redaction_pending']=['frame_id'=>$frame['id'],'box'=>$box];query("UPDATE runs SET status='processing',config=? WHERE id=?",[json_encode($config),$r['id']]);enqueue('redact',$r['project_id'],['run_id'=>$r['id'],'frame_id'=>$frame['id'],'box'=>$box]);query("UPDATE claims SET review_status='pending' WHERE run_id=?",[$r['id']]);audit('frame.redaction_requested',$frame['id']);db()->commit();json_response(['ok'=>true],202);
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/history$#',$path,$m)&&$method==='GET'){owned_run($m[1]);json_response(query('SELECT kind,item_id,before_value,after_value,created_at FROM revisions WHERE run_id=? ORDER BY id DESC LIMIT 100',[$m[1]])->fetchAll(PDO::FETCH_ASSOC));}
if(preg_match('#^/api/projects/([a-f0-9]{32})/target$#',$path,$m)){
    $p=owned_project($m[1]);if($method==='GET'){json_response(query('SELECT * FROM target_inventories WHERE project_id=? ORDER BY created_at DESC LIMIT 1',[$p['id']])->fetch(PDO::FETCH_ASSOC)?:null);}
    if($method==='POST'){$iid=id();db()->beginTransaction();query('INSERT INTO target_inventories(id,project_id) VALUES (?,?)',[$iid,$p['id']]);enqueue('inventory',$p['id'],['inventory_id'=>$iid]);audit('target.inventory_queued',$iid);db()->commit();json_response(['id'=>$iid],202);}
}
