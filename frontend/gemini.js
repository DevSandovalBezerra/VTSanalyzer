async function geminiSettingsPage(returnRunId=null){
    clearInterval(timer);activeRun=null;activeOutputState=null;page='settings';
    if(!returnRunId)current=null;
    shell(`<span class="eyebrow">CONFIGURAÇÃO · SUA CONTA</span><h1>Configurar Gemini</h1><p class="muted">Cadastre a chave e escolha o modelo uma vez. A análise e o relatório de cada vídeo ficam em Outputs → Análise por IA.</p>${returnRunId?'<button id="gemini-return-top" type="button">← Voltar à análise do vídeo</button>':''}<div id="gemini-settings" role="region" aria-label="Configuração do Gemini">Carregando configuração…</div>`);
    if(returnRunId)document.querySelector('#gemini-return-top').onclick=()=>runView(returnRunId,'ai');
    await mountGeminiSettings(document.querySelector('#gemini-settings'),returnRunId);
}

function geminiSettingsMarkup(settings){
    const models=settings.models||[];
    return `<div class="gemini-settings-layout"><section class="card"><span class="eyebrow">1 · ACESSO</span><h2>Chave API</h2><p class="muted">${settings.has_key?`Chave salva · final ${esc(settings.key_suffix)}${settings.validated_at?' · conexão testada':' · teste a conexão'}`:'Nenhuma chave cadastrada.'}</p><form id="gemini-key-form" class="form"><label>${settings.has_key?'Substituir chave':'Sua chave API'}<input type="password" name="api_key" autocomplete="off" spellcheck="false" maxlength="512" placeholder="Cole sua chave do Google AI Studio"></label><div class="actions"><button class="primary">Salvar chave</button><button type="button" id="gemini-test" ${settings.has_key?'':'disabled'}>Testar conexão</button>${settings.has_key?'<button type="button" id="gemini-remove-key">Remover chave</button>':''}</div></form><p class="muted">O teste lista os modelos e comprova o acesso com uma chamada mínima; não envia dados de vídeos. A chave fica criptografada e vinculada à sua conta.</p><a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Obter chave no Google AI Studio ↗</a></section>
    <section class="card"><span class="eyebrow">2 · MODELO</span><h2>Modelo da análise</h2><p class="muted">${settings.validated_at?'Escolha um dos modelos disponíveis para sua chave.':'Salve a chave e teste a conexão para escolher o modelo.'}</p><label class="gemini-label" for="gemini-model">Modelo<select id="gemini-model" ${models.length?'':'disabled'}>${models.length?models.map(m=>`<option value="${esc(m.id)}" ${m.id===settings.model?'selected':''}>${esc(m.label)} · ${esc(m.id)}</option>`).join(''):'<option>Nenhum modelo disponível</option>'}</select></label><button id="gemini-save-model" type="button" ${models.length?'':'disabled'}>Salvar modelo</button><p id="gemini-model-state" class="muted">${settings.model?'Modelo atual: '+esc(settings.model):'Nenhum modelo escolhido.'}</p></section></div>
    <details class="form-details gemini-prompt-settings"><summary>Instruções avançadas · prompt da análise</summary><p class="muted">O prompt-base já está pronto. Edite apenas se quiser mudar as instruções. Preserve os seis marcadores de material.</p><label class="gemini-label" for="gemini-prompt">Prompt enviado ao Gemini</label><textarea id="gemini-prompt" spellcheck="false"></textarea><p id="gemini-draft-state" class="muted">${settings.draft_version?'Versão '+settings.draft_version+' salva.':'Prompt-base pronto para uso.'}</p><div class="actions"><button class="primary" id="gemini-save-draft" type="button">Salvar prompt</button><button id="gemini-copy-prompt" type="button">Copiar prompt</button><button id="gemini-default-prompt" type="button">Restaurar prompt-base</button></div></details><p id="gemini-settings-feedback" class="muted" role="status"></p>`;
}

