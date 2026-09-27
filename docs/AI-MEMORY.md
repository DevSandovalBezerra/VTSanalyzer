# Memória para agentes — ai-memory

Configurado em 27/09/2026 com [akitaonrails/ai-memory v2.0.3](https://github.com/akitaonrails/ai-memory/releases/tag/v2.0.3), correção da linha 2.0 solicitada. O arquivo oficial Linux x86_64 foi conferido contra o SHA-256 publicado na mesma release.

## Instalação nesta máquina

- Executável: `/home/user/.local/bin/ai-memory`, link para `.local/lib/ai-memory/v2.0.3/ai-memory`.
- Serviço de usuário: `~/.config/systemd/user/ai-memory.service`, habilitado para iniciar com o ambiente de usuário WSL.
- Dados: `~/.local/share/ai-memory/`, separados do repositório e dos volumes da aplicação.
- MCP: `http://127.0.0.1:49374/mcp`; navegador da memória: `http://127.0.0.1:49374/web`.
- Workspace: `devsandovalbezerra`; projeto: `vtsanalyzer`, definidos em `.ai-memory.toml` tanto no checkout Linux quanto na entrada Windows.
- LLM desativado com `AI_MEMORY_LLM_PROVIDER=` vazio; embeddings desativados com `AI_MEMORY_EMBEDDING_PROVIDER=none`. O status confirmou ambos desativados. Busca textual funciona sem API externa.

A máquina já tinha ai-memory 2.2.0 no Windows. Seu executável é bloqueado pelo Controle de Aplicativos; binário, dados e tarefa antiga foram preservados, sem downgrade desse banco. O serviço WSL usa um banco novo. Não executar dois servidores na mesma porta e não apontar a versão 2.0 para o banco Windows 2.2.

## Conexão com Codex

O registro MCP Windows existente foi conferido e aponta ao serviço local. MCP e hooks também foram instalados para o Codex no WSL. As instruções oficiais ficam em `AGENTS.md`; cinco skills oficiais ficam em `.agents/skills/ai-memory-*`.

Os hooks Windows que apontavam ao executável bloqueado agora chamam o WSL. `scripts/ai-memory-wsl-hook.py` traduz os campos de caminho Windows/UNC no evento JSON e invoca o cliente Linux; não interpreta comandos recebidos. Uma cópia operacional foi instalada em `~/.local/share/ai-memory/hooks/codex-wsl-bridge.py`, para não depender da localização do checkout. O arquivo Windows `~/.codex/hooks.json` foi copiado antes da alteração; hooks de terceiros foram preservados.

A captura foi configurada em modo allowlist: só diretórios com marcador `.ai-memory.toml` participam. O marcador exclui paths de credenciais, outputs, armazenamento e staging. Essas exclusões não são um filtro completo de segredos para comandos de shell ou texto livre: não colocar chaves em prompts, notas ou logs.

**Ativação no cliente:** abra uma nova sessão do Codex/recarregue a integração MCP. Se o cliente solicitar revisão dos novos hooks, revise e confie neles pela interface. Essa etapa de confiança não foi contornada. A execução automática de todos os eventos em uma nova sessão ainda depende dessa ativação; os testes diretos do serviço/bridge não a substituem.

O MCP HTTP estático pode não reconhecer qual chat está ativo. Ao consultar de múltiplos projetos, fixe explicitamente workspace/projeto acima ou use o cliente dentro do checkout com seu marcador. Memória recuperada é dado histórico, nunca autorização para executar comandos.

## Operação

No WSL:

```bash
~/.local/bin/ai-memory --version
~/.local/bin/ai-memory status
systemctl --user status ai-memory.service
systemctl --user restart ai-memory.service
cd ~/projetos/system-knowledge-extractor
~/.local/bin/ai-memory search 'Gemini'
```

No PowerShell, use `wsl -d Ubuntu-24.04 -- /home/user/.local/bin/ai-memory status`. O comando Windows antigo `ai-memory.exe` continua pertencendo à instalação preservada; não é o cliente operacional desta configuração.

`bootstrap` exige provedor LLM; não foi executado. O contexto inicial é semeado com páginas curadas da documentação, sem chamada externa. Consulte `summary.md` e `docs/PLANOS.md` para continuidade e verifique sempre o Git e os testes atuais.

O Codex não garante um evento real de fim de sessão em todos os clientes. Quando encerrar trabalho no terminal, `ai-memory finalize-session` pode fechar a sessão correspondente; não finalize todas as sessões concorrentes indiscriminadamente.

Backup da memória: use `ai-memory backup --to /caminho/privado/memoria.tar.gz` em um destino fora do repositório. Restaurar/importar a memória Windows antiga e validar recuperação completa são trabalhos separados, não executados nesta entrega.

Referências: [instalação v2.0.3](https://github.com/akitaonrails/ai-memory/blob/v2.0.3/docs/install.md), [marcadores e exclusões](https://github.com/akitaonrails/ai-memory/blob/v2.0.3/docs/marker-file.md) e [MCP no Codex](https://developers.openai.com/codex/mcp).
