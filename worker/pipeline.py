"""Deterministic local media stages. No model calls or original-file mutation."""
import csv
import hashlib
import io
import json
import math
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import time
import uuid
from PIL import Image, ImageDraw
from service import database
from outputs import run_directory

ROOT=Path('/data')
ORDER=['audio','transcript','frames','ocr','screens']

class Cancelled(Exception): pass
class ProcessingError(Exception): pass

def uid(): return uuid.uuid4().hex
def cancelled(rid):
    if not rid:return
    with database() as db:
        r=db.execute('SELECT status FROM runs WHERE id=%s',(rid,)).fetchone()
    if not r or r['status']=='cancelled':raise Cancelled()

def command(args,rid=None,timeout=600):
    # Capture into a temporary file so verbose FFmpeg output cannot fill a pipe or memory.
    with tempfile.TemporaryFile() as out,tempfile.TemporaryFile() as err:
        p=subprocess.Popen(args,stdout=out,stderr=err)
        started=time.monotonic()
        try:
            while p.poll() is None:
                cancelled(rid)
                if time.monotonic()-started>timeout:raise ProcessingError('A etapa excedeu o tempo limite. Revise a duração e o perfil.')
                time.sleep(.15)
            if p.returncode:raise ProcessingError('O motor de mídia rejeitou o arquivo ou os parâmetros. Verifique o formato e tente novamente.')
            out.seek(0);err.seek(0)
            return out.read(8*1024*1024).decode('utf-8','replace'),err.read(8*1024*1024).decode('utf-8','replace')
        finally:
            if p.poll() is None:p.terminate()
            try:p.wait(timeout=5)
            except subprocess.TimeoutExpired:p.kill();p.wait()

def ingest(vid):
    with database() as db:
        video=db.execute('SELECT * FROM videos WHERE id=%s',(vid,)).fetchone()
        chunks=db.execute('SELECT * FROM upload_chunks WHERE video_id=%s ORDER BY number',(vid,)).fetchall()
    if video['status']=='ready':return video['metadata']
    (ROOT/'videos').mkdir(exist_ok=True)
    target=ROOT/'videos'/f"{vid}.{video['extension']}"
    temporary=target.with_suffix('.assembling')
    digest=hashlib.sha256()
    with temporary.open('wb') as dest:
        for chunk in chunks:
            data=(ROOT/'uploads'/vid/f"{chunk['number']}.part").read_bytes()
            if hashlib.sha256(data).hexdigest()!=chunk['sha256']:raise ProcessingError('Um bloco do upload está corrompido. Envie o vídeo novamente.')
            dest.write(data);digest.update(data)
    if temporary.stat().st_size!=video['size']:raise ProcessingError('O tamanho recebido difere do informado.')
    # Uploaded files never select protocols or executable commands.
    output,_=command(['ffprobe','-v','error','-protocol_whitelist','file,pipe','-show_format','-show_streams','-of','json',str(temporary)],timeout=60)
    probe=json.loads(output)
    formats=set(probe.get('format',{}).get('format_name','').split(','))
    allowed={'mp4':{'mov','mp4'},'mov':{'mov','mp4'},'mkv':{'matroska','webm'},'webm':{'matroska','webm'}}
    streams=[s for s in probe.get('streams',[]) if s.get('codec_type')=='video']
    if not streams or not formats.intersection(allowed[video['extension']]):raise ProcessingError('O conteúdo não corresponde a um contêiner de vídeo suportado.')
    stream=streams[0];duration=float(probe['format'].get('duration',0));fps=stream.get('avg_frame_rate','0/1')
    if not math.isfinite(duration) or duration<=0 or duration>int(os.getenv('MAX_VIDEO_SECONDS','7200')):raise ProcessingError('Duração inválida ou superior ao limite configurado.')
    if stream.get('width',0)>7680 or stream.get('height',0)>4320:raise ProcessingError('Resolução acima do limite de 8K.')
    metadata={'duration':duration,'width':stream['width'],'height':stream['height'],'fps':fps,'video_codec':stream.get('codec_name'),'audio_codec':next((s.get('codec_name') for s in probe['streams'] if s.get('codec_type')=='audio'),None),'container':sorted(formats)}
    # Decode the beginning to catch common corrupt files before accepting the source.
    command(['ffmpeg','-v','error','-xerror','-protocol_whitelist','file,pipe','-i',str(temporary),'-t','2','-map','0:v:0','-f','null','-'],timeout=60)
    temporary.replace(target)
    with database() as db:
        duplicate=db.execute('SELECT id FROM videos WHERE project_id=%s AND sha256=%s AND id<>%s LIMIT 1',(video['project_id'],digest.hexdigest(),vid)).fetchone()
        metadata['duplicate_of']=duplicate['id'] if duplicate else None
        db.execute("UPDATE videos SET status='ready',metadata=%s,sha256=%s,error=NULL WHERE id=%s",(json.dumps(metadata),digest.hexdigest(),vid))
    # Delete only validated upload chunks belonging to this generated identifier.
    for chunk in chunks:(ROOT/'uploads'/vid/f"{chunk['number']}.part").unlink(missing_ok=True)
    return metadata

