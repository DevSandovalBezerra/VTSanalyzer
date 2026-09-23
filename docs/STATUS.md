# Estado de execução do PRD

Fonte: PRD 0.2 de 23/09/2026. Este arquivo descreve evidências reais, sem equivaler scaffold a requisito concluído.

## Fundação validada em 23/09/2026

- PHP 8.3, Python 3.12, PostgreSQL 17, Redis 7.4, Nginx e scheduler.
- Docker Compose de desenvolvimento e imagens com código para produção.
- Autenticação Argon2id, proteção CSRF, cookies HttpOnly/SameSite, regeneração da sessão e limite de tentativas.
- Projetos persistentes com histórico de versões, arquivamento e isolamento por proprietário.
- Outbox PostgreSQL para tarefas e despacho Redis; worker determinístico de diagnóstico.
- FFmpeg e Tesseract no worker; pasta alvo somente leitura; volumes persistentes.

## Decisões

- PHP modular próprio e JavaScript nativo; a primeira interface usa CSS local sem dependência de CDN.
- CPU padrão e transcrição local a configurar na fase de mídia.
- Astra preparado, mas inativo por decisão explícita do usuário. Não presumir credenciais nem usar a sessão do Codex como API.
- Arquivos do projeto-alvo não serão executados.

Os seis serviços ficaram saudáveis. O teste recriou todos os containers e comprovou a persistência de PostgreSQL, Redis e mídia, os bind mounts PHP/Python e a recusa de escrita em `/target`. Commit inicial da fundação: `12bb1be`.

## Fluxo local implementado

- Upload de MP4/MOV/MKV/WebM em blocos de 8 MiB, retomada com validação de SHA-256 e envio idempotente.
- Validação real de contêiner, duração, resolução e decodificação inicial usando FFprobe/FFmpeg, no worker.
- Perfis rápido/equilibrado/detalhado, extração de áudio, adaptador local faster-whisper, frames periódicos e por cena, timestamps reais, deduplicação e OCR Tesseract com coordenadas.
- Pré-verificação do worker, motores, espaço de disco e modelo de transcrição em cache.
- Monitor com polling, cancelamento e retomada por etapa; resultados anteriores válidos permanecem intactos.
- Player com HTTP Range, seleção de frame/fala, pesquisa de OCR/transcrição e edição de fala com histórico.
- Ocultação de regiões em imagens derivadas; invalidação do OCR e das aprovações; proteção contra recaptura que restauraria pixels na mesma versão.
- Registro e revisão manual de telas, regras, eventos, fluxos descritivos, entidades, padrões, conceitos e lacunas. Classificação e evidências obrigatórias.
- Aprovação bloqueada por pendências; snapshot imutável para exportação.
- Inventário textual do projeto-alvo montado em leitura; exclusão de dependências, arquivos de segredo, binários e symlinks. Nenhum código-alvo é executado.
- ZIP autocontido com Markdown, JSON, YAML 1.2 (subconjunto JSON), Mermaid básico, evidências citadas por itens aprovados, contexto para Codex e manifesto de hashes.
- Gateway neutro com contrato validado, prompt versionado e bloqueio explícito de chamadas externas.

## Evidências de teste

- `tests/foundation.py`: autenticação, CSRF, isolamento entre contas, projetos versionados, fila real, FFmpeg, OCR e logout — passou.
- `scripts/verify-foundation.sh`: persistência após recriação de todos os containers, montagem de código e alvo somente leitura — passou.
- `tests/media.py`: arquivo MP4 sintético real, upload/retomada/idempotência, metadados, pipeline, timestamps, reexecução de OCR sem refazer frames/áudio e streaming parcial — passou.
- `tests/knowledge.py`: evidência inválida rejeitada, revisão obrigatória, pixels pretos no frame do ZIP, reaprovação após ocultação, imutabilidade do snapshot, hashes do pacote e inventário somente leitura — passou.
- `tests/test_gateway.py`: cinco testes do contrato, incluindo NaN, evidência inexistente, classificação ausente, bloqueio de aprovação pelo modelo e integração desativada — passaram.
- `tests/transcription.py`: narração sintética em português, sem dados do usuário; motor CPU real, timestamps e histórico de correções — passou. O texto reconhecido contém integralmente a demonstração do cadastro de clientes e o comando de salvar.
- `tests/sessions.py`: a sessão autenticada sobrevive à recriação do container PHP usando armazenamento Redis — passou. O modelo Whisper também carregou com `local_files_only=True`.

