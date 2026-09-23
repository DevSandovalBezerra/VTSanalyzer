# Estado de execução do PRD

Fonte: PRD 0.2 de 23/09/2026. Este arquivo descreve evidências reais, sem equivaler scaffold a requisito concluído.

## Fundação em validação

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

## Critérios ainda não concluídos

As fases de mídia, revisão multimodal, conhecimento, comparação, exportação e robustez continuam dependentes da validação da fundação. Os cenários CA-01 a CA-06 não devem ser declarados aprovados sem seus testes integrados.
