"""Gemini job, evidence assembly and output reader with a mocked provider; never calls Google."""
from foundation import client,request
from pathlib import Path
import atexit,json,subprocess

compose=['docker','compose','-f','compose.yml','-f','compose.dev.yml']
# Pause the real processor so a synthetic key can never be picked up for an external request.
running=subprocess.check_output(compose+['ps','--status','running','-q','gemini-worker']).strip()
if running:
    subprocess.run(compose+['stop','gemini-worker'],check=True,stdout=subprocess.DEVNULL)
    atexit.register(lambda: subprocess.run(compose+['up','-d','gemini-worker'],check=True,stdout=subprocess.DEVNULL))
saved=json.loads(Path('/tmp/ske-media-test.json').read_text())
c=client();csrf=request(c,'/session')[1]['csrf']
csrf=request(c,'/login','POST',{'email':saved['email'],'password':saved['password']},csrf)[1]['csrf']
rid=saved['run_id']
status,run=request(c,'/runs/'+rid)
assert status==200 and run['outputs']['screens'],run.get('outputs')
def php(code,value,worker=False):
    pre="require '/app/src/bootstrap.php'; $path='';$method='CLI'; require '/app/src/media.php';require '/app/src/gemini.php'; $v=json_decode(stream_get_contents(STDIN),true);"
    command=['docker','compose','-f','compose.yml','-f','compose.dev.yml','run','--no-deps','--rm','-T','--entrypoint','php','gemini-worker','-r',pre+code] if worker else ['docker','compose','exec','-T','--user','www-data','web','php','-r',pre+code]
    return subprocess.check_output(command,input=json.dumps(value).encode()).decode()

# A disposable key and catalog are installed only on the synthetic fixture account.
php("""$uid=query('SELECT owner_id FROM projects WHERE id=?',[$v['project']])->fetchColumn();
query("UPDATE ai_analyses SET status='cancelled' WHERE run_id=? AND status IN ('queued','processing')",[$v['run']]);
query('INSERT INTO ai_settings(user_id) VALUES (?) ON CONFLICT DO NOTHING',[$uid]);
query('UPDATE ai_settings SET gemini_key_encrypted=?,validated_at=now(),key_version=key_version+1,model=?,available_models=?,prompt_draft=? WHERE user_id=?',
    [gemini_seal_key('mock-provider-key-never-sent',$uid),'gemini-test-flash',json_encode([['id'=>'gemini-test-flash','label'=>'Test Flash','input_token_limit'=>1000000,'output_token_limit'=>8192]]),gemini_default_prompt(),$uid]);""",{'project':saved['project_id'],'run':rid})
status,job=request(c,'/runs/'+rid+'/ai/gemini','POST',{},csrf)
assert status==202 and job['status']=='queued',job
assert request(c,'/runs/'+rid+'/ai/gemini','POST',{},csrf)[0]==409
result=php("""$job=query(\"UPDATE ai_analyses SET status='processing' WHERE id=? RETURNING *\",[$v['id']])->fetch(PDO::FETCH_ASSOC);
$seen=['counts'=>0,'generations'=>0,'images'=>0,'prompt'=>false,'metadata'=>false];
$mock=function($key,$model,$action,$body)use(&$seen){
    if($key!=='mock-provider-key-never-sent'||$model!=='gemini-test-flash')throw new RuntimeException('Unexpected test credentials');
    $parts=$body['contents'][0]['parts'];$text=implode(' ',array_column($parts,'text'));
    $seen['images']+=count(array_filter($parts,fn($p)=>isset($p['inlineData'])));
    $seen['prompt']=$seen['prompt']||str_contains($text,'Mapa cronológico');
    $seen['metadata']=$seen['metadata']||str_contains($text,'Mídia de teste sintética')||str_contains($text,'Vídeo sintético');
    if($action==='countTokens'){$seen['counts']++;return ['totalTokens'=>2000];}
    $seen['generations']++;return ['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'# Relatório: vídeo sintético\\n\\n## 1. Visão geral\\n(Dito) Teste [00:00:00].']]]]]];
};
$run=query('SELECT r.*,v.title,v.context,v.metadata,p.output_directory FROM runs r JOIN videos v ON v.id=r.video_id JOIN projects p ON p.id=v.project_id WHERE r.id=?',[$job['run_id']])->fetch(PDO::FETCH_ASSOC);
$project=['objective'=>'Teste'];
$dummy=[['type'=>'transcript','time'=>0,'body'=>'[00:00:00–00:00:01] ALFA-EVIDENCIA'],['type'=>'transcript','time'=>2,'body'=>'[00:00:02–00:00:03] BETA-EVIDENCIA']];
$splitMock=function($key,$model,$action,$body){
    $text=implode(' ',array_column($body['contents'][0]['parts'],'text'));
    if($action==='countTokens')return ['totalTokens'=>str_contains($text,'ALFA-EVIDENCIA')&&str_contains($text,'BETA-EVIDENCIA')?9000:2000];
    return ['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'# Relatório: parte']]]]]];
};
$seen['split_reports']=count(gemini_analyze_group($job,$run,$project,$dummy,'mock-provider-key-never-sent',['input_token_limit'=>10000,'output_token_limit'=>2048],1,1,$splitMock));
gemini_execute($job,$mock);$seen['snapshot_cleaned']=!is_dir('/data/gemini/'.$job['id']);echo json_encode($seen);""",{'id':job['id']},worker=True)
seen=json.loads(result)
assert seen['counts']>=1 and seen['generations']>=1 and seen['prompt'] and seen['metadata'],seen
assert seen['images']>=1 and seen['split_reports']==2 and seen['snapshot_cleaned'],seen
status,done=request(c,'/runs/'+rid+'/ai/gemini')
assert status==200 and done['status']=='completed' and done['report'].startswith('# Relatório:'),done
assert not done['warnings'],done['warnings']
status,catalog=request(c,'/runs/'+rid+'/outputs')
assert status==200 and any(f['key']=='ai-'+job['id'] for f in catalog['files']),catalog
status,doc=request(c,'/runs/'+rid+'/outputs/text?file=ai-'+job['id'])
assert status==200 and doc['content'].startswith('# Relatório:'),doc
assert request(c,'/runs/'+rid)[1]['outputs']['ai']
print('PASS: queued execution, duplicate guard, real synthetic evidence, inline images, prompt, token-limit splitting, snapshot cleanup, mocked generation, persisted report, output catalog and reader; no Google request')
