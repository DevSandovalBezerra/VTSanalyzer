# Operação e implantação

## Desenvolvimento local

A instalação atual usa Ubuntu-24.04/WSL2 e os dois arquivos Compose:

```bash
cd ~/projetos/system-knowledge-extractor
docker compose -f compose.yml -f compose.dev.yml up -d --wait
docker compose ps
docker compose logs --tail=80 worker scheduler
```

Parar sem apagar dados: `docker compose -f compose.yml -f compose.dev.yml stop`. Nunca usar `down -v` para uma atualização comum: essa opção apaga volumes.

Antes de atualizar código ou reiniciar o worker, aguarde tarefas ativas terminarem. Após atualizar, aplique migrações e reinicie os serviços que carregam módulos Python. Rebuild é necessário para dependências/imagens.

## Outputs

A pasta física de projeto permanece estável quando o nome do projeto muda; os metadados são atualizados. Sincronizações podem aguardar a tarefa atual do worker. A UI distingue processamento de finalização dos arquivos.

Para regenerar metadados de outputs com o worker ocioso:
`docker compose exec -T worker python outputs.py`.

A migração histórica de outputs usa `python outputs.py --migrate` e comparação SHA-256. Não é parte da rotina de um checkout já migrado; siga o código e faça backup antes de usá-la.

## Backup

Preserve juntos: PostgreSQL, volume `media` (originais e segredo mestre Gemini) e pasta `outputs/`. O cache dos modelos pode ser baixado novamente; o volume Redis guarda fila e sessões. Registre também a versão de código e a configuração necessária.

Há estruturas persistentes, mas **backup/restauração automatizados e um ensaio completo de recuperação ainda estão pendentes**. Não presumir que um push para o GitHub inclua dados. As chaves Gemini criptografadas no banco não são recuperáveis sem o segredo mestre correspondente.

A memória dos agentes tem armazenamento próprio e procedimento separado em [AI-MEMORY.md](AI-MEMORY.md).

## Produção futura

`compose.yml` inclui código nas imagens; `compose.dev.yml` é exclusivo de desenvolvimento. Para implantação futura, usar uma `APP_VERSION` distinta, imagens reconstruídas, TLS no proxy frontal, `SESSION_SECURE=1`, `APP_ENV=production` e backups testados.

A configuração atual publica apenas `127.0.0.1:8095`. Esta documentação não afirma que uma implantação pública, escala horizontal ou operação multiusuário corporativa tenham sido validadas. GPU permanece opt-in e não testada.
