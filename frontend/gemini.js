async function geminiSettingsPage(){
    clearInterval(timer);activeRun=null;activeOutputState=null;current=null;page='ai';
    shell('<span class="eyebrow">ANÁLISE POR IA</span><h1>Configurar Gemini</h1><p class="muted">Conecte sua conta e revise as instruções que orientarão a análise dos vídeos.</p><div id="gemini-panel" role="region" aria-label="Configuração do Gemini">Carregando configuração…</div>');
    await mountGemini(document.querySelector('#gemini-panel'));
}
async function mountGemini(host,r=null,stages={}){
    let settings;
    try{settings=await api('/ai/gemini');}catch(e){if(host.isConnected){host.innerHTML='<p class="error" role="alert"></p>';host.querySelector('p').textContent=e.message;}return;}
    if(!host.isConnected)return;
    let draft=settings.prompt;
    const segments=stages.transcript?.status==='completed'?(stages.transcript.result?.segments||[]):[];
    const blocks=stages.ocr?.status==='completed'?(stages.ocr.result?.blocks||[]):[];
    const frames=stages.frames?.status==='completed'?(stages.frames.result?.frames||[]).filter(f=>f.included):[];
    const screenIds=new Set((stages.screens?.status==='completed'?(stages.screens.result?.screens||[]):[]).map(s=>s.frame_id));
    const images=frames.filter(f=>screenIds.has(f.id));
    function render(message=''){
        if(!host.isConnected)return;
        host.innerHTML=`<div class="gemini-grid"><section class="card"><span class="eyebrow">1 · SUA CONTA</span><h2>Chave API do Gemini</h2><p id="gemini-key-state" class="muted">${settings.has_key?`Chave salva · termina em ${esc(settings.key_suffix)}${settings.validated_at?' · conexão testada em '+date(settings.validated_at):' · ainda não testada'}`:'Nenhuma chave cadastrada.'}</p><form id="gemini-key-form" class="form"><label>${settings.has_key?'Substituir chave':'Sua chave API'}<input type="password" name="api_key" autocomplete="off" spellcheck="false" placeholder="Cole sua chave do Google AI Studio" maxlength="512" aria-describedby="gemini-key-help"></label><p id="gemini-key-help" class="muted">A chave é salva criptografada no servidor e vinculada à sua conta.</p><div class="actions"><button class="primary" id="gemini-save-key">Salvar chave</button><button type="button" id="gemini-test" ${settings.has_key?'':'disabled'}>Testar conexão e listar modelos</button>${settings.has_key?'<button type="button" id="gemini-remove-key">Remover chave salva</button>':''}</div></form><p class="muted">O teste consulta os modelos disponíveis. Não envia vídeo, transcrição ou imagens.</p><a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Obter chave no Google AI Studio ↗</a></section>
        <section class="card"><span class="eyebrow">2 · MODELO E MATERIAL</span><h2>Preparar a análise</h2><label class="gemini-label">Modelo<select id="gemini-model" ${settings.models.length?'':'disabled'}>${settings.models.length?settings.models.map(m=>`<option value="${esc(m.id)}" ${m.id===settings.model?'selected':''}>${esc(m.label)} · ${esc(m.id)}</option>`).join(''):'<option>Salve a chave e teste a conexão</option>'}</select></label><p class="muted">O modelo é escolhido entre os retornados pela sua conta Gemini. Salve a escolha junto com o rascunho.</p><h3>Material proposto para envio</h3><ul class="gemini-material"><li>Transcrição completa, com timestamps${r?' · '+segments.length+' trechos':''}.</li><li>Textos reconhecidos nas telas${r?' · '+blocks.length+' blocos de OCR':''}.</li><li>Imagens das telas selecionadas, com identificadores e tempos${r?' · '+images.length+' imagens':''}.</li><li>Título e contexto do projeto e do vídeo.</li></ul><p class="muted">${r?`Vídeo selecionado: ${esc(r.title)}. ${r.outputs?.screens?'Extração local concluída.':'Aguarde a extração local para preparar todo o material.'}`:'Ao abrir Análise por IA dentro de um vídeo, você verá a quantidade de trechos, textos e imagens disponíveis.'}</p><p class="muted">Proposta: enviar os dados extraídos, sem o vídeo e o áudio originais. Antes da execução, o material deverá ser conferido e dividido em partes se exceder o limite do modelo.</p>${r&&images.length?`<details class="form-details"><summary>Conferir imagens propostas (${images.length})</summary><div class="gemini-images">${images.map(f=>`<figure><img loading="lazy" src="/api/runs/${r.id}/frames/${f.id}" alt="Tela em ${timeLabel(f.timestamp)}"><figcaption>${timeLabel(f.timestamp)}</figcaption></figure>`).join('')}</div></details>`:''}</section></div>
        <section class="card gemini-prompt"><div class="row"><div><span class="eyebrow">3 · INSTRUÇÕES DE ANÁLISE</span><h2>${settings.draft_version?'Seu rascunho para análise':'Prompt-base para análise'}</h2></div><span class="badge">${settings.draft_version?'Rascunho salvo · execução pendente':'Prompt-base definido · execução pendente'}</span></div><p class="muted">O prompt-base foi revisado. Você pode ajustar uma cópia para sua conta; salvar o rascunho não inicia uma análise.</p><label class="gemini-label" for="gemini-prompt">Instruções para o Gemini</label><textarea id="gemini-prompt" spellcheck="false"></textarea><p id="gemini-draft-state" class="muted">${settings.draft_version?'Rascunho salvo · versão '+settings.draft_version:'Prompt-base disponível.'}</p><div class="actions"><button class="primary" id="gemini-save-draft">Salvar rascunho e modelo</button><button id="gemini-copy-prompt">Copiar prompt</button><button id="gemini-default-prompt">Restaurar proposta inicial</button></div><p id="gemini-feedback" class="muted" role="status"></p><div class="notice"><strong>Análise ainda não habilitada.</strong> O envio de material e a geração ainda estão em implementação. Nenhum conteúdo do vídeo foi enviado ao Gemini por esta configuração.</div><button id="gemini-analyze" disabled title="Execução ainda não implementada">Analisar com Gemini · em implementação</button></section>`;
        const el=id=>host.querySelector('#'+id);el('gemini-prompt').value=draft;el('gemini-feedback').textContent=message;
        const feedback=(text,error=false)=>{el('gemini-feedback').textContent=text;el('gemini-feedback').className=error?'error':'muted';};
        const busy=(value)=>{host.querySelectorAll('button').forEach(b=>b.disabled=value||b.id==='gemini-analyze'||b.id==='gemini-test'&&!settings.has_key);};
        async function action(fn,label){
            draft=el('gemini-prompt').value;busy(true);feedback(label);
            try{settings=await fn();render('Configuração atualizada. A análise com Gemini ainda não foi implementada.');}
            catch(e){if(host.isConnected){feedback(e.message,true);busy(false);}}
        }
        el('gemini-key-form').onsubmit=async e=>{
            e.preventDefault();const key=e.target.api_key.value.trim();
            if(!key){feedback('Cole sua chave API antes de salvar.',true);return;}
            await action(async()=>{const result=await api('/ai/gemini/key','POST',{api_key:key});return result;},'Salvando chave…');
        };
        el('gemini-test').onclick=()=>action(()=>api('/ai/gemini/test','POST',{}),'Testando conexão e consultando modelos…');
        el('gemini-remove-key')?.addEventListener('click',()=>action(()=>api('/ai/gemini/key','DELETE',{}),'Removendo a chave desta aplicação…'));
        el('gemini-save-draft').onclick=()=>{const model=settings.models.length?el('gemini-model').value:'';return action(async()=>{const result=await api('/ai/gemini/draft','PATCH',{prompt:draft,model});draft=result.prompt;return result;},'Salvando rascunho…');};
        el('gemini-prompt').oninput=()=>{el('gemini-draft-state').textContent='Alterações ainda não salvas. O seu rascunho ainda não foi salvo.';};
        el('gemini-model').onchange=()=>{el('gemini-draft-state').textContent='Modelo alterado. Salve o rascunho para guardar a escolha.';};
        el('gemini-default-prompt').onclick=()=>{draft=settings.default_prompt;el('gemini-prompt').value=draft;el('gemini-draft-state').textContent='Proposta inicial restaurada no editor. Salve para guardar a alteração.';};
        el('gemini-copy-prompt').onclick=async()=>{try{await navigator.clipboard.writeText(el('gemini-prompt').value);feedback('Prompt copiado para revisão.');}catch{el('gemini-prompt').focus();el('gemini-prompt').select();feedback('Selecione e copie o texto do prompt.');}};
    }
    render();
}
