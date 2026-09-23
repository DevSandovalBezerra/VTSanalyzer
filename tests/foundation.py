"""Integration tests against the running local Compose stack; no third-party modules."""
import http.cookiejar
import json
import os
import subprocess
import time
import urllib.error
import urllib.request
import uuid

base=os.getenv('TEST_URL','http://localhost:8095')
def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(c,path,method='GET',data=None,csrf=''):
    req=urllib.request.Request(base+'/api'+path, data=None if data is None else json.dumps(data).encode(),method=method,headers={'Content-Type':'application/json','X-CSRF-Token':csrf})
    try:
        with c.open(req) as response:
            return response.status,json.load(response)
    except urllib.error.HTTPError as error:
        return error.code,json.load(error)

def run():
    for attempt in range(30):
        try:
            with urllib.request.urlopen(base+'/health',timeout=3) as response:
                if response.status==200: break
        except (urllib.error.URLError,TimeoutError):
            if attempt==29: raise
            time.sleep(1)
    token=uuid.uuid4().hex
    credentials=[]
    for suffix in ['a','b']:
        email=f'test-{token}-{suffix}@local.test'
        password=uuid.uuid4().hex
        subprocess.run(['docker','compose','exec','-T','web','php','bin/create-user.php',email],input=password.encode(),check=True,stdout=subprocess.DEVNULL)
        credentials.append((email,password))
    a,b=client(),client()
    assert request(a,'/projects')[0]==401
    csrf=request(a,'/session')[1]['csrf']
    assert request(a,'/login','POST',{'email':credentials[0][0],'password':credentials[0][1]})[0]==419
    status,session=request(a,'/login','POST',dict(zip(['email','password'],credentials[0])),csrf)
    assert status==200
    csrf=session['csrf']
    assert request(a,'/projects','POST',{'name':'','objective':'x'},csrf)[0]==422
    status,p=request(a,'/projects','POST',{'name':'Teste integrado','objective':'Validar fundação','notes':'<script>alert(1)</script>'},csrf)
    assert status==201
    status,p2=request(a,'/projects/'+p['id'],'PATCH',{**p,'name':'Projeto revisado'},csrf)
    assert status==200 and p2['version']==2
    csrf_b=request(b,'/session')[1]['csrf']
    status,sb=request(b,'/login','POST',dict(zip(['email','password'],credentials[1])),csrf_b)
    assert status==200
    assert request(b,'/projects/'+p['id'])[0]==404
    assert request(b,'/projects')[1]==[]
    assert request(a,'/diagnostics','POST',{},csrf)[0]==202
    for _ in range(30):
        d=request(a,'/diagnostics')[1]
        if d['latest'] and d['latest']['status']=='completed':break
        time.sleep(1)
    assert d['latest']['status']=='completed',d
    result=json.loads(d['latest']['result'])
    assert all(result[k] for k in ['ffmpeg','ocr','target_readonly']),result
    assert all(d[k] for k in ['database','redis','worker','scheduler','storage']),d
    assert request(a,'/logout','POST',{},csrf)[0]==200
    assert request(a,'/projects')[0]==401
    # Only remove records created by this test run.
    cleanup='''<?php require 'src/bootstrap.php'; $emails=json_decode(stream_get_contents(STDIN),true); foreach($emails as $email) { $u=query('SELECT id FROM users WHERE email=?',[$email])->fetchColumn(); query('DELETE FROM project_versions WHERE project_id IN (SELECT id FROM projects WHERE owner_id=?)',[$u]); query('DELETE FROM projects WHERE owner_id=?',[$u]); query('DELETE FROM audit_events WHERE actor_id=?',[$u]); query('DELETE FROM users WHERE id=?',[$u]); }'''
    subprocess.run(['docker','compose','exec','-T','web','php','-r',cleanup.removeprefix('<?php ')],input=json.dumps([c[0] for c in credentials]).encode(),check=True)
    print('PASS: authentication, CSRF, isolation, versioning, real queue, FFmpeg, OCR, storage and logout')

if __name__=='__main__': run()
