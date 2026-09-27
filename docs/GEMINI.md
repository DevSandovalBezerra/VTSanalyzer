# Gemini: configuração e prompt em revisão

Esta entrega permite salvar/remover uma chave por usuário, testar o acesso consultando os modelos oficiais e salvar um modelo e prompt como rascunho. Não executa análise nem transmite evidências do vídeo. A execução aguarda a aprovação conjunta do prompt e do material pelo usuário.

O texto proposto está em `app/prompts/gemini-analysis.md` e aparece integralmente no editor Análise por IA. Salvar não aprova nem dispara tarefas. O endpoint de análise retorna 409 enquanto essa etapa estiver pendente.

## Chaves

As chaves são criptografadas com sodium secretbox e chave derivada por usuário. O segredo mestre fica no volume privado `/data/secrets/gemini-master.key`, com permissão 0600. Para restauração, preservar esse arquivo junto ao banco. Não aparece nos outputs, no Git ou nas respostas HTTP. A tela exibe apenas os quatro últimos caracteres.

O botão de teste faz somente GET HTTPS para `generativelanguage.googleapis.com/v1beta/models`, com `x-goog-api-key` em cabeçalho, sem redirects, sem conteúdo do vídeo e sem gerar texto. As respostas de erro do provedor não são reproduzidas diretamente. A seleção de modelos vem da API, sem assumir que um modelo fixo está disponível na conta.

## Proposta de material

Transcrição completa e OCR com timestamps, imagens de telas selecionadas e contexto do vídeo/projeto. O painel de uma análise mostra as quantidades e as imagens propostas; não é uma requisição enviada. A implementação da execução deverá verificar a prontidão e a versão das evidências, medir limites de contexto e dividir o material em lotes quando necessário, sem truncamento silencioso.

## Referências oficiais consultadas em 27/09/2026

- https://ai.google.dev/gemini-api/docs/api-key
- https://ai.google.dev/api/models
- https://ai.google.dev/api/generate-content
