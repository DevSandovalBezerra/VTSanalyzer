<?php
require __DIR__.'/../src/bootstrap.php';
$email=$argv[1]??'';
$password=trim(stream_get_contents(STDIN));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12) { fwrite(STDERR,"Use email válido e senha de pelo menos 12 caracteres via stdin.\n"); exit(1); }
query('INSERT INTO users(id,email,password_hash) VALUES (?,?,?)',[id(),strtolower($email),password_hash($password,PASSWORD_ARGON2ID)]);
echo "Usuário criado.\n";