async function mountGeminiSettings(host,returnRunId=null){
    let settings,draft,dirty=false;
    try{settings=await api('/ai/gemini');draft=settings.prompt;}catch(e){if(host.isConnected){host.innerHTML='<p class="error" role="alert"></p>';host.querySelector('p').textContent=e.message;}return;}
    const el=id=>host.querySelector('#'+id);
    function feedback(message,error=false){const node=el('gemini-settings-feedback');if(node){node.textContent=message;node.className=error?'error':'muted';}}
    function render(message=''){
        if(!host.isConnected)return;
        host.innerHTML=geminiSettingsMarkup(settings);el('gemini-prompt').value=draft;
        if(dirty)el('gemini-draft-state').textContent='Alterações no prompt ainda não salvas.';
        feedback(message);
        async function update(call,busy,success,preserveDraft=true){
            if(preserveDraft){draft=el('gemini-prompt').value;dirty=dirty||draft!==settings.prompt;}
            feedback(busy);host.querySelectorAll('button').forEach(b=>b.disabled=true);
            try{settings=await call();render(success);}catch(e){if(!preserveDraft)dirty=true;render();feedback(e.message,true);}
        }
        el('gemini-key-form').onsubmit=e=>{e.preventDefault();const key=e.target.api_key.value.trim();if(!key){feedback('Cole a chave API antes de salvar.',true);return;}update(()=>api('/ai/gemini/key','POST',{api_key:key}),'Salvando chave…','Chave salva. Teste a conexão para carregar os modelos.');};
        el('gemini-test').onclick=()=>update(()=>api('/ai/gemini/test','POST',{}),'Testando a conexão…','Conexão testada. Modelo compatível selecionado.');
        el('gemini-remove-key')?.addEventListener('click',()=>update(()=>api('/ai/gemini/key','DELETE',{}),'Removendo chave…','Chave removida.'));
        el('gemini-model').onchange=()=>{el('gemini-model-state').textContent='Modelo alterado. Salve para aplicar às próximas análises.';};
        el('gemini-save-model').onclick=()=>{const model=el('gemini-model').value;update(()=>api('/ai/gemini/model','PATCH',{model}),'Salvando modelo…','Modelo salvo para as próximas análises.');};
        el('gemini-prompt').oninput=()=>{dirty=true;el('gemini-draft-state').textContent='Alterações no prompt ainda não salvas.';};
        el('gemini-save-draft').onclick=()=>{draft=el('gemini-prompt').value;update(async()=>{const result=await api('/ai/gemini/draft','PATCH',{prompt:draft});draft=result.prompt;dirty=false;return result;},'Salvando prompt…','Prompt salvo para as próximas análises.',false);};
        el('gemini-default-prompt').onclick=()=>{draft=settings.default_prompt;el('gemini-prompt').value=draft;dirty=true;el('gemini-draft-state').textContent='Prompt-base restaurado no editor. Salve para aplicar.';};
        el('gemini-copy-prompt').onclick=async()=>{try{await navigator.clipboard.writeText(el('gemini-prompt').value);feedback('Prompt copiado.');}catch{el('gemini-prompt').select();feedback('Selecione e copie o prompt.');}};
    }
    render();
}

function geminiAnalysisMarkup(r,settings,job,counts){
    const active=['queued','processing'].includes(job.status),complete=job.status==='completed';
    const ready=r.outputs?.screens&&['review','approved'].includes(r.status);
    const configured=settings.has_key&&settings.validated_at&&settings.model;
    const title=complete?'Relatório pronto':active?'Análise em andamento':job.status==='failed'?'Análise falhou':job.status==='cancelled'?'Análise cancelada':!ready?'Aguardando os resultados locais':!configured?'Configure o Gemini para analisar':'Pronto para analisar';
    const detail=complete?`Gerado com ${esc(job.model)}. Leia o relatório abaixo.`:job.status==='failed'?'Revise o erro abaixo. Você pode tentar novamente ou alterar o modelo em Configurar Gemini.':job.status==='cancelled'?'Você pode iniciar outra análise.':job.status==='queued'?'Sua análise entrou na fila. Esta página atualiza automaticamente.':job.status==='processing'?`Partes analisadas: ${Number(job.progress_done)||0} de ${Number(job.progress_total)||'…'}. Esta página atualiza automaticamente.`:!ready?'A transcrição e as telas precisam terminar antes de iniciar a análise.':!configured?'Cadastre sua chave, teste a conexão e escolha o modelo. Depois volte a este vídeo.':`Transcrição (${counts.segments} trechos), texto das telas (${counts.blocks} blocos) e ${counts.images} imagens serão enviados ao Gemini com o contexto do projeto. O vídeo original não será enviado.`;
    return `<div class="ai-journey" aria-label="Etapas da análise"><span class="${ready?'done':'current'}">1 · Preparar evidências</span><span class="${complete?'done':ready?'current':''}">2 · Analisar com Gemini</span><span class="${complete?'current':''}">3 · Ler relatório</span></div><section class="card ai-analysis"><span class="eyebrow">ANÁLISE DESTE VÍDEO</span><h2>${title}${active?' <span class="processing-spinner" aria-hidden="true"></span>':''}</h2><p class="muted" id="gemini-job-detail" role="status">${detail}</p>${job.status==='failed'?`<p class="error" role="alert">${esc(job.error||'A análise falhou. Tente novamente.')}</p>`:''}${job.status==='cancelled'?'<p class="notice">A análise foi cancelada. Você pode iniciar outra.</p>':''}${active?`<progress id="gemini-job-progress" class="progress" aria-label="Partes analisadas" ${job.progress_total?`max="${Number(job.progress_total)}" value="${Number(job.progress_done)||0}"`:''}></progress><div class="actions"><button id="gemini-cancel" type="button">Cancelar análise</button></div>`:''}${!active&&!complete?`<div class="actions">${!ready?`<button class="primary" id="gemini-monitor" type="button">Acompanhar processamento</button>`:!configured?`<button class="primary" id="gemini-configure" type="button">Configurar Gemini</button>`:`<button class="primary" id="gemini-analyze" type="button">${job.status==='failed'?'Tentar novamente →':'Iniciar análise com Gemini →'}</button>${job.status==='failed'?'<button id="gemini-configure" type="button">Revisar modelo e prompt</button>':''}`}</div>`:''}${complete?`<div class="actions"><button id="gemini-copy-report" type="button">Copiar relatório</button><a class="output-button" href="/api/runs/${r.id}/ai/gemini/${job.id}/download">Baixar Markdown ↓</a></div>${job.warnings?.length?`<details class="notice ai-warnings"><summary>${job.warnings.length===1?'1 referência do relatório precisa de revisão':`${job.warnings.length} referências do relatório precisam de revisão`}</summary><ul>${job.warnings.map(w=>`<li>${esc(w)}</li>`).join('')}</ul></details>`:''}<h3>Relatório</h3><pre id="gemini-report" class="output-reader" tabindex="0"></pre><details class="form-details ai-repeat"><summary>Gerar outra análise</summary><p class="muted">Uma nova execução substitui o relatório mais recente nesta tela. Salve o atual se precisar consultá-lo depois.</p><button id="gemini-analyze" type="button" ${configured&&ready?'':'disabled'}>Gerar nova análise</button>${configured?'':'<button id="gemini-configure" type="button">Configurar Gemini</button>'}</details>`:''}</section><p id="gemini-feedback" class="muted" role="status"></p>`;
}

