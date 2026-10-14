# Contrato de API · User Register 2.0

Base: `/api/user_register/settings/`. Esta API atende a interface do plugin, **não é uma API pública de integração com ERPs**.

## Autenticação e segurança

- Sessão autenticada da WebGUI e privilégio User Register do OPNsense.
- Toda escrita/download exige POST e o cabeçalho CSRF nativo `X-CSRFToken`.
- `ApiControllerBase` verifica autenticação, autorização e CSRF antes das ações. Não contornar esses checks.
- Credenciais de API key são recusadas: a importação e as senhas pertencem à sessão do operador.
- Payloads JSON nos POSTs, exceto upload, que utiliza `multipart/form-data`.
- Respostas `Cache-Control: no-store, private` e `Pragma: no-cache`.
- Um job por sessão. Prévia nova invalida token anterior; com credenciais já emitidas é obrigatório encerrar o job anterior primeiro.
- Método incorreto: 405; dados/token/expiração inválidos: 400; sem autorização: 401/403 conforme framework; erro interno: 500.
- Resposta comum: `status` igual a `ok`, `warning` ou `error`, com `message` quando necessário. Falhas internas não expõem stack traces, nomes ou senhas.

## Endpoints

| Método | Ação | Entrada | Saída |
|---|---|---|---|
| GET | `get` | nenhuma | `version`, `limits`, `job` ou `null` |
| POST | `preview` | multipart: `file` XLSX e `expires` opcional | `job` com linhas e resumo; não cria contas |
| POST | `import` | `token`, `offset` inteiro, `confirm: true` booleano | progresso após até dez candidatos |
| POST | `sync` | `token` | sincroniza até dez contas pendentes; `warning` se houver falha |
| POST | `download` | `token` | `csv`: texto CSV UTF-8 com BOM e separador `;` |
| POST | `clear` | `token` | `status: ok`; elimina dados temporários, não contas |

`expires`: vazio ou data futura estrita `YYYY-MM-DD`; aplicada somente às novas contas. Não há suporte a senha escolhida pelo cliente, atualização de usuários, grupos, shell ou privilégios no payload.

## Estrutura pública do job

| Campo | Descrição |
|---|---|
| `token` | 32 bytes aleatórios, codificados em hexadecimal; vinculado à sessão |
| `deadline` | Timestamp Unix de validade lógica da prévia/credenciais |
| `sheets` | Nomes das abas processadas |
| `expires` | Validade das novas contas ou vazio |
| `summary` | Contadores `total`, `new`, `existing`, `duplicate`, `error` |
| `records` | Linhas; presente em `get` e `preview`, omitido no progresso |
| `offset` | Número de candidatos processados, incluindo conflitos detectados ao gravar |
| `created` | Quantidade efetivamente criada e com credencial na sessão |
| `skipped` | Contas preservadas porque passaram a existir depois da prévia |
| `sync_pending` | Quantidade de sincronizações ainda pendentes |
| `done` | Todos os candidatos processados; a sincronização ainda pode estar pendente |

Cada linha contém `username` (matrícula textual), `name`, `course`, `line` original, `status` e `error` (mensagem ou vazio). Estados: `new`, `existing`, `duplicate`, `error`.

O job público nunca inclui a lista de senhas ou os hashes. Apenas a ação explícita `download` retorna as credenciais emitidas nessa sessão.

## Idempotência e concorrência

O cliente envia o `offset` conhecido. Se for menor que o já gravado, o servidor devolve o progresso, sem recriar o lote. Se for maior ou não for inteiro, rejeita. O cliente não envia a lista de usuários a criar: o servidor usa a prévia guardada na sessão.

O lock nativo de sessão serializa as requisições de um operador, inclusive abas do mesmo navegador. Outro operador possui outra sessão. Na persistência, o lock de `Config` recarrega a configuração e o modelo é instanciado **depois** desse lock. Nomes existentes são conferidos novamente antes de criar.

Uma linha inválida bloqueia o arquivo inteiro antes do primeiro lote. Durante a execução, a atomicidade é por lote e não existe rollback do arquivo inteiro. Um crash entre persistência do config e persistência da sessão pode exigir redefinição manual da senha de contas já criadas; consulte o README.

## Download e retenção

CSV: `matricula;nome;curso;senha;validade`. O frontend transforma o texto em arquivo local com Blob, sem colocar token ou dados pessoais na URL. Campos com indicadores de fórmulas são neutralizados; matrículas recebem prefixo textual para preservar zeros. Esse CSV é de entrega de credenciais, não o modelo de entrada do plugin.

Dados sensíveis ficam disponíveis por 30 minutos após o último lote/importação/sincronização. O prazo é aplicado no próximo acesso ao backend; a coleta física de sessões ociosas depende do PHP. `clear` apaga imediatamente o job da sessão. Não encerra a sessão de administração do OPNsense.

## Compatibilidade com o protótipo

O antigo `get` estático foi substituído pelo diagnóstico e recuperação de job. As ações `list` e `save` descritas na documentação antiga eram apenas propostas e não foram implementadas: a listagem/edição permanente continua no gerenciador nativo do OPNsense.