## Limites desta entrega e próximas fases

O PRD completo **ainda não está concluído**. A entrega é uma primeira implementação funcional local, não a aprovação integral dos 128 RFs e 30 RNFs.

- Astra: adaptador preparado, deliberadamente inativo. Análise multimodal, regras automáticas, contradições, comparação semântica e recomendações automáticas dependem de definir e integrar o provedor.
- O comparador atual fornece inventário seguro e revisão manual; não produz uma matriz automática de equivalência funcional. Importação ZIP/Git não foi implementada; a integração disponível é a pasta autorizada.
- O editor atual registra fluxos como conclusões descritivas. Editor visual de nós/arestas, eventos estruturados com transições, união/divisão de telas e restauração de revisões permanecem pendentes.
- O plano exportado é um roteiro explícito de revisão; não inventa estimativas ou dependências específicas do código. Mermaid contém os fluxos aprovados como nós independentes.
- Papéis granulares, colaboração, retenção/exclusão administrada, backup/restauração automatizados, métricas de produção e migrações reversas ainda precisam da fase de robustez.
- CPU foi o ambiente exercitado. A configuração GPU é opt-in e requer imagem CUDA/drivers compatíveis e validação própria.
- O frontend inicial usa JavaScript nativo e CSS local. Tailwind permanece uma recomendação a incorporar se for adotado um build frontend.
- A captura está limitada a 600 frames por método por execução; vídeos longos exigem ampliar o intervalo. Não foi comprovado o desempenho para vídeos de duas horas.
- CA-01/CA-02/CA-05 completos dependem da análise semântica futura. CA-03/CA-04/CA-06 tiveram seus mecanismos locais exercitados, mas não constituem avaliação de qualidade com vídeos reais de domínio.

Não interpretar a presença dos arquivos de saída como conclusão das funcionalidades semânticas que dependem do Astra.

## Outputs por projeto — 23/09/2026

- Pasta `outputs/<nome>--<id>/` no WSL, legível pelo Windows, separada por vídeo e versão da análise.
- Áudio e imagens têm localização única; transcrição TXT/SRT/JSON, OCR, telas, conclusões e histórico são sincronizados por outbox transacional após revisões.
- Snapshots e ZIPs ficam junto à análise; inventários pertencem à pasta do projeto. O nome físico da pasta é estável, e os metadados acompanham renomeações.
- Dados existentes migrados com comparação SHA-256 antes da remoção dos arquivos antigos. Outputs excluídos do Git e do contexto Docker; acesso do web somente leitura.
- `tests/media.py`, `tests/knowledge.py` e `tests/transcription.py` passaram novamente. `tests/outputs.py` verificou atualização após edição, separação de projetos com nomes iguais, renomeação, áudio, frames ocultados, streaming, ZIPs e inventários.

## Interface de outputs — 23/09/2026

- Aba **Frames e textos**, com acesso direto pelos cartões de vídeo/versão.
- Galeria com zoom, miniaturas, navegação, filtros, OCR/fala por frame e download; reprodução opcional do áudio extraído.
- Leitor de TXT/SRT/JSON com busca destacada, cópia, download e apresentação legível de transcrição, OCR, telas e conclusões.
- Catálogo autenticado e restrito à análise do proprietário. Prévia textual limitada a 2 MB; nenhum HTML dos outputs é interpretado.
- `tests/output_viewer.py` passou. Verificação no navegador confirmou galeria, filtros, navegação, zoom, leitura/original, transcrição e busca de termos, sem erros JavaScript registrados.

## Cadastro livre — 23/09/2026

- Botão **Registrar** na entrada, com usuário simples ou e-mail, senha a partir de 6 caracteres, confirmação e opção de mostrar/ocultar senha.
- Sem convite, aprovação administrativa ou verificação de e-mail. A criação inicia a sessão automaticamente e usa explicitamente o papel `user`.
- Login sem distinção entre maiúsculas/minúsculas, bloqueio de duplicidades inclusive por concorrência, hash Argon2id, CSRF e isolamento de projetos preservados.
- `tests/registration.py` passou: validação, senha simples, confirmação, duplicidade, sessão, papel comum mesmo com tentativa de enviar admin, hash, acesso antigo e isolamento.
- Tela de entrada e formulário de cadastro conferidos visualmente no navegador.
