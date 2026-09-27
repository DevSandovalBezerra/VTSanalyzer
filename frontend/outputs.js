function outputsView(){
    return `<section id="output-browser" aria-label="Outputs da análise"><div class="empty" role="status">Carregando os arquivos da análise…</div></section>`;
}

async function bindOutputs(r,stages,section='files'){
    const host=document.querySelector('#output-browser');
    let catalog;
    try{catalog=await api(`/runs/${r.id}/outputs`);}catch(e){if(host.isConnected)host.innerHTML=`<p class="error" role="alert">${esc(e.message)}</p><button id="outputs-retry">Tentar novamente</button>`;host.querySelector('#outputs-retry')?.addEventListener('click',()=>bindOutputs(r,stages,section));return;}
    if(!host.isConnected)return;
    const detected=stages.screens?.status==='completed'?(stages.screens.result?.screens||[]):[];
    const screenIds=new Set(detected.map(s=>s.frame_id));
    const allFrames=catalog.frames.filter(f=>section!=='screens'||!screenIds.size||screenIds.has(f.id)).sort((a,b)=>a.timestamp-b.timestamp);
    const blocks=stages.ocr?.status==='completed'?(stages.ocr.result?.blocks||[]):[];
    const frameText=new Map();
    for(const b of blocks)frameText.set(b.frame_id,((frameText.get(b.frame_id)||'')+' '+b.text).trim());
    const segments=stages.transcript?.status==='completed'?(stages.transcript.result?.segments||[]):[];
    const bytes=n=>n<1024?`${n} B`:n<1048576?`${(n/1024).toFixed(1)} KB`:`${(n/1048576).toFixed(1)} MB`;
    const stamp=s=>{const ms=Math.round(s*1000);return `${String(Math.floor(ms/60000)).padStart(2,'0')}:${String(Math.floor(ms/1000)%60).padStart(2,'0')}.${String(ms%1000).padStart(3,'0')}`;};
    const frameUrl=f=>`/api/runs/${r.id}/frames/${f.id}`;
    const docUrl=f=>`/api/runs/${r.id}/outputs/text?file=${encodeURIComponent(f.key)}`;
    let shown=[],selected=null,activeFile=null,documentContent='',documentLoaded=false,documentSequence=0,zoom='fit';
    host.innerHTML=`<div class="output-heading row"><div><span class="eyebrow">BIBLIOTECA DA ANÁLISE</span><h2>${section==='transcript'?'Transcrição':section==='screens'?'Telas principais e suas transcrições':'Todos os arquivos'}</h2><p class="muted">${section==='transcript'?'Leia, copie ou baixe as falas do vídeo.':section==='screens'?`${allFrames.length} telas extraídas · texto reconhecido e falas de cada trecho`:`${allFrames.length} frames · ${catalog.files.length} documentos`} · versão ${r.version}</p></div><button id="outputs-refresh">↻ Atualizar outputs</button></div>
        ${catalog.sync?.status==='failed'?'<p class="notice" role="status">A atualização dos arquivos falhou. Os arquivos disponíveis podem estar desatualizados.</p>':['queued','processing'].includes(catalog.sync?.status)?'<p class="notice" role="status">Os arquivos estão sendo atualizados. Use “Atualizar outputs” após o processamento.</p>':''}
        <div class="output-switch" role="group" aria-label="Tipo de output"><button id="outputs-frames" aria-pressed="true">▧ Frames <span>${allFrames.length}</span></button><button id="outputs-text" aria-pressed="false">≡ Textos <span>${catalog.files.length}</span></button></div>
        <div id="output-frame-panel"><div class="output-toolbar"><label class="output-search">Buscar nos frames<input type="search" id="output-frame-search" placeholder="Texto reconhecido ou tempo, ex.: 00:05"></label><label class="output-checkbox"><input type="checkbox" id="output-duplicates"> Incluir frames semelhantes</label><span id="output-frame-count" class="muted" role="status"></span></div>
        <div class="output-frame-layout"><div class="output-canvas-card"><div class="output-frame-nav"><button id="output-previous" aria-label="Frame anterior">←</button><span id="output-frame-position" aria-live="polite"></span><button id="output-next" aria-label="Próximo frame">→</button><label>Zoom <select id="output-zoom"><option value="fit">Ajustar</option><option value="100">100%</option><option value="150">150%</option><option value="200">200%</option></select></label></div><div class="output-canvas" id="output-canvas"></div><div class="output-frame-meta" id="output-frame-meta"></div></div><aside class="output-context card"><span class="eyebrow">NO FRAME SELECIONADO</span><h3>Texto da tela</h3><div id="output-frame-ocr" class="output-prose"></div><h3>${section==='screens'?'Transcrição deste trecho':'Fala no momento'}</h3><div id="output-frame-speech" class="output-prose muted"></div></aside></div>
        <div id="output-thumbnails" class="output-thumbnails" aria-label="Galeria de frames"></div></div>
        <div id="output-text-panel" hidden><div class="output-text-layout"><nav id="output-files" class="output-files" aria-label="Arquivos de texto"></nav><section class="output-document card" aria-label="Leitor de texto"><div class="row"><div><h3 id="output-document-title">Selecione um documento</h3><p id="output-document-meta" class="muted"></p></div><div id="output-document-actions" class="actions" hidden><button id="output-copy">Copiar texto</button><a id="output-download" class="output-button" download>Baixar arquivo</a></div></div><div class="output-reader-tools"><label>Buscar neste documento<input type="search" id="output-text-search" placeholder="Digite uma palavra ou expressão"></label><label id="output-mode-label">Visualização<select id="output-reader-mode"><option value="read">Leitura</option><option value="raw">Original</option></select></label></div><p id="output-search-result" class="muted" role="status"></p><p id="output-truncated" class="notice" hidden>A prévia está limitada a 2 MB. Baixe o arquivo para consultar o conteúdo completo.</p><div id="output-text-loading" role="status"></div><pre id="output-reader" class="output-reader" tabindex="0" aria-label="Conteúdo do documento"></pre></section></div></div>
        ${catalog.audio?`<details class="output-audio"><summary>Ouvir áudio extraído</summary><audio controls preload="none" src="/api/runs/${r.id}/outputs/audio"></audio></details>`:''}`;
    const el=id=>host.querySelector('#'+id);
    function switchPanel(text){el('output-frame-panel').hidden=text;el('output-text-panel').hidden=!text;el('outputs-frames').setAttribute('aria-pressed',String(!text));el('outputs-text').setAttribute('aria-pressed',String(text));if(text&&!activeFile&&catalog.files.length)loadDocument(catalog.files[0]);}
    el('outputs-frames').onclick=()=>switchPanel(false);el('outputs-text').onclick=()=>switchPanel(true);
    el('outputs-refresh').onclick=()=>runView(r.id,section);
    function renderFrame(){
        const i=shown.findIndex(f=>f.id===selected);const frame=shown[i];
        el('output-previous').disabled=i<=0;el('output-next').disabled=i<0||i===shown.length-1;el('output-zoom').disabled=!frame;
        el('output-frame-position').textContent=frame?`${i+1} de ${shown.length} · ${stamp(frame.timestamp)}`:'Nenhum frame';
        el('output-canvas').innerHTML=frame?`<img id="output-main-image" src="${frameUrl(frame)}" alt="Frame capturado em ${stamp(frame.timestamp)}" class="${zoom==='fit'?'fit':''}">`:`<p>${allFrames.length?'Nenhum frame corresponde ao filtro.':'Os frames aparecerão aqui após a etapa de captura.'}</p>`;
        const image=el('output-main-image');if(image){if(zoom!=='fit')image.width=Math.round(frame.width*Number(zoom)/100);image.onerror=()=>{if(image.isConnected)el('output-canvas').textContent='Este frame está indisponível. Atualize os outputs.';};}
        el('output-frame-meta').innerHTML=frame?`<span>${frame.width} × ${frame.height} · ${frame.capture==='scene'?'Mudança de cena':'Captura periódica'} ${frame.redactions?.length?'· Região ocultada':''} ${!frame.included?'· Semelhante a outro frame':''}</span><a href="${frameUrl(frame)}" download="frame-${frame.id}.jpg">Baixar frame ↓</a>`:'';
        el('output-frame-ocr').textContent=frame?(frameText.get(frame.id)||'Nenhum texto reconhecido neste frame.'):'Selecione um frame.';
        el('output-frame-speech').textContent=frame?(segments.filter(s=>section==='screens'?s.end>=frame.timestamp&&s.start<(allFrames[allFrames.findIndex(f=>f.id===frame.id)+1]?.timestamp??Infinity):s.start<=frame.timestamp&&s.end>=frame.timestamp).map(s=>s.text).join('\n')||'Nenhuma fala reconhecida neste trecho.'):'—';
        host.querySelectorAll('[data-output-frame]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.outputFrame===selected)));
    }
    function filterFrames(){
        const q=el('output-frame-search').value.trim().toLocaleLowerCase('pt-BR');
        shown=allFrames.filter(f=>(el('output-duplicates').checked||f.included)&&(!q||stamp(f.timestamp).includes(q)||(frameText.get(f.id)||'').toLocaleLowerCase('pt-BR').includes(q)));
        if(!shown.some(f=>f.id===selected))selected=shown[0]?.id;
        el('output-frame-count').textContent=`${shown.length} de ${allFrames.length} frames`;
        el('output-thumbnails').innerHTML=shown.map(f=>`<button data-output-frame="${f.id}" aria-pressed="${f.id===selected}" aria-label="Ver frame em ${stamp(f.timestamp)}"><img src="${frameUrl(f)}" alt="" loading="lazy"><span>${stamp(f.timestamp)}</span>${f.redactions?.length?'<small>Ocultação aplicada</small>':''}</button>`).join('');
        host.querySelectorAll('[data-output-frame]').forEach(b=>b.onclick=()=>{selected=b.dataset.outputFrame;renderFrame();});renderFrame();
    }
    el('output-frame-search').oninput=filterFrames;el('output-duplicates').onchange=filterFrames;
    function step(delta){const i=shown.findIndex(f=>f.id===selected);if(shown[i+delta]){selected=shown[i+delta].id;renderFrame();}}
    el('output-previous').onclick=()=>step(-1);el('output-next').onclick=()=>step(1);
    el('output-zoom').onchange=e=>{zoom=e.target.value;renderFrame();};filterFrames();

    function readable(content){
        if(activeFile?.format!=='json'||el('output-reader-mode').value==='raw')return content;
        let data;try{data=JSON.parse(content);}catch{return content;}
        if(data===null)return 'Resultado ainda indisponível.';
        if(activeFile.key==='ocr')return data.blocks?.map(b=>`[${stamp(b.timestamp)}] ${b.text}`).join('\n\n')||'Nenhum texto reconhecido.';
        if(activeFile.key==='screens')return data.screens?.map(s=>`${s.name}\n${stamp(s.timestamp)} · ${s.classification}\n${s.note||''}`).join('\n\n')||'Nenhuma tela identificada.';
        if(activeFile.key==='transcript-json')return data.segments?.map(s=>`[${stamp(s.start)} → ${stamp(s.end)}]\n${s.text}`).join('\n\n')||data.reason||'Sem falas reconhecidas.';
        if(activeFile.key==='analysis')return `ANÁLISE · VERSÃO ${data.run?.version}\nEstado: ${stateLabels[data.run?.status]||data.run?.status}\n\n${data.aviso||''}\n\nETAPAS\n${(data.etapas||[]).map(s=>`${stageLabels[s.name]||s.name}: ${stateLabels[s.status]||s.status}${s.error?' — '+s.error:''}`).join('\n')}\n\nCONCLUSÕES\n${(data.conclusoes||[]).map(c=>`${c.title}\n${c.description}\n${c.classification} · ${stateLabels[c.review_status]||c.review_status}`).join('\n\n')||'Nenhuma conclusão registrada.'}`;
        return JSON.stringify(data,null,2);
    }
    function renderText(){
        if(!documentLoaded)return;
        const text=readable(documentContent);const q=el('output-text-search').value.trim();
        el('output-reader').classList.toggle('raw',activeFile?.format==='json'&&el('output-reader-mode').value==='raw');
        if(!text){el('output-reader').textContent='Arquivo vazio.';el('output-search-result').textContent='';return;}
        if(!q){el('output-reader').textContent=text;el('output-search-result').textContent='';return;}
        const pattern=new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'giu');let last=0,count=0,html='';
        for(const match of text.matchAll(pattern)){html+=esc(text.slice(last,match.index))+'<mark>'+esc(match[0])+'</mark>';last=match.index+match[0].length;count++;if(count>=5000)break;}
        html+=esc(text.slice(last));el('output-reader').innerHTML=html;el('output-search-result').textContent=`${count}${count>=5000?'+':''} ocorrência${count===1?'':'s'}`;
    }
    async function loadDocument(file){
        const sequence=++documentSequence;activeFile=file;documentContent='';documentLoaded=false;el('output-reader').textContent='';el('output-document-title').textContent=file.label;el('output-document-meta').textContent=`${file.path} · ${bytes(file.bytes)} · ${date(file.modified)}`;
        el('output-document-actions').hidden=true;el('output-truncated').hidden=true;el('output-text-search').value='';el('output-search-result').textContent='';el('output-text-loading').textContent='Carregando documento…';el('output-mode-label').hidden=file.format!=='json';
        host.querySelectorAll('[data-output-file]').forEach(b=>b.setAttribute('aria-current',String(b.dataset.outputFile===file.key)));
        try{const result=await api(`/runs/${r.id}/outputs/text?file=${encodeURIComponent(file.key)}`);if(sequence!==documentSequence||!host.isConnected)return;documentContent=result.content;documentLoaded=true;el('output-truncated').hidden=!result.truncated;el('output-document-actions').hidden=false;el('output-download').href=docUrl(file)+'&download=1';el('output-text-loading').textContent='';renderText();}catch(e){if(sequence===documentSequence&&host.isConnected)el('output-text-loading').textContent=e.message;}
    }
    el('output-files').innerHTML=catalog.files.filter(f=>section!=='transcript'||f.key.startsWith('transcript-')).map(f=>`<button data-output-file="${esc(f.key)}"><span>${esc(f.label)}</span><small>${esc(f.format.toUpperCase())} · ${bytes(f.bytes)}</small></button>`).join('')||'<p class="muted">Os documentos aparecerão após a geração dos outputs. Atualize para verificar.</p>';
    host.querySelectorAll('[data-output-file]').forEach(b=>b.onclick=()=>loadDocument(catalog.files.find(f=>f.key===b.dataset.outputFile)));
    el('output-text-search').oninput=renderText;el('output-reader-mode').onchange=renderText;
    if(section==='screens'){host.querySelector('.output-switch').hidden=true;el('output-duplicates').closest('label').hidden=true;}
    if(section==='transcript'){
        host.querySelector('.output-switch').hidden=true;el('output-frame-panel').hidden=true;el('output-text-panel').hidden=false;
        const transcript=catalog.files.find(f=>f.key==='transcript-txt')||catalog.files.find(f=>f.key==='transcript-json');
        if(transcript)await loadDocument(transcript);else el('output-text-loading').textContent=stages.transcript?.error||'A transcrição aparecerá aqui após o processamento. Consulte a aba Processamento.';
    }
    el('output-copy').onclick=async()=>{try{await navigator.clipboard.writeText(readable(documentContent));toast('Texto copiado.');}catch{toast('Não foi possível copiar. Selecione o texto no leitor ou baixe o arquivo.');}};
}
