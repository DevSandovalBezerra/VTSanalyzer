> Documento de escopo original (23/09/2026), preservado como referência. Não representa status de implementação. As decisões posteriores e o escopo vigente estão em [PLANOS.md](PLANOS.md), incluindo simplificação dos formulários e adoção do Gemini.

# PRD — Video-to-System Knowledge Web

**Nome provisório:** System Knowledge Extractor  
**Versão:** 0.2  
**Data:** 23 de setembro de 2026  
**Status:** Proposta para validação  
**Idioma inicial:** Português do Brasil  
**Modelo principal de análise:** Astra  

---

## 1. Resumo executivo

O System Knowledge Extractor será uma aplicação web capaz de receber vídeos demonstrativos de sistemas, sincronizar fala e interface, identificar telas e estados visuais, reconstruir jornadas de usuário, levantar regras de negócio e gerar um pacote de conhecimento utilizável pelo Codex.

O produto não terá como objetivo copiar um sistema visualmente. Seu propósito será compreender os padrões funcionais, fluxos, estados, validações e decisões de experiência demonstrados no vídeo, sempre mantendo evidências vinculadas ao timestamp e ao frame de origem.

A solução terá uma interface web para:

- criar projetos de análise;
- enviar e gerenciar vídeos;
- configurar e executar o processamento;
- acompanhar as etapas em tempo real;
- revisar transcrição, telas, eventos, fluxos e inferências;
- comparar o sistema demonstrado com um projeto-alvo;
- exportar documentação, dados estruturados e contexto para o Codex.

---

## 2. Problema

Vídeos de demonstração concentram conhecimento importante sobre sistemas, mas esse conhecimento fica aprisionado em uma mídia sequencial e difícil de pesquisar.

A transcrição recupera somente a fala. Ela não registra adequadamente:

- quais telas foram apresentadas;
- quais campos foram preenchidos;
- onde o usuário clicou;
- como os componentes mudaram;
- quais mensagens apareceram;
- quais estados antecederam uma ação;
- como filtros, tabelas, menus e modais se comportaram;
- quais regras foram apenas faladas e quais foram efetivamente demonstradas.

Analisar tudo manualmente é lento e sujeito a interpretações inconsistentes. Também é arriscado pedir diretamente a uma IA que “reproduza o sistema”, pois ela pode misturar fatos observados, declarações do narrador e inferências.

---

## 3. Visão do produto

Transformar qualquer vídeo de uso de software em um conjunto estruturado, pesquisável, revisável e rastreável de conhecimento funcional.

O sistema deverá responder:

1. Quais telas existem?
2. Qual é a finalidade de cada tela?
3. Quais ações o usuário executa?
4. Quais dados entram e quais resultados saem?
5. Quais validações e regras aparecem?
6. Quais estados e transições compõem o fluxo?
7. O que foi observado, narrado, inferido ou permanece desconhecido?
8. Quais padrões podem ser adotados no projeto-alvo?
9. Quais alterações seriam necessárias no código existente?

---

## 4. Objetivos

### 4.1 Objetivos do produto

- Reduzir o trabalho manual de análise de vídeos de sistemas.
- Manter rastreabilidade entre cada conclusão e sua evidência.
- Produzir documentação funcional consistente.
- Permitir revisão humana antes da adoção das conclusões.
- Preparar contexto de alta qualidade para o Codex.
- Comparar a referência observada com um sistema em desenvolvimento.
- Gerar um plano de implementação sem modificar automaticamente o projeto.

### 4.2 Indicadores de sucesso

| Indicador | Meta inicial |
|---|---:|
| Execuções concluídas sem intervenção técnica | 95% |
| Conclusões com evidência associada | 100% |
| Telas duplicadas eliminadas | Pelo menos 80% |
| Eventos classificados como observado, narrado, inferido ou desconhecido | 100% |
| Artefatos aprovados pelo usuário sem correção estrutural | 85% |
| Tempo de revisão de um vídeo bem gravado | Menor que 40% da duração do vídeo |
| Exportação de pacote válido para o Codex | 100% das análises aprovadas |

---

## 5. Não objetivos

O produto não deverá:

- descompilar ou recuperar o código-fonte do sistema filmado;
- descobrir com certeza regras internas não exibidas no vídeo;
- copiar logotipos, textos proprietários ou identidade visual;
- capturar credenciais ou dados sensíveis sem tratamento;
- modificar automaticamente o projeto-alvo no MVP;
- substituir entrevistas de discovery quando o vídeo for incompleto;
- garantir equivalência entre o comportamento visível e a implementação interna;
- analisar continuamente vídeo ao vivo no MVP.

---

## 6. Princípios

1. **Evidência antes da conclusão:** toda afirmação importante deve apontar para timestamp, frame, fala ou arquivo.
2. **Observação não é inferência:** o sistema deve distinguir claramente as duas coisas.
3. **Humano no controle:** nenhuma regra inferida será aprovada silenciosamente.
4. **Reprocessamento seletivo:** uma falha não deve obrigar o reinício de todo o vídeo.
5. **Privacidade por padrão:** o processamento local deverá ser possível.
6. **Modelo substituível:** Astra será o perfil padrão, mas não ficará acoplado ao domínio.
7. **Adoção, não clonagem:** o objetivo será aproveitar padrões funcionais adequados.
8. **Saída útil para desenvolvimento:** os resultados devem ser consumíveis por humanos e pelo Codex.

---

## 7. Premissas e decisões iniciais

| Tema | Decisão inicial |
|---|---|
| Implantação | Desenvolvimento híbrido: código local no WSL e serviços no Docker Compose; produção em containers |
| Interface | Web responsiva em português |
| Camada web | PHP 8.3 ou superior |
| Interface dinâmica | JavaScript com componentes leves e Tailwind CSS |
| Processamento multimídia | Workers Python |
| Extração de áudio e vídeo | FFmpeg |
| Detecção de cenas | OpenCV ou PySceneDetect |
| OCR | Motor configurável; Tesseract, PaddleOCR ou equivalente |
| Transcrição | Motor local ou serviço configurável |
| Modelo de análise | Perfil Astra como padrão |
| Fila | Redis |
| Banco relacional | PostgreSQL |
| Arquivos | Volume local ou armazenamento compatível com S3 |
| Forma de trabalho | Assíncrona, com monitoramento de progresso |
| Escopo inicial | Usuário único, preparado para múltiplos usuários |
| Integração com projeto | Upload ZIP, repositório Git ou pasta local em implantação self-hosted |

### 7.1 Uso do Astra

