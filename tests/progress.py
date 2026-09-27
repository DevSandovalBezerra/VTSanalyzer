"""Readiness gates across queue, processing, sync, retry, failure and completion."""
from foundation import client, request
from pathlib import Path
import json
import subprocess
import uuid

saved=json.loads(Path('/tmp/ske-media-test.json').read_text())
c=client();csrf=request(c,'/session')[1]['csrf']
csrf=request(c,'/login','POST',{'email':saved['email'],'password':saved['password']},csrf)[1]['csrf']
status,p=request(c,'/projects','POST',{'name':'Teste de progresso '+uuid.uuid4().hex[:8]},csrf);assert status==201,p
status,v=request(c,'/projects/'+p['id']+'/videos','POST',{'name':'progresso.mp4','size':16},csrf);assert status==201,v
rid=uuid.uuid4().hex
base=['docker','compose','exec','-T']
def php(code,data):
    return subprocess.check_output(base+['web','php','-r',"require 'src/bootstrap.php'; $v=json_decode(stream_get_contents(STDIN),true); "+code],input=json.dumps(data).encode()).decode()

php('''query("UPDATE videos SET status='ready',metadata='{}' WHERE id=?",[$v['video']]);
query("INSERT INTO runs(id,video_id,status,config,version) VALUES (?,?,'queued','{\"profile\":\"balanced\"}',1)",[$v['run'],$v['video']]);
foreach(['audio','transcript','frames','ocr','screens'] as $name)query('INSERT INTO stages(run_id,name) VALUES (?,?)',[$v['run'],$name]);'''.replace("'{\"profile\":\"balanced\"}'", "'{}'"),{'video':v['id'],'run':rid})

def read():
    status,r=request(c,'/runs/'+rid);assert status==200,r;return r
def state(run_status,stage_states):
    php("query('UPDATE runs SET status=?,updated_at=now() WHERE id=?',[$v['status'],$v['id']]); foreach($v['stages'] as $name=>$status)query('UPDATE stages SET status=?,result=?,updated_at=now() WHERE run_id=? AND name=?',[$status,json_encode($v['results'][$name]),$v['id'],$name]);",
        {'id':rid,'status':run_status,'stages':stage_states,'results':{'audio':{'available':False},'transcript':{'segments':[],'reason':'Vídeo de teste sem fala.'},'frames':{'frames':[]},'ocr':{'blocks':[]},'screens':{'screens':[]}}})
def sync():
    subprocess.run(base+['worker','python','-c',"import sys; from outputs import sync_run; sync_run(sys.argv[1])",rid],check=True)

def blocked():
    r=read();assert r['outputs']['transcript'] is False,r['outputs']
    for fmt in ['txt','json','srt']:
        assert request(c,'/runs/'+rid+'/outputs/text?file=transcript-'+fmt)[0]==409
        assert request(c,'/runs/'+rid+'/outputs/text?file=transcript-'+fmt+'&download=1')[0]==409
    status,catalog=request(c,'/runs/'+rid+'/outputs');assert status==200,catalog
    assert not any(f['key'].startswith('transcript-') for f in catalog['files'])
    row=next(row for row in request(c,'/outputs')[1] if row['run_id']==rid)
    assert row['outputs']['transcript'] is False

blocked()
assert request(c,'/videos/'+v['id']+'/runs','POST',{},csrf)[0]==409
state('processing',{'audio':'completed','transcript':'processing'});sync();blocked()
state('processing',{'transcript':'completed'});sync()
r=read();assert r['outputs']['transcript'] and not r['outputs']['screens'],r['outputs']
assert request(c,'/runs/'+rid+'/outputs/text?file=transcript-txt')[0]==200
state('review',{name:'completed' for name in ['audio','transcript','frames','ocr','screens']});sync()
r=read();assert r['outputs']['transcript'] and r['outputs']['screens'] and not r['outputs']['ai'],r['outputs']

# A file from the preceding stage version must not unlock a fresh retry.
check=php("""$path='';$method='GET';require 'src/media.php';
$r=query('SELECT r.*,v.project_id,p.output_directory FROM runs r JOIN videos v ON v.id=r.video_id JOIN projects p ON p.id=v.project_id WHERE r.id=?',[$v['id']])->fetch(PDO::FETCH_ASSOC);
$stages=query('SELECT * FROM stages WHERE run_id=?',[$v['id']])->fetchAll(PDO::FETCH_ASSOC);
foreach($stages as &$s)if($s['name']==='transcript')$s['updated_at']=(new DateTimeImmutable($s['updated_at']))->modify('+1 second')->format('Y-m-d H:i:s.uP');unset($s);
echo json_encode(run_output_availability($r,$stages));""",{'id':rid})
assert json.loads(check)['transcript'] is False,check
state('processing',{'transcript':'processing'});blocked()
state('failed',{'transcript':'failed'});blocked()
state('cancelled',{'transcript':'pending'});blocked()
state('review',{'transcript':'completed'});sync()
assert read()['outputs']['transcript']
assert request(client(),'/runs/'+rid+'/outputs/text?file=transcript-txt')[0]==401
print('PASS: queued/processing gates, disabled downloads, hidden unfinished documents, independent transcript release, synchronized completion, stale-version rejection, retry/failure/cancellation and duplicate-run prevention')
