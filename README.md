# System Knowledge Extractor

Implementação do PRD 0.2, em português. Código canônico no filesystem Linux do WSL2; serviços em Docker Compose. O WAMP não hospeda a aplicação.

## Desenvolvimento

Pré-requisitos: Ubuntu WSL2, Git e Docker Desktop com integração da distribuição habilitada. Ver documentação oficial: https://docs.docker.com/desktop/features/wsl/use-wsl/.

```bash
cd ~/projetos/system-knowledge-extractor
cp .env.example .env
# Substitua POSTGRES_PASSWORD e REDIS_PASSWORD por segredos aleatórios.
chmod 600 .env
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait
docker compose exec -T web php bin/migrate.php
read -rs -p 'Senha inicial (mínimo 12 caracteres): ' initial_password
printf '%s' "$initial_password" | docker compose exec -T web php bin/create-user.php admin@local.test
unset initial_password
```

Acesso: http://localhost:8095. Somente loopback é publicado. PostgreSQL, Redis e PHP não expõem portas ao host. O instalador `scripts/install-wsl.sh` gera segredos e copia o pacote de preparação para `~/projetos/system-knowledge-extractor`; recusa sobrescrever diretórios não gerenciados.

O código PHP, Python e frontend é montado em leitura no desenvolvimento. Alterações PHP/JS/CSS são visíveis ao recarregar. Reinicie worker/scheduler após alterações no laço Python (`docker compose restart worker scheduler`). Dependências exigem reconstrução da imagem. Nenhum arquivo de vídeo fica no filesystem descartável de containers.

## Operação

```bash
docker compose ps
docker compose logs --tail=80 worker scheduler
docker compose -f compose.yml -f compose.dev.yml stop
docker compose -f compose.yml -f compose.dev.yml up -d --wait
```

Os volumes `database`, `queue`, `media` e `model-cache` persistem ao recriar containers. Não execute `down -v` se deseja conservar dados. `TARGET_SOURCE` deve apontar para um diretório explicitamente autorizado; ele será montado em `/target` como somente leitura. O fixture padrão contém apenas um README.

## Produção

Construa imagens com `APP_VERSION` único para cada versão e use somente `compose.yml` (sem `compose.dev.yml`). Esse arquivo inclui código nas imagens e não monta código da máquina. O projeto-alvo continua em leitura. Configure TLS em proxy frontal, `SESSION_SECURE=1`, `APP_ENV=production`, backups e segredos do ambiente. Não exponha diretamente a instalação de desenvolvimento à internet.

CPU é o padrão. `compose.gpu.yml` é um override opt-in para reserva NVIDIA; só pode ser usado após validar drivers, runtime e bibliotecas CUDA do motor escolhido. A presença do arquivo não comprova aceleração.

## Integração Astra

O usuário decidiu definir o acesso posteriormente. `MODEL_PROFILE=astra` é um nome lógico, não um identificador presumido de API. Endpoint, identificador técnico e credencial permanecem vazios e `ALLOW_EXTERNAL_MODELS=0`. Nenhuma mídia é enviada a serviços externos por padrão.

## Verificações

`tests/foundation.py` exercita autenticação, CSRF, isolamento entre contas, versionamento de projetos, fila real e diagnóstico. `scripts/verify-foundation.sh` verifica persistência após recriação, bind mounts, FFmpeg e alvo somente leitura. Consulte `docs/STATUS.md` para o escopo realmente validado.
