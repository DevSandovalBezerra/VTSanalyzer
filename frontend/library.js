const outputSections={outputs:'Outputs',transcript:'Transcrição',screens:'Telas principais',ai:'Análise por IA'};
const outputDescription={outputs:'Tudo o que foi extraído dos seus vídeos, em um só lugar.',transcript:'Escolha um vídeo para ler, copiar ou baixar a transcrição.',screens:'Escolha um vídeo para ver as telas, o texto reconhecido e as falas de cada trecho.',ai:'Análise do conteúdo, do assunto e dos pontos principais do vídeo.'};

async function outputLibrary(section='outputs'){
    activeRun=null;
    clearInterval(timer);let [rows,items]=await Promise.all([api('/outputs'),api('/projects')]);
    projects=items;
    shell(`<div class="row"><div><span class="eyebrow">SEUS RESULTADOS</span><h1>${outputSections[section]}</h1><p class="muted">${outputDescription[section]}</p></div><button class="primary" id="library-upload">＋ Enviar vídeo</button></div>
        <div class="output-steps"><span>1. Envie o vídeo</span><span>2. Inicie o processamento</span><strong>3. Leia os outputs aqui</strong></div>
        ${section==='ai'?'<div class="notice"><strong>Análise automática por IA ainda não ativada.</strong> A transcrição e as telas já podem ser consultadas. A conexão com o provedor de IA precisa ser configurada.</div>':''}
        <div class="library-filters"><label>Buscar vídeo<input type="search" id="library-search" placeholder="Nome do vídeo ou do projeto"></label><label>Projeto<select id="library-project"><option value="">Todos os projetos</option>${items.map(p=>`<option value="${p.id}">${esc(p.name)}${p.status==='archived'?' (arquivado)':''}</option>`).join('')}</select></label><button id="library-refresh">Atualizar</button></div><p id="library-count" class="muted" role="status"></p><p id="library-connection" class="muted" role="status">Atualização automática ativa.</p><div id="library-results" class="library-results"></div>`);
    document.querySelector('#library-upload').onclick=chooseUploadProject;
    document.querySelector('#library-refresh').onclick=()=>refresh();
    const libraryHost=document.querySelector('#library-results');
    function render(){
        if(!libraryHost.isConnected)return;
        const q=document.querySelector('#library-search').value.trim().toLocaleLowerCase('pt-BR');
        const pid=document.querySelector('#library-project').value;
        const filtered=rows.filter(r=>(!pid||r.project_id===pid)&&(!q||`${r.title} ${r.original_name} ${r.project_name}`.toLocaleLowerCase('pt-BR').includes(q)));
        document.querySelector('#library-count').textContent=`${filtered.length} ${filtered.length===1?'resultado':'resultados'}`;
        document.querySelector('#library-results').innerHTML=filtered.map(r=>{
            const stages=Object.fromEntries((typeof r.stages==='string'?JSON.parse(r.stages):r.stages||[]).map(s=>[s.name,s.status]));
            const available=name=>outputWaitLabel(r,name);
            return `<article class="card library-card"><div class="row"><div><span class="eyebrow">${esc(r.project_name)}${r.project_status==='archived'?' · ARQUIVADO':''}</span><h2>${esc(r.title)}</h2><p class="muted">${esc(r.original_name)}${r.run_id?` · versão ${r.version} · ${date(r.created_at)}`:''}</p></div><span class="badge">${stateLabels[r.status||r.video_status]||esc(r.status||r.video_status)}</span></div>
            ${progressView(r)}${r.error||r.video_error?`<p class="error">${esc(r.error||r.video_error)}</p>`:''}
            ${r.run_id?`<div class="output-cards compact"><button data-output-run="${r.run_id}" data-output-section="transcript" ${outputDisabled(r,'transcript')} class="${section==='transcript'?'selected':''}"><strong>Transcrição</strong><span>${available('transcript')}</span></button><button data-output-run="${r.run_id}" data-output-section="screens" ${outputDisabled(r,'screens')} class="${section==='screens'?'selected':''}"><strong>Telas principais</strong><span>${available('screens')} · imagens e falas</span></button><button data-output-run="${r.run_id}" data-output-section="ai" ${outputDisabled(r,'ai')} class="${section==='ai'?'selected':''}"><strong>Análise por IA</strong><span>Configurar Gemini · prompt em revisão</span></button></div><div class="actions library-actions"><button data-output-run="${r.run_id}" data-output-section="files" ${outputDisabled(r,'files')}>Todos os arquivos</button><button data-output-run="${r.run_id}" data-output-section="monitor">Ver processamento</button><button data-library-project="${r.project_id}">Abrir projeto</button></div>`:`<p class="muted">${r.video_status==='ready'?'Vídeo pronto. Inicie o processamento para gerar a transcrição e as telas.':r.video_status==='uploading'?'O envio está incompleto. Continue para gerar os outputs.':r.video_status==='invalid'?'O arquivo não pôde ser processado. Abra o projeto para enviar outro vídeo.':'O vídeo está sendo validado. Atualize para acompanhar.'}</p><button class="primary" data-library-video="${r.video_id}" data-project-id="${r.project_id}">${r.video_status==='ready'?'Iniciar processamento':r.video_status==='uploading'?'Retomar envio':'Abrir projeto'}</button>`}</article>`;
        }).join('')||`<div class="empty"><h2>${rows.length?'Nenhum vídeo encontrado':'Seus outputs vão aparecer aqui'}</h2><p class="muted">${rows.length?'Ajuste a busca ou o filtro de projeto.':'Envie um vídeo e inicie o processamento para consultar transcrição, telas e arquivos.'}</p></div>`;
        root.querySelectorAll('[data-output-run]').forEach(b=>b.onclick=()=>runView(b.dataset.outputRun,b.dataset.outputSection));
        root.querySelectorAll('[data-library-project]').forEach(b=>b.onclick=()=>openProject(b.dataset.libraryProject));
        root.querySelectorAll('[data-library-video]').forEach(b=>b.onclick=async()=>{
            current=await api('/projects/'+b.dataset.projectId);
            const videos=await api('/projects/'+current.id+'/videos');const v=videos.find(v=>v.id===b.dataset.libraryVideo);
            if(v.status==='ready')analysisForm(v);else if(v.status==='uploading')uploadForm(current,v);else await openProject(current.id);
        });
    }
    document.querySelector('#library-search').oninput=render;
    document.querySelector('#library-project').onchange=render;
    render();
    let refreshing=false;
    async function refresh(){
        if(refreshing||!libraryHost.isConnected)return;refreshing=true;
        try{const next=await api('/outputs');if(!libraryHost.isConnected)return;if(JSON.stringify(next)!==JSON.stringify(rows)){rows=next;render();}document.querySelector('#library-connection').textContent='Atualizado agora. Acompanhamento automático ativo.';}
        catch(e){if(libraryHost.isConnected)document.querySelector('#library-connection').textContent='Conexão interrompida. Tentando atualizar automaticamente…';}
        finally{refreshing=false;}
    }
    timer=setInterval(refresh,2500);
}

