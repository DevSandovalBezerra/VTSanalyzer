# Testes e evidências

Última consolidação: 27/09/2026. Os testes de integração executam contra a aplicação real em `http://localhost:8095`, usam Docker e criam contas/projetos com mídia sintética. Execute a partir da raiz WSL, preferencialmente em ambiente de teste. Parte dos testes deixa fixtures e resultados para a etapa seguinte.

## Alterações recentes verificadas em 27/09

- `tests/practical_flow.py`: passou; executa `media.run()`, valida processamento real, formulários mínimos, título por nome de arquivo, biblioteca com/sem análise e isolamento por proprietário.
- `tests/progress.py`: passou; fila, execução, liberação parcial, sincronização, versões antigas, repetição, falha, cancelamento, downloads e análise duplicada.
- `tests/progress_ui.cjs`: passou; bloqueios visuais, contagem de etapas, permanência no monitor, reconexão e configuração de IA acessível sem resultado pronto.
- `tests/gemini_settings.py`: passou com chaves fictícias e transporte simulado. Verifica criptografia por usuário, máscara, CSRF, troca/remoção, catálogo paginado, modelo, rascunho e pré-requisitos da geração. Não realizou chamadas externas.
- `tests/gemini_execution.py`: passou com vídeo sintético e provedor simulado; verifica fila, montagem multimodal, divisão pelo limite, limpeza da cópia temporária, relatório e catálogo de Outputs. O teste pausa e retoma `gemini-worker` para impedir qualquer requisição externa com a chave simulada.
- `tests/gemini_flow_ui.cjs`: passou; configuração separada de resultados, estados de início/progresso/falha/conclusão e apresentação escapada dos avisos.
- PHP lint, sintaxe JS e `git diff --check`: passaram nas alterações recentes.
- Navegador: menu Outputs, transcrição, telas com falas, acompanhamento e configuração Gemini conferidos.

Os testes de integração Python de progresso e Gemini dependem de `/tmp/ske-media-test.json`, criado pelo teste de mídia/fluxo prático. Esse arquivo contém credenciais de teste, é local e não deve ser publicado.

Antes de executar `gemini_execution.py`, aguarde tarefas Gemini reais terminarem: o teste para e reinicia esse processador. Os testes `.cjs` verificam a renderização com dados simulados e não enviam material ao Google.

Sequência para reproduzir a validação recente:

```bash
python3 tests/practical_flow.py
python3 tests/progress.py
python3 tests/gemini_settings.py
python3 tests/gemini_execution.py
node tests/progress_ui.cjs
node tests/gemini_flow_ui.cjs
```

Node 18+ é necessário apenas para o teste de interface (usa fetch), não para executar o frontend. Na máquina atual, esse teste também pode ser executado pelo Node do Windows a partir do arquivo no compartilhamento WSL.

## Suíte anterior

As evidências de 23/09 estão em [STATUS.md](STATUS.md): fundação, persistência, sessões, mídia, transcrição, conhecimento, outputs, leitor e cadastro. Não afirmar que a suíte inteira foi reexecutada em 27/09.

`tests/output_viewer.py` depende de fixtures temporárias de mídia narrada e snapshot aprovado; a tentativa recente ficou bloqueada pela ausência dessas fixtures. Isso não foi contabilizado como teste aprovado. Para reproduzir seu cenário, prepare as fixtures conforme os scripts de mídia, conhecimento e transcrição e confira os caminhos esperados no próprio teste.

`tests/test_gateway.py` valida o contrato legado, não uma chamada real ao Gemini. `scripts/verify-foundation.sh` recria containers e deve ser usado em uma janela apropriada.

## Ainda não validado

Qualidade semântica do relatório real e de outros vídeos de referência, custo e limites de contexto; carga e vídeos de duas horas; GPU; restauração completa de backup; implantação pública de produção. A saúde dos containers não substitui esses testes.

## Verificação da publicação e memória — 27/09

Links Markdown locais e `git diff --check` passaram. A varredura de assinaturas comuns de credenciais encontrou zero candidatos em 97 blobs históricos e 95 arquivos de trabalho antes da publicação; não é garantia de detecção universal de segredos. Nenhum arquivo de runtime privado entrou no conjunto publicado.

O bridge Windows/WSL foi verificado com caminhos de drive/UNC, campos aninhados e uma sequência sintética SessionStart/UserPromptSubmit/SessionEnd. O servidor persistiu os três eventos. Inicialização MCP e catálogo de ferramentas responderam. Escrita de contexto e busca por Gemini passaram; providers LLM e embedding reportaram disabled. A ativação de hooks confiáveis em nova sessão real do cliente permanece pendente.

## Verificação Gemini com a chave cadastrada — 27/09/2026

A chamada mínima `countTokens` confirmou HTTP 404 para `gemini-2.5-flash` e HTTP 200 para `gemini-3.8-flash`. O material de uma análise existente foi medido sem imprimir seu conteúdo: 382 segmentos de fala, 87 telas e 9 lotes. Após a correção, a primeira tarefa chegou a 5/10 e recebeu HTTP 503; a repetição limitada foi aplicada. A execução seguinte concluiu 10/10 com uma repetição HTTP 503 observada em log, Markdown salvo e relatório aberto no Chrome. Dez referências de horário geraram avisos de revisão. Nenhuma chave, fala, imagem ou relatório privado foi incluído nesta documentação.

## Revisão documental — 27/09/2026

Arquitetura, API, instalação, configuração, operação e guia de uso foram confrontados com os fontes no estado `fd93cc0`. Esta atualização altera somente Markdown. A conferência passou em 17 documentos e 37 links relativos, com blocos de código fechados e `git diff --check` sem erros; os testes funcionais listados acima são evidências da entrega anterior, não uma nova execução nesta revisão.
