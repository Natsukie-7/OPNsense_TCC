# Contrato de API - User Register

Este documento define o padrao de resposta e os endpoints do plugin.

## Padrao de resposta

Sucesso:

{
  "status": "success",
  "message": "Mensagem curta",
  "data": {}
}

Erro:

{
  "status": "error",
  "message": "Descricao do erro",
  "data": {
    "fields": {}
  }
}

Regras:

- status: obrigatorio (success ou error)
- message: obrigatorio e curto
- data: opcional, objeto quando existir payload

## Endpoints atuais

## GET /api/user_register/settings/get

Descricao:

- Endpoint inicial de teste de comunicacao UI <-> backend.

Resposta atual:

{
  "status": "success",
  "message": "O backend recebeu sua requisicao com sucesso!"
}

## Endpoints planejados

## GET /api/user_register/settings/list

Descricao:

- Lista registros de usuario para preencher a tela.

Resposta de sucesso (exemplo):

{
  "status": "success",
  "message": "Lista carregada",
  "data": {
    "items": []
  }
}

## POST /api/user_register/settings/save

Descricao:

- Cria ou atualiza registro de usuario.

Payload base (exemplo):

{
  "username": "",
  "email": "",
  "enabled": true
}

Erros esperados:

- Campo obrigatorio ausente
- Formato invalido
- Registro duplicado

## Convencoes de nome

- Use nomes de acao claros: list/get/save/delete
- Evite endpoint generico sem semantica
- Mantenha consistencia entre nome da acao e comportamento

## Versionamento do contrato

Quando houver mudanca de payload ou semantica:

- Atualizar este documento na mesma PR
- Registrar impacto no README
- Descrever plano de compatibilidade, se necessario
