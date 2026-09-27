<?php
// Readiness belongs to the completed stage and its synchronized output version.
function output_stage_map(array $stages): array {
    $map=[];foreach($stages as $stage)$map[$stage['name']]=$stage;return $map;
}
function output_stage_file_ready(array $run,array $stages,string $stage,string $relative): bool {
    $s=$stages[$stage]??null;
    if(!$s||$s['status']!=='completed'||empty($run['output_directory']))return false;
    $folder=run_output_path($run);$file=$folder.'/'.$relative;
    if(!is_file($file))return false;
    static $markers=[];
    if(!array_key_exists($folder,$markers)){
        $marker=$folder.'/.readiness.json';
        $markers[$folder]=is_file($marker)?json_decode(file_get_contents($marker),true):null;
    }
    if($markers[$folder]!==null){
        $saved=$markers[$folder]['stages'][$stage]??null;
        return $saved && $saved['status']==='completed' &&
            new DateTimeImmutable($saved['updated_at'])==new DateTimeImmutable($s['updated_at']);
    }
    // Compatibility for outputs created before version markers were introduced.
    $reference=$stage==='audio'?$folder.'/audio.json':($stage==='frames'?$folder.'/frames.json':$file);
    return is_file($reference)&&filemtime($reference)>=strtotime($s['updated_at']);
}
function run_output_availability(array $run,array $stages): array {
    $map=output_stage_map($stages);$ready=[];
    foreach(['audio'=>'audio.json','transcript'=>'transcricao/transcricao.txt','frames'=>'frames.json','ocr'=>'ocr.json','screens'=>'telas.json'] as $name=>$file)
        $ready[$name]=output_stage_file_ready($run,$map,$name,$file);
    return ['transcript'=>$ready['transcript'],
        'screens'=>$ready['screens']&&$ready['frames']&&$ready['ocr']&&$ready['transcript'],
        'files'=>in_array(true,$ready,true),'review'=>$ready['transcript']&&$ready['frames'],
        'knowledge'=>!in_array(false,$ready,true),'ai'=>(bool)query("SELECT 1 FROM ai_analyses WHERE run_id=? AND status='completed' LIMIT 1",[$run['id']??$run['run_id']])->fetchColumn(),
        'sync'=>query("SELECT status,error FROM jobs WHERE project_id=? AND kind='outputs' ORDER BY created_at DESC LIMIT 1",[$run['project_id']])->fetch(PDO::FETCH_ASSOC)?:null];
}
function output_document_stage(string $key): ?string {
    if(str_starts_with($key,'transcript-'))return 'transcript';
    return in_array($key,['audio','frames','ocr','screens'],true)?$key:null;
}
