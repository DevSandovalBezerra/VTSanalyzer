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

`MODEL_PROFILE=astra`, `MODEL_ENDPOINT`, `MODEL_ID`, `MODEL_API_KEY` e `ALLOW_EXTERNAL_MODELS=0` pertencem ao gateway legado. Não configuram o painel Gemini nem habilitam sua geração. O teste de conexão Gemini consulta a API de modelos quando o usuário clica em Testar. Não envia evidências.

A configuração de ai-memory é independente: veja [AI-MEMORY.md](AI-MEMORY.md).

## Arquivos Compose

`compose.yml` define os seis serviços e as imagens com código. `compose.dev.yml` monta fontes locais em leitura para desenvolvimento. `compose.gpu.yml` é uma opção experimental; aceleração GPU não foi validada.

Alterações em variáveis exigem recriar os serviços para serem aplicadas. Mudanças em dependências ou Dockerfiles exigem rebuild. Nunca compartilhar a saída integral de `docker compose config` com segredos interpolados.
