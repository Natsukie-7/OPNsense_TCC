# Plano de Implementacao - User Register

Este plano quebra a implementacao em entregas pequenas para reduzir retrabalho.

## Objetivo

Implementar um plugin OPNsense com UI e API para registro/gestao de usuarios, com padrao consistente de codigo e permissao.

## Contexto atual (agosto/2026)

- Entrada de menu exibida no Lobby como `Cadastro de alunos`
- Definicao de menu em `src/opnsense/mvc/app/models/OPNsense/UserRegister/Menu/Menu.xml`
- Rota de UI atual: `/ui/user_register/`
- Endpoint de teste atual: `/api/user_register/settings/get`
- ACL atual liberando: `ui/user_register/*` e `api/user_register/*`

## Definicao de pronto (DoD)

Uma feature e considerada pronta quando:

- Tem criterio de aceite descrito
- Possui rota UI/API mapeada (quando aplicavel)
- Possui permissao no XML
- Retorna erros previsiveis e mensagens claras
- Foi validada manualmente no OPNsense
- Foi documentada no contrato de API

## Backlog por ordem sugerida

## 1) Modelagem inicial

- Definir entidade principal e campos
- Definir validacoes de negocio
- Definir estrategia de persistencia

Criterios de aceite:

- Campos definidos e documentados
- Casos invalidos mapeados
- Estrategia de persistencia aprovada

## 2) API de leitura

- Implementar endpoint de listagem/consulta
- Padronizar formato de resposta

Criterios de aceite:

- Endpoint retorna status/message/data
- Erro de validacao retorna status=error
- View consegue consumir o retorno

## 3) API de escrita

- Implementar endpoint de criacao/atualizacao
- Validar entradas obrigatorias

Criterios de aceite:

- Gravacao bem-sucedida
- Erros de validacao claros
- Sem quebra de fluxo na UI

## 4) UI funcional

- Exibir dados reais
- Tratar loading, erro e sucesso
- Remover dependencia de scripts inline grandes

Criterios de aceite:

- Fluxo principal completo na interface
- Mensagens amigaveis ao usuario
- Comportamento consistente apos reload

## 5) Seguranca e permissao

- Revisar escopo de permissao por rota
- Garantir menor privilegio possivel

Criterios de aceite:

- Rotas protegidas corretamente
- Nao ha acesso indevido para perfil sem permissao

## 6) Qualidade de entrega

- Validar build/deploy limpo
- Testar instalacao em ambiente de teste
- Atualizar README e contrato de API

Criterios de aceite:

- Deploy concluido sem erro
- Fluxos principais validados
- Documentacao atualizada

## Checklist por PR

- O que muda nesta PR?
- Qual problema resolve?
- Quais arquivos de permissao foram alterados?
- Quais cenarios foram testados?
- Existe impacto de compatibilidade?

## Registro rapido de decisoes

Use este bloco em cada iteracao:

- Data:
- Feature:
- Decisao tomada:
- Alternativas descartadas:
- Risco aceito:
- Proxima acao:
