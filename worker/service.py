import json
import os
import signal
import subprocess
import sys
import time
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
            'transcription': False, 'model_profile': os.getenv('MODEL_PROFILE', 'astra'),
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
    while running:
        try:
            r.set(heartbeat, str(time.time()), ex=30)
            if scheduler:
                # Database outbox: re-delivery recovers crashes between DB commit and Redis notification.
                with database() as db:
                    if db.execute("SELECT to_regclass('jobs') AS name").fetchone()['name']:
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
            try:
                if job['kind'] != 'diagnostic':
                    raise ValueError('Tipo de tarefa não suportado.')
                result=diagnostic()
                with database() as db:
                    db.execute("UPDATE jobs SET status='completed',result=%s,updated_at=now() WHERE id=%s",(json.dumps(result),job['id']))
            except Exception:
                with database() as db:
                    db.execute("UPDATE jobs SET status='failed',error='A tarefa falhou. Verifique o diagnóstico.',updated_at=now() WHERE id=%s",(job['id'],))
        except Exception as exc:
            print(json.dumps({'service':'scheduler' if scheduler else 'worker','error_type':type(exc).__name__}), flush=True)
            time.sleep(3)

if __name__ == '__main__':
    main()
