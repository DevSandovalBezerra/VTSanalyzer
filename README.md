# System Knowledge Extractor

Implementação do PRD 0.2, em português. Código canônico no filesystem Linux do WSL2; serviços em Docker Compose. O WAMP não hospeda a aplicação.

## Desenvolvimento

Pré-requisitos: Ubuntu WSL2, Git e Docker Desktop com integração da distribuição habilitada. Ver documentação oficial: https://docs.docker.com/desktop/features/wsl/use-wsl/.

```bash
cd ~/projetos/system-knowledge-extractor
cp .env.example .env
# Substitua POSTGRES_PASSWORD e REDIS_PASSWORD por segredos aleatórios.
chmod 600 .env
docker compose build worker
bash scripts/prepare-outputs.sh
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait
docker compose exec -T web php bin/migrate.php
read -rs -p 'Senha inicial (mínimo 12 caracteres): ' initial_password
printf '%s' "$initial_password" | docker compose exec -T web php bin/create-user.php admin@local.test
unset initial_password
```

Acesso: http://localhost:8095. Somente loopback é publicado. PostgreSQL, Redis e PHP não expõem portas ao host. O instalador `scripts/install-wsl.sh` gera segredos e copia o pacote de preparação para `~/projetos/system-knowledge-extractor`; recusa sobrescrever diretórios não gerenciados.

O código PHP, Python e frontend é montado em leitura no desenvolvimento. Alterações PHP/JS/CSS são visíveis ao recarregar. Reinicie worker/scheduler após alterações no laço Python (`docker compose restart worker scheduler`). Dependências exigem reconstrução da imagem. Nenhum arquivo de vídeo fica no filesystem descartável de containers.

## Operação

Depois de entrar, crie um projeto, envie o vídeo e aguarde a validação técnica. Abra **Nova análise**, escolha o perfil e acompanhe o monitor. Em **Revisão sincronizada**, selecione frames e falas; em **Conhecimento e exportação**, registre conclusões, indique evidências e revise cada item. Feche a revisão para congelar a versão e gerar o ZIP.

Antes de processar vídeos com áudio, instale os pesos de transcrição:

```bash
bash scripts/download-model.sh
```

Esse comando baixa o modelo público configurado para um volume persistente. Depois, a transcrição usa `local_files_only=True`, sem enviar áudio. Vídeos sem áudio registram explicitamente essa condição. O diagnóstico verifica o cache; a aplicação bloqueia vídeos com áudio enquanto o modelo estiver ausente.

O vídeo original é preservado. Regiões ocultadas são removidas das imagens derivadas e dos pacotes subsequentes dessa versão; o original continua acessível ao proprietário. A exportação é privada/local, não é publicação pública. Só as evidências citadas por conclusões aprovadas entram no ZIP. As inferências permanecem classificadas como inferências mesmo depois da aprovação.

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

`tests/foundation.py` exercita autenticação, CSRF, isolamento entre contas, versionamento de projetos, fila real e diagnóstico. `scripts/verify-foundation.sh` verifica persistência após recriação, bind mounts, FFmpeg e alvo somente leitura. `tests/media.py` e `tests/knowledge.py` exercitam o fluxo com mídia sintética, ocultação e pacote final. `tests/transcription.py` testa narração sintética em português contra o Whisper local. Consulte `docs/STATUS.md` para o escopo realmente validado e as pendências do PRD.

Os scripts `checkpoint.sh`, `media-checkpoint.sh`, `validate.sh` e `prepare-model.sh` foram usados na instalação inicial a partir do pacote Windows. Eles copiam arquivos para o repositório WSL. **Não os reaplique sobre trabalho posterior no WSL.** Para continuar o desenvolvimento, edite diretamente o repositório Linux e execute os testes ali.

## Outputs por projeto

Os arquivos derivados ficam em `outputs/<nome-do-projeto>--<id>/`, no próprio repositório WSL. No Windows, abra `\\wsl.localhost\Ubuntu-24.04\home\user\projetos\system-knowledge-extractor\outputs`. Cada pasta contém `projeto.json` e `LEIA-ME.txt` para identificação; o nome da pasta permanece estável ao renomear o projeto.

Dentro de `videos/<id>/analises/vNNN--<id>/` ficam áudio WAV (quando disponível), frames JPG, folha de contato (quando disponível), transcrição TXT/SRT/JSON, OCR, telas, análise, histórico, snapshots e ZIPs exportados. `video.json` identifica o vídeo. Inventários de código ficam em `inventarios/` no projeto. Astra continua desativado.

A pasta contém resultados de trabalho, incluindo itens ainda não aprovados. A fila atualiza os metadados após processamento e revisões; a atualização pode aguardar a tarefa em andamento. Imagens e ZIPs têm uma única localização, sem cópias paralelas no volume de mídia. O web tem acesso somente de leitura. Outputs são ignorados pelo Git e devem integrar o backup juntamente com o banco e o volume dos originais.

Para uma instalação existente: pare web/worker/scheduler, prepare a pasta com `bash scripts/prepare-outputs.sh`, aplique `php bin/migrate.php` em um container web temporário, execute `docker compose run --rm --no-deps worker python outputs.py --migrate` e recrie os serviços. A migração compara SHA-256 antes de remover os arquivos antigos e pode ser retomada. Para regenerar somente JSON/TXT/SRT, use `docker compose exec -T worker python outputs.py` com o worker ocioso.

## Leitor de outputs no sistema

Abra um projeto e clique em **Frames e textos · vN** no vídeo, ou abra a análise e selecione a aba **Frames e textos**. A galeria apresenta imagens ampliadas, miniaturas, tempo de captura, zoom, filtro de frames semelhantes, busca pelo OCR e o texto/fala associado ao instante. É possível baixar o frame e ouvir o áudio extraído quando disponível.

Em **Textos**, escolha a transcrição, OCR, telas, análise, histórico ou snapshot aprovado. O leitor permite buscar termos, copiar o texto, alternar entre leitura e JSON original e baixar o arquivo. Arquivos de até 2 MB são exibidos integralmente; acima disso, a prévia indica o limite e mantém o download completo. **Atualizar outputs** consulta novamente os arquivos gerados pela fila.

As rotas exigem sessão e propriedade do projeto, usam um catálogo permitido de documentos e verificam o caminho real do arquivo. O leitor trata todo o conteúdo como texto, sem executar HTML ou scripts. `tests/output_viewer.py` valida acesso, isolamento entre contas, catálogo, downloads, snapshot aprovado, SRT vazio, Range de áudio e rejeição de caminhos arbitrários.

## Cadastro livre

Na tela de entrada, clique em **Registrar**. Escolha um usuário simples (3–40 letras sem acentos, números, ponto, traço ou sublinhado) ou e-mail, uma senha de pelo menos 6 caracteres e confirme a senha. Não há exigência de símbolos, convite, aprovação administrativa ou confirmação por e-mail. Após criar a conta, a entrada é automática.

Novas contas recebem o papel `user` explicitamente. O login é normalizado para minúsculas e permanece no campo `email` existente por compatibilidade com as contas antigas. Senhas continuam armazenadas como hash; cada conta acessa apenas seus próprios projetos. Contas e senhas anteriores continuam funcionando.
