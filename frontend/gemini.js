async function geminiSettingsPage(){
    clearInterval(timer);activeRun=null;activeOutputState=null;current=null;page='ai';
    shell('<span class="eyebrow">ANÁLISE POR IA</span><h1>Configurar Gemini</h1><p class="muted">Salve sua chave e revise o prompt. Depois escolha um vídeo em Outputs para gerar o relatório.</p><div id="gemini-panel" role="region" aria-label="Configuração do Gemini">Carregando…</div>');
    await mountGemini(document.querySelector('#gemini-panel'));
}
async function mountGemini(host,r=null,stages={}){
    let settings,job={status:'not_started'},draft,dirty=false,poll=null;
    try{settings=await api('/ai/gemini');draft=settings.prompt;if(r)job=await api(`/runs/${r.id}/ai/gemini`);}
    catch(e){if(host.isConnected){host.innerHTML='<p class="error" role="alert"></p>';host.querySelector('p').textContent=e.message;}return;}
    if(!host.isConnected)return;
    const segments=stages.transcript?.status==='completed'?(stages.transcript.result?.segments||[]):[];
    const blocks=stages.ocr?.status==='completed'?(stages.ocr.result?.blocks||[]):[];
    const selected=new Set((stages.screens?.status==='completed'?(stages.screens.result?.screens||[]):[]).map(s=>s.frame_id));
    const images=(stages.frames?.status==='completed'?(stages.frames.result?.frames||[]):[]).filter(f=>f.included&&selected.has(f.id));
    const el=id=>host.querySelector('#'+id);
    const canAnalyze=()=>!!(r&&r.outputs?.screens&&settings.has_key&&settings.validated_at&&settings.model&&!dirty&&!['queued','processing'].includes(job.status));
    function feedback(message,error=false){const node=el('gemini-feedback');if(node){node.textContent=message;node.className=error?'error':'muted';}}
    function renderJob(){
        if(!host.isConnected)return;
        const status=el('gemini-job');if(!status)return;
        const active=['queued','processing'].includes(job.status);
        const name={queued:'Na fila',processing:'Analisando com Gemini',completed:'Relatório pronto',failed:'A análise falhou',cancelled:'Análise cancelada',not_started:'Nenhuma análise gerada'}[job.status]||job.status;
        status.innerHTML=`<div class="row"><h3>${name}</h3>${active?'<span class="processing-spinner" aria-hidden="true"></span>':''}</div><p id="gemini-job-detail" class="muted" role="status"></p>${active?'<progress id="gemini-job-progress" class="progress" aria-label="Partes analisadas"></progress><button id="gemini-cancel" type="button">Cancelar análise</button>':''}${job.status==='completed'?`<div class="actions"><button id="gemini-copy-report" type="button">Copiar relatório</button><a id="gemini-download-report" class="output-button" href="/api/runs/${r.id}/ai/gemini/${job.id}/download">Baixar Markdown ↓</a></div><h3>Relatório de análise</h3><pre id="gemini-report" class="output-reader" tabindex="0"></pre>`:''}`;
        const detail=el('gemini-job-detail');
        detail.textContent=job.status==='queued'?'Aguardando o processador Gemini.':job.status==='processing'?`Processando partes do material: ${job.progress_done} de ${job.progress_total||'…'}. A página atualiza automaticamente.`:job.status==='failed'?job.error||'Tente novamente.':job.status==='cancelled'?'Você pode iniciar outra análise.':job.status==='completed'?`Modelo: ${job.model}. ${job.warnings?.length?'Revise as referências indicadas abaixo.':'Referências automáticas conferidas; revise o conteúdo antes de usar.'}`:'Prepare a chave e as evidências para começar.';
        if(active){const p=el('gemini-job-progress');if(job.progress_total){p.max=job.progress_total;p.value=job.progress_done;}el('gemini-cancel').onclick=async()=>{try{await api(`/runs/${r.id}/ai/gemini/${job.id}/cancel`,'POST',{});job=await api(`/runs/${r.id}/ai/gemini`);renderJob();}catch(e){feedback(e.message,true);}};}
        if(job.status==='completed'){
            el('gemini-report').textContent=job.report||'';
            if(job.warnings?.length){const note=document.createElement('p');note.className='notice';note.textContent=job.warnings.join(' · ');status.insertBefore(note,el('gemini-report'));}
            el('gemini-copy-report').onclick=async()=>{try{await navigator.clipboard.writeText(job.report);feedback('Relatório copiado.');}catch{feedback('Selecione o texto do relatório para copiar.',true);}};
        }
        const analyze=el('gemini-analyze');if(analyze){analyze.disabled=!canAnalyze();analyze.textContent=active?'Análise em andamento…':job.status==='completed'?'Gerar nova análise com Gemini':'Analisar com Gemini';}
        if(!active&&poll){clearInterval(poll);poll=null;}
    }
    function startPolling(){if(poll||!r||!['queued','processing'].includes(job.status))return;poll=setInterval(async()=>{
        if(!host.isConnected){clearInterval(poll);poll=null;return;}
        try{job=await api(`/runs/${r.id}/ai/gemini`);renderJob();}catch(e){feedback('Conexão interrompida. Tentando acompanhar a análise…',true);}
    },2500);}
    function render(message=''){
        if(!host.isConnected)return;
        host.innerHTML=`<div class="gemini-grid"><section class="card"><span class="eyebrow">1 · SUA CONTA</span><h2>Chave API do Gemini</h2><p class="muted">${settings.has_key?`Chave salva · termina em ${esc(settings.key_suffix)}${settings.validated_at?' · conexão testada em '+date(settings.validated_at):' · teste a conexão'}`:'Nenhuma chave cadastrada.'}</p><form id="gemini-key-form" class="form"><label>${settings.has_key?'Substituir chave':'Sua chave API'}<input type="password" name="api_key" autocomplete="off" spellcheck="false" maxlength="512" placeholder="Cole sua chave do Google AI Studio"></label><p class="muted">A chave fica criptografada no servidor e vinculada à sua conta.</p><div class="actions"><button class="primary">Salvar chave</button><button type="button" id="gemini-test" ${settings.has_key?'':'disabled'}>Testar conexão e listar modelos</button>${settings.has_key?'<button type="button" id="gemini-remove-key">Remover chave</button>':''}</div></form><p class="muted">O teste consulta modelos, sem enviar o conteúdo do vídeo.</p><a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Obter chave no Google AI Studio ↗</a></section>
        <section class="card"><span class="eyebrow">2 · MODELO E EVIDÊNCIAS</span><h2>Preparar análise</h2><label class="gemini-label">Modelo<select id="gemini-model" ${settings.models?.length?'':'disabled'}>${settings.models?.length?settings.models.map(m=>`<option value="${esc(m.id)}" ${m.id===settings.model?'selected':''}>${esc(m.label)} · ${esc(m.id)}</option>`).join(''):'<option>Salve a chave e teste a conexão</option>'}</select></label><p class="muted">Salve a escolha do modelo junto com o prompt.</p><ul class="gemini-material"><li>Transcrição com horários: ${r?segments.length+' trechos':'escolha um vídeo'}.</li><li>Textos OCR: ${r?blocks.length+' blocos':'escolha um vídeo'}.</li><li>Telas selecionadas: ${r?images.length+' imagens':'escolha um vídeo'}.</li><li>Título, contexto e objetivo do projeto.</li></ul><p class="muted">${r?r.outputs?.screens?'Extração local pronta. O envio ao Gemini só começa quando você clicar em “Analisar”.':'Aguarde a extração local e a sincronização dos outputs.':'Abra Análise por IA em um vídeo para gerar o relatório.'}</p><p class="muted">A análise envia esses derivados ao Gemini, sem o arquivo de vídeo ou áudio original. Partes extensas são divididas e consolidadas.</p>${r&&images.length?`<details class="form-details"><summary>Ver telas que serão enviadas (${images.length})</summary><div class="gemini-images">${images.map(f=>`<figure><img loading="lazy" src="/api/runs/${r.id}/frames/${f.id}" alt="Tela em ${timeLabel(f.timestamp)}"><figcaption>${timeLabel(f.timestamp)}</figcaption></figure>`).join('')}</div></details>`:''}</section></div>
        <section class="card gemini-prompt"><span class="eyebrow">3 · INSTRUÇÕES</span><h2>${settings.draft_version?'Seu prompt salvo':'Prompt-base revisado'}</h2><p class="muted">Mantenha os seis marcadores de material. Salvar não inicia a análise.</p><label class="gemini-label" for="gemini-prompt">Prompt enviado ao Gemini</label><textarea id="gemini-prompt" spellcheck="false"></textarea><p id="gemini-draft-state" class="muted">${settings.draft_version?'Versão '+settings.draft_version+' salva.':'Prompt-base pronto.'}</p><div class="actions"><button class="primary" id="gemini-save-draft">Salvar prompt e modelo</button><button id="gemini-copy-prompt">Copiar prompt</button><button id="gemini-default-prompt">Restaurar prompt-base</button></div><p id="gemini-feedback" class="muted" role="status"></p>${r?`<div class="notice">${!settings.has_key?'Salve sua chave e teste a conexão para habilitar a análise.':!settings.validated_at?'Teste a conexão para escolher um modelo.':!r.outputs?.screens?'Aguarde a transcrição e as telas ficarem prontas.':'Pronto para gerar. A API Gemini poderá consumir sua cota.'}</div><button class="primary" id="gemini-analyze">Analisar com Gemini</button><section id="gemini-job" class="card" aria-label="Resultado da análise"></section>`:'<p class="notice">Escolha um vídeo em Outputs para analisar.</p>'}</section>`;
        el('gemini-prompt').value=draft;feedback(message);renderJob();
        async function action(fn,label,saveDraft=false){const wasDirty=dirty;draft=el('gemini-prompt').value;feedback(label);host.querySelectorAll('button').forEach(b=>b.disabled=true);
            try{settings=await fn();dirty=saveDraft?false:wasDirty;render('Configuração atualizada.');}
            catch(e){feedback(e.message,true);renderJob();host.querySelectorAll('button').forEach(b=>{if(b.id!=='gemini-analyze')b.disabled=false;});el('gemini-test').disabled=!settings.has_key;}}
        el('gemini-key-form').onsubmit=e=>{e.preventDefault();const key=e.target.api_key.value.trim();if(!key){feedback('Cole a chave API antes de salvar.',true);return;}action(()=>api('/ai/gemini/key','POST',{api_key:key}),'Salvando chave…');};
        el('gemini-test').onclick=()=>action(()=>api('/ai/gemini/test','POST',{}),'Consultando modelos disponíveis…');
        el('gemini-remove-key')?.addEventListener('click',()=>action(()=>api('/ai/gemini/key','DELETE',{}),'Removendo a chave…'));
        el('gemini-save-draft').onclick=()=>{draft=el('gemini-prompt').value;const model=settings.models?.length?el('gemini-model').value:'';action(async()=>{const result=await api('/ai/gemini/draft','PATCH',{prompt:draft,model});draft=result.prompt;return result;},'Salvando prompt…',true);};
        el('gemini-prompt').oninput=()=>{dirty=true;el('gemini-draft-state').textContent='Alterações não salvas.';renderJob();};
        el('gemini-model').onchange=()=>{dirty=true;el('gemini-draft-state').textContent='Modelo não salvo.';renderJob();};
        el('gemini-default-prompt').onclick=()=>{draft=settings.default_prompt;el('gemini-prompt').value=draft;dirty=true;el('gemini-draft-state').textContent='Prompt-base restaurado no editor. Salve para aplicar.';renderJob();};
        el('gemini-copy-prompt').onclick=async()=>{try{await navigator.clipboard.writeText(el('gemini-prompt').value);feedback('Prompt copiado.');}catch{el('gemini-prompt').select();feedback('Selecione e copie o prompt.');}};
        el('gemini-analyze')?.addEventListener('click',async()=>{if(!canAnalyze())return;el('gemini-analyze').disabled=true;feedback('Enviando análise para a fila…');
            try{job=await api(`/runs/${r.id}/ai/gemini`,'POST',{});feedback('Análise iniciada.');renderJob();startPolling();}
            catch(e){feedback(e.message,true);renderJob();}});
    }
    render();startPolling();
}
