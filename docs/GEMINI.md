# Gemini: análise do vídeo

O prompt-base revisado está em `app/prompts/gemini-analysis.md`. Use **Configurar Gemini** no menu lateral para salvar a chave da conta, testar a conexão, escolher o modelo e, se necessário, editar o prompt avançado. Depois abra **Outputs → Análise por IA**, escolha um vídeo, inicie a análise e acompanhe o progresso. Ao terminar, a mesma tela mostra o relatório para leitura, cópia e download. O Markdown também fica em **Outputs → Todos os arquivos**.

A interface mostrou uma chave real testada e uma tarefa que falhou por recusa do material ou modelo em 27/09/2026. A integração foi verificada com chave e respostas simuladas; ainda é necessário investigar a falha e validar um relatório real concluído. Não cole chaves em chat, código ou Git.

## Chave e envio

A chave é criptografada com sodium secretbox e derivação por usuário. O segredo mestre fica em `/data/secrets/gemini-master.key` no volume privado, com permissão 0600; preserve-o ao restaurar o banco. A API nunca devolve a chave, apenas os quatro últimos caracteres. O teste de conexão consulta somente a lista de modelos em HTTPS; não envia material do vídeo.

Ao clicar em **Iniciar análise com Gemini**, o servidor exige chave testada, modelo salvo e transcrição/telas sincronizadas. A tarefa é gravada no PostgreSQL e executada pelo serviço `gemini-worker`. Ele envia ao Gemini somente transcrição com horários, OCR, imagens de telas selecionadas, título, contexto e objetivo do projeto. Vídeo e áudio originais não são enviados. Uma chave trocada ou evidências alteradas antes do início invalidam a tarefa. O prompt e as versões das evidências são congelados no pedido. No início da tarefa, as imagens selecionadas são copiadas para uma pasta privada temporária, conferidas novamente e removidas ao terminar. A interface mostra fila, progresso por partes, erro ou conclusão; pode cancelar e iniciar outra análise.

O servidor escapa títulos, contexto, fala e OCR antes de preencher o prompt. Cada tela recebe IDs `IMG-###` e `OCR-###` com horário, e a imagem é enviada junto ao bloco correspondente. A API `countTokens` mede cada lote segundo os limites informados para o modelo, reservando saída. Lotes que não cabem são divididos; relatórios parciais são consolidados, sem descartar evidência silenciosamente. Se uma evidência isolada ou relatório não couber, a tarefa falha com indicação para escolher um modelo maior. Respostas interrompidas por limite de saída não são tratadas como relatório completo.

O resultado é salvo em `outputs/<projeto>/videos/<vídeo>/analises/<versão>/gemini/<id>.md` e no banco, com mapa dos IDs de evidência. IDs e horários citados são conferidos automaticamente contra o material enviado; referências desconhecidas geram avisos. Essa conferência não valida a interpretação do modelo, portanto o relatório ainda requer revisão humana.

Rascunhos anteriores à revisão do prompt não são substituídos. Para usar o texto novo, clique em **Restaurar prompt-base** e **Salvar prompt**. O prompt deve conservar os seis marcadores `{{TITULO}}`, `{{CONTEXTO}}`, `{{OBJETIVO_PROJETO}}`, `{{COBERTURA_ENVIADA}}`, `{{TRANSCRICAO}}` e `{{TELAS}}`.

## Operação e validação

Em desenvolvimento, inicie com `docker compose -f compose.yml -f compose.dev.yml up -d`. O serviço web aplica migrações antes de ficar pronto; `gemini-worker` aguarda o web. Para conferir os serviços: `docker compose -f compose.yml -f compose.dev.yml ps web gemini-worker`.

`tests/gemini_settings.py` verifica criptografia, CSRF, isolamento, teste de modelos simulado e pré-requisitos. `tests/gemini_flow_ui.cjs` verifica os estados do percurso e a separação entre configuração e relatório. `tests/gemini_execution.py` usa mídia sintética e transporte simulado para verificar montagem multimodal, divisão por limite, limpeza das cópias temporárias, persistência e leitura do relatório, sem chamadas ao Google. Nenhum teste usa chave ou vídeo real do usuário.

## Referências oficiais

- [Geração de conteúdo e respostas](https://ai.google.dev/api/generate-content)
- [Contagem de tokens](https://ai.google.dev/api/tokens)
- [Imagens inline na API generateContent](https://ai.google.dev/gemini-api/docs/generate-content/image-understanding)
