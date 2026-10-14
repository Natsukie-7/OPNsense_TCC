# User Register · Cadastro de alunos no OPNsense

Plugin 2.0.0 para importar uma planilha de alunos e criar **contas locais do OPNsense**, utilizáveis pelo Portal Cativo com autenticação **Local Database**. Menu: **Lobby → Cadastro de alunos**. URL: `/ui/user_register/`.

Não é um sistema acadêmico: não matricula pessoas no ERP da faculdade, não configura regras de firewall automaticamente e não cria zonas de Portal Cativo. Essas operações exigem configurações ou integrações específicas.

## Funcionalidades

- Upload XLSX com várias abas, com ou sem cabeçalho; aba identifica o curso.
- Prévia com contagens, paginação, busca e indicação de erros, contas existentes e repetições.
- Matrícula como login; nome na descrição e curso no comentário do usuário nativo.
- Senhas aleatórias individuais de 24 caracteres, nunca derivadas da matrícula.
- Contas sem shell, sem privilégios administrativos, sem chaves SSH/API e sem associação a grupos.
- Data de expiração opcional; criação em lotes de dez, com pausa e retomada na mesma sessão.
- Preservação de contas existentes, inclusive senhas, nomes, grupos e permissões.
- Download de credenciais CSV e remoção explícita dos dados temporários.
- Gravação pelo modelo `OPNsense\Auth\User`, com lock de configuração, revalidação de duplicidade e hash nativo.
- Sincronização pelo configd, com indicação de falhas e possibilidade de tentar novamente.

## Formato da planilha

| Coluna | Conteúdo | Exemplo fictício |
|---|---|---|
| A | Matrícula/RA: 5 a 20 dígitos | 0012345678901 |
| B | Nome completo, até 200 bytes em UTF-8 | Pessoa de Exemplo |

Cada aba corresponde a um curso. A primeira linha já pode conter um aluno. Cabeçalhos como `RA / Nome`, `Matrícula / Nome completo` são opcionais. Linhas vazias são ignoradas. Colunas adicionais não são importadas. Todas as abas são consideradas, inclusive abas ocultas; remova da cópia de importação qualquer aba que não deva ser processada.

**Matrículas devem preferencialmente estar como Texto no Excel.** Zeros já perdidos pelo Excel não podem ser recuperados. Números com mais de 15 dígitos e notação científica são rejeitados para impedir cadastro com matrícula arredondada. Fórmulas em A/B, macros, links externos e arquivos protegidos por senha não são aceitos.

Limites: 5 MiB compactados, 25 MiB descompactados, 8 MiB por entrada ZIP, 250 entradas, 100.000 delimitadores de tags XML, 30 abas e 5.000 linhas físicas. O limite de upload do PHP também se aplica. `.xls`, CSV e `.xlsm` não são entradas suportadas.

Validação da planilha fornecida em 13/10/2026: **10 abas, 1.193 alunos, zero duplicidades e zero erros**, somente leitura. Os dados pessoais não fazem parte do repositório, dos testes ou do pacote.

## Requisitos

### Computador de desenvolvimento

Linux, Bash, PHP 8.2+ com ZipArchive/SimpleXML/DOM/mbstring, Composer, Python 3, tar, sha256sum, SSH e SCP. Node.js é opcional para validação sintática do JavaScript.

### VM

OPNsense recente com `OPNsense\Auth\User`, `generatePasswordHash()`, `Config::lock()` e `Backend::configdpRun()`. O alvo é a série 26.7; **a versão efetivamente instalada é conferida pelo instalador**, e a homologação real não deve ser presumida só pelos testes Linux. Requer PHP 8.2+, zip, SimpleXML, DOM, mbstring e os componentes MVC/Phalcon da distribuição. Não é compatível com pfSense nem com versões antigas que só possuem o gerenciador legado de usuários.

O instalador avisa caso falte uma extensão. Instale a extensão correspondente à versão PHP da própria VM; não substitua o PHP do OPNsense por pacotes de outra versão. Ajuste `upload_max_filesize`/`post_max_size` pelos mecanismos suportados pela versão do OPNsense se o arquivo superar o limite do ambiente.

## Uso

