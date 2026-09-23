from foundation import client,request
from media import eventually
import json
from pathlib import Path
import subprocess

saved=json.loads(Path('/tmp/ske-media-test.json').read_text())
c=client();csrf=request(c,'/session')[1]['csrf'];assert request(c,'/login','POST',{'email':saved['email'],'password':saved['password']},csrf)[0]==200
subprocess.run(['docker','compose','-f','compose.yml','-f','compose.dev.yml','up','-d','--force-recreate','--no-deps','--wait','web'],check=True)
def check():
    try:return request(c,'/session')[1].get('user')
    except Exception:return None
assert eventually(check,lambda x:x is not None,30)['email']==saved['email']
print('PASS: authenticated session persisted across PHP container recreation')
