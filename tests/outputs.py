"""Integration checks for project folders, live revisions, media and exports."""
import hashlib
import json
from pathlib import Path
import subprocess
from foundation import client, request
from media import eventually


def load(path):
    return json.loads(path.read_text()) if path.exists() else None


def run():
    saved = load(Path('/tmp/ske-media-test.json'))
    c = client()
    csrf = request(c, '/session')[1]['csrf']
    csrf = request(c, '/login', 'POST', {'email': saved['email'], 'password': saved['password']}, csrf)[1]['csrf']
    pid = saved['project_id']
    p = eventually(lambda: request(c, '/projects/' + pid)[1], lambda p: bool(p['output_directory']))
    folder = Path('outputs') / p['output_directory']
    assert folder.is_dir() and folder.name.endswith('--' + pid)
    assert not folder.is_symlink()
    assert request(c, '/projects/' + pid, 'PATCH', {**p, 'name': p['name'] + ' renomeado'}, csrf)[0] == 200
    eventually(lambda: load(folder / 'projeto.json'), lambda x: x and x['name'].endswith(' renomeado'))
    assert request(c, '/projects/' + pid)[1]['output_directory'] == p['output_directory']
    assert request(c, '/projects/' + pid, 'PATCH', p, csrf)[0] == 200
    eventually(lambda: load(folder / 'projeto.json'), lambda x: x and x['name'] == p['name'])

    videos = request(c, '/projects/' + pid + '/videos')[1]
    narration = next(v for v in videos if v['title'] == 'Narração sintética em português')
    rid = json.loads(narration['runs'])[0]['id']
    r = request(c, '/runs/' + rid)[1]
    analysis = folder / 'videos' / r['video_id'] / 'analises' / f"v{r['version']:03}--{rid}"
    transcript = next(s['result'] for s in r['stages'] if s['name'] == 'transcript')
    segment = transcript['segments'][0]
    edited = segment['text'] + ' [verificação dos outputs]'
    assert request(c, f"/runs/{rid}/transcript/{segment['id']}", 'PATCH', {'text': edited}, csrf)[0] == 200
    eventually(lambda: load(analysis / 'transcricao/transcricao.json'), lambda x: x and x['segments'][0]['text'] == edited)
    assert edited in (analysis / 'transcricao/transcricao.txt').read_text()
    assert edited in (analysis / 'transcricao/transcricao.srt').read_text()
    assert (analysis / 'audio.wav').stat().st_size > 44
    assert load(analysis / 'historico.json')[-1]['after_value']['text'] == edited
    assert request(c, f"/runs/{rid}/transcript/{segment['id']}", 'PATCH', {'text': segment['text']}, csrf)[0] == 200
    eventually(lambda: load(analysis / 'transcricao/transcricao.json'), lambda x: x and x['segments'][0]['text'] == segment['text'])

    approved = request(c, '/runs/' + saved['run_id'])[1]
    approved_folder = folder / 'videos' / approved['video_id'] / 'analises' / f"v{approved['version']:03}--{approved['id']}"
    eventually(lambda: load(approved_folder / 'analise.json'), lambda x: x and x['run']['status'] == 'approved')
    frame = next(s['result'] for s in approved['stages'] if s['name'] == 'frames')['frames'][0]
    with c.open(f"http://localhost:8095/api/runs/{approved['id']}/frames/{frame['id']}") as response:
        assert response.read() == (approved_folder / 'frames' / (frame['id'] + '.jpg')).read_bytes()
    assert frame['redactions'] and not (approved_folder / 'contact-sheet.jpg').exists()
    artifact = request(c, '/runs/' + approved['id'] + '/exports')[1][0]
    package = approved_folder / 'exportacoes' / (artifact['id'] + '.zip')
    with c.open('http://localhost:8095/api/exports/' + artifact['id']) as response:
        assert hashlib.sha256(response.read()).hexdigest() == hashlib.sha256(package.read_bytes()).hexdigest()
    assert load(approved_folder / 'snapshots' / (artifact['snapshot_id'] + '.json'))['run_id'] == approved['id']
    assert list((folder / 'inventarios').glob('*.json'))
    for path in folder.rglob('*.json'):
        load(path)
    assert c.open('http://localhost:8095/outputs/' + folder.name + '/projeto.json').headers.get_content_type() != 'application/json'
    # Same display name must never merge distinct projects; punctuation cannot escape the root.
    status, other = request(c, '/projects', 'POST', {'name': p['name'], 'objective': 'Teste de isolamento dos outputs'}, csrf)
    assert status == 201
    other = eventually(lambda: request(c, '/projects/' + other['id'])[1], lambda x: bool(x['output_directory']))
    assert other['output_directory'] != p['output_directory']
    other_folder = Path('outputs') / other['output_directory']
    assert request(c, '/projects/' + other['id'], 'PATCH', {**other, 'name': '../../ Projeto : Áudio ?'}, csrf)[0] == 200
    eventually(lambda: load(other_folder / 'projeto.json'), lambda x: x and x['name'] == '../../ Projeto : Áudio ?')
    assert other_folder.resolve().is_relative_to(Path('outputs').resolve())
    assert not (other_folder / 'videos').exists()
    assert request(c, '/projects/' + other['id'], 'PATCH', {**other, 'name': 'Teste de isolamento dos outputs', 'status': 'archived'}, csrf)[0] == 200
    print('PASS: project folders, stable rename, distinct IDs, live TXT/SRT/JSON and history, audio, masked frame streaming, ZIP and snapshot, inventories, no public filesystem route')


if __name__ == '__main__':
    run()
