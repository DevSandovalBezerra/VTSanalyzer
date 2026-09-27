# VTSanalyzer — resumo de continuidade

Atualizado em 27/09/2026. Leia este arquivo primeiro, depois [PLANOS](docs/PLANOS.md) e [STATUS](docs/STATUS.md). Confirme o estado atual no código e no Git antes de atuar.

## Objetivo e preferência do usuário

Sistema interno para destrinchar vídeos e tornar transcrição, telas e análise por IA fáceis de acessar. Formulários devem pedir apenas o necessário. Menu lateral destaca Outputs, Transcrição, Telas principais e Análise por IA. Não reintroduzir os três níveis de sensibilidade na interface.

## Ambiente real

- Código canônico: `/home/user/projetos/system-knowledge-extractor`, Ubuntu-24.04/WSL2.
- Pasta Windows `C:\wamp64\www\VTSAnalizer`: entrega/abertura, sem servidor da aplicação.
- URL local: `http://localhost:8095`.
- Docker Compose: Nginx, PHP 8.3, PostgreSQL 17, Redis 7.4, worker e scheduler Python 3.12.
- Branch main; destino: `https://github.com/DevSandovalBezerra/VTSanalyzer.git`.
- PHP/JS/CSS são montados no desenvolvimento. Após alteração Python, reiniciar worker/scheduler quando estiverem ociosos.

## Entregue

Cadastro livre, projetos isolados, upload retomável, validação, transcrição faster-whisper local, extração de frames, OCR e telas. Revisão manual com evidências, ocultação, snapshots e ZIP. Outputs por projeto/vídeo/versão, leitores com busca/cópia/download.

Fluxo simplificado: título por nome de arquivo, objetivo/origem/contexto opcionais, sem sensibilidade nas telas. Progresso por cinco etapas, polling sem trocar a seção e bloqueios até a sincronização. Marcador `.readiness.json` impede liberar arquivos de uma versão antiga.

## Gemini: limite exato

Configuração por conta, chave criptografada, consulta de modelos, rascunho editável e prévia do material implementados. Prompt em `app/prompts/gemini-analysis.md`.

**O prompt-base foi revisado e fechado para implementação. Não houve geração real nem envio de vídeo ao Gemini.** O endpoint de execução retorna 409. O próximo trabalho de produto é implementar P07. Credenciais do Gemini devem ser inseridas pela interface; não pedir para colar chaves no chat.

O gateway Astra em `worker/gateway.py` é legado e não chama o Gemini. Não usar sua existência como prova de análise por IA funcional.

## Validação

Em 27/09 passaram practical_flow, progress, progress_ui e gemini_settings, além de sintaxe e inspeção no navegador. Gemini foi testado com transporte simulado. O leitor antigo output_viewer não foi revalidado por falta de fixture temporária. A suíte completa tem evidências históricas em 23/09, não uma nova execução integral.

## Dados e continuidade

Originais e segredo mestre Gemini ficam no volume media; derivados em outputs; banco em volume PostgreSQL. Esses dados não vão ao GitHub. Não usar down -v para atualizar. Não reaplicar scripts históricos de cópia Windows sobre o checkout atual.

[AI-MEMORY.md](docs/AI-MEMORY.md) registra a memória dos agentes. Ela é independente do analisador de vídeos. Documentação/código/testes atuais prevalecem sobre lembranças antigas.

## Publicação e memória

Aplicação e documentação publicadas na main em 27/09/2026; primeiro commit `607b037`, confirmado no GitHub. Os planos P08/P09 registram a entrega e a etapa restante no cliente.

ai-memory 2.0.3 funciona no WSL em localhost:49374, com contexto gravado e busca validada. O MCP respondeu ao handshake e os hooks sintéticos persistiram três eventos. LLM e embeddings externos estão desativados. Abrir nova sessão/recarregar MCP e revisar a confiança dos hooks, quando solicitado, para ativar o uso automático no cliente.
