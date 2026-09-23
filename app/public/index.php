<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>getenv('SESSION_SECURE')==='1']);
session_start();
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self' blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('Cache-Control: no-store');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$method=$_SERVER['REQUEST_METHOD'];
try {
    if ($path==='/health') { db()->query('SELECT 1'); queue()->ping(); json_response(['status'=>'ok','version'=>getenv('APP_VERSION')]); }
    if (in_array($path,['/assets/app.js','/assets/app.css','/assets/media.js','/assets/knowledge.js'],true)) { header('Content-Type: '.(str_ends_with($path,'.js')?'text/javascript':'text/css').'; charset=utf-8'); readfile('/frontend/'.basename($path)); exit; }
    $_SESSION['csrf']??=bin2hex(random_bytes(32));
    if ($method!=='GET' && !hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) json_response(['error'=>'Sessão expirada. Recarregue a página.'],419);
    if ($path==='/api/session') json_response(['user'=>$_SESSION['user']??null,'csrf'=>$_SESSION['csrf']]);
    if ($path==='/api/login' && $method==='POST') {
        $v=input(); $email=strtolower(trim((string)($v['email']??''))); $r=queue(); $key='login:'.hash('sha256',($_SERVER['REMOTE_ADDR']??'').$email);
        if ((int)$r->get($key)>=10) json_response(['error'=>'Muitas tentativas. Aguarde 15 minutos.'],429);
        $user=query('SELECT * FROM users WHERE email=?',[$email])->fetch(PDO::FETCH_ASSOC);
        if (!$user||!password_verify((string)($v['password']??''),$user['password_hash'])) { $r->incr($key); $r->expire($key,900); json_response(['error'=>'E-mail ou senha inválidos.'],401); }
        $r->del($key); session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); $_SESSION['user']=['id'=>$user['id'],'email'=>$user['email'],'role'=>$user['role']]; audit('login'); json_response(['user'=>$_SESSION['user'],'csrf'=>$_SESSION['csrf']]);
    }
    if (str_starts_with($path,'/api/')) {
        if (!isset($_SESSION['user'])) json_response(['error'=>'Entre para continuar.'],401);
        if ($path==='/api/logout' && $method==='POST') { audit('logout'); $_SESSION=[]; session_destroy(); json_response(['ok'=>true]); }
        if ($path==='/api/projects' && $method==='GET') json_response(query('SELECT p.*,(SELECT count(*) FROM jobs j WHERE j.project_id=p.id) AS jobs_count FROM projects p WHERE owner_id=? ORDER BY updated_at DESC',[$_SESSION['user']['id']])->fetchAll(PDO::FETCH_ASSOC));
        if ($path==='/api/projects' && $method==='POST') {
            $v=input(); $pid=id(); $name=required($v,'name'); $objective=required($v,'objective',4000);
            query('INSERT INTO projects(id,owner_id,name,objective,domain,language,notes) VALUES (?,?,?,?,?,?,?)',[$pid,$_SESSION['user']['id'],$name,$objective,substr((string)($v['domain']??''),0,200),'pt-BR',substr((string)($v['notes']??''),0,8000)]);
            audit('project.created',$pid); json_response(owned_project($pid),201);
        }
        if (preg_match('#^/api/projects/([a-f0-9]{32})$#',$path,$m)) {
            $p=owned_project($m[1]);
            if ($method==='GET') json_response($p);
            if ($method==='PATCH') {
                $v=input(); $name=required($v,'name'); $objective=required($v,'objective',4000); $status=$v['status']??$p['status']; if(!in_array($status,['active','archived'],true)) json_response(['error'=>'Estado inválido.'],422);
                db()->beginTransaction();
                query('SELECT id FROM projects WHERE id=? FOR UPDATE',[$p['id']]); $p=owned_project($p['id']);
                query('INSERT INTO project_versions(project_id,version,data) VALUES (?,?,?)',[$p['id'],$p['version'],json_encode($p,JSON_THROW_ON_ERROR)]);
                query('UPDATE projects SET name=?,objective=?,domain=?,notes=?,status=?,version=version+1,updated_at=now() WHERE id=?',[$name,$objective,substr((string)($v['domain']??$p['domain']),0,200),substr((string)($v['notes']??$p['notes']),0,8000),$status,$p['id']]); audit('project.updated',$p['id']); db()->commit(); json_response(owned_project($p['id']));
            }
        }
        if($path==='/api/diagnostics' && $method==='GET') {
            $r=queue(); json_response(['database'=>true,'redis'=>$r->ping()!==false,'worker'=>(bool)$r->exists('worker:heartbeat'),'scheduler'=>(bool)$r->exists('scheduler:heartbeat'),'storage'=>is_writable('/data'),'version'=>getenv('APP_VERSION'),'latest'=>query("SELECT status,result,error FROM jobs WHERE kind='diagnostic' ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC)?:null]);
        }
        if($path==='/api/diagnostics' && $method==='POST') { $jid=id(); query("INSERT INTO jobs(id,kind) VALUES (?,'diagnostic')",[$jid]); audit('diagnostic.queued',$jid); json_response(['id'=>$jid],202); }
        if($path==='/api/audit' && $method==='GET') json_response(query('SELECT action,subject_id,created_at FROM audit_events WHERE actor_id=? ORDER BY id DESC LIMIT 100',[$_SESSION['user']['id']])->fetchAll(PDO::FETCH_ASSOC));
        if (is_file(__DIR__.'/../src/media.php')) require __DIR__.'/../src/media.php';
        json_response(['error'=>'Recurso não encontrado.'],404);
    }
    header('Content-Type: text/html; charset=utf-8'); readfile('/frontend/index.html');
} catch (JsonException) { json_response(['error'=>'JSON inválido.'],422); }
catch (Throwable $e) { $ref=id(); error_log('error_ref='.$ref.' type='.get_class($e)); json_response(['error'=>'Não foi possível concluir. Verifique o diagnóstico.','reference'=>$ref],500); }
