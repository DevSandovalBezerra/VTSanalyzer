"""Project-scoped local outputs. Media files have one canonical location."""
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import sys
import unicodedata
from service import database

ROOT = Path(os.getenv('OUTPUTS_PATH', '/outputs'))
LEGACY = Path('/data')


def slug(name):
    text = unicodedata.normalize('NFKD', name).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', '-', text).strip('-')[:48].rstrip('-') or 'projeto'


def atomic_text(path, text):
    path.parent.mkdir(parents=True, exist_ok=True)
    temp = path.with_name('.' + path.name + '.tmp')
    temp.write_text(text, encoding='utf-8')
    temp.replace(path)


def structured(path, value):
    atomic_text(path, json.dumps(value, ensure_ascii=False, indent=2, default=str, allow_nan=False) + '\n')


def project_directory(pid):
    with database() as db:
        project = db.execute('SELECT * FROM projects WHERE id=%s', (pid,)).fetchone()
        if not project:
            raise ValueError('Projeto inexistente.')
        name = project['output_directory']
        if not name:
            name = slug(project['name']) + '--' + pid
            name = db.execute('UPDATE projects SET output_directory=COALESCE(output_directory,%s) WHERE id=%s RETURNING output_directory', (name, pid)).fetchone()['output_directory']
    if not re.fullmatch(r'[a-z0-9-]+--[a-f0-9]{32}', name):
        raise ValueError('Diretório de saída inválido.')
    folder = ROOT / name
    folder.mkdir(parents=True, exist_ok=True)
    return folder


def run_directory(rid):
    with database() as db:
        row = db.execute('SELECT r.id,r.version,v.id AS video_id,v.project_id FROM runs r JOIN videos v ON v.id=r.video_id WHERE r.id=%s', (rid,)).fetchone()
    if not row:
        raise ValueError('Análise inexistente.')
    folder = project_directory(row['project_id']) / 'videos' / row['video_id'] / 'analises' / f"v{row['version']:03d}--{rid}"
    folder.mkdir(parents=True, exist_ok=True)
    return folder


def export_path(aid):
    with database() as db:
        artifact = db.execute('SELECT run_id FROM artifacts WHERE id=%s', (aid,)).fetchone()
    folder = run_directory(artifact['run_id']) / 'exportacoes'
    folder.mkdir(exist_ok=True)
    return folder / f'{aid}.zip'


def timestamp(seconds):
    millis = max(0, round(float(seconds) * 1000))
    hours, millis = divmod(millis, 3600000)
    minutes, millis = divmod(millis, 60000)
    seconds, millis = divmod(millis, 1000)
    return f'{hours:02}:{minutes:02}:{seconds:02},{millis:03}'


def sync_run(rid):
    folder = run_directory(rid)
    with database() as db:
        db.execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')
        run = db.execute('SELECT * FROM runs WHERE id=%s', (rid,)).fetchone()
        stages = db.execute('SELECT * FROM stages WHERE run_id=%s ORDER BY name', (rid,)).fetchall()
        claims = db.execute('SELECT * FROM claims WHERE run_id=%s ORDER BY created_at,id', (rid,)).fetchall()
        history = db.execute('SELECT * FROM revisions WHERE run_id=%s ORDER BY id', (rid,)).fetchall()
        snapshots = db.execute('SELECT * FROM snapshots WHERE run_id=%s ORDER BY created_at', (rid,)).fetchall()
        artifacts = db.execute('SELECT * FROM artifacts WHERE run_id=%s ORDER BY created_at', (rid,)).fetchall()
    # Pending results must not be mistaken for current evidence after a retry.
    results = {s['name']: s['result'] if s['status'] == 'completed' else None for s in stages}
    transcript = results.get('transcript') or {'segments': [], 'reason': 'Transcrição ainda indisponível.'}
    structured(folder / 'transcricao' / 'transcricao.json', transcript)
    segments = transcript.get('segments', [])
    atomic_text(folder / 'transcricao' / 'transcricao.txt', '\n'.join(f"[{timestamp(s['start'])} --> {timestamp(s['end'])}] {s['text']}" for s in segments) + ('\n' if segments else transcript.get('reason', 'Sem falas reconhecidas.') + '\n'))
    atomic_text(folder / 'transcricao' / 'transcricao.srt', '\n\n'.join(f"{i}\n{timestamp(s['start'])} --> {timestamp(s['end'])}\n{s['text'].strip()}" for i, s in enumerate(segments, 1)) + ('\n' if segments else ''))
    for name, filename in [('audio', 'audio.json'), ('frames', 'frames.json'), ('ocr', 'ocr.json'), ('screens', 'telas.json')]:
        structured(folder / filename, results.get(name))
    structured(folder / 'analise.json', {'run': run, 'etapas': [{k: v for k, v in s.items() if k != 'result'} for s in stages], 'conclusoes': claims, 'analise_semantica': 'desativada', 'aviso': 'Resultados de trabalho. Consulte review_status; somente itens aprovados integram os pacotes revisados.'})
    structured(folder / 'historico.json', history)
    structured(folder / 'exportacoes.json', artifacts)
    for snapshot in snapshots:
        structured(folder / 'snapshots' / (snapshot['id'] + '.json'), snapshot)
    structured(folder / 'INDICE.json', {'run_id': rid, 'version': run['version'], 'status': run['status'], 'arquivos': sorted(str(p.relative_to(folder)) for p in folder.rglob('*') if p.is_file() and not p.name.startswith('.') and p.name != 'INDICE.json')})


