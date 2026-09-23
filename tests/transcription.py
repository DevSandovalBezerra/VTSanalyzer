"""Portuguese synthetic narration through the real offline Whisper CPU engine."""
from pathlib import Path
import json
import subprocess
import time
import urllib.request
from foundation import client,request
from media import eventually

def run():
    saved=json.loads(Path('/tmp/ske-media-test.json').read_text());c=client();csrf=request(c,'/session')[1]['csrf'];csrf=request(c,'/login','POST',{'email':saved['email'],'password':saved['password']},csrf)[1]['csrf']
    subprocess.run(['docker','compose','cp','tests/fixtures/narration.wav','worker:/tmp/ske-narration.wav'],check=True)
    video=subprocess.run(['docker','compose','exec','-T','worker','ffmpeg','-v','error','-f','lavfi','-i','color=c=white:s=640x360:r=5','-i','/tmp/ske-narration.wav','-shortest','-c:v','libx264','-pix_fmt','yuv420p','-c:a','aac','-movflags','frag_keyframe+empty_moov','-f','mp4','pipe:1'],capture_output=True,check=True).stdout
    status,v=request(c,f"/projects/{saved['project_id']}/videos",'POST',{'title':'Narração sintética em português','name':'narration.mp4','size':len(video),'sensitivity':'internal'},csrf);assert status==201
    req=urllib.request.Request('http://localhost:8095/api/uploads/'+v['id']+'/chunks/0',data=video,method='PUT',headers={'X-CSRF-Token':csrf,'Content-Type':'application/octet-stream'});assert c.open(req).status==200
    assert request(c,f"/uploads/{v['id']}/complete",'POST',{},csrf)[0]==202
    ready=eventually(lambda:request(c,f"/uploads/{v['id']}")[1]['video'],lambda x:x['status']in ['ready','invalid']);assert ready['status']=='ready',ready
    status,created=request(c,f"/videos/{v['id']}/runs",'POST',{'profile':'quick'},csrf);assert status==202,created
    rid=created['id'];done=eventually(lambda:request(c,f'/runs/{rid}')[1],lambda x:x['status']in ['review','failed'],seconds=240);assert done['status']=='review',done
    result=next(s['result'] for s in done['stages'] if s['name']=='transcript')
    combined=' '.join(s['text'] for s in result['segments']).lower()
    assert 'cadastro' in combined and 'salvar' in combined,combined
    assert all(0<=s['start']<s['end'] for s in result['segments'])
    first=result['segments'][0]
    assert request(c,f"/runs/{rid}/transcript/{first['id']}",'PATCH',{'text':first['text']+' [revisado]'},csrf)[0]==200
    history=request(c,f'/runs/{rid}/history')[1];assert history and history[0]['kind']=='transcript'
    print('PASS: real offline Portuguese transcription, timestamps, edit and preserved revision history')
    print('Recognized synthetic narration:',combined)

if __name__=='__main__':run()
