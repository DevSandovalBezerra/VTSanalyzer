# Gemini: análise do vídeo

O prompt-base revisado está em `app/prompts/gemini-analysis.md`. Use **Configurar Gemini** no menu lateral para salvar a chave da conta, testar a conexão, escolher o modelo e, se necessário, editar o prompt avançado. Depois abra **Outputs → Análise por IA**, escolha um vídeo, inicie a análise e acompanhe o progresso. Ao terminar, a mesma tela mostra o relatório para leitura, cópia e download. O Markdown também fica em **Outputs → Todos os arquivos**.

Em 27/09/2026, a recusa inicial foi atribuída ao modelo salvo `gemini-2.5-flash`: aparecia na listagem, porém `countTokens` mínimo retornou HTTP 404 para a chave da conta. Após escolher `gemini-3.8-flash` e tratar uma falha temporária HTTP 503, uma análise real terminou em 10/10 e o relatório foi salvo em Outputs. Dez horários do texto não foram encontrados nas evidências e aparecem como avisos para revisão humana. Não cole chaves em chat, código ou Git.

## Chave e envio

A chave é criptografada com sodium secretbox e derivação por usuário. O segredo mestre fica em `/data/secrets/gemini-master.key` no volume privado, com permissão 0600; preserve-o ao restaurar o banco. A API nunca devolve a chave, apenas os quatro últimos caracteres. O teste de conexão consulta a lista de modelos e faz uma chamada mínima `countTokens` para comprovar que o selecionado responde com esta chave; não envia material do vídeo. Ao trocar o modelo, a aplicação também prova seu uso antes de salvar. Modelos listados podem estar indisponíveis para uma chave específica.

Ao clicar em **Iniciar análise com Gemini**, o servidor exige chave testada, modelo salvo e transcrição/telas sincronizadas. A tarefa é gravada no PostgreSQL e executada pelo serviço `gemini-worker`. Ele envia ao Gemini somente transcrição com horários, OCR, imagens de telas selecionadas, título, contexto e objetivo do projeto. Vídeo e áudio originais não são enviados. Uma chave trocada ou evidências alteradas antes do início invalidam a tarefa. O prompt e as versões das evidências são congelados no pedido. No início da tarefa, as imagens selecionadas são copiadas para uma pasta privada temporária, conferidas novamente e removidas ao terminar. A interface mostra fila, progresso por partes, erro ou conclusão; pode cancelar e iniciar outra análise.

O servidor escapa títulos, contexto, fala e OCR antes de preencher o prompt. Cada tela recebe IDs `IMG-###` e `OCR-###` com horário, e a imagem é enviada junto ao bloco correspondente. A API `countTokens` mede cada lote segundo os limites informados para o modelo, reservando saída. Lotes que não cabem são divididos; relatórios parciais são consolidados, sem descartar evidência silenciosamente. Se uma evidência isolada ou relatório não couber, a tarefa falha com indicação para escolher um modelo maior. Respostas interrompidas por limite de saída não são tratadas como relatório completo. Para Gemini 3, a geração usa `thinkingLevel: low` e reserva até 16.384 tokens de saída conforme o limite do modelo. Chamadas com timeout ou HTTP 408/429/500/502/503/504 têm até cinco tentativas com espera exponencial e variação aleatória; outros erros mostram seu código HTTP e não são repetidos.

O resultado é salvo em `outputs/<projeto>/videos/<vídeo>/analises/<versão>/gemini/<id>.md` e no banco, com mapa dos IDs de evidência. IDs e horários citados são conferidos automaticamente contra o material enviado; referências desconhecidas geram avisos. Essa conferência não valida a interpretação do modelo, portanto o relatório ainda requer revisão humana.

Rascunhos anteriores à revisão do prompt não são substituídos. Para usar o texto novo, clique em **Restaurar prompt-base** e **Salvar prompt**. O prompt deve conservar os seis marcadores `{{TITULO}}`, `{{CONTEXTO}}`, `{{OBJETIVO_PROJETO}}`, `{{COBERTURA_ENVIADA}}`, `{{TRANSCRICAO}}` e `{{TELAS}}`.

## Operação e validação

Em desenvolvimento, inicie com `docker compose -f compose.yml -f compose.dev.yml up -d`. O serviço web aplica migrações antes de ficar pronto; `gemini-worker` aguarda o web. Para conferir os serviços: `docker compose -f compose.yml -f compose.dev.yml ps web gemini-worker`.

`tests/gemini_settings.py` verifica criptografia, CSRF, isolamento, teste de modelos simulado e pré-requisitos. `tests/gemini_flow_ui.cjs` verifica os estados do percurso e a separação entre configuração e relatório. `tests/gemini_execution.py` usa mídia sintética e transporte simulado para verificar montagem multimodal, divisão por limite, limpeza das cópias temporárias, persistência e leitura do relatório, sem chamadas ao Google. Os testes automatizados não usam chave ou vídeo real do usuário. A validação manual de 27/09 usou a chave já cadastrada e um vídeo existente para executar uma análise completa; o conteúdo privado permaneceu fora do Git.

## Referências oficiais

- [Geração de conteúdo e respostas](https://ai.google.dev/api/generate-content)
- [Contagem de tokens](https://ai.google.dev/api/tokens)
- [Imagens inline na API generateContent](https://ai.google.dev/gemini-api/docs/generate-content/image-understanding)