def audio(run,video,folder,results):
    if not video['metadata']['audio_codec']:return {'available':False,'reason':'O vídeo não possui faixa de áudio.'}
    path=folder/'audio.wav'
    command(['ffmpeg','-y','-v','error','-protocol_whitelist','file,pipe','-i',str(source(video)),'-vn','-ac','1','-ar','16000',str(path)],run['id'],1800)
    return {'available':True,'path':'audio.wav','sample_rate':16000}

def source(video):return ROOT/'videos'/f"{video['id']}.{video['extension']}"

def transcript(run,video,folder,results):
    if not results['audio']['available']:return {'segments':[],'reason':'Sem áudio na origem.','engine':'none'}
    from faster_whisper import WhisperModel
    model_name=os.getenv('TRANSCRIPTION_MODEL','small')
    try:model=WhisperModel(model_name,device=os.getenv('COMPUTE_DEVICE','cpu'),compute_type='int8',download_root='/home/app/.cache/whisper',local_files_only=True)
    except Exception:raise ProcessingError('Modelo de transcrição não instalado no cache local. Execute scripts/download-model.sh e retome esta etapa.') from None
    segments,info=model.transcribe(str(folder/'audio.wav'),language=run['config']['language'],vad_filter=True)
    output=[]
    for s in segments:
        cancelled(run['id']);output.append({'id':uid(),'start':round(s.start,3),'end':round(s.end,3),'text':s.text.strip(),'original_text':s.text.strip(),'avg_logprob':s.avg_logprob,'no_speech_prob':s.no_speech_prob})
    return {'segments':output,'engine':'faster-whisper','model':model_name,'language':info.language}

def perceptual_hash(path):
    with Image.open(path) as original:
        values=list(original.convert('L').resize((9,8)).getdata())
    return sum((values[y*9+x]>values[y*9+x+1])<<(y*8+x) for y in range(8) for x in range(8))

