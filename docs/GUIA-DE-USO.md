# Guia de uso do VTSanalyzer

Atualizado em 27/09/2026. Acesse [localhost:8095](http://localhost:8095) e entre na sua conta. **Outputs** é a entrada principal para consultar os vídeos e seus resultados; use a busca ou o filtro de projeto.

## Do vídeo aos resultados locais

1. Crie um projeto com nome. O objetivo é opcional.
2. Use **Enviar vídeo** e selecione MP4, MOV, MKV ou WebM. Sem título, o sistema usa o nome do arquivo; origem e contexto são opcionais.
3. Aguarde a validação do arquivo e inicie o processamento. Escolha o perfil; ajustes de captura são opcionais.
4. Acompanhe as etapas de áudio, transcrição, captura, OCR e agrupamento de telas. O progresso conta etapas concluídas e não estima o tempo restante.
5. Abra **Transcrição** para ler/copiar/baixar o texto, ou **Telas principais** para ver imagens, OCR e falas associadas. Cada acesso é liberado quando seu resultado está pronto e sincronizado.

Se a conexão cair durante o upload, selecione novamente o mesmo arquivo para continuar. Se o processamento falhar, consulte a mensagem na tela de processamento; resultados pendentes continuam bloqueados.

## Configurar o Gemini na conta

Abra **Configurar Gemini** no menu lateral. Salve sua chave API e clique em **Testar conexão**. O teste consulta o catálogo e verifica acesso ao modelo; confira a escolha exibida. Se escolher outro modelo, clique em **Salvar modelo**.

O prompt-base já está pronto. A edição fica em **Instruções avançadas** e é opcional. Ao editar, preserve os seis marcadores de material e clique em **Salvar prompt**. Salvar o modelo e salvar o prompt são ações independentes. Uma configuração feita a partir de um vídeo oferece **Voltar à análise do vídeo**.

## Iniciar a análise e ler o relatório

1. Abra **Outputs → Análise por IA** e escolha um vídeo com transcrição e telas concluídas.
2. Confira a quantidade de trechos, blocos de OCR e imagens informada na tela. Clique em **Iniciar análise com Gemini**.
3. Acompanhe **Análise em andamento**. O processamento acontece em segundo plano; a página se atualiza automaticamente. Fechar o navegador não cancela a tarefa.
4. Ao aparecer **Relatório pronto**, leia o texto, use **Copiar relatório** ou **Baixar Markdown**. Esse é o fim do fluxo de geração.

A análise envia transcrição, OCR, imagens selecionadas e contexto ao Gemini. Os arquivos originais de vídeo e áudio permanecem locais. O modelo usado fica identificado no relatório concluído.

Uma parte pode levar mais tempo quando o serviço externo responde lentamente ou quando há novas tentativas. Se necessário, use **Cancelar análise**; o cancelamento pode aguardar a chamada em andamento terminar. Em falha, leia a mensagem: **Tentar novamente** cria uma nova execução desde o começo, e **Revisar modelo e prompt** abre a configuração com retorno ao vídeo.

## Consultar novamente e revisar avisos

No cartão do vídeo em Outputs, **Análise por IA — Relatório pronto** reabre o resultado. **Todos os arquivos** lista também relatórios concluídos anteriores da mesma versão. Para gerar outro relatório, expanda **Gerar outra análise** na tela do resultado.

Se houver referências a revisar, expanda o aviso para ver quais horários ou IDs não foram encontrados pelo verificador. Compare-os com a transcrição e as telas; o texto gerado não é uma aprovação automática dos fatos. Os dez avisos registrados na validação de 27/09 pertencem àquele relatório, não são uma quantidade fixa do sistema.

O relatório Gemini e a revisão manual de conclusões são resultados distintos. Gerar o relatório não aprova conclusões nem produz automaticamente o ZIP do conhecimento revisado.

Para problemas de modelo, cota ou conexão, consulte [Gemini](GEMINI.md). Para instalar ou iniciar os serviços, veja [primeiro uso](GETTING-STARTED.md) e [operação](DEPLOYMENT.md).
