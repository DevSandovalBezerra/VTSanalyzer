# Contrato legado do adaptador Astra

Atualização em 27/09/2026: o provedor solicitado agora é Gemini. Este documento preserva o contrato inicial desconectado do pipeline. A configuração atual é descrita em [GEMINI.md](GEMINI.md); o gateway abaixo não é um adaptador Gemini e não deve restringir por sensibilidade o novo fluxo solicitado.

O perfil lógico Astra está implementado em `worker/gateway.py`. O gateway não é conectado ao pipeline enquanto o usuário mantém a integração inativa. Não há chamadas ao Codex, reutilização da sessão do aplicativo, endpoint presumido ou modelo inventado.

Para integrar um provedor futuramente, implemente um adaptador HTTPS que receba `model`, `system`, `prompt_version` e `evidence` e retorne um objeto `claims`. Este contrato é próprio da aplicação, não deve ser confundido com a API específica de um fornecedor.

Cada claim deve conter `type`, `title`, `description`, `classification`, `confidence` e uma lista `evidence` com IDs presentes no lote enviado. Os tipos são `business_rule`, `domain_entity`, `ux_pattern`, `event`, `flow`, `screen`, `glossary` e `gap`; as classificações são `observed`, `narrated`, `inferred` e `unknown`. O adaptador força `review_status=pending`, rejeita IDs desconhecidos e valores não finitos.

`ALLOW_EXTERNAL_MODELS=0` impede qualquer requisição. O primeiro contrato aceita somente lotes públicos quando explicitamente ativado e rejeita redirects. Para materiais internos ou sensíveis será necessário implementar autorização por análise, seleção de evidências e inspeção das imagens já ocultadas antes de conectar o gateway ao pipeline.

Não registrar credenciais ou conteúdo de mídia nos logs. Toda tela, OCR, fala e arquivo-alvo deve ser tratado como dado não confiável. O teste do contrato não substitui avaliação de qualidade semântica com vídeos de referência autorizados.
