<?php
declare(strict_types=1);

// Only the selected, synchronized derivatives are sent. The original media never leaves the server.
function gemini_time(float $seconds): string {
    $n=max(0,(int)floor($seconds));
    return sprintf('%02d:%02d:%02d',intdiv($n,3600),intdiv($n%3600,60),$n%60);
}
function gemini_xml(string $value): string {return htmlspecialchars($value,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function gemini_stage_versions(string $rid): array {
    $rows=query("SELECT name,status,updated_at,md5(coalesce(result::text,'')) AS digest FROM stages WHERE run_id=? ORDER BY name",[$rid])->fetchAll(PDO::FETCH_ASSOC);
    $result=[];foreach($rows as $row)$result[$row['name']]=[$row['status'],$row['updated_at'],$row['digest']];
    return $result;
}
function gemini_evidence(array $run,string $snapshotDir): array {
    $rid=$run['id'];$transcript=stage_result($rid,'transcript');$ocr=stage_result($rid,'ocr');
    $frames=stage_result($rid,'frames')['frames']??[];$screens=stage_result($rid,'screens')['screens']??[];
    $selected=array_fill_keys(array_column($screens,'frame_id'),true);
    $events=[];$manifest=['segments'=>[],'screens'=>[],'times'=>[]];
    foreach($transcript['segments']??[] as $s){
        $start=gemini_time((float)$s['start']);$end=gemini_time((float)$s['end']);
        $body='['.$start.'–'.$end.'] '.gemini_xml((string)($s['speaker']??'')) . ' '.gemini_xml((string)$s['text']);
        $events[]=['type'=>'transcript','time'=>(float)$s['start'],'body'=>trim($body)];
        $manifest['segments'][]=$s['id']??null;$manifest['times'][$start]=true;$manifest['times'][$end]=true;
    }
    $index=0;$folder=run_output_path($run);
    foreach($frames as $frame){
        if(empty($frame['included'])||!isset($selected[$frame['id']]))continue;
        $file=$folder.'/frames/'.$frame['id'].'.jpg';
        if(!is_file($file)||filesize($file)>5*1024*1024)throw new RuntimeException('Uma tela selecionada está ausente ou excede 5 MB. Revise os outputs antes de analisar.');
        $copy=$snapshotDir.'/'.$frame['id'].'.jpg';
        if(!copy($file,$copy))throw new RuntimeException('Não foi possível congelar uma tela para análise.');
        $index++;$label=sprintf('%03d',$index);$stamp=gemini_time((float)$frame['timestamp']);
        $ocrText=implode("\n",array_map(fn($b)=>(string)$b['text'],array_values(array_filter($ocr['blocks']??[],fn($b)=>($b['frame_id']??'')===$frame['id']))));
        $events[]=['type'=>'screen','time'=>(float)$frame['timestamp'],'id'=>'IMG-'.$label,
            'ocr_id'=>'OCR-'.$label,'body'=>'<tela id="IMG-'.$label.'" ts="'.$stamp.'"><ocr id="OCR-'.$label.'">'.gemini_xml($ocrText).'</ocr>',
            'file'=>$copy,'bytes'=>(int)filesize($copy)];
        $manifest['screens'][]=['id'=>'IMG-'.$label,'ocr_id'=>'OCR-'.$label,'frame_id'=>$frame['id'],'time'=>$stamp];
        $manifest['times'][$stamp]=true;
    }
    usort($events,fn($a,$b)=>($a['time']<=>$b['time'])?:strcmp($a['type'],$b['type']));
    if(!$events)throw new RuntimeException('Não há transcrição nem telas selecionadas para analisar.');
    $manifest['times']=array_keys($manifest['times']);
    return [$events,$manifest];
}
function gemini_groups(array $events): array {
    $groups=[];$current=[];$chars=0;$bytes=0;$images=0;
    foreach($events as $event){
        $size=strlen($event['body']);$imageBytes=$event['bytes']??0;$newImage=$event['type']==='screen'?1:0;
        if($current&&($chars+$size>60000||$bytes+$imageBytes>6*1024*1024||$images+$newImage>10)){
            $groups[]=$current;$current=[];$chars=0;$bytes=0;$images=0;
        }
        $current[]=$event;$chars+=$size;$bytes+=$imageBytes;$images+=$newImage;
    }
    if($current)$groups[]=$current;
    return $groups;
}
function gemini_parts(array $job,array $run,array $project,array $events,string $coverage): array {
    $prompt=$job['prompt_snapshot'];
    foreach(['{{TITULO}}','{{CONTEXTO}}','{{OBJETIVO_PROJETO}}','{{COBERTURA_ENVIADA}}','{{TRANSCRICAO}}','{{TELAS}}'] as $placeholder)
        if(!str_contains($prompt,$placeholder))throw new RuntimeException('O prompt salvo precisa conter todos os seis marcadores de material. Restaure o prompt-base e salve novamente.');
    $speech=implode("\n",array_column(array_values(array_filter($events,fn($e)=>$e['type']==='transcript')),'body'));
    [$before,$after]=explode('{{TELAS}}',$prompt,2);
    $values=['{{TITULO}}'=>gemini_xml((string)$run['title']),'{{CONTEXTO}}'=>gemini_xml((string)($run['context']??'')),
        '{{OBJETIVO_PROJETO}}'=>gemini_xml((string)($project['objective']??'')),'{{COBERTURA_ENVIADA}}'=>gemini_xml($coverage),
        '{{TRANSCRICAO}}'=>$speech?:'Nenhuma fala reconhecida neste trecho.'];
    $before=strtr($before,$values);$after=strtr($after,$values);$parts=[['text'=>$before]];
    foreach($events as $event)if($event['type']==='screen'){
        $parts[]=['text'=>$event['body']];
        $data=file_get_contents($event['file']);if($data===false)throw new RuntimeException('Não foi possível ler uma tela selecionada.');
        $parts[]=['inlineData'=>['mimeType'=>'image/jpeg','data'=>base64_encode($data)]];
        $parts[]=['text'=>'</tela>'];
    }
    $parts[]=['text'=>$after];return $parts;
}
function gemini_http(string $key,string $model,string $action,array $body): array {
    if(!preg_match('/^gemini-[a-zA-Z0-9._-]+$/D',$model)||!in_array($action,['countTokens','generateContent'],true))throw new RuntimeException('Modelo Gemini inválido.');
    $url='https://generativelanguage.googleapis.com/v1beta/models/'.$model.':'.$action;
    $payload=json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    if(strlen($payload)>18*1024*1024)throw new RuntimeException('Lote de evidências excede o limite de envio.');
    $curl=curl_init($url);$response='';
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['x-goog-api-key: '.$key,'Content-Type: application/json'],CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>$action==='generateContent'?240:45,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION=>function($handle,$chunk)use(&$response){if(strlen($response)+strlen($chunk)>8*1024*1024)return 0;$response.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if($ok===false)throw new RuntimeException('A conexão com Gemini falhou ou expirou. Tente novamente.');
    if(in_array($code,[400,401,403],true))throw new RuntimeException('O Gemini recusou a requisição. Verifique chave, modelo e acesso à API.');
    if($code===429)throw new RuntimeException('A cota do Gemini foi atingida. Verifique sua conta e tente novamente.');
    if($code>=500)throw new RuntimeException('O Gemini está temporariamente indisponível. Tente novamente.');
    if($code!==200)throw new RuntimeException('O Gemini recusou o material ou o modelo selecionado. Tente outro modelo ou revise o material.');
    $data=json_decode($response,true);if(!is_array($data))throw new RuntimeException('Resposta inválida do Gemini.');
    return $data;
}
function gemini_count(string $key,string $model,array $parts,?callable $transport=null): int {
    $response=($transport??'gemini_http')($key,$model,'countTokens',['contents'=>[['role'=>'user','parts'=>$parts]]]);
    $count=$response['totalTokens']??null;if(!is_int($count)||$count<0)throw new RuntimeException('O Gemini não retornou a contagem de tokens.');return $count;
}
function gemini_generate(string $key,string $model,array $parts,int $outputLimit,?callable $transport=null): string {
    $response=($transport??'gemini_http')($key,$model,'generateContent',[
        'contents'=>[['role'=>'user','parts'=>$parts]],'generationConfig'=>['maxOutputTokens'=>$outputLimit,'temperature'=>0.2]]);
    $candidate=$response['candidates'][0]??null;$finish=$candidate['finishReason']??'';
    if($finish==='MAX_TOKENS')throw new RuntimeException('O relatório excedeu o limite de saída do modelo. Escolha um modelo com maior capacidade.');
    if($finish!=='STOP')throw new RuntimeException('O Gemini não concluiu esta parte da análise ('.$finish.').');
    $text=implode("\n",array_column($candidate['content']['parts']??[],'text'));
    if(trim($text)==='')throw new RuntimeException('O Gemini retornou um relatório vazio.');
    return trim($text);
}
function gemini_check_cancel(string $id): void {
    $status=query('SELECT status FROM ai_analyses WHERE id=?',[$id])->fetchColumn();
    if($status!=='processing')throw new RuntimeException('Análise cancelada.');
}
function gemini_analyze_group(array $job,array $run,array $project,array $events,string $key,array $model,int $number,int $total,?callable $transport): array {
    gemini_check_cancel($job['id']);
    $first=gemini_time((float)$events[0]['time']);$last=gemini_time((float)$events[count($events)-1]['time']);
    $metadata=json_decode($run['metadata']??'{}',true)?:[];
    $duration=isset($metadata['duration'])?' Duração total informada: '.gemini_time((float)$metadata['duration']).'.':'';
    $scope=$total===1?'Todos os segmentos e telas selecionadas dos outputs locais estão neste lote.':'Cobertura parcial dos outputs locais; os demais lotes serão consolidados.';
    $coverage="Parte $number de $total; intervalo de evidências {$first}–{$last}. $scope O relatório final reunirá todas as partes. Esta parte contém ".count(array_filter($events,fn($e)=>$e['type']==='transcript')).' segmentos e '.count(array_filter($events,fn($e)=>$e['type']==='screen')).' telas. Timestamps exibidos ao segundo, a partir dos tempos reais extraídos.'.$duration.' Telas são amostras selecionadas por agrupamento local; vídeo e áudio originais não foram enviados.';
    $parts=gemini_parts($job,$run,$project,$events,$coverage);
    $limit=(int)$model['input_token_limit'];$output=min(8192,(int)$model['output_token_limit']);
    if($limit<=0||$output<1024)throw new RuntimeException('O modelo não informou limites suficientes para análise. Escolha outro modelo.');
    if(gemini_count($key,$job['model'],$parts,$transport)>$limit-$output){
        if(count($events)<2)throw new RuntimeException('Uma evidência não cabe no contexto do modelo. Escolha um modelo maior ou reduza o material.');
        $middle=intdiv(count($events),2);
        return array_merge(gemini_analyze_group($job,$run,$project,array_slice($events,0,$middle),$key,$model,$number,$total,$transport),
            gemini_analyze_group($job,$run,$project,array_slice($events,$middle),$key,$model,$number,$total,$transport));
    }
    return [gemini_generate($key,$job['model'],$parts,$output,$transport)];
}
function gemini_merge(array $reports,string $key,array $job,array $model,?callable $transport,int $depth=0): string {
    if(count($reports)===1)return $reports[0];
    $output=min(8192,(int)$model['output_token_limit']);$limit=(int)$model['input_token_limit'];
    $instruction="Reúna os relatórios parciais abaixo em um único relatório Markdown em português do Brasil. Preserve as 8 seções do prompt original e toda referência verificável. Não crie timestamps, IDs, fatos ou observações visuais; mantenha rótulos Visto/OCR/Dito/Inferência e registre conflitos/lacunas. O material cobre partes do mesmo vídeo; elimine repetições sem descartar conteúdo relevante. Comece por # Relatório:.\n\n";
    $parts=[['text'=>$instruction.implode("\n\n--- RELATÓRIO PARCIAL ---\n\n",$reports)]];
    if(gemini_count($key,$job['model'],$parts,$transport)<=$limit-$output)return gemini_generate($key,$job['model'],$parts,$output,$transport);
    if($depth>=4)throw new RuntimeException('Não foi possível consolidar o relatório no contexto deste modelo. Escolha um modelo maior.');
    if(count($reports)===2){
        $short=[];foreach($reports as $partial){
            $condense=[['text'=>"Condense o relatório parcial, preservando todas as afirmações centrais, rótulos de origem, lacunas e referências existentes. Não invente novas referências. Responda em Markdown.\n\n".$partial]];
            if(gemini_count($key,$job['model'],$condense,$transport)>$limit-$output)throw new RuntimeException('Um relatório parcial excedeu o contexto do modelo. Escolha um modelo maior.');
            $short[]=gemini_generate($key,$job['model'],$condense,max(1024,intdiv($output,2)),$transport);
        }
        return gemini_merge($short,$key,$job,$model,$transport,$depth+1);
    }
    $next=[];foreach(array_chunk($reports,2) as $pair)$next[]=count($pair)===1?$pair[0]:gemini_merge($pair,$key,$job,$model,$transport,$depth+1);
    return gemini_merge($next,$key,$job,$model,$transport,$depth+1);
}
function gemini_warnings(string $report,array $manifest): array {
    $warnings=[];$ids=array_fill_keys(array_merge(array_column($manifest['screens'],'id'),array_column($manifest['screens'],'ocr_id')),true);
    preg_match_all('/\[(?:IMG|OCR)-\d+\]/',$report,$matches);
    foreach(array_unique($matches[0]) as $ref)if(!isset($ids[trim($ref,'[]')]))$warnings[]='Referência de tela não encontrada no material: '.$ref;
    $times=array_fill_keys($manifest['times'],true);preg_match_all('/\b\d{2}:\d{2}:\d{2}\b/',$report,$matches);
    foreach(array_unique($matches[0]) as $time)if(!isset($times[$time]))$warnings[]='Horário não encontrado no material: '.$time;
    return $warnings;
}
function gemini_remove_snapshot(string $folder): void {
    if(!preg_match('#^/data/gemini/[a-f0-9]{32}$#D',$folder)||!is_dir($folder))return;
    foreach(glob($folder.'/*')?:[] as $file)if(is_file($file))unlink($file);
    rmdir($folder);
}
function gemini_execute(array $job,?callable $transport=null): void {
    $run=query('SELECT r.*,v.project_id,v.title,v.context,v.metadata,p.output_directory FROM runs r JOIN videos v ON v.id=r.video_id JOIN projects p ON p.id=v.project_id WHERE r.id=?',[$job['run_id']])->fetch(PDO::FETCH_ASSOC);
    if(!$run)throw new RuntimeException('Execução não encontrada.');
    $project=query('SELECT objective FROM projects WHERE id=?',[$run['project_id']])->fetch(PDO::FETCH_ASSOC);
    $settings=query('SELECT * FROM ai_settings WHERE user_id=?',[$job['user_id']])->fetch(PDO::FETCH_ASSOC);
    if(!$settings||!$settings['validated_at']||!$settings['gemini_key_encrypted']||(int)$settings['key_version']!==(int)$job['key_version'])throw new RuntimeException('A chave Gemini mudou ou não está validada. Teste a conexão e tente novamente.');
    $models=json_decode($settings['available_models'],true);$model=null;foreach($models as $candidate)if($candidate['id']===$job['model'])$model=$candidate;
    if(!$model)throw new RuntimeException('O modelo selecionado não está mais disponível. Teste a conexão novamente.');
    if(!in_array($run['status'],['review','approved'],true)||gemini_stage_versions($run['id'])!=json_decode($job['stage_versions'],true))throw new RuntimeException('As evidências mudaram após o pedido. Inicie outra análise.');
    $key=gemini_open_key($settings['gemini_key_encrypted'],$job['user_id']);
    $snapshot='/data/gemini/'.$job['id'];
    try {
        if(!is_dir('/data/gemini')&&!mkdir('/data/gemini',0700,true)&&!is_dir('/data/gemini'))throw new RuntimeException('Não foi possível preparar o material da análise.');
        if(!mkdir($snapshot,0700))throw new RuntimeException('Não foi possível preparar o material da análise.');
        [$events,$manifest]=gemini_evidence($run,$snapshot);
        $freshStatus=query('SELECT status FROM runs WHERE id=?',[$run['id']])->fetchColumn();
        if(!in_array($freshStatus,['review','approved'],true)||gemini_stage_versions($run['id'])!=json_decode($job['stage_versions'],true))throw new RuntimeException('As evidências mudaram durante a preparação. Inicie outra análise.');
        $groups=gemini_groups($events);$reports=[];
        query('UPDATE ai_analyses SET evidence_manifest=?,progress_total=?,updated_at=now() WHERE id=?',[json_encode($manifest,JSON_THROW_ON_ERROR),count($groups)+1,$job['id']]);
        foreach($groups as $i=>$group){
            $reports=array_merge($reports,gemini_analyze_group($job,$run,$project,$group,$key,$model,$i+1,count($groups),$transport));
            gemini_check_cancel($job['id']);
            query('UPDATE ai_analyses SET progress_done=?,updated_at=now() WHERE id=?',[$i+1,$job['id']]);
        }
        $report=gemini_merge($reports,$key,$job,$model,$transport);gemini_check_cancel($job['id']);
        $warnings=gemini_warnings($report,$manifest);
        $dir=run_output_path($run).'/gemini';if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Não foi possível criar a pasta do relatório.');
        $file=$dir.'/'.$job['id'].'.md';$temp=$file.'.tmp';
        if(file_put_contents($temp,$report."\n",LOCK_EX)===false||!rename($temp,$file))throw new RuntimeException('Não foi possível salvar o relatório.');
        query("UPDATE ai_analyses SET status='completed',result_text=?,warnings=?,progress_done=progress_total,updated_at=now() WHERE id=? AND status='processing'",[$report,json_encode($warnings,JSON_THROW_ON_ERROR),$job['id']]);
    } finally {gemini_remove_snapshot($snapshot);sodium_memzero($key);}
}
