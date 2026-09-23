<?php
declare(strict_types=1);
function db(): PDO {
    static $db;
    return $db ??= new PDO('pgsql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('POSTGRES_DB'), getenv('POSTGRES_USER'), getenv('POSTGRES_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function queue(): Redis {
    $r = new Redis(); $r->connect(getenv('REDIS_HOST') ?: 'redis', 6379, 3); $r->auth(getenv('REDIS_PASSWORD')); return $r;
}
function query(string $sql, array $params = []): PDOStatement { $q = db()->prepare($sql); $q->execute($params); return $q; }
function id(): string { return bin2hex(random_bytes(16)); }
function json_response(mixed $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit; }
function audit(string $action, ?string $subject = null): void { query('INSERT INTO audit_events(actor_id,action,subject_id) VALUES (?,?,?)', [$_SESSION['user']['id'] ?? null, $action, $subject]); }
function input(): array { $v = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR); if (!is_array($v)) json_response(['error'=>'Corpo inválido.'],422); return $v; }
function required(array $data, string $key, int $max = 200): string { $s=trim((string)($data[$key]??'')); if ($s==='' || strlen($s)>$max) json_response(['error'=>"Preencha corretamente: $key."],422); return $s; }
function owned_project(string $project): array { $p=query('SELECT * FROM projects WHERE id=? AND owner_id=?',[$project,$_SESSION['user']['id']])->fetch(PDO::FETCH_ASSOC); if (!$p) json_response(['error'=>'Projeto não encontrado.'],404); return $p; }