function chooseUploadProject(){
    const active=projects.filter(p=>p.status==='active');
    if(!active.length){projectForm();return;}
    if(active.length===1){current=active[0];uploadForm(current);return;}
    const dialog=document.createElement('dialog');
    dialog.innerHTML=`<form class="form"><h2>Enviar vídeo</h2><label>Projeto<select name="project">${active.map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></label><div class="actions"><button class="primary">Escolher vídeo</button><button type="button" id="upload-new-project">Novo projeto</button><button type="button" id="upload-close">Cancelar</button></div></form>`;
    root.append(dialog);dialog.onclose=()=>dialog.remove();
    dialog.querySelector('#upload-close').onclick=()=>dialog.close();
    dialog.querySelector('#upload-new-project').onclick=()=>{dialog.close();projectForm();};
    dialog.querySelector('form').onsubmit=e=>{e.preventDefault();current=active.find(p=>p.id===e.target.project.value);dialog.close();uploadForm(current);};
    dialog.showModal();
}

function runOutputsHome(r,stages){
    const status=name=>outputWaitLabel(r,name);
    return `<h2>O que você quer ver?</h2><div class="output-cards"><button data-output-open="transcript" ${outputDisabled(r,'transcript')}><span class="eyebrow">${status('transcript')}</span><strong>Transcrição</strong><span>Leia tudo o que foi falado. Copie o texto ou baixe as legendas.</span></button><button data-output-open="screens" ${outputDisabled(r,'screens')}><span class="eyebrow">${status('screens')}</span><strong>Telas principais</strong><span>Veja as imagens, o texto da tela e a transcrição de cada trecho.</span></button><button data-output-open="ai" ${outputDisabled(r,'ai')}><span class="eyebrow">Configuração e revisão</span><strong>Análise por IA</strong><span>Configure sua chave Gemini e revise o prompt para analisar o conteúdo do vídeo.</span></button></div><div class="actions library-actions"><button data-output-open="files" ${outputDisabled(r,'files')}>Todos os arquivos e downloads</button><button data-output-open="monitor">Acompanhar processamento</button><button id="back-library">Ver todos os vídeos</button></div>`;
}
function bindOutputShortcuts(r){
    root.querySelectorAll('[data-output-open]').forEach(b=>b.onclick=()=>runView(r.id,b.dataset.outputOpen));
    document.querySelector('#back-library').onclick=()=>{current=null;activeRun=null;navigate('outputs');};
}

function aiOutputView(){return '<div id="gemini-panel" role="region" aria-label="Configuração do Gemini">Carregando configuração…</div>';}
async function bindAiOutput(r,stages){await mountGemini(document.querySelector('#gemini-panel'),r,stages);}