Astra será tratado como um perfil lógico do gateway de modelos. O identificador técnico do modelo, as credenciais e os limites deverão ser configuráveis.

O Astra será usado para:

- analisar frames selecionados;
- relacionar fala e estado visual;
- identificar componentes e padrões;
- reconstruir fluxos;
- levantar regras e entidades;
- classificar o grau de confiança;
- comparar a referência com o projeto-alvo;
- gerar documentação estruturada.

FFmpeg, OCR, detecção de cenas e deduplicação continuarão determinísticos e independentes do modelo.

---

## 8. Perfis de usuário

### 8.1 Administrador

Configura modelos, processamento, armazenamento, segurança, retenção e usuários.

### 8.2 Analista de produto

Cria projetos, executa análises, revisa evidências, corrige fluxos e aprova conclusões.

### 8.3 Desenvolvedor

Consulta os fluxos, compara com o código, avalia lacunas e usa o pacote no Codex.

### 8.4 Revisor

Comenta, valida ou rejeita telas, regras, eventos e documentos gerados.

---

## 9. Escopo do MVP

### 9.1 Incluído

- autenticação local;
- criação de projetos;
- upload de um ou mais vídeos;
- validação técnica dos arquivos;
- processamento assíncrono;
- extração e transcrição de áudio;
- captura periódica e por mudança de cena;
- deduplicação de frames;
- OCR;
- sincronização por timestamp;
- detecção assistida de telas e transições;
- análise com Astra;
- inventário de telas;
- fluxos e regras de negócio;
- revisão e edição humana;
- importação de projeto-alvo;
- matriz de lacunas;
- exportação em Markdown, JSON, Mermaid e ZIP;
- pacote de contexto para o Codex.

### 9.2 Fora do MVP

- colaboração simultânea em tempo real;
- gravação de vídeo dentro da plataforma;
- execução automática de alterações no repositório;
- plugin nativo para navegador;
- análise contínua de transmissões;
- cobrança e planos comerciais;
- comparação pixel a pixel para clonagem visual.

---

## 10. Jornada principal

~~~mermaid
flowchart TD
    A["Criar projeto"] --> B["Enviar vídeo"]
    B --> C["Configurar análise"]
    C --> D["Executar processamento"]
    D --> E["Revisar transcrição e telas"]
    E --> F["Validar timeline e fluxos"]
    F --> G["Aprovar regras e padrões"]
    G --> H["Comparar com projeto-alvo"]
    H --> I["Exportar pacote para o Codex"]
~~~

### 10.1 Etapas de processamento

1. Recebimento e validação do vídeo.
2. Leitura dos metadados.
3. Extração e normalização do áudio.
4. Transcrição segmentada.
5. Captura periódica de frames.
6. Detecção de mudanças de cena.
7. Deduplicação perceptual.
8. OCR e detecção de regiões.
9. Agrupamento em estados de tela.
10. Sincronização com a fala.
11. Análise semântica pelo Astra.
12. Geração de fluxos, regras e entidades.
13. Preparação para revisão humana.
14. Comparação com projeto-alvo.
15. Exportação.

---

## 11. Telas da aplicação web

| Código | Tela | Finalidade |
|---|---|---|
| UI-01 | Login | Autenticação |
| UI-02 | Painel | Projetos recentes, processamento e alertas |
| UI-03 | Novo projeto | Nome, objetivo, domínio, idioma e observações |
| UI-04 | Projeto | Visão geral dos vídeos, análises e artefatos |
| UI-05 | Upload | Envio, validação e classificação do vídeo |
| UI-06 | Configuração | Perfil de captura, OCR, transcrição e Astra |
| UI-07 | Monitor | Progresso, logs funcionais, cancelamento e retomada |
| UI-08 | Revisão | Player, transcrição, frame e evento sincronizados |
| UI-09 | Inventário de telas | Agrupamento, nomes, componentes e estados |
| UI-10 | Editor de fluxo | Nós, transições, condições e evidências |
| UI-11 | Conhecimento | Regras, entidades, permissões e padrões |
| UI-12 | Comparação | Referência versus projeto-alvo |
| UI-13 | Exportação | Seleção dos documentos e formatos |
| UI-14 | Configurações | Modelos, motores, armazenamento e retenção |
| UI-15 | Auditoria | Histórico de execuções, edições e aprovações |

---

## 12. Arquitetura de referência

~~~mermaid
flowchart TD
    A["Navegador"] --> B["Aplicação web PHP"]
    B --> C["PostgreSQL"]
    B --> D["Armazenamento de arquivos"]
    B --> E["Fila Redis"]
    E --> F["Workers Python"]
    F --> G["FFmpeg, OCR e visão"]
    F --> H["Gateway de modelos"]
    H --> I["Perfil Astra"]
    F --> C
    F --> D
    B --> J["Gerador de pacote Codex"]
~~~

### 12.1 Componentes

- **Aplicação web:** autenticação, projetos, uploads, revisão e exportação.
- **Orquestrador:** cria execuções, controla estados e distribui tarefas.
- **Workers de mídia:** áudio, frames, cenas, deduplicação e folhas de contato.
- **Worker de texto:** transcrição, OCR, normalização e indexação.
- **Gateway de modelos:** encapsula o acesso ao Astra e futuros modelos.
- **Motor de conhecimento:** converte resultados em telas, eventos, regras e fluxos.
- **Comparador de projeto:** inspeciona o projeto-alvo e produz a matriz de lacunas.
- **Gerador de artefatos:** cria documentos e pacote para o Codex.

---

# 13. Requisitos funcionais

## 13.1 Acesso e projetos

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-001 | O sistema deverá autenticar usuários por e-mail e senha. | P0 | Credenciais válidas iniciam uma sessão; inválidas não expõem detalhes internos. |
| RF-002 | O sistema deverá permitir encerramento da sessão ativa. | P0 | Após sair, páginas protegidas não poderão ser acessadas sem nova autenticação. |
| RF-003 | O sistema deverá aplicar perfis de Administrador, Analista, Desenvolvedor e Revisor. | P1 | Cada perfil visualiza e executa somente as ações autorizadas. |
| RF-004 | O usuário deverá criar um projeto de análise. | P0 | Nome e objetivo obrigatórios geram um projeto com identificador único. |
| RF-005 | O projeto deverá armazenar domínio, idioma, descrição e observações. | P0 | Os dados permanecem disponíveis após salvar e reabrir o projeto. |
| RF-006 | O usuário deverá editar os metadados do projeto. | P0 | As alterações são versionadas e exibidas imediatamente. |
| RF-007 | O sistema deverá listar projetos por status e data de atualização. | P0 | O painel permite localizar projetos ativos, processando, concluídos e arquivados. |
| RF-008 | O usuário deverá arquivar e restaurar projetos. | P1 | Projetos arquivados saem da lista principal sem exclusão dos dados. |
| RF-009 | O sistema deverá exibir um resumo do projeto. | P0 | O resumo apresenta vídeos, execuções, pendências de revisão e exportações. |

