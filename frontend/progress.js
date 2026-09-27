const processingOrder=['audio','transcript','frames','ocr','screens'];
let activeOutputState=null;
function processingStages(r){return typeof r.stages==='string'?JSON.parse(r.stages):r.stages||[];}
function outputEnabled(r,section){if(section==='ai')return true;return !['transcript','screens','files','review','knowledge','ai'].includes(section)||r.outputs?.[section]===true;}
function outputWaitLabel(r,section){
    if(section==='ai')return r.outputs?.ai?'Relatório disponível':'Configurar Gemini e analisar';
    if(outputEnabled(r,section))return 'Disponível';
    const needed=section==='transcript'?['transcript']:section==='screens'?['transcript','frames','ocr','screens']:processingOrder;
    const stages=processingStages(r).filter(s=>needed.includes(s.name));
    if(stages.some(s=>s.status==='failed'))return 'Etapa com falha — veja o processamento';
    if(stages.length===needed.length&&stages.every(s=>s.status==='completed'))return 'Finalizando arquivos';
    if(r.status==='cancelled')return 'Processamento cancelado';
    return stages.some(s=>s.status==='processing')?'Em processamento':'Aguardando processamento';
}
function outputDisabled(r,section){return outputEnabled(r,section)?'':`disabled aria-disabled="true" title="${esc(outputWaitLabel(r,section))}"`;}
function progressView(r){
    if(!r.id&&!r.run_id){
        return r.video_status==='validating'?'<div class="run-progress" role="status"><strong><span class="processing-spinner" aria-hidden="true"></span>Validando vídeo</strong><progress class="progress" aria-label="Validação do vídeo"></progress><p>Verificando o arquivo antes de iniciar a análise.</p></div>':'';
    }
    const stages=processingStages(r),done=stages.filter(s=>s.status==='completed').length;
    const running=processingOrder.find(name=>stages.some(s=>s.name===name&&s.status==='processing'));
    const syncFailed=done===5&&!r.outputs?.screens&&r.outputs?.sync?.status==='failed';
    const failed=r.status==='failed'||syncFailed,cancelled=r.status==='cancelled',queued=r.status==='queued';
    const syncing=done===5&&!r.outputs?.screens&&!failed&&!cancelled;
    const busy=['queued','processing'].includes(r.status)||syncing;
    const title=syncFailed?'Falha ao salvar os resultados':failed?'Processamento interrompido':cancelled?'Processamento cancelado':syncing?'Finalizando arquivos':queued?'Na fila de processamento':done===5?'Processamento concluído':running?stageLabels[running]:'Preparando processamento';
    const detail=syncFailed?'Não foi possível finalizar os arquivos. Consulte o diagnóstico.':failed?'Consulte a etapa com falha e tente novamente.':cancelled?'Você pode retomar as etapas pendentes.':syncing?'Salvando os resultados. Os acessos serão liberados automaticamente.':queued?'Aguardando o processador local. Esta tela é atualizada automaticamente.':done===5?'Transcrição e telas prontas para consulta.':'Acompanhe as etapas abaixo. Cada resultado será liberado quando estiver pronto.';
    return `<section class="run-progress ${failed?'progress-failed':''}" aria-label="Progresso do processamento"><div class="row"><strong>${busy?'<span class="processing-spinner" aria-hidden="true"></span>':''}${esc(title)}</strong><span>${done} de 5 etapas concluídas · ${done*20}% das etapas</span></div><progress class="progress" max="5" value="${done}" aria-label="Etapas concluídas" aria-valuetext="${done} de 5 etapas concluídas"></progress><p role="status">${detail}</p><ol class="processing-steps">${processingOrder.map(name=>{const status=stages.find(s=>s.name===name)?.status||'pending';return `<li class="stage-${status}"><span aria-hidden="true">${status==='completed'?'✓':status==='failed'?'!':status==='processing'&&!cancelled&&!failed?'◉':'○'}</span> ${stageLabels[name]} <small>${status==='completed'?'Concluída':status==='failed'?'Falhou':cancelled?'Pausada':status==='processing'?'Em andamento':'Aguardando'}</small></li>`;}).join('')}</ol></section>`;
}
function applyOutputLocks(r){
    const selector='[data-section],[data-output-open],[data-nav="transcript"],[data-nav="screens"],[data-nav="ai"],#ai-transcript,#ai-screens';
    root.querySelectorAll(selector).forEach(b=>{
        const section=b.dataset.section||b.dataset.outputOpen||b.dataset.nav||(b.id==='ai-transcript'?'transcript':'screens');
        b.disabled=!outputEnabled(r,section);
        b.setAttribute('aria-disabled',String(b.disabled));
        b.title=b.disabled?outputWaitLabel(r,section):'';
    });
}
function runNeedsRefresh(r){if(r.outputs?.sync?.status==='failed'&&!['queued','processing'].includes(r.status))return false;return ['queued','processing'].includes(r.status)||(processingStages(r).some(s=>s.status==='completed')&&!r.outputs?.screens&&!['failed','cancelled'].includes(r.status));}
function startRunRefresh(r,section,host){
    if(!runNeedsRefresh(r))return;
    let inFlight=false;
    timer=setInterval(async()=>{
        if(!host.isConnected||inFlight)return;
        inFlight=true;
        try{
            const next=await api('/runs/'+r.id);
            if(!host.isConnected)return;
            activeOutputState=next.outputs;applyOutputLocks(next);
            const area=host.querySelector('#live-run-progress');if(area)area.innerHTML=progressView(next);
            if(section==='outputs'){
                const home=host.querySelector('#run-outputs-home');home.innerHTML=runOutputsHome(next,Object.fromEntries(next.stages.map(s=>[s.name,s])));bindOutputShortcuts(next);applyOutputLocks(next);
            }
            if(section==='monitor'&&JSON.stringify(next.stages.map(s=>[s.name,s.status,s.error]))!==JSON.stringify(r.stages.map(s=>[s.name,s.status,s.error]))||section==='monitor'&&next.status!==r.status){
                await runView(r.id,section,host);return;
            }
            const status=host.querySelector('#progress-connection');if(status)status.textContent='Atualização automática ativa.';
            if(!runNeedsRefresh(next)){clearInterval(timer);if(status)status.textContent='Resultados atualizados.';}
        }catch(e){if(host.isConnected){const status=host.querySelector('#progress-connection');if(status)status.textContent='Não foi possível atualizar. Tentando reconectar automaticamente…';}}
        finally{inFlight=false;}
    },2500);
}
