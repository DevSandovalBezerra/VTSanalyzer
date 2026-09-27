"""Settings, secret handling and prompt draft gates. No provider calls or real keys."""
from foundation import client,request
from pathlib import Path
import json
import subprocess
import uuid

saved=json.loads(Path('/tmp/ske-media-test.json').read_text())
a=client();csrf=request(a,'/session')[1]['csrf']
csrf=request(a,'/login','POST',{'email':saved['email'],'password':saved['password']},csrf)[1]['csrf']
def php(code,data):
    return subprocess.check_output(['docker','compose','exec','-T','--user','www-data','web','php','-r',"require 'src/bootstrap.php'; $path='';$method='GET';require 'src/gemini.php';$v=json_decode(stream_get_contents(STDIN),true);"+code],input=json.dumps(data).encode()).decode()
assert request(client(),'/ai/gemini')[0]==401
status,s=request(a,'/ai/gemini');assert status==200 and not s['analysis_enabled'],s
assert s['prompt_status']=='draft' and 'Mapa cronológico' in s['prompt']
assert request(a,'/ai/gemini/key','POST',{'api_key':'x'*32})[0]==419
assert request(a,'/ai/gemini/key','POST',{'api_key':'x\n'+'y'*30},csrf)[0]==422
key='test-key-not-real-'+uuid.uuid4().hex
status,s=request(a,'/ai/gemini/key','POST',{'api_key':key},csrf)
assert status==200 and s['has_key'] and s['key_suffix']==key[-4:] and not s['validated_at'],s
assert key not in json.dumps(s) and 'gemini_key_encrypted' not in s
check=json.loads(php("""$s=query('SELECT * FROM ai_settings WHERE user_id=(SELECT id FROM users WHERE email=?)',[$v['email']])->fetch(PDO::FETCH_ASSOC);
$roundtrip=gemini_open_key($s['gemini_key_encrypted'],$s['user_id'])===$v['key'];
$cross=false;try{gemini_open_key($s['gemini_key_encrypted'],'another-user');}catch(RuntimeException){$cross=true;}
echo json_encode(['roundtrip'=>$roundtrip,'encrypted'=>$s['gemini_key_encrypted']!==$v['key'],'cross_rejected'=>$cross,'mode'=>(fileperms('/data/secrets/gemini-master.key')&0777)]);""",{'email':saved['email'],'key':key}))
assert check=={'roundtrip':True,'encrypted':True,'cross_rejected':True,'mode':384},check
models=json.loads(php("""$mock=function($key,$token){return $token===''?['models'=>[['name'=>'models/gemini-test-flash','displayName'=>'Test Flash','supportedGenerationMethods'=>['generateContent']],['name'=>'models/gemini-test-image','supportedGenerationMethods'=>['generateContent']],['name'=>'models/embedding','supportedGenerationMethods'=>['embedContent']]],'nextPageToken'=>'next']:['models'=>[['name'=>'models/gemini-test-pro','supportedGenerationMethods'=>['generateContent']]]];};echo json_encode(gemini_list_models('fake-key',$mock));""",{}))
assert [m['id'] for m in models]==['gemini-test-flash','gemini-test-pro'],models
draft=s['prompt']+'\n\nDê atenção especial aos fluxos demonstrados.'
status,s=request(a,'/ai/gemini/draft','PATCH',{'prompt':draft},csrf)
assert status==200 and s['prompt']==draft and s['prompt_status']=='draft' and not s['analysis_enabled'],s
assert request(a,'/ai/gemini/draft','PATCH',{'model':'invented-model'},csrf)[0]==422
assert request(a,'/ai/gemini/draft','PATCH',{'prompt':''},csrf)[0]==422
assert request(a,'/runs/'+saved['run_id']+'/ai/gemini','POST',{},csrf)[0]==409
# Populate a mocked model catalog to verify selection validation without external calls.
php("query('UPDATE ai_settings SET available_models=? WHERE user_id=(SELECT id FROM users WHERE email=?)',[json_encode($v['models']),$v['email']]);",{'email':saved['email'],'models':models})
status,s=request(a,'/ai/gemini/draft','PATCH',{'prompt':draft,'model':'gemini-test-flash'},csrf)
assert status==200 and s['model']=='gemini-test-flash'
key2='replacement-not-real-'+uuid.uuid4().hex
status,s=request(a,'/ai/gemini/key','POST',{'api_key':key2},csrf)
assert s['model']=='' and not s['models'] and not s['validated_at'] and s['prompt']==draft
b=client();btoken=request(b,'/session')[1]['csrf'];email='gemini-isolation-'+uuid.uuid4().hex+'@local.test';password=uuid.uuid4().hex
status,user=request(b,'/register','POST',{'email':email,'password':password,'password_confirmation':password},btoken);assert status==201
try:
    bs=request(b,'/ai/gemini')[1];assert not bs['has_key'] and bs['prompt']!=draft
    assert request(b,'/runs/'+saved['run_id']+'/ai/gemini','POST',{},user['csrf'])[0]==404
finally:
    php("$uid=query('SELECT id FROM users WHERE email=?',[$v['email']])->fetchColumn();query('DELETE FROM ai_settings WHERE user_id=?',[$uid]);query('DELETE FROM audit_events WHERE actor_id=?',[$uid]);query('DELETE FROM users WHERE id=?',[$uid]);",{'email':email})
status,s=request(a,'/ai/gemini/key','DELETE',{},csrf)
assert status==200 and not s['has_key'] and s['prompt']==draft
assert request(a,'/ai/gemini/test','POST',{},csrf)[0]==422
print('PASS: per-user encrypted key, masking, CSRF, replacement/removal, draft persistence, model selection, mocked pagination/filtering, cross-account isolation and analysis approval gate; no external requests')