## 13.2 Entrada e gestão de vídeos

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-010 | O usuário deverá enviar vídeos por seleção de arquivo ou arrastar e soltar. | P0 | O arquivo entra na fila e o progresso é apresentado. |
| RF-011 | O sistema deverá aceitar inicialmente MP4, MOV, MKV e WebM. | P0 | Formatos suportados são aceitos; os demais são rejeitados com orientação. |
| RF-012 | O sistema deverá validar tamanho, extensão, contêiner, codec e integridade básica. | P0 | Arquivos inválidos não iniciam processamento e exibem a causa. |
| RF-013 | O upload deverá ser retomável após interrupções. | P1 | Uma interrupção não obriga o reenvio dos blocos concluídos. |
| RF-014 | O sistema deverá apresentar nome, tamanho, duração, resolução, FPS e codecs. | P0 | Os metadados são exibidos antes da execução. |
| RF-015 | O usuário deverá informar título, origem e contexto de cada vídeo. | P0 | As informações acompanham todas as análises derivadas. |
| RF-016 | O usuário deverá classificar a sensibilidade do vídeo. | P0 | A classificação define avisos e política de retenção aplicável. |
| RF-017 | O sistema deverá calcular uma assinatura do arquivo para detectar duplicidade. | P1 | O envio de arquivo idêntico alerta o usuário antes de duplicar o processamento. |
| RF-018 | O usuário deverá substituir o vídeo mantendo o histórico de versões. | P1 | A versão anterior permanece rastreável e os resultados indicam sua origem. |
| RF-019 | O sistema deverá permitir vários vídeos no mesmo projeto. | P0 | Cada vídeo é processado separadamente e pode participar da análise consolidada. |

## 13.3 Configuração e execução

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-020 | O sistema deverá oferecer perfis de processamento rápido, equilibrado e detalhado. | P0 | Cada perfil preenche automaticamente os parâmetros correspondentes. |
| RF-021 | O usuário deverá configurar o intervalo de captura periódica. | P0 | O intervalo selecionado é aplicado e registrado na execução. |
| RF-022 | O usuário deverá configurar a sensibilidade de mudança de cena. | P0 | A alteração modifica a quantidade de cenas detectadas. |
| RF-023 | O usuário deverá selecionar os idiomas de transcrição e OCR. | P0 | Os motores recebem os idiomas selecionados e o resultado registra a configuração. |
| RF-024 | O usuário deverá escolher processamento local ou serviços externos disponíveis. | P1 | A execução utiliza apenas os motores autorizados. |
| RF-025 | O Astra deverá aparecer como perfil padrão de análise. | P0 | Uma nova configuração vem com Astra selecionado, podendo ser alterada por administrador. |
| RF-026 | O usuário deverá escolher os artefatos que deseja gerar. | P0 | Somente os artefatos selecionados entram no plano da execução. |
| RF-027 | O sistema deverá executar uma verificação de pré-requisitos. | P0 | Falta de espaço, motor ou credencial bloqueia o início com orientação objetiva. |
| RF-028 | O usuário deverá iniciar o processamento pela interface. | P0 | A ação cria uma execução identificável e assíncrona. |
| RF-029 | O sistema deverá exibir o progresso geral e por etapa. | P0 | Percentual, etapa atual, início e mensagens funcionais são atualizados sem recarregar a página. |
| RF-030 | O usuário deverá cancelar uma execução em andamento. | P0 | O cancelamento interrompe novas tarefas e preserva resultados já concluídos. |
| RF-031 | O usuário deverá retomar ou repetir uma execução. | P0 | É possível continuar do último ponto válido ou criar nova versão completa. |

## 13.4 Transcrição e áudio

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-032 | O sistema deverá extrair e normalizar o áudio do vídeo. | P0 | É gerado áudio compatível com o motor selecionado sem alterar o original. |
| RF-033 | O sistema deverá transcrever o áudio em segmentos com início e fim. | P0 | Cada segmento possui texto e timestamps reproduzíveis no player. |
| RF-034 | O sistema deverá armazenar a confiança fornecida pelo motor de transcrição. | P1 | Segmentos de baixa confiança podem ser filtrados. |
| RF-035 | O usuário deverá editar a transcrição. | P0 | A correção cria uma nova versão sem apagar o texto original. |
| RF-036 | O sistema deverá sincronizar o clique no segmento com o vídeo. | P0 | O player navega para o início do segmento selecionado. |
| RF-037 | O sistema deverá pesquisar termos na transcrição. | P0 | A pesquisa retorna segmentos, timestamps e contexto. |
| RF-038 | O sistema deverá permitir marcar falas irrelevantes. | P1 | Segmentos ignorados não alimentam a análise, mas permanecem no histórico. |
| RF-039 | O sistema deverá exportar a transcrição em Markdown, SRT e VTT. | P1 | O arquivo exportado conserva a ordem e os timestamps. |

