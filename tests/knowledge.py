from foundation import client,request
from media import eventually
import hashlib
import io
import json
from pathlib import Path
import subprocess
import urllib.request
import zipfile

def run():
    saved=json.loads(Path('/tmp/ske-media-test.json').read_text());rid=saved['run_id'];c=client();csrf=request(c,'/session')[1]['csrf'];csrf=request(c,'/login','POST',{'email':saved['email'],'password':saved['password']},csrf)[1]['csrf']
    r=request(c,'/runs/'+rid)[1];stages={s['name']:s for s in r['stages']};frame=stages['frames']['result']['frames'][0]
    claim={'type':'screen','title':'Tela sintética','description':'Padrão de teste visível na captura.','classification':'observed','confidence':.95,'review_status':'approved','evidence':[frame['id']]}
    assert request(c,f'/runs/{rid}/claims','POST',{**claim,'evidence':['other-run-frame']},csrf)[0]==422
    status,item=request(c,f'/runs/{rid}/claims','POST',claim,csrf);assert status==200
    assert request(c,f'/runs/{rid}/approve','POST',{},csrf)[0]==422  # new items cannot self-approve
    assert request(c,f"/runs/{rid}/claims/{item['id']}",'PATCH',claim,csrf)[0]==200
    assert request(c,f'/runs/{rid}/redact','POST',{'frame_id':frame['id'],'x':0,'y':0,'width':frame['width'],'height':frame['height']},csrf)[0]==202
    redacted=eventually(lambda:request(c,f'/runs/{rid}')[1],lambda x:x['status']in ['review','failed']);assert redacted['status']=='review',redacted
    assert redacted['claims'][0]['review_status']=='pending'
    assert request(c,f'/runs/{rid}/resume','POST',{'stage':'frames'},csrf)[0]==409  # never restore original pixels
    assert request(c,f"/runs/{rid}/claims/{item['id']}",'PATCH',claim,csrf)[0]==200
    assert request(c,f'/runs/{rid}/approve','POST',{},csrf)[0]==201
    assert request(c,f"/runs/{rid}/claims/{item['id']}",'PATCH',claim,csrf)[0]==409
    status,artifact=request(c,f'/runs/{rid}/exports','POST',{},csrf);assert status==202
    exports=eventually(lambda:request(c,f'/runs/{rid}/exports')[1],lambda x:x and x[0]['status']in ['completed','failed']);assert exports[0]['status']=='completed',exports
    with c.open('http://localhost:8095/api/exports/'+artifact['id']) as response:package=response.read()
    with zipfile.ZipFile(io.BytesIO(package)) as archive:
        assert archive.testzip()is None
        names=archive.namelist();assert not any(n.endswith('.mp4') for n in names)
        manifest=json.loads(archive.read('system-knowledge/MANIFEST.json'));assert manifest['model_used']is False
        for f in manifest['files']:assert hashlib.sha256(archive.read('system-knowledge/'+f['path'])).hexdigest()==f['sha256']
        evidence=json.loads(archive.read('system-knowledge/data/evidence.json'));assert len(evidence)==1
        image=archive.read('system-knowledge/'+evidence[0]['reference'])
        verified=subprocess.run(['docker','compose','exec','-T','worker','python','-c','import io,sys; from PIL import Image; im=Image.open(io.BytesIO(sys.stdin.buffer.read())); assert im.convert("RGB").getbbox() is None; print("MASKED")'],input=image,capture_output=True,check=True)
        assert b'MASKED'in verified.stdout
    assert request(c,f"/projects/{saved['project_id']}/target",'POST',{},csrf)[0]==202
    inv=eventually(lambda:request(c,f"/projects/{saved['project_id']}/target")[1],lambda x:x and x['status']in ['completed','failed']);assert inv['status']=='completed',inv
    result=json.loads(inv['result']);assert result['code_executed']is False and result['files']
    print('PASS: evidence validation, mandatory review, irreversible derived masking, reapproval, immutable snapshot, ZIP hashes, approved-only evidence, readonly inventory')

if __name__=='__main__':run()
