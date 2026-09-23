<?php
if($path==='/api/register' && $method==='POST'){
    if(isset($_SESSION['user']))json_response(['error'=>'Saia da conta atual para registrar outro usuário.'],409);
    $v=input();
    if(!is_string($v['email']??null)||!is_string($v['password']??null)||!is_string($v['password_confirmation']??null))
        json_response(['error'=>'Preencha o usuário, a senha e a confirmação.'],422);
    $login=strtolower(trim($v['email']));$password=$v['password'];
    $validUsername=preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/D',$login);
    if(strlen($login)>254 || (!$validUsername && !filter_var($login,FILTER_VALIDATE_EMAIL)))
        json_response(['error'=>'Use um usuário de 3 a 40 letras, números, pontos, traços ou sublinhados, ou um e-mail válido.'],422);
    if(strlen($password)>512 || trim($password)==='' || preg_match('/\A.{6,128}\z/us',$password)!==1)
        json_response(['error'=>'Escolha uma senha com 6 a 128 caracteres.'],422);
    if(!hash_equals($password,$v['password_confirmation']))json_response(['error'=>'As senhas não coincidem.'],422);
    $created=query("INSERT INTO users(id,email,password_hash,role) VALUES (?,?,?,'user') ON CONFLICT(email) DO NOTHING RETURNING id,email,role",[id(),$login,password_hash($password,PASSWORD_ARGON2ID)])->fetch(PDO::FETCH_ASSOC);
    if(!$created)json_response(['error'=>'Este usuário ou e-mail já está cadastrado. Escolha outro ou entre na sua conta.'],409);
    session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['user']=$created;
    audit('user.registered',$created['id']);
    json_response(['user'=>$created,'csrf'=>$_SESSION['csrf']],201);
}