## 13.5 Frames, cenas, telas e OCR

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-040 | O sistema deverá extrair frames em intervalos regulares. | P0 | A quantidade de frames corresponde ao intervalo e à duração útil. |
| RF-041 | O sistema deverá extrair frames adicionais quando detectar mudança visual. | P0 | Transições relevantes produzem frames mesmo fora do intervalo regular. |
| RF-042 | Cada frame deverá preservar o timestamp de origem. | P0 | O usuário consegue abrir o vídeo exatamente no ponto associado. |
| RF-043 | O sistema deverá reduzir a resolução de análise sem alterar o original. | P0 | Frames derivados respeitam o limite configurado e o original permanece intacto. |
| RF-044 | O sistema deverá identificar frames duplicados ou quase idênticos. | P0 | Grupos duplicados mantêm um representante e referências aos descartados. |
| RF-045 | O usuário deverá restaurar um frame excluído pela deduplicação. | P1 | O frame restaurado volta a participar da análise. |
| RF-046 | O sistema deverá agrupar frames semelhantes como um estado de tela. | P0 | Frames do mesmo estado aparecem em um grupo revisável. |
| RF-047 | O sistema deverá sugerir um nome funcional para cada estado de tela. | P0 | A sugestão pode ser aceita ou editada pelo usuário. |
| RF-048 | O usuário deverá unir ou separar estados de tela. | P0 | A operação atualiza as referências sem perder evidências. |
| RF-049 | O sistema deverá detectar texto visível por OCR. | P0 | O texto, sua região e o frame de origem ficam armazenados. |
| RF-050 | O usuário deverá corrigir o texto reconhecido pelo OCR. | P0 | A correção é versionada e utilizada nas análises posteriores. |
| RF-051 | O sistema deverá pesquisar textos reconhecidos nas telas. | P0 | A busca retorna tela, frame, região e timestamp. |
| RF-052 | O sistema deverá gerar folhas de contato com frames relevantes. | P1 | Cada miniatura possui identificador e timestamp legível. |
| RF-053 | O usuário deverá incluir ou excluir manualmente um frame da análise. | P0 | A seleção é respeitada no próximo processamento sem apagar o arquivo. |
| RF-054 | O usuário deverá recortar ou ocultar regiões sensíveis. | P0 | A região protegida não é enviada ao modelo nem aparece nas exportações públicas. |

## 13.6 Timeline e reconstrução de fluxo

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-055 | O sistema deverá criar uma timeline sincronizada de fala, frame, tela e evento. | P0 | Ao selecionar qualquer elemento, os demais são posicionados no mesmo instante. |
| RF-056 | O sistema deverá sugerir eventos de interação. | P0 | Eventos apresentam ação, tela anterior, tela posterior e confiança. |
| RF-057 | Os eventos deverão aceitar os tipos navegar, clicar, preencher, selecionar, pesquisar, salvar, cancelar, confirmar, erro e sistema. | P0 | Todo evento recebe um tipo válido ou fica marcado como não classificado. |
| RF-058 | O usuário deverá criar, editar, dividir, unir e excluir eventos. | P0 | A timeline é recalculada sem remover a evidência original. |
| RF-059 | Cada evento deverá registrar ator ou perfil de usuário quando identificável. | P0 | O ator aparece no fluxo ou é marcado como desconhecido. |
| RF-060 | Cada evento deverá permitir registrar entrada, ação, regra e saída. | P0 | Os quatro campos podem ser revisados individualmente. |
| RF-061 | O sistema deverá ligar eventos por transições. | P0 | A sequência principal pode ser percorrida do início ao fim. |
| RF-062 | O sistema deverá representar condições e caminhos alternativos. | P1 | Uma decisão pode possuir duas ou mais saídas nomeadas. |
| RF-063 | O sistema deverá representar erros e recuperação. | P1 | O fluxo diferencia caminho de sucesso, falha e retorno. |
| RF-064 | O sistema deverá gerar automaticamente um diagrama Mermaid. | P0 | O diagrama é válido, editável e consistente com os eventos aprovados. |
| RF-065 | O usuário deverá editar o fluxo em modo visual. | P1 | Alterações visuais atualizam a estrutura sem perder os identificadores. |
| RF-066 | O sistema deverá detectar lacunas temporais relevantes. | P1 | Trechos sem evidência suficiente aparecem como pendência de revisão. |

## 13.7 Análise com Astra e extração do conhecimento

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-067 | O sistema deverá enviar ao Astra apenas os frames e textos selecionados para cada bloco. | P0 | O registro da análise lista exatamente as evidências utilizadas. |
| RF-068 | O sistema deverá dividir vídeos extensos em blocos com sobreposição controlada. | P0 | Eventos na fronteira não desaparecem e duplicidades são conciliadas. |
| RF-069 | O sistema deverá usar respostas estruturadas validadas por esquema. | P0 | Saídas inválidas são rejeitadas e repetidas sem contaminar os artefatos. |
| RF-070 | O sistema deverá versionar prompts, perfil de modelo e parâmetros. | P0 | Toda conclusão informa a configuração que a produziu. |
| RF-071 | O sistema deverá classificar cada afirmação como observada, narrada, inferida ou desconhecida. | P0 | Nenhuma afirmação aprovada permanece sem classificação. |
| RF-072 | O sistema deverá atribuir nível de confiança a cada afirmação. | P0 | A confiança é exibida e pode ser ajustada pelo revisor. |
| RF-073 | O sistema deverá associar evidências a cada afirmação. | P0 | O usuário abre o frame ou trecho de fala a partir da conclusão. |
| RF-074 | O sistema deverá gerar um inventário de telas. | P0 | Cada tela inclui finalidade, componentes, ações, estados e evidências. |
| RF-075 | O sistema deverá gerar um inventário de componentes de interface. | P1 | Menus, tabelas, formulários, filtros, modais e alertas são relacionados às telas. |
| RF-076 | O sistema deverá identificar regras de negócio aparentes. | P0 | Cada regra registra condição, efeito, evidência, confiança e status de aprovação. |
| RF-077 | O sistema deverá identificar validações de campos e formulários. | P0 | Campo, condição, mensagem e comportamento ficam documentados. |
| RF-078 | O sistema deverá identificar papéis e permissões demonstrados. | P1 | A permissão é vinculada ao ator e marcada como observada ou inferida. |
| RF-079 | O sistema deverá sugerir entidades e relacionamentos de domínio. | P0 | Cada entidade apresenta atributos visíveis e origem da inferência. |
| RF-080 | O sistema deverá identificar termos e conceitos do domínio. | P0 | O glossário evita duplicidade e registra sinônimos. |
| RF-081 | O sistema deverá identificar padrões de experiência reutilizáveis. | P0 | O padrão descreve problema, comportamento, contexto e evidência. |
| RF-082 | O sistema deverá indicar contradições entre fala e interface. | P0 | A inconsistência apresenta as duas evidências sem escolher silenciosamente uma versão. |