def sync_project(pid):
    folder = project_directory(pid)
    with database() as db:
        project = db.execute('SELECT id,name,objective,domain,language,notes,status,version,created_at,updated_at,output_directory FROM projects WHERE id=%s', (pid,)).fetchone()
        videos = db.execute('SELECT * FROM videos WHERE project_id=%s ORDER BY created_at', (pid,)).fetchall()
        runs = db.execute('SELECT r.id FROM runs r JOIN videos v ON r.video_id=v.id WHERE v.project_id=%s ORDER BY r.created_at', (pid,)).fetchall()
        inventories = db.execute('SELECT * FROM target_inventories WHERE project_id=%s ORDER BY created_at', (pid,)).fetchall()
    structured(folder / 'projeto.json', project)
    for video in videos:
        structured(folder / 'videos' / video['id'] / 'video.json', video)
    for run in runs:
        sync_run(run['id'])
    for inventory in inventories:
        structured(folder / 'inventarios' / (inventory['id'] + '.json'), inventory)
    atomic_text(folder / 'LEIA-ME.txt', f"Projeto: {project['name']}\nID: {pid}\n\nObjetivo: {project['objective']}\n\nCada vídeo possui video.json e uma pasta analises/vNNN--ID por execução.\nCada análise inclui audio.wav (quando disponível), frames/*.jpg, frames.json,\ntranscricao/transcricao.txt, .srt e .json, ocr.json, telas.json, analise.json,\nhistorico.json, snapshots/ e exportacoes/ (quando gerados).\n\nA pasta é estável ao renomear o projeto; projeto.json acompanha o nome atual.\nRevisões atualizam os arquivos pela fila local. Falhas aparecem nos jobs outputs.\nEstes são resultados de trabalho, sujeitos à revisão. Astra permanece desativado.\nO vídeo original permanece no armazenamento privado da aplicação.\n")
    return {'project_id': pid, 'directory': folder.name, 'runs': len(runs)}


def digest(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def migrate_files(source, destination):
    """Resume-safe relocation: verify every byte before removing legacy copies."""
    if not source.exists():
        return
    if source.is_symlink() or not source.resolve().is_relative_to(LEGACY):
        raise ValueError('Origem de migração inválida.')
    if not destination.resolve().is_relative_to(ROOT.resolve()):
        raise ValueError('Destino de migração inválido.')
    files = sorted(p for p in source.rglob('*') if p.is_file()) if source.is_dir() else [source]
    for old in files:
        if old.is_symlink():
            raise ValueError('Link inesperado na migração.')
        new = destination / old.relative_to(source) if source.is_dir() else destination
        new.parent.mkdir(parents=True, exist_ok=True)
        if not new.exists():
            temp = new.with_name('.' + new.name + '.migrating')
            shutil.copyfile(old, temp)
            if digest(old) != digest(temp):
                raise ValueError('Falha de integridade na migração.')
            temp.replace(new)
        if digest(old) != digest(new):
            raise ValueError('Destino diverge da origem; ambos foram preservados.')
    if source.is_dir():
        shutil.rmtree(source)
    else:
        source.unlink()


def migrate_all():
    with database() as db:
        runs = db.execute('SELECT id FROM runs').fetchall()
        artifacts = db.execute("SELECT id FROM artifacts WHERE status='completed'").fetchall()
    for run in runs:
        migrate_files(LEGACY / 'runs' / run['id'], run_directory(run['id']))
    for artifact in artifacts:
        migrate_files(LEGACY / 'exports' / (artifact['id'] + '.zip'), export_path(artifact['id']))


if __name__ == '__main__':
    if '--migrate' in sys.argv:
        migrate_all()
    with database() as db:
        projects = db.execute('SELECT id FROM projects ORDER BY created_at').fetchall()
    for project in projects:
        print(json.dumps(sync_project(project['id']), ensure_ascii=False))
