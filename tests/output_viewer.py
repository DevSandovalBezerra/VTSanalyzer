"""Output reader authorization, allowlist, downloads and real media checks."""
from foundation import client, request
from pathlib import Path
import json
import subprocess
import urllib.request
import uuid

saved=json.loads(Path('/tmp/ske-media-test.json').read_text())
c=client();csrf=request(c,'/session')[1]['csrf']
csrf=request(c,'/login','POST',{'email':saved['email'],'password':saved['password']},csrf)[1]['csrf']
videos=request(c,f"/projects/{saved['project_id']}/videos")[1]
video=next(v for v in videos if v['title']=='Narração sintética em português')
rid=json.loads(video['runs'])[0]['id']
status,catalog=request(c,f'/runs/{rid}/outputs');assert status==200,catalog
assert catalog['frames'] and catalog['audio']
files={f['key']:f for f in catalog['files']}
assert {'transcript-txt','transcript-json','transcript-srt','ocr','analysis','history'} <= files.keys()
for key in files:
    status,doc=request(c,f'/runs/{rid}/outputs/text?file={key}')
    assert status==200 and isinstance(doc['content'],str),doc
    with c.open(f'http://localhost:8095/api/runs/{rid}/outputs/text?file={key}&download=1') as response:
        assert 'attachment;' in response.headers['Content-Disposition']
        assert response.read().decode()==doc['content']
assert 'cadastro' in request(c,f'/runs/{rid}/outputs/text?file=transcript-txt')[1]['content'].lower()
for key in ['..%2F..%2F.env','%2Fetc%2Fpasswd','snapshot-'+'f'*32,'unknown','']:
    assert request(c,f'/runs/{rid}/outputs/text?file={key}')[0]==404
assert request(c,f'/runs/{rid}/outputs/text?file[]=ocr')[0]==404
for suffix in ['outputs','outputs/text?file=transcript-txt','outputs/audio']:
    assert request(client(),f'/runs/{rid}/{suffix}')[0]==401
token=uuid.uuid4().hex;email=f'output-reader-{token}@local.test';password=uuid.uuid4().hex
subprocess.run(['docker','compose','exec','-T','web','php','bin/create-user.php',email],input=password.encode(),check=True,stdout=subprocess.DEVNULL)
try:
    other=client();t=request(other,'/session')[1]['csrf'];assert request(other,'/login','POST',{'email':email,'password':password},t)[0]==200
    for suffix in ['outputs','outputs/text?file=transcript-txt','outputs/audio']:
        assert request(other,f'/runs/{rid}/{suffix}')[0]==404
finally:
    cleanup="require 'src/bootstrap.php'; $email=trim(stream_get_contents(STDIN)); $uid=query('SELECT id FROM users WHERE email=?',[$email])->fetchColumn(); query('DELETE FROM audit_events WHERE actor_id=?',[$uid]); query('DELETE FROM users WHERE id=?',[$uid]);"
    subprocess.run(['docker','compose','exec','-T','web','php','-r',cleanup],input=email.encode(),check=True)
with c.open(urllib.request.Request(f'http://localhost:8095/api/runs/{rid}/outputs/audio',headers={'Range':'bytes=0-43'})) as response:
    assert response.status==206 and response.read().startswith(b'RIFF')
approved=request(c,f"/runs/{saved['run_id']}/outputs")[1]
assert any(f['key'].startswith('snapshot-') for f in approved['files'])
with c.open(f"http://localhost:8095/api/runs/{saved['run_id']}/outputs/text?file=transcript-srt&download=1") as response:
    assert response.status==200 and response.read()==b''
for file,mime in [('outputs.js','text/javascript'),('outputs.css','text/css')]:
    with c.open('http://localhost:8095/assets/'+file) as response:
        assert response.headers.get_content_type()==mime
print('PASS: output catalog, readable text, exact downloads, approved snapshots, empty SRT, audio ranges, path rejection, authentication and cross-account isolation')
