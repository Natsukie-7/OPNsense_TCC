# OPNsense User Register Plugin

Plugin em desenvolvimento para OPNsense, com foco em cadastro e gestão de usuários para o contexto do TCC.

Este documento foi organizado para evitar acoplamento cedo demais e guiar a implementação por fases.

## Documentos complementares

- `docs/PLANO_IMPLEMENTACAO.md`: execução por fases com Definition of Done.
- `docs/API_CONTRATO.md`: padrão e evolução dos endpoints.

## Objetivo do projeto

- Disponibilizar uma interface WebGUI no OPNsense para operações de registro de usuários.
- Expor backend via API MVC do OPNsense para integração da tela com regras de negócio.
- Empacotar e instalar o plugin diretamente no firewall de teste com um fluxo simples de build e deploy.

## Estado atual (MVP inicial)

- Entrada no Lobby com o nome `Cadastro de alunos`.
- Rota de UI do plugin em `/ui/user_register/`.
- Controller de UI carregando a view principal.
- Endpoint de teste em `/api/user_register/settings/get` retornando resposta estática.
- Permissões básicas mapeadas para UI e API.
- Script de build/deploy remoto funcional.

## Estrutura do repositório

```text
.
├── build_plugin.sh
├── Makefile
├── README.md
├── docs/
│   ├── API_CONTRATO.md
│   └── PLANO_IMPLEMENTACAO.md
└── src/
  └── opnsense/
    └── mvc/app/
      ├── controllers/OPNsense/UserRegister/
      │   ├── IndexController.php
      │   └── Api/SettingsController.php
      ├── models/OPNsense/UserRegister/
      │   ├── ACL/ACL.xml
      │   └── Menu/Menu.xml
      └── views/OPNsense/UserRegister/index.volt
```

## Arquitetura (como o plugin funciona hoje)

1. O `Menu/Menu.xml` do modulo cria a entrada no `Lobby` da WebGUI.
2. A rota de UI abre `IndexController`.
3. A view `index.volt` renderiza a tela e usa JavaScript para chamar a API.
4. `SettingsController` responde a chamada em `/api/user_register/settings/get`.
5. O XML de permissões libera acesso para `ui/user_register/*` e `api/user_register/*`.

## Convenções de implementação recomendadas

Para manter o projeto sustentável conforme crescer:

- Controllers de UI devem ficar enxutos: apenas renderização, carregamento de dados para tela e roteamento.
- Regras de negócio devem ficar no Model (ou classes auxiliares no mesmo domínio), não na view.
- Endpoints de API devem responder de forma consistente:
  - `status`: `success` ou `error`
  - `message`: texto curto
  - `data`: payload opcional
- Toda nova rota de UI/API deve ter permissão explícita no XML de priv.
- Evitar lógica crítica em JavaScript inline; mover para arquivo separado quando a tela crescer.
- Antes de implementar uma feature, registrar critérios de aceite (entrada, processamento, saída e erros esperados).

## Plano de implementação por fases

### Fase 1 - Base funcional

- [x] Estrutura de plugin criada.
- [x] Rota UI e API de teste funcionando.
- [x] Build/deploy automatizado.
- [ ] Definir Model inicial (schema e validações mínimas).

### Fase 2 - Funcionalidade principal

- [ ] Criar endpoints reais para operações de usuário (ex.: criar, listar, validar).
- [ ] Implementar persistência/configuração via model do OPNsense.
- [ ] Adaptar a tela para consumir dados reais da API.
- [ ] Tratar erros de backend no frontend com mensagens claras.

### Fase 3 - Robustez

- [ ] Revisar permissões por perfil (não só acesso global).
- [ ] Validar entradas no backend (campos obrigatórios, formato, duplicidade).
- [ ] Adicionar logs úteis para troubleshooting.
- [ ] Criar checklist de testes manuais por fluxo.

### Fase 4 - Entrega

- [ ] Ajustar versão e metadados do plugin.
- [ ] Validar instalação limpa e upgrade.
- [ ] Documentar limitações conhecidas.
- [ ] Congelar escopo do TCC e preparar demonstração.

## Ambiente de desenvolvimento

### 1. Criar estrutura externa de referência

Em uma pasta fora deste repositório:

```bash
git clone https://github.com/opnsense/core.git
git clone https://github.com/phalcon/ide-stubs.git
```

Estrutura esperada:

```text
opnsense-dev/
├── core/
└── ide-stubs/
```

### 2. Configurar VS Code (Intelephense)

No arquivo `.vscode/settings.json`, incluir:

```json
{
  "intelephense.environment.includePaths": [
    "/caminho/para/opnsense-dev/core",
    "/caminho/para/opnsense-dev/ide-stubs"
  ]
}
```

Substitua os caminhos pelo local real da sua máquina.

## Build e deploy

O script `build_plugin.sh` faz:

1. Criação da pasta do plugin no OPNsense remoto.
2. Envio de `src/` e `Makefile` via SCP.
3. Execução remota de:
   - `make clean`
   - `make package`
   - `pkg install -y work/pkg/*.pkg`
   - limpeza de cache de menu
   - restart da WebGUI

Executar manualmente:

```bash
./build_plugin.sh
```

Ou via tarefa do VS Code:

- `Build & Deploy OPNsense Plugin`

## Checklist mínimo antes de cada deploy

- Confirmar IP e acesso SSH ao OPNsense.
- Garantir que novas rotas têm permissão no XML.
- Revisar se respostas da API seguem padrão (`status`, `message`, `data`).
- Validar navegação básica da UI após restart da WebGUI.
- Testar pelo menos um cenário de sucesso e um de erro.

## Riscos atuais e mitigação

- Risco: endpoint ainda com resposta mock.
  - Mitigação: priorizar implementação do model e payload real na Fase 2.
- Risco: JavaScript inline pode crescer desorganizado.
  - Mitigação: extrair scripts para arquivo dedicado ao ampliar a tela.
- Risco: permissões amplas podem expor mais que o necessário.
  - Mitigação: granular permissões conforme novos endpoints surgirem.

## Próximos passos sugeridos (curto prazo)

1. Definir entidade principal de dados do plugin (campos e validações).
2. Implementar primeiro endpoint real (ex.: listagem) no `SettingsController`.
3. Conectar a view a esse endpoint com renderização de dados reais.
4. Registrar exemplos de request/response para manter padrão de API.
