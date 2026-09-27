# VTSanalyzer

Aplicação interna para destrinchar vídeos: transcrição local, capturas de tela, OCR, revisão de evidências e exportação do conhecimento. A interface prioriza os **Outputs**, com acesso direto a **Transcrição**, **Telas principais** e **Análise por IA**.

Estado em **27/09/2026**: processamento local e leitura de resultados funcionais. O prompt-base está ligado à execução Gemini em segundo plano, com relatório na área Análise por IA e em Outputs. Os testes automatizados usam provedor simulado. A interface mostrou uma chave testada e uma tentativa real que falhou; ainda não há relatório real concluído. O PRD completo ainda não está concluído.

## Fluxo de uso

1. Entre ou use **Registrar** para criar uma conta.
2. Crie um projeto informando seu nome. O objetivo é opcional.
3. Envie um vídeo MP4, MOV, MKV ou WebM. O título pode ficar vazio: será usado o nome do arquivo. Origem e contexto são opcionais.
4. Após a validação do vídeo, inicie uma análise e acompanhe o processamento.
5. Abra a transcrição ou as telas quando o respectivo resultado estiver disponível. A barra indica etapas concluídas; não estima tempo restante.
6. Em **Configurar Gemini**, salve sua chave, teste a conexão e escolha o modelo. O prompt-base já está pronto; sua edição fica em instruções avançadas. Depois abra **Outputs → Análise por IA**, escolha um vídeo processado, clique em **Iniciar análise com Gemini**, acompanhe o progresso e leia ou baixe o relatório na mesma tela.

O menu Outputs abre depois do login e permite filtrar por projeto ou buscar vídeos. Cada versão de análise tem seus próprios resultados. A galeria associa imagens ao OCR e às falas do trecho. O leitor oferece busca, cópia e download. A revisão manual e a exportação de conhecimento continuam disponíveis.

## Executar

Ambiente validado: Ubuntu 24.04 no WSL2 com Docker Desktop integrado. A aplicação abre em [localhost:8095](http://localhost:8095). O WAMP não hospeda a aplicação.

Consulte [Instalação e primeiro uso](docs/GETTING-STARTED.md) para o passo a passo. Em uma instalação existente:

```bash
cd ~/projetos/system-knowledge-extractor
docker compose -f compose.yml -f compose.dev.yml up -d --wait
```

Para baixar os pesos de transcrição na primeira instalação, execute `bash scripts/download-model.sh` com os serviços ativos. A transcrição usa faster-whisper local; áudio não é enviado para esse processamento.

## Dados e resultados

Os originais ficam no volume Docker `media`. Os derivados ficam em `outputs/<nome-do-projeto>--<id>/videos/<id>/analises/vNNN--<id>/`, separados por projeto, vídeo e versão. Há transcrição TXT/SRT/JSON, áudio quando disponível, imagens, OCR, telas, relatório Gemini quando solicitado, histórico, snapshots e exportações.

O GitHub contém código, documentação e fixtures sintéticas. Vídeos reais, outputs, banco, chaves e `.env` ficam fora do Git. Fazer push não é fazer backup dos dados da aplicação.

## Documentação

- [summary.md](summary.md): contexto rápido para retomar o trabalho.
- [Planos e status](docs/PLANOS.md): entregas concluídas, pendências e critérios de aceite.
- [Histórico de validações](docs/STATUS.md): evidências por data.
- [Arquitetura](docs/ARCHITECTURE.md), [configuração](docs/CONFIGURATION.md) e [API](docs/API.md).
- [Instalação](docs/GETTING-STARTED.md), [desenvolvimento](docs/DEVELOPMENT.md), [testes](docs/TESTING.md) e [operação](docs/DEPLOYMENT.md).
- [Gemini](docs/GEMINI.md) e [prompt-base definido](app/prompts/gemini-analysis.md).
- [Memória para agentes](docs/AI-MEMORY.md).
- [PRD original](docs/PRD.md): escopo de referência; não é um relatório de funcionalidades concluídas.
- [Gateway legado](docs/GATEWAY.md): contrato inicial, ainda desconectado do pipeline.

O nome técnico `system-knowledge-extractor` permanece nos serviços, imagens e instalação WSL para preservar os dados existentes.
