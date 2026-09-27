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

## Gemini: estado atual

O prompt-base revisado está em `app/prompts/gemini-analysis.md`. A tela Análise por IA agora permite executar uma tarefa Gemini em segundo plano com chave própria do usuário, acompanhar progresso/cancelamento e ler ou baixar o relatório também em Outputs. A chave é criptografada por conta; o teste de conexão só lista modelos. O processador envia transcrição, OCR, telas selecionadas e contexto, sem vídeo/áudio originais. Há contagem de tokens, divisão/consolidação, cópia temporária das imagens para manter a versão e avisos de referências não encontradas. Consulte [GEMINI.md](docs/GEMINI.md).

**Ainda não houve geração real:** o usuário informou que não cadastrou sua chave. Testes usaram chave e respostas simuladas; qualidade, cota e comportamento de um modelo real dependem de validação posterior na interface. Nunca pedir chave no chat.

O gateway Astra em `worker/gateway.py` é legado e não chama o Gemini.

## Validação

Em 27/09 passaram `gemini_settings` e `gemini_execution` com transporte simulado, incluindo leitura do relatório em Outputs, sintaxe PHP/JS e acesso autenticado a Outputs e à configuração após migração. O teste real do Gemini aguarda a chave do usuário. O processador `gemini-worker` está ativo. Verificar logs e status após qualquer atualização; o web aplica migrações ao iniciar.

## Dados e continuidade

Originais e segredo mestre Gemini ficam no volume media; derivados em outputs; banco em volume PostgreSQL. Esses dados não vão ao GitHub. Não usar down -v para atualizar. Não reaplicar scripts históricos de cópia Windows sobre o checkout atual.

[AI-MEMORY.md](docs/AI-MEMORY.md) registra a memória dos agentes. Ela é independente do analisador de vídeos. Documentação/código/testes atuais prevalecem sobre lembranças antigas.

## Publicação e memória

Aplicação e documentação publicadas na main em 27/09/2026; primeiro commit `607b037`, confirmado no GitHub. Os planos P08/P09 registram a entrega e a etapa restante no cliente.

ai-memory 2.0.3 funciona no WSL em localhost:49374, com contexto gravado e busca validada. O MCP respondeu ao handshake e os hooks sintéticos persistiram três eventos. LLM e embeddings externos estão desativados. Abrir nova sessão/recarregar MCP e revisar a confiança dos hooks, quando solicitado, para ativar o uso automático no cliente.
