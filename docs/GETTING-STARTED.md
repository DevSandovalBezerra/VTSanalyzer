# Instalação e primeiro uso

## Instalação nova

Pré-requisitos: Ubuntu no WSL2, Git, Docker Desktop com integração WSL habilitada e espaço para vídeos, derivados e modelo. Execute no terminal Linux:

```bash
mkdir -p ~/projetos
cd ~/projetos
git clone https://github.com/DevSandovalBezerra/VTSanalyzer.git system-knowledge-extractor
cd system-knowledge-extractor
cp .env.example .env
```

Edite `.env` e substitua as duas senhas placeholder. Depois:

```bash
chmod 600 .env
docker compose build worker
bash scripts/prepare-outputs.sh
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait
bash scripts/download-model.sh
```

O serviço web aplica as migrações automaticamente ao iniciar.

Abra [localhost:8095](http://localhost:8095), clique em **Registrar** e crie sua conta. Usuário simples tem 3–40 caracteres; a senha admite 6 ou mais caracteres. O script administrativo `app/bin/create-user.php` é uma alternativa de operação e exige senha maior; o cadastro pelo navegador não depende dele.

## Primeiro vídeo

Crie um projeto com nome, envie o arquivo e aguarde sua validação. Título vazio usa o nome do arquivo. Objetivo, origem e contexto são opcionais; os detalhes de captura ficam recolhidos. Inicie a análise após a mídia estar pronta.

Na central **Outputs**, veja o progresso e abra **Transcrição** ou **Telas principais** quando liberados. Transcrição oferece TXT, SRT e JSON. Telas associa imagens, OCR e falas do trecho.

Para gerar o relatório, abra **Configurar Gemini** no menu lateral, salve sua chave, teste a conexão e confira o modelo selecionado. O prompt-base está pronto; sua edição é opcional. Em **Outputs → Análise por IA**, escolha o vídeo processado e clique em **Iniciar análise com Gemini**. A mesma tela acompanha as partes e, ao terminar, mostra **Relatório pronto**, com leitura, cópia e download. A configuração é por conta e fica separada da consulta de resultados. Veja o [guia de uso](GUIA-DE-USO.md).

## Instalação existente

O código canônico desta máquina está em `/home/user/projetos/system-knowledge-extractor`. Não clone por cima dele e não execute novamente os scripts históricos que copiam o pacote Windows.

No Windows, os outputs estão em:
`\\wsl.localhost\Ubuntu-24.04\home\user\projetos\system-knowledge-extractor\outputs`.

Se o diagnóstico indicar ausência do modelo, execute o download com o worker ativo. Falhas de mídia ou falta de espaço aparecem na análise/diagnóstico. Os testes usam arquivos sintéticos; não é necessário reenviar vídeos reais para validar a instalação.

Para rotina e restauração, veja [DEPLOYMENT.md](DEPLOYMENT.md).