def frames(run,video,folder,results):
    frame_dir=folder/'frames';frame_dir.mkdir(exist_ok=True)
    cfg=run['config'];candidates=[]
    filters=[('periodic',f"isnan(prev_selected_t)+gte(t-prev_selected_t\\,{cfg['interval']})"),('scene',f"gt(scene\\,{cfg['scene']})")]
    for label,selection in filters:
        temp=folder/('capture-'+uid());temp.mkdir()
        _,log=command(['ffmpeg','-y','-hide_banner','-protocol_whitelist','file,pipe','-i',str(source(video)),'-an','-vf',f"select={selection},showinfo,scale=w='min({cfg['width']},iw)':h=-2",'-fps_mode','vfr','-frames:v',str(cfg['max_frames']),str(temp/'%06d.jpg')],run['id'],3600)
        times=[float(t) for t in re.findall(r'pts_time:([\d.eE+-]+)',log)]
        for file,t in zip(sorted(temp.glob('*.jpg')),times):candidates.append((t,label,file))
    candidates.sort(key=lambda item:item[0]);items=[];hashes=[];last=-1
    for timestamp,label,file in candidates:
        cancelled(run['id'])
        if abs(timestamp-last)<.04:continue
        last=timestamp;fid=uid();h=perceptual_hash(file)
        duplicate=next((oldid for oldh,oldid in hashes if (oldh^h).bit_count()<=5),None)
        shutil.copyfile(file,frame_dir/f'{fid}.jpg')
        with Image.open(file) as im:width,height=im.size
        items.append({'id':fid,'timestamp':round(timestamp,3),'capture':label,'hash':f'{h:016x}','duplicate_of':duplicate,'included':duplicate is None,'width':width,'height':height,'redactions':[]})
        if duplicate is None:hashes.append((h,fid))
    for temp in {file.parent for _,_,file in candidates}:
        for file in temp.glob('*.jpg'):file.unlink()
        temp.rmdir()
    representatives=[f for f in items if f['included']]
    sheet=Image.new('RGB',(960,max(1,math.ceil(min(len(representatives),60)/4))*165),'#edf3ed');draw=ImageDraw.Draw(sheet)
    for i,f in enumerate(representatives[:60]):
        with Image.open(frame_dir/f"{f['id']}.jpg") as im:
            im.thumbnail((230,135));x=(i%4)*240;y=(i//4)*165;sheet.paste(im,(x,y));draw.text((x+4,y+139),f"{f['timestamp']:.3f}s",fill='#183c2a')
    sheet.save(folder/'contact-sheet.jpg')
    return {'frames':items,'representative_count':len(representatives),'capture_limit_per_method':cfg['max_frames'],'possibly_truncated':len(candidates)>=cfg['max_frames']}

def ocr(run,video,folder,results):
    blocks=[]
    for frame in results['frames']['frames']:
        if not frame['included']:continue
        text,_=command(['tesseract',str(folder/'frames'/f"{frame['id']}.jpg"),'stdout','-l',run['config']['ocr_language'],'tsv'],run['id'],120)
        for row in csv.DictReader(io.StringIO(text),delimiter='\t'):
            if row.get('text','').strip() and float(row.get('conf',-1))>=0:
                blocks.append({'id':uid(),'frame_id':frame['id'],'timestamp':frame['timestamp'],'text':row['text'],'confidence':float(row['conf'])/100,'box':{k:int(row[k]) for k in ['left','top','width','height']}})
    return {'blocks':blocks,'engine':'tesseract'}

def screens(run,video,folder,results):
    groups=[]
    for frame in results['frames']['frames']:
        if not frame['included']:continue
        words=[b['text'] for b in results['ocr']['blocks'] if b['frame_id']==frame['id']]
        groups.append({'id':uid(),'name':' '.join(words[:8]) or f"Tela em {frame['timestamp']:.1f}s",'frame_id':frame['id'],'timestamp':frame['timestamp'],'classification':'observed','review_status':'pending','components':[],'note':'Agrupamento visual determinístico. Finalidade e componentes ainda precisam de revisão.'})
    return {'screens':groups,'events':[],'flows':[],'semantic_analysis':'disabled'}

def process(rid):
    with database() as db:
        run=db.execute('SELECT * FROM runs WHERE id=%s',(rid,)).fetchone()
        video=db.execute('SELECT * FROM videos WHERE id=%s',(run['video_id'],)).fetchone()
        if run['config'].get('redaction_pending'):raise ProcessingError('Ocultação pendente. Repita a ocultação antes de analisar.')
        if run['status']=='cancelled':raise Cancelled()
        db.execute("UPDATE runs SET status='processing',error=NULL,updated_at=now() WHERE id=%s",(rid,))
    folder=run_directory(rid)
    results={}
    for name in ORDER:
        cancelled(rid)
        with database() as db:stage=db.execute('SELECT * FROM stages WHERE run_id=%s AND name=%s',(rid,name)).fetchone()
        if stage['status']=='completed':results[name]=stage['result'];continue
        with database() as db:db.execute("UPDATE stages SET status='processing',error=NULL,updated_at=now() WHERE run_id=%s AND name=%s",(rid,name))
        try:
            result=globals()[name](run,video,folder,results)
            cancelled(rid)
            with database() as db:db.execute("UPDATE stages SET status='completed',result=%s,error=NULL,updated_at=now() WHERE run_id=%s AND name=%s",(json.dumps(result),rid,name))
            results[name]=result
        except Cancelled:raise
        except Exception as exc:
            message=str(exc) if isinstance(exc,ProcessingError) else 'Falha no motor local. Consulte o diagnóstico e repita a etapa.'
            with database() as db:
                db.execute("UPDATE stages SET status='failed',error=%s,updated_at=now() WHERE run_id=%s AND name=%s",(message,rid,name))
                db.execute("UPDATE runs SET status='failed',error=%s,updated_at=now() WHERE id=%s AND status<>'cancelled'",(message,rid))
            raise
    with database() as db:db.execute("UPDATE runs SET status='review',updated_at=now() WHERE id=%s AND status='processing'",(rid,))
    return {'run_id':rid,'status':'review'}
