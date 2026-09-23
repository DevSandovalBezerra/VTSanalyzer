"""Real upload, FFmpeg, OCR, stage retry and isolation smoke test."""
from foundation import client,request
import json
import subprocess
import time
import urllib.request
import uuid
from pathlib import Path

def eventually(fn,predicate,seconds=120):
    for _ in range(seconds):
        value=fn()
        if predicate(value):return value
        time.sleep(1)
    raise AssertionError(value)

def run():
    suffix=uuid.uuid4().hex
    email=f'media-{suffix}@local.test';password=uuid.uuid4().hex
    subprocess.run(['docker','compose','exec','-T','web','php','bin/create-user.php',email],input=password.encode(),check=True)
    source=subprocess.run(['docker','compose','exec','-T','worker','ffmpeg','-v','error','-f','lavfi','-i','testsrc2=size=640x360:rate=10','-t','3','-c:v','libx264','-pix_fmt','yuv420p','-movflags','frag_keyframe+empty_moov','-f','mp4','pipe:1'],capture_output=True,check=True).stdout
    c=client();csrf=request(c,'/session')[1]['csrf'];csrf=request(c,'/login','POST',{'email':email,'password':password},csrf)[1]['csrf']
    status,p=request(c,'/projects','POST',{'name':'Mídia de teste sintética','objective':'Validar pipeline determinístico'},csrf);assert status==201
    assert request(c,f"/projects/{p['id']}/videos",'POST',{'title':'inválido','name':'payload.php','size':100},csrf)[0]==422
    status,v=request(c,f"/projects/{p['id']}/videos",'POST',{'title':'Vídeo sintético','name':'fixture.mp4','size':len(source),'sensitivity':'internal'},csrf);assert status==201
    assert request(c,f"/uploads/{v['id']}/complete",'POST',{},csrf)[0]==422
    req=urllib.request.Request('http://localhost:8095/api/uploads/'+v['id']+'/chunks/0',data=source,method='PUT',headers={'X-CSRF-Token':csrf,'Content-Type':'application/octet-stream'})
    assert c.open(req).status==200
    assert c.open(req).status==200  # identical chunk is idempotent
    assert request(c,f"/uploads/{v['id']}")[1]['chunks'][0]['size']==len(source)
    assert request(c,f"/uploads/{v['id']}/complete",'POST',{},csrf)[0]==202
    ready=eventually(lambda:request(c,f"/uploads/{v['id']}")[1]['video'],lambda x:x['status']in ['ready','invalid']);assert ready['status']=='ready',ready
    metadata=json.loads(ready['metadata']);assert 2.8<metadata['duration']<3.3
    status,r=request(c,f"/videos/{v['id']}/runs",'POST',{'profile':'balanced','interval':1},csrf);assert status==202
    rid=r['id'];done=eventually(lambda:request(c,f'/runs/{rid}')[1],lambda x:x['status']in ['review','failed']);assert done['status']=='review',done
    stages={s['name']:s for s in done['stages']};assert stages['audio']['result']['available']is False
    frames=stages['frames']['result']['frames'];assert len(frames)>=3,frames
    assert frames[0]['timestamp']==0 and frames[-1]['timestamp']>=2
    assert all(0<=f['timestamp']<=metadata['duration'] for f in frames)
    saved_frames=stages['frames']['result'];saved_audio=stages['audio']['updated_at']
    assert request(c,f'/runs/{rid}/resume','POST',{'stage':'ocr'},csrf)[0]==202
    again=eventually(lambda:request(c,f'/runs/{rid}')[1],lambda x:x['status']in ['review','failed']);assert again['status']=='review',again
    again_stages={s['name']:s for s in again['stages']};assert again_stages['frames']['result']==saved_frames
    assert again_stages['audio']['updated_at']==saved_audio
    req=urllib.request.Request(f"http://localhost:8095/api/videos/{v['id']}/stream",headers={'Range':'bytes=0-99'})
    with c.open(req) as response:assert response.status==206 and len(response.read())==100
    Path('/tmp/ske-media-test.json').write_text(json.dumps({'email':email,'password':password,'project_id':p['id'],'video_id':v['id'],'run_id':rid}))
    Path('/tmp/ske-media-test.json').chmod(0o600)
    print('PASS: resumable/idempotent upload, format validation, real metadata, media stages, timestamps, OCR retry, range streaming')

if __name__=='__main__':run()