1. Faça um snapshot da VM antes da primeira instalação. Para teste inicial, utilize **uma planilha pequena com alunos fictícios**.
2. Entre na interface HTTPS como administrador. Delegue a permissão **System: User Register — importar alunos e emitir credenciais** somente a operadores autorizados. Ela permite criar contas e consultar dados pessoais/senhas da própria importação.
3. Acesse **Lobby → Cadastro de alunos** e envie a planilha; informe a validade, se desejado.
4. Confira todas as abas. Os filtros da tabela são apenas visuais, não selecionam o que será importado.
5. Havendo erros, corrija a planilha e envie novamente. Qualquer linha inválida bloqueia a confirmação do arquivo inteiro.
6. Marque a autorização e clique em **Criar contas**. Não feche a página durante um lote. A pausa mantém as contas já criadas.
7. Aguarde também a sincronização. Em caso de falha, confira o serviço configd e retome a sincronização pela mesma tela.
8. Baixe o CSV e confirme que foi salvo. Entregue **somente a credencial individual** de cada aluno por canal seguro.
9. Clique em **Encerrar e apagar dados temporários**. Isso não apaga as contas já criadas.

Usuários ficam disponíveis no gerenciador nativo **System → Access → Users** (o rótulo pode variar com o idioma/versão). Alterações, bloqueios, redefinições de senha e exclusões continuam sendo feitos por esse gerenciador.

## Portal Cativo

Na zona de Portal Cativo da rede dos alunos, selecione **Local Database** como servidor de autenticação. Aplique as configurações e teste por um dispositivo conectado à interface dessa zona: um login novo deve funcionar; senha incorreta deve falhar. DNS, DHCP, interfaces, regras de firewall, redirecionamento e acesso à Internet permanecem sob responsabilidade da configuração da rede. O plugin não muda essas regras.

Não conceda acesso WebGUI/SSH aos alunos. Autenticar na base local não equivale a autorizar acesso administrativo. Valide essa separação no ambiente antes de usar dados reais.

## Build e implantação SSH

O ponto de entrada é [build_plugin.sh](build_plugin.sh). A tarefa existente do VS Code **Build & Deploy OPNsense Plugin** executa o deploy completo.

| Modo | Resultado |
|---|---|
| Sem argumentos | Testes, dependências, bundle, SSH, testes na VM, backup, pacote e instalação |
| `--package-only` | Testes e bundle local com dependências; não conecta à VM |
| `--check` | Prepara bundle e verifica conexão/requisitos básicos da VM; não instala |

Configurações por ambiente:

| Variável | Padrão | Finalidade |
|---|---|---|
| `OPNSENSE_IP` | `192.168.56.10` | IP, hostname ou alias do SSH |
| `OPNSENSE_PORT` | `22` | Porta SSH; pode ser a porta local encaminhada pelo túnel |
| `OPNSENSE_USER` | `root` | Usuário; o instalador exige UID 0 |
| `OPNSENSE_IDENTITY` | não definida | Caminho da chave privada existente, opcional |

Se a conexão passa por túnel, use o host/alias e a porta que **encaminham ao SSH da VM**, não a porta do painel HTTPS. Configurações `ProxyJump`/`IdentityFile` em `~/.ssh/config` são respeitadas. Nenhuma opção desabilita a verificação de identidade do servidor. Verifique a impressão digital pelo console da VM caso apareça um host novo.

**Digite senhas exclusivamente no terminal do SSH.** Não use senha em variáveis, scripts, documentação, mensagens de chat ou parâmetros de linha de comando.

O deploy:

1. Instala dependências com [composer.lock](src/opnsense/mvc/app/library/OPNsense/UserRegister/composer.lock), executa testes e auditoria Composer.
2. Produz o bundle de fontes e seu SHA-256 em `dist/` (artefatos ignorados pelo Git).
3. Reutiliza uma conexão SSH autenticada para SCP, verifica checksum no destino e executa o instalador remoto.
4. Verifica APIs e executa testes do plugin, incluindo modelos nativos com configuração temporária fictícia; não cadastra alunos na configuração real.
5. Preserva configuração e pacote anterior, quando instalado, em uma pasta privada de backup dentro de `/conf/backup/` na VM.
6. Cria um pacote FreeBSD com `pkg create`, instala com `pkg add`, verifica checksums e reinicia a WebGUI. Haverá breve indisponibilidade do painel.
7. Copia somente o pacote gerado para `dist/` no computador. **Não copia a configuração da VM nem planilhas.**

O pacote mantém o identificador da base original, `os-OPNSense_User_Register-devel`, versão 2.0.0. O sufixo `-devel` foi preservado para permitir atualizar a instalação existente; não indica publicação no repositório oficial. O método padrão não exige `/usr/plugins`, ports tree, make ou Composer na VM. [Makefile](Makefile) permanece disponível para ambientes com o framework oficial de plugins; gere o vendor antes de usar esse caminho.

