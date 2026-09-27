"""Outputs entry point, minimal forms and owner isolation using synthetic media."""
from foundation import client, request
from pathlib import Path
import json
import subprocess
import uuid
from media import run

run()
saved=json.loads(Path('/tmp/ske-media-test.json').read_text())
a=client();csrf=request(a,'/session')[1]['csrf']
csrf=request(a,'/login','POST',{'email':saved['email'],'password':saved['password']},csrf)[1]['csrf']
assert request(client(),'/outputs')[0]==401
status,rows=request(a,'/outputs');assert status==200,rows
row=next(r for r in rows if r['run_id']==saved['run_id'])
assert row['project_id']==saved['project_id']
assert any(s['name']=='transcript' for s in json.loads(row['stages']))
assert request(a,'/runs/'+row['run_id']+'/outputs')[0]==200
status,p=request(a,'/projects','POST',{'name':'Formulário mínimo'},csrf)
assert status==201 and p['objective']=='',p
status,p=request(a,'/projects/'+p['id'],'PATCH',{'name':p['name'],'objective':''},csrf)
assert status==200 and p['objective']=='',p
status,v=request(a,'/projects/'+p['id']+'/videos','POST',{'name':'Demonstração prática.mp4','size':20},csrf)
assert status==201,v
video=request(a,'/uploads/'+v['id'])[1]['video']
assert video['title']=='Demonstração prática' and video['sensitivity']=='internal',video
rows=request(a,'/outputs')[1]
assert any(r['video_id']==v['id'] and r['run_id'] is None for r in rows)
assert request(a,'/projects','POST',{'name':'','objective':''},csrf)[0]==422
assert request(a,'/projects','POST',{'name':'Limite','objective':'x'*4001},csrf)[0]==422
b=client();token=request(b,'/session')[1]['csrf'];email='outputs-isolation-'+uuid.uuid4().hex+'@local.test';password=uuid.uuid4().hex
status,session=request(b,'/register','POST',{'email':email,'password':password,'password_confirmation':password},token)
assert status==201,session
try:
    assert request(b,'/outputs')==(200,[])
    assert request(b,'/runs/'+saved['run_id']+'/outputs')[0]==404
finally:
    cleanup="require 'src/bootstrap.php'; $email=trim(stream_get_contents(STDIN)); $uid=query('SELECT id FROM users WHERE email=?',[$email])->fetchColumn(); query('DELETE FROM audit_events WHERE actor_id=?',[$uid]); query('DELETE FROM users WHERE id=?',[$uid]);"
    subprocess.run(['docker','compose','exec','-T','web','php','-r',cleanup],input=email.encode(),check=True)
print('PASS: minimal project, optional objective, filename title, internal default, outputs with/without runs, authentication and account isolation')
