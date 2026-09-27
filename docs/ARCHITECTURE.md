# Arquitetura

Estado conferido em 27/09/2026. Fontes: `compose.yml`, `app/public/index.php`, `app/src/`, `worker/` e `frontend/`.

## Componentes

O Nginx publica apenas a porta de loopback 8095 e encaminha requisições ao PHP 8.3. A API modular usa PostgreSQL 17 para dados e outbox, Redis 7.4 para sessões e fila, um scheduler para despachar tarefas e um worker Python 3.12 para processamento. O frontend usa JavaScript nativo e CSS local; não exige npm nem um build de assets. A geração Gemini tem um processador PHP separado, `gemini-worker`, que consulta a fila `ai_analyses` diretamente no PostgreSQL. Essa fila não passa pelo scheduler Python nem pelo Redis.

```mermaid
flowchart LR
    Usuario[Navegador] --> Nginx
    Nginx --> PHP[API PHP]
    PHP --> DB[(PostgreSQL)]
    PHP --> Redis[(Redis)]
    DB --> Scheduler
    Scheduler --> Redis
    Redis --> Worker[Worker Python]
    Worker --> DB
    Worker --> Outputs[Outputs por projeto]
    PHP --> Outputs
    Worker --> Media[Volume de originais]
    DB --> GeminiWorker[Processador Gemini PHP]
    GeminiWorker --> DB
    GeminiWorker --> Outputs
    GeminiWorker --> Media
    GeminiWorker --> Gemini[API Gemini HTTPS]
```

## Módulos

- `app/src/bootstrap.php`: banco, fila, sessão, identificação e utilitários de resposta.
- `register.php`: cadastro livre com validação e papel comum.
- `media.php`: upload, validação, execução, cancelamento, retomada, streaming e edição de transcrição.
- `progress.php`: disponibilidade dos outputs a partir das etapas e da versão dos arquivos sincronizados.
- `outputs.php`: biblioteca global, catálogo e leitura autenticada.
- `knowledge.php`: conclusões manuais, revisão, ocultação, snapshots, exportação e inventário.
- `gemini.php`: chave por usuário, catálogo e verificação de acesso ao modelo, salvamento separado de modelo/prompt, criação e consulta de tarefas, cancelamento e download.
- `gemini_engine.php`: montagem multimodal, cópia temporária de imagens, contagem de tokens, divisão/consolidação, tentativas de recuperação HTTP, persistência e avisos de referências.
- `app/bin/gemini-worker.php`: execução sequencial das tarefas Gemini; ao iniciar, marca tarefas que estavam em processamento como falhas.
- `worker/service.py`: laços de worker e scheduler; `pipeline.py`: motores de mídia; `outputs.py`: sincronização dos arquivos; `knowledge.py`: pacotes e inventário.
- `worker/gateway.py`: contrato legado de provedor, desconectado do pipeline.
- `frontend/library.js`, `outputs.js`, `progress.js` e `gemini.js`: biblioteca de resultados, leitores, acompanhamento, configuração global Gemini e percurso de análise por vídeo.

## Fluxo e consistência

O upload envia blocos de 8 MiB, controla integridade por SHA-256 e admite retomada. A conclusão enfileira ingestão; FFprobe/FFmpeg verificam a mídia. A análise cria uma versão com cinco etapas: áudio, transcrição, frames, OCR e telas. As ferramentas são FFmpeg, faster-whisper e Tesseract.

Etapa concluída no banco não basta para liberar um documento: os arquivos precisam estar sincronizados. `worker/outputs.py` publica `.readiness.json` com as versões das etapas; o PHP compara essas versões. Assim, o arquivo de uma tentativa antiga não libera uma nova tentativa. Há compatibilidade por data para outputs anteriores ao marcador.

A revisão produz conclusões com evidências. Aprovar congela um snapshot; a exportação usa esse snapshot e inclui apenas evidências citadas pelos itens aprovados. Uma ocultação invalida aprovações e OCR da versão afetada.

A análise Gemini exige outputs sincronizados e execução local em revisão ou aprovada. O pedido congela modelo, prompt, versão da chave e versões das etapas. Ao começar, o processador confere essas versões e copia as telas selecionadas para uma pasta temporária privada. Transcrição, OCR, imagens selecionadas e contexto são enviados em lotes; vídeo e áudio originais permanecem locais. O relatório final é salvo no PostgreSQL e em Markdown nos Outputs. Ele não cria nem aprova automaticamente as conclusões da revisão manual.

## Persistência e limites

- PostgreSQL: contas, projetos, vídeos, execuções, etapas, revisões, snapshots, tarefas locais, configurações Gemini e tarefas/relatórios em `ai_analyses`.
- Redis: sessões, filas e sinais de vida.
- Volume `media`: originais/uploads e segredo mestre Gemini.
- Pasta `outputs/`: derivados, textos, imagens, relatórios Gemini e exportações; web em leitura, workers Python e Gemini em escrita.
- Volume `model-cache`: pesos locais.
- `/target`: projeto-alvo montado somente para leitura; inventário não executa código.

As contas são isoladas por proprietário. A classificação antiga de sensibilidade permanece no armazenamento por compatibilidade, com padrão interno, mas saiu do formulário. Nenhuma retenção diferenciada por esses níveis foi implementada.

ai-memory é uma ferramenta de contexto dos agentes de desenvolvimento, independente da aplicação e do Gemini. Seus dados não pertencem aos volumes do VTSanalyzer.

A configuração atual prevê uma instância de `gemini-worker`. Escala horizontal e retomada dos lotes após reinício não estão implementadas; reiniciar esse serviço durante uma geração exige iniciar outra análise. O cancelamento é conferido entre operações e não interrompe imediatamente uma chamada HTTPS em andamento.