## 13.8 Revisão humana

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-083 | O sistema deverá disponibilizar uma área unificada de revisão. | P0 | Player, transcrição, tela, evento e conclusão ficam visíveis de forma sincronizada. |
| RF-084 | Todo item gerado deverá possuir status pendente, aprovado, rejeitado ou precisa confirmar. | P0 | O status pode ser filtrado e aparece nas exportações. |
| RF-085 | O usuário deverá editar itens gerados pela IA. | P0 | O valor original e a alteração permanecem no histórico. |
| RF-086 | O usuário deverá aprovar ou rejeitar itens individualmente ou em lote. | P0 | A operação afeta somente os itens selecionados. |
| RF-087 | O usuário deverá comentar em telas, eventos, regras e fluxos. | P1 | O comentário registra autor, data e item relacionado. |
| RF-088 | O sistema deverá manter histórico de revisões. | P0 | É possível comparar versões e identificar o responsável. |
| RF-089 | O usuário deverá restaurar uma versão anterior de um item. | P1 | A restauração cria nova versão e não apaga o histórico. |
| RF-090 | O sistema deverá oferecer filtros por tipo, confiança, status e fonte. | P0 | A lista atualiza combinando os filtros selecionados. |
| RF-091 | O sistema deverá impedir aprovação final com pendências obrigatórias. | P0 | O bloqueio lista os itens que precisam de decisão. |
| RF-092 | O sistema deverá registrar a aprovação do conjunto de conhecimento. | P0 | A aprovação gera uma versão imutável disponível para exportação. |

## 13.9 Comparação com projeto-alvo

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-093 | O usuário deverá vincular um projeto-alvo por ZIP, Git ou pasta local autorizada. | P0 | A origem é validada e registrada sem executar código do projeto. |
| RF-094 | O sistema deverá permitir configurar exclusões de arquivos e diretórios. | P0 | Segredos, dependências e arquivos binários excluídos não são analisados. |
| RF-095 | O sistema deverá inventariar rotas, telas, componentes e entidades identificáveis no projeto-alvo. | P0 | O inventário apresenta arquivo, símbolo e grau de confiança. |
| RF-096 | O sistema deverá mapear telas da referência para telas do projeto-alvo. | P0 | Cada correspondência pode ser confirmada, corrigida ou marcada como inexistente. |
| RF-097 | O sistema deverá gerar uma matriz de lacunas. | P0 | Cada item recebe situação: existente, parcial, ausente, divergente ou não aplicável. |
| RF-098 | O sistema deverá comparar regras e validações. | P0 | A comparação mostra referência, implementação encontrada e diferença. |
| RF-099 | O sistema deverá comparar padrões de navegação e experiência. | P1 | O resultado identifica padrões aproveitáveis sem exigir cópia visual. |
| RF-100 | O sistema deverá identificar componentes potencialmente reutilizáveis. | P1 | Cada sugestão aponta o arquivo de origem e a adaptação necessária. |
| RF-101 | O sistema deverá gerar recomendações de adoção, adaptação ou descarte. | P0 | A recomendação contém justificativa, impacto e evidência. |
| RF-102 | O sistema deverá gerar um plano de implementação ordenado por dependências. | P0 | As tarefas indicam pré-requisitos, resultado esperado e critérios de aceite. |
| RF-103 | O sistema deverá estimar impacto por camada. | P1 | Banco, backend, interface, integrações, testes e documentação são avaliados separadamente. |
| RF-104 | O sistema não deverá alterar o projeto-alvo durante a análise. | P0 | A integração opera somente em leitura até autorização explícita futura. |

## 13.10 Documentos, exportação e Codex

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-105 | O sistema deverá gerar um relatório executivo. | P0 | O documento explica problema, referência analisada, conclusões e próximos passos. |
| RF-106 | O sistema deverá gerar inventário de telas e componentes. | P0 | O documento contém identificadores e links relativos para evidências. |
| RF-107 | O sistema deverá gerar jornadas e fluxos. | P0 | Os fluxos incluem narrativa e diagrama Mermaid válido. |
| RF-108 | O sistema deverá gerar catálogo de regras de negócio. | P0 | As regras incluem classificação, confiança, status e evidências. |
| RF-109 | O sistema deverá gerar modelo conceitual de entidades. | P0 | Entidades e relacionamentos são marcados como observados ou inferidos. |
| RF-110 | O sistema deverá gerar catálogo de padrões de experiência. | P0 | Cada padrão descreve quando usar, benefício e risco de adoção. |
| RF-111 | O sistema deverá gerar a análise de lacunas. | P0 | O documento reflete somente a versão aprovada da comparação. |
| RF-112 | O sistema deverá gerar plano de implementação. | P0 | O plano contém fases, dependências, critérios de aceite e rastreabilidade. |
| RF-113 | O sistema deverá exportar dados estruturados em JSON e YAML. | P0 | Os arquivos passam na validação dos esquemas publicados no pacote. |
| RF-114 | O sistema deverá exportar documentos em Markdown. | P0 | Links internos e caminhos de evidências funcionam após descompactar. |
| RF-115 | O sistema deverá criar um pacote ZIP autocontido. | P0 | O pacote contém manifesto, documentos, dados e evidências autorizadas. |
| RF-116 | O sistema deverá gerar um arquivo de contexto para o Codex. | P0 | O arquivo explica fontes, limites, convenções e ordem recomendada de leitura. |

## 13.11 Administração, privacidade e ciclo de vida

| ID | Requisito | Prioridade | Critério de aceite |
|---|---|---|---|
| RF-117 | O administrador deverá configurar o gateway e o perfil Astra. | P0 | Credenciais não são exibidas após salvar e a conexão pode ser testada. |
| RF-118 | O administrador deverá configurar motores de transcrição e OCR. | P0 | Um teste confirma disponibilidade antes de ativar o motor. |
| RF-119 | O administrador deverá configurar limites de arquivo, duração e concorrência. | P0 | Novas execuções respeitam os limites definidos. |
| RF-120 | O administrador deverá configurar política de retenção. | P0 | Arquivos vencidos entram em processo de exclusão auditável. |
| RF-121 | O usuário deverá excluir permanentemente vídeos e resultados autorizados. | P0 | A interface informa o impacto e exige confirmação explícita. |
| RF-122 | O sistema deverá manter trilha de auditoria. | P0 | Upload, execução, edição, aprovação, exportação e exclusão são registrados. |
| RF-123 | O sistema deverá apresentar uso de armazenamento e processamento. | P1 | O painel mostra consumo por projeto e total. |
| RF-124 | O sistema deverá permitir backup e restauração dos metadados. | P1 | Um backup restaurado recupera projetos, relações e configurações não secretas. |
| RF-125 | O sistema deverá disponibilizar diagnóstico de saúde. | P0 | Banco, fila, workers, FFmpeg, OCR, transcrição e gateway são verificados. |
| RF-126 | O sistema deverá apresentar logs funcionais sem expor segredos. | P0 | Mensagens ajudam a corrigir falhas e mascaram valores sensíveis. |
| RF-127 | O usuário deverá reprocessar somente uma etapa selecionada. | P0 | Etapas dependentes são invalidadas e recalculadas de forma controlada. |
| RF-128 | O sistema deverá versionar execuções e artefatos. | P0 | É possível identificar exatamente qual execução gerou cada arquivo. |

