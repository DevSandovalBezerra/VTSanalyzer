# Configuração

Referência: `.env.example` e `compose.yml`. Não colocar segredos reais em documentação ou commits.

## Ambiente da aplicação

- `APP_ENV=local`: identificação do ambiente; `APP_PORT=8095`: porta local do Nginx.
- `APP_VERSION=0.1.0`: identificação da aplicação e tag das imagens. Não representa conclusão do PRD.
- `SESSION_SECURE=0`: desenvolvimento HTTP local; usar `1` com HTTPS.
- `POSTGRES_DB=system_knowledge` e `POSTGRES_USER=system_knowledge`: banco e usuário.
- `POSTGRES_PASSWORD` e `REDIS_PASSWORD`: substituir placeholders por segredos distintos.
- `TARGET_SOURCE=./tests/fixtures/target`: diretório-alvo montado somente em leitura.
- `TRANSCRIPTION_MODEL=small`: modelo faster-whisper; baixar pesos antes de processar áudio.
- `MAX_VIDEO_SECONDS=7200`: limite de duração. Não é garantia de desempenho para duas horas.
- `MAX_UPLOAD_BYTES=4294967296`: limite de tamanho, 4 GiB.

`STORAGE_PATH=/data` e `OUTPUTS_PATH=/outputs` são definidos pelo Compose. Não apontar para pastas temporárias se deseja preservar os dados.

## Gemini e gateway anterior

A chave Gemini é configurada pela interface e pertence à conta conectada. O banco guarda a chave criptografada; o segredo mestre fica em `/data/secrets/gemini-master.key`. Preserve ambos no backup.

`MODEL_PROFILE=astra`, `MODEL_ENDPOINT`, `MODEL_ID`, `MODEL_API_KEY` e `ALLOW_EXTERNAL_MODELS=0` pertencem ao gateway legado. Não configuram o painel Gemini. A geração Gemini depende da chave cadastrada e testada pela própria conta na interface. O teste de conexão Gemini consulta o catálogo e verifica acesso com `countTokens` usando apenas texto mínimo. Não envia evidências do vídeo. A listagem pode conter modelos indisponíveis para a chave; a seleção é validada também ao salvar outro modelo.

A configuração de ai-memory é independente: veja [AI-MEMORY.md](AI-MEMORY.md).

## Arquivos Compose

`compose.yml` define os serviços, incluindo `gemini-worker`, e as imagens com código. O web aplica migrações ao iniciar; `gemini-worker` aguarda o web ficar saudável. `compose.dev.yml` monta fontes locais em leitura para desenvolvimento. `compose.gpu.yml` é uma opção experimental; aceleração GPU não foi validada.

Alterações em variáveis exigem recriar os serviços para serem aplicadas. Mudanças em dependências ou Dockerfiles exigem rebuild. Nunca compartilhar a saída integral de `docker compose config` com segredos interpolados.

## Parâmetros atuais da geração

Modelo e prompt são configurações por conta, salvas separadamente em **Configurar Gemini**. Cada tarefa conserva uma cópia dessas escolhas. A preferência implementada começa em `gemini-3.8-flash` quando o modelo salvo não responde; um modelo existente que responda é mantido. A disponibilidade é conferida com a chave, não presumida pelo nome no catálogo.

Os limites abaixo estão em `app/src/gemini_engine.php`, sem variáveis de ambiente próprias:

- Formação inicial de lotes: até 10 imagens, 6 MiB de imagens e 60.000 bytes de texto acumulados antes de abrir outro lote. A contagem efetiva de tokens pode dividi-lo novamente.
- Imagem selecionada: até 5 MiB; corpo JSON enviado: até 18 MiB. Esses são limites locais de montagem, não uma declaração dos limites comerciais do provedor.
- Saída: até 16.384 tokens por chamada, limitada também pelo catálogo do modelo. Gemini 3 usa `thinkingLevel: low`; os demais IDs usam `temperature: 0.2`.
- Timeout: conexão de até 10 segundos; até 45 segundos para `countTokens` e 240 segundos para `generateContent`, por tentativa. Erros transitórios têm até cinco tentativas, com esperas de 1, 2, 4 e 8 segundos mais até 0,5 segundo de variação aleatória.

A repetição se aplica a `countTokens` e `generateContent`; a consulta inicial do catálogo tem tratamento próprio. Consulte [Gemini](GEMINI.md) para diagnóstico e [operação](DEPLOYMENT.md) antes de reiniciar o processador.
