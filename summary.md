# VTSanalyzer — resumo de continuidade

Atualizado em 27/09/2026. Leia este arquivo primeiro, depois [PLANOS](docs/PLANOS.md) e [STATUS](docs/STATUS.md). Confirme o estado atual no código e no Git antes de atuar.

## Objetivo e preferência do usuário

Sistema interno para destrinchar vídeos e tornar transcrição, telas e análise por IA fáceis de acessar. Formulários devem pedir apenas o necessário. Menu lateral destaca Outputs, Transcrição, Telas principais e Análise por IA. Não reintroduzir os três níveis de sensibilidade na interface.

## Ambiente real

- Código canônico: `/home/user/projetos/system-knowledge-extractor`, Ubuntu-24.04/WSL2.
- Pasta Windows `C:\wamp64\www\VTSAnalizer`: entrega/abertura, sem servidor da aplicação.
- URL local: `http://localhost:8095`.
- Docker Compose: Nginx, PHP 8.3, PostgreSQL 17, Redis 7.4, worker/scheduler Python 3.12 e processador Gemini PHP.
- Branch main; destino: `https://github.com/DevSandovalBezerra/VTSanalyzer.git`.
- Fontes são montados no desenvolvimento. Reiniciar worker/scheduler após mudanças Python e `gemini-worker` após mudanças nas funções PHP da geração, sempre com tarefas ociosas. Reinício do Gemini não retoma lotes parciais.

## Entregue

Cadastro livre, projetos isolados, upload retomável, validação, transcrição faster-whisper local, extração de frames, OCR e telas. Revisão manual com evidências, ocultação, snapshots e ZIP. Outputs por projeto/vídeo/versão, leitores com busca/cópia/download.

Fluxo simplificado: título por nome de arquivo, objetivo/origem/contexto opcionais, sem sensibilidade nas telas. Progresso por cinco etapas, polling sem trocar a seção e bloqueios até a sincronização. Marcador `.readiness.json` impede liberar arquivos de uma versão antiga.

## Gemini: estado atual

O prompt-base revisado está em `app/prompts/gemini-analysis.md`. Configurar Gemini reúne chave, teste de conexão, modelo e prompt avançado. Outputs → Análise por IA lista vídeos; a tela de cada vídeo permite iniciar, acompanhar, cancelar e ler/baixar o relatório. Configuração e resultado não dividem o mesmo formulário. A chave é criptografada por conta; o teste de conexão lista e prova o acesso ao modelo com uma chamada mínima. O processador envia transcrição, OCR, telas selecionadas e contexto, sem vídeo/áudio originais. Há contagem de tokens, divisão/consolidação, cópia temporária das imagens para manter a versão e avisos de referências não encontradas. Consulte [GEMINI.md](docs/GEMINI.md).

Em 27/09, a recusa inicial foi diagnosticada: `gemini-2.5-flash` constava no catálogo, mas a chamada mínima `countTokens` retornou HTTP 404 para esta chave. O teste de conexão agora comprova uso do modelo e selecionou `gemini-3.8-flash`; o erro HTTP informa a causa. O material medido tinha 382 segmentos de fala e 87 telas em 9 lotes, sem ultrapassar o limite local de envio. Uma primeira execução avançou até 5/10 e encontrou HTTP 503. Após adicionar repetição com espera exponencial, a execução real terminou em 10/10 e salvou o relatório no banco e em Outputs. O relatório tem dez avisos de horários não encontrados nas evidências; revisar essas referências antes de usar conclusões como fatos. Nunca registrar ou pedir a chave no chat.

O gateway Astra em `worker/gateway.py` é legado e não chama o Gemini.

## Validação

Em 27/09 passaram `gemini_settings` e `gemini_execution` com transporte simulado, incluindo leitura do relatório em Outputs, sintaxe PHP/JS e acesso autenticado a Outputs e à configuração após migração. O fluxo de interface foi separado em configuração global e análise por vídeo; consulte `gemini_flow_ui.cjs`. Além dos testes simulados, a análise real de um vídeo existente foi concluída e aberta no Chrome com `gemini-3.8-flash`; `gemini-worker` está ativo. A qualidade factual integral do texto não foi atestada. Verificar logs e status após qualquer atualização; o web aplica migrações ao iniciar.

## Dados e continuidade

Originais e segredo mestre Gemini ficam no volume media; derivados em outputs; banco em volume PostgreSQL. Esses dados não vão ao GitHub. Não usar down -v para atualizar. Não reaplicar scripts históricos de cópia Windows sobre o checkout atual.

[AI-MEMORY.md](docs/AI-MEMORY.md) registra a memória dos agentes. Ela é independente do analisador de vídeos. Documentação/código/testes atuais prevalecem sobre lembranças antigas.

## Publicação e memória

Aplicação publicada na main em 27/09/2026; primeiro commit `607b037`. A correção Gemini `fd93cc0` foi confirmada no GitHub. A revisão documental posterior alinha arquitetura, API, instalação, configuração, operação e [guia de uso](docs/GUIA-DE-USO.md) a essa implementação. Os planos P08/P09 registram a entrega e a etapa restante no cliente.

ai-memory 2.0.3 funciona no WSL em localhost:49374, com contexto gravado e busca validada. O MCP respondeu ao handshake e os hooks sintéticos persistiram três eventos. LLM e embeddings externos estão desativados. Abrir nova sessão/recarregar MCP e revisar a confiança dos hooks, quando solicitado, para ativar o uso automático no cliente.
