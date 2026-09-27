# API local

Referência do comportamento atual em `app/public/index.php` e `app/src/`, conferida em 27/09/2026. A API usa sessão por cookie. IDs de entidades nas rotas têm 32 caracteres hexadecimais.

## Sessão e erros

`GET /api/session` devolve usuário e token CSRF. Envie esse token em `X-CSRF-Token` nas mutações e preserve os cookies. Login/cadastro devolvem o token da nova sessão. Corpos usuais são JSON; blocos de upload são bytes.

- 401: autenticação necessária ou credenciais inválidas.
- 404: recurso não encontrado, incluindo recurso de outra conta.
- 409: estado incompatível ou resultado ainda indisponível.
- 419: CSRF/sessão.
- 422: validação.
- 429: limite de tentativas de autenticação/cadastro.
- 500: mensagem genérica com referência, sem stack trace.

`GET /health` verifica banco e Redis; não autentica o usuário nem comprova sucesso do pipeline.

## Contas e projetos

- `POST /api/register`: `email` (usuário ou e-mail), `password`, `password_confirmation`.
- `POST /api/login`: `email`, `password`; `POST /api/logout`: encerra sessão.
- `GET|POST /api/projects`: listar/criar. Criar exige `name`; `objective`, `domain` e `notes` são opcionais.
- `GET|PATCH /api/projects/{id}`: leitura/edição; PATCH exige nome e registra versão. Status aceita active/archived.

## Vídeos e processamento

- `GET|POST /api/projects/{id}/videos`: listar/iniciar upload. POST exige `name` do arquivo e `size`; título omitido usa o nome sem extensão.
- `GET /api/uploads/{id}`: vídeo e blocos recebidos.
- `PUT /api/uploads/{id}/chunks/{numero}`: bytes do bloco; tamanho padrão 8 MiB.
- `POST /api/uploads/{id}/complete`: enfileira validação.
- `GET /api/videos/{id}/stream`: original com HTTP Range.
- `POST /api/videos/{id}/runs`: cria análise; exige mídia pronta e rejeita outra análise ativa.
- `GET /api/runs/{id}`: estado, etapas, conclusões e disponibilidade de outputs.
- `POST /api/runs/{id}/cancel` e `/resume`: cancelar/retomar; resume pode receber `stage`.
- `GET /api/runs/{id}/frames/{frame}`: imagem.
- `PATCH /api/runs/{id}/transcript/{segmento}`: `text`, durante revisão.

## Outputs e conhecimento

- `GET /api/outputs`: vídeos/versões da conta, inclusive vídeos sem análise.
- `GET /api/runs/{id}/outputs`: catálogo permitido.
- `GET /api/runs/{id}/outputs/text?file={chave}`: documento; `download=1` baixa completo. Prévia limitada a 2 MB. Use chaves do catálogo, nunca caminhos arbitrários.
- `GET /api/runs/{id}/outputs/audio`: áudio quando pronto, com Range.
- `POST /api/runs/{id}/claims` e `PATCH /api/runs/{id}/claims/{claim}`: conclusões com tipo, título, descrição, classificação, confiança e evidências válidas.
- `POST /api/runs/{id}/approve`: snapshot após resolver pendências e aprovar ao menos uma conclusão.
- `GET|POST /api/runs/{id}/exports`: listar/enfileirar ZIP.
- `GET /api/exports/{id}`: baixar ZIP pronto.
- `POST /api/runs/{id}/redact`: `frame_id`, `x`, `y`, `width`, `height`; oculta região derivada.
- `GET /api/runs/{id}/history`: revisões.
- `GET|POST /api/projects/{id}/target`: consultar/enfileirar inventário do diretório-alvo.

## Gemini

- `GET /api/ai/gemini`: configuração mascarada, catálogo e rascunho.
- `POST /api/ai/gemini/key`: `api_key`; guarda criptografada e invalida validação/catalogação anterior.
- `DELETE /api/ai/gemini/key`: remove a cópia local.
- `POST /api/ai/gemini/test`: consulta modelos na API Google; não gera análise.
- `PATCH /api/ai/gemini/draft`: salva prompt e modelo selecionado.
- `POST /api/runs/{id}/ai/gemini`: exige chave testada, modelo salvo e outputs locais prontos; cria tarefa (202). Rejeita outra tarefa ativa (409).
- `GET /api/runs/{id}/ai/gemini`: estado, progresso, erro, avisos e relatório concluído.
- `POST /api/runs/{id}/ai/gemini/{tarefa}/cancel`: cancela tarefa ativa (202).
- `GET /api/runs/{id}/ai/gemini/{tarefa}/download`: baixa o relatório Markdown concluído.
- Relatórios concluídos também entram no catálogo de outputs com chave `ai-{tarefa}`.

## Operação

`GET|POST /api/diagnostics` consulta estado/enfileira diagnóstico. `GET /api/audit` lista os últimos eventos da conta. Esta referência descreve as rotas existentes; não constitui contrato externo versionado.