async function mountGeminiAnalysis(host,r,stages={}){
    let settings,job,poll=null;
    try{[settings,job]=await Promise.all([api('/ai/gemini'),api(`/runs/${r.id}/ai/gemini`)]);}catch(e){if(host.isConnected){host.innerHTML='<p class="error" role="alert"></p>';host.querySelector('p').textContent=e.message;}return;}
    if(!host.isConnected)return;
    const counts={segments:stages.transcript?.result?.segments?.length||0,blocks:stages.ocr?.result?.blocks?.length||0,images:stages.screens?.result?.screens?.length||0};
    const el=id=>host.querySelector('#'+id);
    function feedback(message,error=false){const node=el('gemini-feedback');if(node){node.textContent=message;node.className=error?'error':'muted';}}
    function render(message=''){
        if(!host.isConnected)return;
        host.innerHTML=geminiAnalysisMarkup(r,settings,job,counts);
        feedback(message);
        if(job.status==='completed'){
            el('gemini-report').textContent=job.report||'';
            el('gemini-copy-report').onclick=async()=>{try{await navigator.clipboard.writeText(job.report||'');feedback('Relatório copiado.');}catch{feedback('Selecione o texto do relatório para copiar.',true);}};
        }
        el('gemini-configure')?.addEventListener('click',()=>geminiSettingsPage(r.id));
        el('gemini-monitor')?.addEventListener('click',()=>runView(r.id,'monitor'));
        el('gemini-cancel')?.addEventListener('click',async()=>{try{await api(`/runs/${r.id}/ai/gemini/${job.id}/cancel`,'POST',{});job=await api(`/runs/${r.id}/ai/gemini`);render('Análise cancelada.');}catch(e){feedback(e.message,true);}});
        el('gemini-analyze')?.addEventListener('click',async()=>{el('gemini-analyze').disabled=true;feedback('Enviando análise para a fila…');try{job=await api(`/runs/${r.id}/ai/gemini`,'POST',{});render('Análise iniciada.');startPolling();}catch(e){render();feedback(e.message,true);}});
        if(!['queued','processing'].includes(job.status)&&poll){clearInterval(poll);poll=null;}
    }
    function startPolling(){if(poll||!['queued','processing'].includes(job.status))return;poll=setInterval(async()=>{
        if(!host.isConnected){clearInterval(poll);poll=null;return;}
        try{job=await api(`/runs/${r.id}/ai/gemini`);render();}catch{feedback('Conexão interrompida. Tentando acompanhar a análise…',true);}
    },2500);}
    render();startPolling();
}