---

# 14. Requisitos não funcionais

| ID | Requisito |
|---|---|
| RNF-001 | A aplicação deverá funcionar nos navegadores atuais baseados em Chromium, Firefox e Edge. |
| RNF-002 | A interface deverá ser responsiva para desktop e tablet, priorizando desktop. |
| RNF-003 | A interface deverá atender, no mínimo, aos critérios essenciais da WCAG 2.2 nível AA. |
| RNF-004 | O upload deverá usar blocos e retomada para arquivos grandes. |
| RNF-005 | As operações pesadas deverão ocorrer fora do processo web. |
| RNF-006 | O sistema deverá continuar navegável durante o processamento. |
| RNF-007 | Uma falha de etapa não deverá apagar resultados válidos das etapas anteriores. |
| RNF-008 | As tarefas deverão ser idempotentes quando repetidas com a mesma configuração. |
| RNF-009 | O sistema deverá limitar quantidade de frames enviados ao modelo por análise. |
| RNF-010 | Senhas deverão usar algoritmo de hash moderno e fator de custo configurável. |
| RNF-011 | Segredos deverão ser armazenados fora do banco em texto simples. |
| RNF-012 | Todo acesso autenticado deverá usar proteção contra CSRF e controle de sessão. |
| RNF-013 | Arquivos enviados deverão ser tratados como dados, nunca executados. |
| RNF-014 | O sistema deverá impedir travessia de diretórios e validar caminhos. |
| RNF-015 | Conteúdo enviado ao modelo deverá respeitar a classificação de sensibilidade. |
| RNF-016 | Regiões ocultadas deverão ser removidas da imagem derivada enviada ao modelo. |
| RNF-017 | Logs deverão mascarar tokens, senhas, cookies, chaves e dados classificados. |
| RNF-018 | A solução deverá suportar implantação por Docker Compose. |
| RNF-019 | Os serviços deverão fornecer verificações de saúde. |
| RNF-020 | O banco deverá aceitar migrações versionadas e reversíveis quando possível. |
| RNF-021 | Artefatos deverão possuir identificadores estáveis e versões. |
| RNF-022 | JSON e YAML exportados deverão ser validados antes da entrega. |
| RNF-023 | O pacote deverá registrar versão da aplicação, modelo, prompts e motores. |
| RNF-024 | O sistema deverá permitir processamento totalmente local, exceto quando o usuário escolher serviços externos. |
| RNF-025 | A arquitetura deverá permitir substituição do Astra sem alterar as entidades do domínio. |
| RNF-026 | A interface deverá exibir erro compreensível e um identificador técnico para suporte. |
| RNF-027 | O sistema deverá coletar métricas de fila, duração, falhas e uso de recursos. |
| RNF-028 | A exclusão definitiva deverá remover originais, derivados, índices e caches relacionados. |
| RNF-029 | O MVP deverá suportar vídeos de até duas horas, com limite configurável. |
| RNF-030 | O tempo de resposta das páginas, sem processamento pesado, deverá ficar abaixo de dois segundos em ambiente recomendado. |

---

## 15. Modelo de dados conceitual

| Entidade | Responsabilidade |
|---|---|
| User | Identidade e perfil |
| Project | Unidade principal da análise |
| ProjectMember | Associação e permissão |
| VideoAsset | Vídeo original e metadados |
| VideoVersion | Histórico do arquivo-fonte |
| ProcessingProfile | Parâmetros reutilizáveis |
| ProcessingRun | Execução versionada |
| ProcessingStage | Estado e resultado de cada etapa |
| TranscriptSegment | Fala com timestamps |
| Frame | Imagem extraída e timestamp |
| FrameGroup | Grupo de duplicidade ou estado visual |
| ScreenState | Tela ou estado funcional |
| OcrBlock | Texto e coordenadas |
| TimelineEvent | Ação sincronizada |
| Flow | Jornada ou processo |
| FlowStep | Etapa e transições |
| Evidence | Referência para frame, fala, evento ou arquivo |
| KnowledgeClaim | Afirmação classificada e rastreável |
| BusinessRule | Regra extraída |
| DomainEntity | Entidade sugerida |
| UxPattern | Padrão de experiência |
| TargetSource | Projeto-alvo vinculado |
| CodeInventoryItem | Elemento identificado no projeto |
| GapFinding | Correspondência ou lacuna |
| Artifact | Documento ou dado exportável |
| Review | Aprovação, rejeição ou comentário |
| AuditEvent | Registro de operação |

### 15.1 Classificação de uma afirmação

| Tipo | Definição |
|---|---|
| Observada | Há evidência visual direta do comportamento |
| Narrada | O comportamento foi afirmado na fala, mas não necessariamente exibido |
| Inferida | A conclusão resulta da interpretação das evidências |
| Desconhecida | Não há informação suficiente |

### 15.2 Estados da execução

~~~mermaid
stateDiagram-v2
    [*] --> Rascunho
    Rascunho --> NaFila
    NaFila --> Processando
    Processando --> AguardandoRevisao
    Processando --> Falhou
    Processando --> Cancelado
    Falhou --> NaFila
    Cancelado --> NaFila
    AguardandoRevisao --> Aprovado
    Aprovado --> Exportado
~~~

---

## 16. Contratos estruturados de saída

Cada item de conhecimento deverá conter, no mínimo:

- identificador;
- tipo;
- título;
- descrição;
- classificação da afirmação;
- confiança;
- status de revisão;
- lista de evidências;
- origem da análise;
- versão do prompt;
- perfil do modelo;
- execução de origem;
- data de criação;
- histórico de revisão.

Exemplo conceitual:

    {
      "id": "BR-023",
      "type": "business_rule",
      "title": "Bloqueio de documento duplicado",
      "classification": "observed",
      "confidence": 0.94,
      "review_status": "approved",
      "evidence": [
        {
          "type": "frame",
          "timestamp": "00:03:42.500",
          "reference": "frames/frame_00231.png"
        }
      ]
    }

---

## 17. Pacote de conhecimento para o Codex

Estrutura prevista:

    system-knowledge/
    ├── README.md
    ├── CONTEXT_FOR_CODEX.md
    ├── MANIFEST.json
    ├── TRANSCRIPT.md
    ├── SCREEN_INVENTORY.md
    ├── USER_FLOWS.md
    ├── BUSINESS_RULES.md
    ├── DOMAIN_MODEL.md
    ├── UX_PATTERNS.md
    ├── GAP_ANALYSIS.md
    ├── IMPLEMENTATION_PLAN.md
    ├── data/
    │   ├── screens.json
    │   ├── events.json
    │   ├── flows.json
    │   ├── rules.json
    │   └── evidence.json
    └── evidence/
        ├── frames/
        └── contact-sheets/

