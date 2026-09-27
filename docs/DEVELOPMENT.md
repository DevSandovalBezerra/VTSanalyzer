# Desenvolvimento

## Onde editar

Nesta máquina, edite diretamente `/home/user/projetos/system-knowledge-extractor` no Ubuntu-24.04. A pasta `C:\wamp64\www\VTSAnalizer` é um ponto de entrega/abertura; não hospeda a aplicação. No Windows, o código canônico é acessível pelo compartilhamento WSL.

A branch de trabalho é `main`; o remoto solicitado é `https://github.com/DevSandovalBezerra/VTSanalyzer.git`. Consulte `git status` antes de alterar para preservar trabalho em andamento.

## Ciclo de alteração

O código web PHP, JS e CSS usa bind mounts no desenvolvimento: novas requisições e o recarregamento da página aplicam as mudanças. O processo PHP `gemini-worker` é persistente e exige reinício após alterações no motor da geração. O worker mantém módulos Python carregados; antes de reiniciá-lo, aguarde tarefas em execução terminarem. Depois de mudanças Python:

```bash
docker compose -f compose.yml -f compose.dev.yml restart worker scheduler
```

Para alterações nas funções PHP da geração, use `docker compose -f compose.yml -f compose.dev.yml restart gemini-worker` somente com tarefas ociosas. Reiniciar no meio de uma análise a marca como falha; não há retomada de lotes. Dependências e Dockerfiles exigem reconstrução das imagens. Migrações incrementais ficam em `app/migrations/`; o web as aplica ao iniciar. Em uma instalação já ativa, aplique com `docker compose exec -T web php bin/migrate.php` antes de testar as novas rotas. Não há migrações reversas implementadas.

## Convenções de produto

O objetivo é uso interno prático: nome do projeto e arquivo de vídeo são os dados essenciais de seus formulários. Outputs devem ser fáceis de encontrar. Não reintroduzir classificação de sensibilidade na interface ou campos obrigatórios sem finalidade concreta.

O progresso conta etapas, não segundos. Todo acesso a um resultado incompleto deve respeitar os indicadores devolvidos pela API. Uma atualização automática não pode trocar a seção que o usuário está lendo. Nunca apresentar revisão manual como análise gerada por IA.

O prompt Gemini está em `app/prompts/gemini-analysis.md`. O serviço `gemini-worker` processa tarefas com transcrição, OCR e telas selecionadas; confira `docs/GEMINI.md` para limites, segurança, estados e testes simulados. Não use a chave de um usuário para testes automatizados.

## Verificar e entregar

Use os testes adequados descritos em [TESTING.md](TESTING.md), confira `git diff --check` e atualize `summary.md`, planos e histórico quando o comportamento mudar. A memória dos agentes complementa o código e os testes; não substitui a verificação do estado atual.

Os scripts `checkpoint.sh`, `media-checkpoint.sh`, `validate.sh`, `prepare-model.sh`, `finalize.sh` e `install-wsl.sh` são históricos da preparação inicial. Inspecione-os antes de usar; alguns copiam arquivos do Windows e podem substituir alterações posteriores. O fluxo diário usa o checkout Linux, Compose e testes diretamente.

Não versionar `.env`, arquivos de acesso, outputs, mídia real, dumps ou dados locais de memória.
