# Planos e status

Consolidado em 27/09/2026. Concluído significa implementado e com evidência no escopo descrito, não aprovação integral do PRD. Os planos abaixo organizam a entrega existente e a continuidade; não atribuem estimativas sem avaliação.

## P01 — Fundação e contas · CONCLUÍDO

PHP/Python/PostgreSQL/Redis/Nginx, fila, volumes, autenticação, cadastro livre e projetos isolados. Evidências históricas: foundation, sessions, registration e verify-foundation. Restam requisitos corporativos em P10.

## P02 — Extração local · CONCLUÍDO no escopo CPU

Upload retomável, validação, áudio, transcrição local, frames, OCR e seleção de telas; cancelamento/retomada por etapa. Evidências: media, transcription e practical_flow. Desempenho de vídeos longos e GPU estão em P10.

## P03 — Revisão e exportação · CONCLUÍDO no escopo manual

Evidências, edição de fala, conclusões manuais, ocultação, snapshot aprovado, inventário somente leitura e ZIP. Evidências históricas: knowledge e outputs. Inferência automática e comparação semântica não fazem parte desta conclusão.

## P04 — Fluxo prático e Outputs · CONCLUÍDO

Entrada em Outputs; atalhos de transcrição, telas e IA; busca/filtro; título por arquivo; objetivo, origem e contexto opcionais. Classificação de sensibilidade removida das telas. Evidências: practical_flow e navegador.

## P05 — Progresso e bloqueios · CONCLUÍDO

Etapas visíveis, acompanhamento automático, proteção contra navegação involuntária, resultados bloqueados até sincronização da versão correta e rejeição de início duplicado. Evidências: progress, progress_ui e navegador.

## P06 — Gemini: configuração e prompt · IMPLEMENTADO; PROMPT EM APROVAÇÃO

Chave criptografada por conta, teste/listagem de modelos, editor de rascunho e prévia do material. Evidência: gemini_settings com transporte simulado e navegador. A geração está bloqueada no servidor.

**Próximo passo:** revisar com o usuário o texto de `app/prompts/gemini-analysis.md`. Testar conexão com chave real fornecida na interface. Salvar um rascunho não equivale a aprová-lo.

## P07 — Gemini: execução e resultados · PLANEJADO

Depende de P06 aprovado. Implementar:
- congelamento da versão de prompt e evidências selecionadas;
- contagem de contexto e divisão em lotes, sem truncamento silencioso;
- chamada Gemini no servidor/fila, sem revelar a chave;
- estados claros de fila, execução, falha, cancelamento e resultado;
- análise do assunto, cronologia, telas, processos e aplicação prática com referências;
- persistência, leitura e exportação do resultado na área Análise por IA.

**Aceite:** executar com mídia autorizada; recuperar referências válidas; exibir limitações; testar falhas de autenticação, cota, timeout e retomada; demonstrar no navegador que o resultado vem do Gemini.

## P08 — Documentação e publicação · EM FINALIZAÇÃO

README, arquitetura, instalação, configuração, API, testes, operação, planos e summary preparados a partir do código. Publicar código e docs na main do GitHub informado, sem dados de trabalho.

**Aceite:** links locais válidos, diferenças revisadas, commit criado e hash remoto igual ao local.

## P09 — Contexto entre agentes · EM IMPLANTAÇÃO

Instalar ai-memory da linha 2.0 no WSL, preservar instalação anterior Windows, registrar contexto e decisões, conectar o acesso pelo Codex e documentar operação.

**Aceite:** serviço acessível, versão confirmada, memória gravada/consultada e configuração dos agentes conferida. Limitações de captura no cliente devem ficar explícitas.

## P10 — Evolução posterior · BACKLOG

Comparação semântica com código-alvo; importação ZIP/Git; editor visual de fluxos; transições estruturadas; união/divisão de telas; restauração de revisões; papéis e colaboração; políticas de exclusão; backup/restauração; métricas, carga, GPU e vídeos longos. Não iniciar tudo automaticamente: priorizar depois da análise Gemini funcional.

## Decisões vigentes

1. Uso interno, foco em extrair e entender o vídeo. Evitar campos obrigatórios sem necessidade.
2. Sensibilidade saiu da interface. O campo legado continua com padrão interno para compatibilidade.
3. Outputs é a entrada principal; resultados incompletos ficam indisponíveis.
4. O progresso mede etapas concluídas, sem falsa estimativa de tempo.
5. Gemini é o provedor solicitado; Astra permanece apenas como contrato legado desconectado.
6. Prompt precisa de aprovação conjunta antes de implementar/liberar geração.
7. Código canônico no WSL; WAMP é apenas entrega/abertura.
8. Documentação e memória registram fatos comprovados e pendências, sem tratar planos como entregas.