O arquivo CONTEXT_FOR_CODEX.md deverá orientar o agente a:

- ler primeiro o manifesto;
- respeitar a distinção entre fato e inferência;
- não inventar regras ausentes;
- usar somente itens aprovados como requisito;
- tratar itens pendentes como perguntas;
- não copiar identidade visual;
- comparar os padrões com a arquitetura existente;
- propor mudanças antes de implementá-las;
- preservar código não relacionado.

---

## 18. Cenários de aceite ponta a ponta

### CA-01 — Análise básica

**Dado** um vídeo MP4 válido com demonstração de cadastro,  
**quando** o usuário executar o perfil equilibrado,  
**então** o sistema deverá produzir transcrição, telas, timeline, fluxo, regras e evidências sincronizadas.

### CA-02 — Revisão de inferência

**Dado** que o Astra inferiu uma regra não demonstrada,  
**quando** o analista abrir a regra,  
**então** ela deverá aparecer como inferida, com confiança e evidências, sem aprovação automática.

### CA-03 — Proteção de dado sensível

**Dado** um frame contendo credencial,  
**quando** o usuário ocultar a região,  
**então** nenhuma análise posterior ou exportação autorizada deverá conter os pixels originais daquela região.

### CA-04 — Recuperação de falha

**Dado** que o OCR falhou após a transcrição e extração de frames,  
**quando** o usuário repetir somente o OCR,  
**então** áudio, transcrição e frames não deverão ser recalculados.

### CA-05 — Comparação com projeto

**Dado** um pacote de conhecimento aprovado e um projeto-alvo válido,  
**quando** a comparação for executada,  
**então** o sistema deverá produzir correspondências, lacunas, recomendações e plano de implementação sem alterar arquivos do projeto.

### CA-06 — Exportação para o Codex

**Dado** que as pendências obrigatórias foram resolvidas,  
**quando** o usuário exportar o pacote,  
**então** os documentos, dados, evidências permitidas e manifesto deverão formar um ZIP válido e autocontido.

---

## 19. Plano de desenvolvimento e entrega

### 19.1 Recomendação prioritária — ambiente híbrido local e Docker

**Decisão prioritária P0:** o código-fonte deverá permanecer em uma pasta local versionada pelo Git, preferencialmente dentro do sistema de arquivos do Ubuntu no WSL2, enquanto PHP, Python, PostgreSQL, Redis, FFmpeg e serviços auxiliares serão executados por Docker Compose.

Essa decisão deverá ser implantada antes do desenvolvimento das funcionalidades de análise. O objetivo é garantir liberdade de edição, hot reload, depuração e acesso ao Git sem instalar diretamente no Windows todas as dependências especializadas do produto.

Pasta recomendada:

    /home/usuario/projetos/system-knowledge-extractor

O projeto não deverá ser iniciado dentro de C:\wamp64\www, pois o WAMP não cobre adequadamente os workers Python, FFmpeg, Redis, filas, PostgreSQL e eventual acesso à GPU exigidos pela solução.

Estrutura inicial recomendada:

    system-knowledge-extractor/
    ├── app/                 # Aplicação e API PHP
    ├── worker/              # Processamento Python
    ├── frontend/            # JavaScript e Tailwind CSS
    ├── storage/
    │   ├── videos/
    │   ├── audio/
    │   ├── frames/
    │   └── exports/
    ├── docker/
    │   ├── php/
    │   ├── python/
    │   └── nginx/
    ├── docs/
    ├── tests/
    ├── compose.yml
    ├── .env.example
    └── README.md

#### Serviços mínimos do ambiente

| Serviço | Responsabilidade |
|---|---|
| web | Aplicação PHP e API |
| nginx | HTTP, arquivos estáticos e upload de arquivos grandes |
| worker | Python, FFmpeg, OCR, transcrição e preparação para o Astra |
| postgres | Banco relacional |
| redis | Filas, bloqueios e eventos de progresso |
| scheduler | Retenção, limpeza e tarefas periódicas |
| minio | Opcional para armazenamento compatível com S3 |

#### Regras do desenvolvimento

- O código local deverá ser montado nos containers por bind mounts.
- Alterações em PHP, Python e JavaScript deverão aparecer sem reconstrução completa das imagens.
- Vídeos, áudios, frames e exportações deverão permanecer em volumes persistentes externos às camadas internas dos containers.
- Projetos analisados deverão ser montados explicitamente e em modo somente leitura.
- Segredos deverão permanecer em arquivo de ambiente não versionado ou em mecanismo próprio de secrets.
- O ambiente deverá oferecer perfis separados para CPU e GPU.
- O uso da GPU será opcional no MVP; a aplicação deverá continuar funcionando por CPU ou serviços externos.
- A configuração de produção não deverá usar bind mounts de código: deverá utilizar imagens versionadas e imutáveis.

#### Desenvolvimento versus produção

| Desenvolvimento | Produção |
|---|---|
| Código local montado nos containers | Código incluído em imagens versionadas |
| Hot reload e depuração | Imagens imutáveis |
| Logs detalhados | Logs controlados e auditáveis |
| Portas locais para diagnóstico | Somente portas necessárias |
| Dados de teste | Volumes persistentes e backup |
| Perfil CPU ou GPU selecionável | Perfil validado para a infraestrutura |

#### Restrições que deverão ser tratadas sem limitar o produto

1. **GPU:** o acesso à NVIDIA pelo Docker Desktop e WSL2 deverá ser opcional e validado por diagnóstico.
2. **Vídeos grandes:** uploads e derivados deverão usar volumes persistentes, quotas e verificação prévia de espaço.
3. **Projetos locais:** somente diretórios autorizados poderão ser montados para comparação, sempre em modo somente leitura.
4. **Desempenho no Windows:** o repositório deverá permanecer preferencialmente no sistema de arquivos do WSL, evitando o custo de I/O de diretórios montados do Windows.
5. **Portabilidade:** nenhum fluxo funcional poderá depender exclusivamente de caminhos, ferramentas ou recursos específicos da máquina do desenvolvedor.

#### Condição de saída desta recomendação

O desenvolvimento das funcionalidades da Fase 1 somente deverá começar quando:

