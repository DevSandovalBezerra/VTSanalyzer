# Gemini: prompt-base definido, execução pendente

Esta entrega permite salvar/remover uma chave por usuário, testar o acesso consultando os modelos oficiais e salvar um modelo e prompt por usuário como rascunho. O prompt-base foi revisado em 27/09/2026. A execução ainda não foi implementada: não gera análise nem transmite evidências do vídeo.

O texto proposto está em `app/prompts/gemini-analysis.md` e aparece integralmente no editor Análise por IA. Salvar não dispara tarefas. O endpoint de análise retorna 409 enquanto a execução não estiver implementada. Rascunhos já salvos por contas existentes não são substituídos automaticamente; o usuário pode restaurar o prompt-base no editor.

## Chaves

As chaves são criptografadas com sodium secretbox e chave derivada por usuário. O segredo mestre fica no volume privado `/data/secrets/gemini-master.key`, com permissão 0600. Para restauração, preservar esse arquivo junto ao banco. Não aparece nos outputs, no Git ou nas respostas HTTP. A tela exibe apenas os quatro últimos caracteres.

O botão de teste faz somente GET HTTPS para `generativelanguage.googleapis.com/v1beta/models`, com `x-goog-api-key` em cabeçalho, sem redirects, sem conteúdo do vídeo e sem gerar texto. As respostas de erro do provedor não são reproduzidas diretamente. A seleção de modelos vem da API, sem assumir que um modelo fixo está disponível na conta.

## Proposta de material

Transcrição completa e OCR com timestamps, imagens de telas selecionadas e contexto do vídeo/projeto. O painel de uma análise mostra as quantidades e as imagens propostas; não é uma requisição enviada. A implementação da execução deverá verificar a prontidão e a versão das evidências, medir limites de contexto e dividir o material em lotes quando necessário, sem truncamento silencioso.


## Contrato de montagem para a implementação

As notas de montagem do arquivo de revisão `C:/wamp64/www/VTSAnalizer/prompt-analise-video.md` não fazem parte do prompt enviado ao modelo. O texto executável é somente `app/prompts/gemini-analysis.md`.

- Enviar cada segmento de transcrição com início e fim reais, mantendo falante quando conhecido. Declarar em `cobertura_enviada` se o material é completo ou parcial e quais intervalos estão ausentes ou incertos.
- Associar cada imagem selecionada a OCR, timestamp e um ID legível `IMG-###`/`OCR-###`. Persistir o mapa para os IDs internos da aplicação. Enviar a imagem como parte multimodal na posição da tela correspondente.
- Escapar os textos de título, contexto, transcrição e OCR antes de inserir em marcas estruturais. Conteúdo do vídeo nunca pode fechar uma tag do prompt.
- Medir o tamanho de entrada e reservar saída segundo os limites do modelo retornado pela API. Dividir e consolidar quando necessário, com índices e cobertura explícitos; nunca truncar silenciosamente.
- Conferir IDs e horários citados pelo relatório contra o material realmente enviado. Referência válida não prova por si que a conclusão é correta; manter revisão humana.

Um rascunho salvo por uma conta antes desta revisão não muda automaticamente. O botão **Restaurar proposta inicial** carrega o prompt-base atual no editor para conferência e salvamento opcional.

## Referências oficiais consultadas em 27/09/2026

- https://ai.google.dev/gemini-api/docs/api-key
- https://ai.google.dev/api/models
- https://ai.google.dev/api/generate-content
