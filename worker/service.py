import json
import os
import signal
import subprocess
import sys
import time
import threading
import importlib
import importlib.util
from pathlib import Path
import psycopg
import redis
from psycopg.rows import dict_row

def database():
    return psycopg.connect(host=os.environ['DB_HOST'], dbname=os.environ['POSTGRES_DB'], user=os.environ['POSTGRES_USER'], password=os.environ['POSTGRES_PASSWORD'], row_factory=dict_row)

def queue():
    return redis.Redis(host=os.environ['REDIS_HOST'], password=os.environ['REDIS_PASSWORD'], decode_responses=True, socket_timeout=5)

def diagnostic():
    return {'ffmpeg': subprocess.run(['ffmpeg', '-version'], capture_output=True, timeout=10).returncode == 0,
            'ocr': subprocess.run(['tesseract', '--version'], capture_output=True, timeout=10).returncode == 0,
            'transcription': importlib.util.find_spec('faster_whisper') is not None, 'model_profile': os.getenv('MODEL_PROFILE', 'astra'),
            'transcription_model_cached': any(Path('/home/app/.cache/whisper').glob('**/model.bin')),
            'model_configured': bool(os.getenv('MODEL_ENDPOINT') and os.getenv('MODEL_ID')),
            'target_readonly': os.path.isdir('/target') and not os.access('/target', os.W_OK)}

def main():
    r=queue()
    scheduler='--scheduler' in sys.argv
    heartbeat='scheduler:heartbeat' if scheduler else 'worker:heartbeat'
    if '--health' in sys.argv or '--health-scheduler' in sys.argv:
        key='scheduler:heartbeat' if '--health-scheduler' in sys.argv else 'worker:heartbeat'
        sys.exit(0 if r.exists(key) else 1)
    running=True
    def stop(*_):
        nonlocal running
        running=False
    signal.signal(signal.SIGTERM, stop)
    signal.signal(signal.SIGINT, stop)
    active_job=[None]
    def keepalive():
        while running:
            try:
                r.set(heartbeat,str(time.time()),ex=30)
                if active_job[0]:
                    with database() as db:db.execute("UPDATE jobs SET updated_at=now() WHERE id=%s AND status='processing'",(active_job[0],))
            except Exception:pass
            time.sleep(5)
    threading.Thread(target=keepalive,daemon=True).start()
    capabilities_at=0
    while running:
        try:
            r.set(heartbeat, str(time.time()), ex=30)
            if not scheduler and time.monotonic()-capabilities_at>30:
                r.set('worker:capabilities',json.dumps(diagnostic()),ex=120)
                capabilities_at=time.monotonic()
            if scheduler:
                # Database outbox: re-delivery recovers crashes between DB commit and Redis notification.
                with database() as db:
                    if db.execute("SELECT to_regclass('jobs') AS name").fetchone()['name']:
                        db.execute("UPDATE jobs j SET status='failed',error='Sincronização substituída por outra pendente.' WHERE j.kind='outputs' AND j.status='processing' AND j.updated_at < now()-interval '90 seconds' AND EXISTS (SELECT 1 FROM jobs q WHERE q.project_id=j.project_id AND q.kind='outputs' AND q.status='queued')")
                        db.execute("UPDATE jobs SET status='queued' WHERE status='processing' AND updated_at < now()-interval '90 seconds'")
                        for job in db.execute("SELECT id FROM jobs WHERE status='queued' ORDER BY created_at LIMIT 100").fetchall():
                            if r.set('dispatch:'+job['id'], '1', nx=True, ex=30):
                                r.lpush('jobs', job['id'])
                time.sleep(3)
                continue
            item=r.brpop('jobs', timeout=2)
            if not item:
                continue
            with database() as db:
                job=db.execute("UPDATE jobs SET status='processing',updated_at=now() WHERE id=%s AND status='queued' RETURNING *",(item[1],)).fetchone()
            if not job:
                continue
            active_job[0]=job['id']
            try:
                if job['kind']=='outputs':
                    import outputs
                    importlib.reload(outputs)
                    result=outputs.sync_project(job['payload']['project_id'])
                elif job['kind']=='diagnostic':result=diagnostic()
                else:
                    import pipeline
                    importlib.reload(pipeline)
                    if job['kind']=='ingest':result=pipeline.ingest(job['payload']['video_id'])
                    elif job['kind']=='pipeline':result=pipeline.process(job['payload']['run_id'])
                    elif job['kind'] in {'redact','export','inventory'}:
                        import knowledge
                        importlib.reload(knowledge)
                        if job['kind']=='redact':result=knowledge.redact(job['payload'])
                        elif job['kind']=='export':result=knowledge.export_package(job['payload']['artifact_id'])
                        else:result=knowledge.inventory(job['payload']['inventory_id'])
                    else:raise ValueError('Tipo de tarefa não suportado.')
                with database() as db:
                    db.execute("UPDATE jobs SET status='completed',result=%s,updated_at=now() WHERE id=%s",(json.dumps(result),job['id']))
            except Exception as exc:
                is_cancelled=type(exc).__name__=='Cancelled'
                message=str(exc) if type(exc).__name__=='ProcessingError' else 'A tarefa falhou. Verifique o diagnóstico.'
                with database() as db:
                    db.execute("UPDATE jobs SET status=%s,error=%s,updated_at=now() WHERE id=%s",('cancelled' if is_cancelled else 'failed',None if is_cancelled else message,job['id']))
                    if job['kind']=='ingest':db.execute("UPDATE videos SET status='invalid',error=%s WHERE id=%s",(message,job['payload']['video_id']))
                    if job['kind']=='export':db.execute("UPDATE artifacts SET status='failed',error=%s WHERE id=%s",(message,job['payload']['artifact_id']))
                    if job['kind']=='inventory':db.execute("UPDATE target_inventories SET status='failed',error=%s WHERE id=%s",(message,job['payload']['inventory_id']))
                    if job['kind']=='redact':db.execute("UPDATE runs SET status='failed',error=%s WHERE id=%s",(message,job['payload']['run_id']))
            finally:active_job[0]=None
        except Exception as exc:
            print(json.dumps({'service':'scheduler' if scheduler else 'worker','error_type':type(exc).__name__}), flush=True)
            time.sleep(3)

if __name__ == '__main__':
    main()