## Testes e limitações de validação

- [tests/run.sh](tests/run.sh): lint PHP, 30 verificações de leitor/validação/segurança, 17 verificações de protocolo HTTP/sessão com adapters simulados, sintaxe JS e XML. Aceita caminho opcional de uma planilha para resumo **sem imprimir alunos**.
- [tests/native.php](tests/native.php): testes com `Auth\User` e `Core\Config` reais em diretório temporário e configd simulado. Aceita checkout do core ou `/usr/local` na VM. Sem Phalcon, termina com código 77 e mensagem SKIP; o teste não é anunciado como aprovado.
- O teste nativo não testa o configd real, a autenticação HTTPS nem o Portal Cativo. Use o [checklist de homologação](docs/PLANO_IMPLEMENTACAO.md).
- Em Linux sem o runtime completo, o editor pode informar classes OPNsense desconhecidas. Configure os caminhos das fontes do core e stubs da versão correspondente; não instale classes falsas dentro do plugin para mascarar problemas.

## Segurança, LGPD e recuperação

- Senhas são persistidas **somente como hashes na configuração de usuários**. O algoritmo segue a configuração nativa do OPNsense (normalmente bcrypt).
- A prévia e o CSV exigem sessão WebGUI autenticada, ACL e CSRF. API keys não são aceitas nesse fluxo. Respostas possuem `Cache-Control: no-store`.
- As credenciais em claro ficam temporariamente no armazenamento de sessão do servidor e no CSV baixado. O prazo lógico é 30 minutos desde o último lote; expiração é verificada no próximo acesso. A remoção física de sessões ociosas também depende do coletor de sessões do PHP. Para remoção imediata use **Encerrar**. Proteja os arquivos/snapshots da VM e o computador do operador.
- Nenhuma senha é retornada na prévia ou no progresso. Não há endpoint de recuperar senhas de contas já existentes.
- A atomicidade é **por lote**, não pelo arquivo inteiro. Interrupção, expiração ou falha em lote posterior não desfaz os anteriores. Reenvio preserva contas já criadas, mas não recupera suas senhas se a sessão/CSV foi perdido.
- Uma interrupção abrupta do processo entre a gravação da configuração e a gravação da sessão pode deixar contas criadas sem credenciais exportáveis; redefina essas senhas pelo gerenciador nativo. Não há promessa de transação distribuída entre configuração e sessão.
- A sincronização é separada da persistência. Falhas são exibidas e podem ser repetidas sem redefinir senhas; não encerre a sessão antes de resolver as pendências.
- Matrícula repetida com mesmo nome é criada uma vez; com nomes diferentes, bloqueia a importação. Nenhuma conta é automaticamente removida por não aparecer em uma nova planilha.
- Não inclua planilhas/credenciais reais em commits, issues ou logs. Retenção e entrega aos alunos devem seguir a política da instituição e a LGPD.

### Rollback

O instalador imprime a pasta de backup na VM. Reinstale o pacote anterior dessa pasta pelo gerenciador `pkg` e reinicie a WebGUI. Sem pacote anterior, remova o plugin pelo gerenciador de pacotes. Essas operações **não removem usuários criados**. Restaurar toda a configuração ou o snapshot também reverte outras mudanças do firewall; só faça isso conscientemente pelo fluxo de recuperação do OPNsense. O instalador não executa rollback destrutivo automático.

## Organização

- [SettingsController.php](src/opnsense/mvc/app/controllers/OPNsense/UserRegister/Api/SettingsController.php): protocolo autenticado e estado da importação.
- [SpreadsheetReader.php](src/opnsense/mvc/app/library/OPNsense/UserRegister/SpreadsheetReader.php): limites de arquivo e leitura com SimpleXLSX (MIT; licença distribuída no vendor).
- [ImportPlanner.php](src/opnsense/mvc/app/library/OPNsense/UserRegister/ImportPlanner.php): validação, duplicidade e CSV.
- [UserImporter.php](src/opnsense/mvc/app/library/OPNsense/UserRegister/UserImporter.php): integração nativa de usuários e sincronização.
- [API_CONTRATO.md](docs/API_CONTRATO.md): endpoints e payloads.
- [PLANO_IMPLEMENTACAO.md](docs/PLANO_IMPLEMENTACAO.md): estado verificável e homologação.