- o repositório local estiver criado e versionado;
- o Docker Compose iniciar todos os serviços essenciais;
- PHP e Python refletirem alterações locais;
- PostgreSQL e Redis passarem nas verificações de saúde;
- FFmpeg estiver acessível pelo worker;
- os volumes persistirem após recriação dos containers;
- um diretório de projeto puder ser montado em modo somente leitura;
- o procedimento de instalação estiver documentado no README.

### Fase 0 — Fundação

- estrutura local do projeto dentro do WSL2;
- repositório;
- Docker Compose para desenvolvimento e produção;
- bind mounts para desenvolvimento;
- volumes persistentes para vídeos e artefatos;
- perfis opcionais de CPU e GPU;
- banco e migrações;
- autenticação;
- projetos;
- armazenamento;
- fila e workers;
- diagnóstico de saúde.

### Fase 1 — Ingestão e mídia

- upload retomável;
- validação;
- metadados;
- áudio;
- transcrição;
- frames;
- cenas;
- deduplicação;
- OCR.

### Fase 2 — Revisão multimodal

- player sincronizado;
- transcrição editável;
- agrupamento de telas;
- timeline;
- evidências;
- ocultação de dados.

### Fase 3 — Astra e conhecimento

- gateway de modelos;
- respostas estruturadas;
- classificação de afirmações;
- inventário de telas;
- regras;
- entidades;
- padrões;
- fluxos.

### Fase 4 — Projeto-alvo e Codex

- ingestão segura do projeto;
- inventário técnico;
- matriz de lacunas;
- recomendações;
- plano;
- pacote para Codex.

### Fase 5 — Robustez

- auditoria;
- backup;
- retenção;
- métricas;
- otimização;
- documentação operacional;
- testes ponta a ponta.

---

## 20. Estratégia de testes

### 20.1 Testes unitários

- cálculo de timestamps;
- deduplicação;
- normalização de OCR;
- validadores de esquema;
- transições de estado;
- regras de permissão;
- geração de manifestos.

### 20.2 Testes de integração

- PHP com fila;
- fila com workers;
- worker com FFmpeg;
- worker com OCR;
- worker com transcrição;
- gateway com Astra;
- exportador com armazenamento.

### 20.3 Testes de referência

Será mantido um pequeno conjunto de vídeos autorizados com resultados esperados para avaliar:

- cenas capturadas;
- duplicidades removidas;
- texto reconhecido;
- alinhamento temporal;
- telas identificadas;
- regras extraídas;
- taxa de inferências indevidas.

### 20.4 Testes de segurança

- arquivos malformados;
- ZIP com travessia de diretórios;
- arquivos disfarçados;
- tentativa de execução de conteúdo;
- injeção de prompt presente na tela ou fala;
- exposição de segredos em logs;
- acesso cruzado entre projetos.

---

## 21. Riscos e mitigação

| Risco | Impacto | Mitigação |
|---|---|---|
| Vídeo com baixa resolução | OCR e componentes incorretos | Alertar qualidade e permitir revisão manual |
| Cortes rápidos demais | Perda de estados intermediários | Captura periódica combinada com mudança de cena |
| Muitos frames semelhantes | Custo e lentidão | Deduplicação e agrupamento perceptual |
| Inferência tratada como requisito | Implementação incorreta | Classificação obrigatória e aprovação humana |
| Dados pessoais nas telas | Exposição indevida | Classificação, ocultação e modo local |
| Prompt injection no vídeo | Desvio da análise | Tratar todo conteúdo extraído como dado não confiável |
| Mudança do modelo Astra | Inconsistência | Gateway, versionamento e testes de referência |
| Projeto-alvo muito grande | Contexto excessivo | Inventário, filtros e análise incremental |
| Dependência de GPU | Dificuldade de instalação | Perfis CPU, GPU e serviços externos opcionais |
| Cópia indevida de produto | Risco jurídico | Foco em padrão funcional e alerta de propriedade intelectual |

---

## 22. Critérios para considerar o MVP concluído

O MVP será considerado concluído quando:

1. Um usuário conseguir criar projeto e enviar vídeo pela interface.
2. O sistema concluir o pipeline sem comandos manuais.
3. Transcrição, frames, OCR e timeline estiverem sincronizados.
4. Astra gerar resultados estruturados e rastreáveis.
5. O usuário conseguir revisar e aprovar o conhecimento.
6. O sistema distinguir observação, narração, inferência e desconhecido.
7. Um projeto-alvo puder ser comparado em modo somente leitura.
8. A matriz de lacunas e o plano de implementação forem gerados.
9. O pacote do Codex puder ser exportado e validado.
10. Falhas puderem ser retomadas por etapa.
11. Dados sensíveis puderem ser ocultados antes da análise externa.
12. Os cenários CA-01 a CA-06 passarem.

---

## 23. Decisões ainda abertas

| Decisão | Opções |
|---|---|
| Framework PHP | PHP modular próprio ou Laravel |
| Interface dinâmica | Alpine.js, Vue ou React |
| Motor local de transcrição | Whisper, faster-whisper ou serviço compatível |
| OCR padrão | PaddleOCR ou Tesseract |
| Banco no ambiente local | PostgreSQL recomendado; avaliar MySQL se necessário |
| Acesso ao Astra | Codex, API ou adaptador fornecido pelo ambiente |
| Limite padrão de vídeo | 30, 60 ou 120 minutos |
| Multiusuário no MVP | Desativado, básico ou completo |
| Importação Git | Somente repositórios locais ou também remotos autenticados |
| Retenção padrão | Manual, 30 dias ou 90 dias |

---

## 24. Recomendação técnica final

A primeira implementação deverá manter a interface e a regra de aplicação em PHP, conforme o padrão preferencial do projeto, e concentrar o processamento especializado em Python.

Separação recomendada:

- **PHP:** autenticação, projetos, API web, upload, revisão, auditoria e exportação.
- **JavaScript:** interface, player sincronizado, timeline e editor de fluxo.
- **Python:** mídia, OCR, transcrição, deduplicação, agrupamento visual e gateway analítico.
- **Astra:** compreensão multimodal, síntese, reconstrução funcional e comparação.
- **FFmpeg:** transformação de áudio e vídeo.
- **Redis:** filas e eventos de progresso.
- **PostgreSQL:** metadados, versões, estados e evidências.
- **Docker Compose:** instalação e execução reproduzível.

Essa divisão reduz o acoplamento, preserva a simplicidade da camada web e mantém as tarefas de visão computacional no ecossistema mais adequado.
