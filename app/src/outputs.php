<?php
// Fixed catalog: callers select a key, never a filesystem path.
function output_documents(string $rid): array {
    $documents=[
        'transcript-txt'=>['Transcrição','transcricao/transcricao.txt'],
        'transcript-srt'=>['Legendas SRT','transcricao/transcricao.srt'],
        'transcript-json'=>['Transcrição estruturada','transcricao/transcricao.json'],
        'ocr'=>['Texto das telas · OCR','ocr.json'],
        'screens'=>['Telas identificadas','telas.json'],
        'analysis'=>['Análise e conclusões','analise.json'],
        'history'=>['Histórico de revisão','historico.json'],
        'frames'=>['Índice dos frames','frames.json'],
        'audio'=>['Informações do áudio','audio.json'],
        'exports'=>['Exportações','exportacoes.json'],
        'index'=>['Índice de arquivos','INDICE.json'],
    ];
    foreach(query('SELECT id,created_at FROM snapshots WHERE run_id=? ORDER BY created_at DESC',[$rid])->fetchAll(PDO::FETCH_ASSOC) as $s)
        $documents['snapshot-'.$s['id']]=['Versão aprovada · '.$s['created_at'],'snapshots/'.$s['id'].'.json'];
    return $documents;
}
function available_output(string $folder,string $relative): ?string {
    $base=realpath($folder);$file=realpath($folder.'/'.$relative);
    return $base && $file && str_starts_with($file,$base.'/') && is_file($file) ? $file : null;
}
if(preg_match('#^/api/runs/([a-f0-9]{32})/outputs(?:/(text|audio))?$#',$path,$m)&&$method==='GET'){
    $r=owned_run($m[1]);$documents=output_documents($r['id']);$folder=$r['output_directory']?run_output_path($r):'';
    $action=$m[2]??'';
    if($action==='text'){
        $key=$_GET['file']??'';
        if(!is_string($key)||!isset($documents[$key]))json_response(['error'=>'Documento não encontrado.'],404);
        $file=$folder?available_output($folder,$documents[$key][1]):null;
        if(!$file)json_response(['error'=>'O arquivo ainda não foi gerado. Atualize após o processamento.'],404);
        header('X-Content-Type-Options: nosniff');
        if(($_GET['download']??'')==='1')stream_file($file,'text/plain; charset=utf-8',true);
        $limit=2*1024*1024;$content=file_get_contents($file,false,null,0,$limit);
        // Keep the preview valid UTF-8 when the byte limit bisects a character.
        for($n=0;$n<4 && preg_match('//u',$content)!==1;$n++)$content=substr($content,0,-1);
        json_response(['key'=>$key,'content'=>$content,'truncated'=>filesize($file)>$limit,'bytes'=>filesize($file)]);
    }
    if($action==='audio'){
        $file=$folder?available_output($folder,'audio.wav'):null;
        if(!$file)json_response(['error'=>'Áudio ainda indisponível.'],404);
        stream_file($file,'audio/wav');
    }
    $files=[];
    foreach($documents as $key=>[$label,$relative]){
        $file=$folder?available_output($folder,$relative):null;
        if($file)$files[]=['key'=>$key,'label'=>$label,'path'=>$relative,'bytes'=>filesize($file),'modified'=>gmdate('c',filemtime($file)),'format'=>pathinfo($relative,PATHINFO_EXTENSION)];
    }
    $stage=query("SELECT result FROM stages WHERE run_id=? AND name='frames' AND status='completed'",[$r['id']])->fetchColumn();
    $frames=[];
    foreach(($stage?json_decode($stage,true)['frames']??[]:[]) as $frame){
        if($folder && available_output($folder,'frames/'.$frame['id'].'.jpg'))$frames[]=$frame;
    }
    $sync=query("SELECT status,error,updated_at FROM jobs WHERE project_id=? AND kind='outputs' ORDER BY created_at DESC LIMIT 1",[$r['project_id']])->fetch(PDO::FETCH_ASSOC)?:null;
    json_response(['files'=>$files,'frames'=>$frames,'audio'=>(bool)($folder && available_output($folder,'audio.wav')),'sync'=>$sync]);
}
