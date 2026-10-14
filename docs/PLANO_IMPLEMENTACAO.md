# Entrega e homologação · 2.0.0

## Implementado

- [x] Leitura do formato real: abas por curso, A=matrícula, B=nome, sem cabeçalho obrigatório.
- [x] Prévia paginada, filtros, resumo, confirmação e mensagens de erro.
- [x] Validações no servidor, limites de ZIP/XML, bloqueio de fórmulas e prevenção de CSV injection.
- [x] Importação por modelo nativo com locks, sem edição manual da configuração XML.
- [x] Novas contas habilitadas sem privilégios/shell; existentes preservadas.
- [x] Senhas individuais aleatórias, hash nativo, validade opcional.
- [x] Lotes de dez, repetição idempotente, pausa/retomada e sincronização separada.
- [x] Exportação de credenciais e remoção dos dados da sessão.
- [x] ACL explícita por endpoint e proteção de sessão/CSRF pelo framework.
- [x] Bundle com dependências travadas e instalador SSH com checksum e backup.
- [x] Documentação de uso, contrato API, limites, privacidade e recuperação.

## Evidência local em 13/10/2026

- Planilha fornecida analisada somente em leitura: 1.193 registros, 10 abas, nenhuma duplicidade ou linha inválida.
- 30 verificações de planilha/validação/segurança passaram.
- 17 verificações do protocolo de sessão/importação passaram com adapters simulados.
- PHP lint, sintaxe JavaScript, parse XML e auditoria Composer passaram.
- SimpleXLSX 1.1.19 instalado pelo Composer; nenhuma vulnerabilidade conhecida reportada pela auditoria executada.
- Teste com modelos nativos preparado, mas o PHP local não possui Phalcon. **Não considerar esse teste aprovado no Linux.** O instalador exige sua execução no ambiente OPNsense antes de alterar o pacote.
- SSH da VM responde no endereço configurado; autenticação por chave recusada. Instalação e homologação dependem da autenticação no terminal.

## Checklist obrigatório na VM

### Instalação e atualização

- [ ] Confirmar versão/arquitetura reais da VM e extensões PHP.
- [ ] Fazer snapshot pelo hypervisor, mantendo acesso pelo console.
- [ ] Executar pré-check SSH e confirmar identidade da chave do host.
- [ ] Executar teste nativo isolado do instalador e verificar resultado.
- [ ] Instalar o pacote; conferir verificação de checksums.
- [ ] Verificar menu, abertura da tela, arquivos JS/CSS e ausência de erro PHP.
- [ ] Testar atualização de uma instalação anterior sem perda de contas.
- [ ] Confirmar localização do backup e testar rollback em snapshot de laboratório.

### Fluxo funcional, inicialmente com dados fictícios

- [ ] Importar duas abas, incluindo uma sem cabeçalho e RA com zeros iniciais.
- [ ] Prévia não altera usuários nem configurações.
- [ ] Confirmar cadastro e verificar usuários no gerenciador nativo.
- [ ] Validar nome, comentário de curso, expiração e UID regular.
- [ ] Confirmar ausência de shell, privilégios WebGUI, chaves e grupos privilegiados.
- [ ] Baixar credenciais e validar o login individual de teste.
- [ ] Reenviar a planilha: nenhuma senha, nome ou permissão existente é alterada.
- [ ] Pausar e retomar sem duplicações; recarregar a página e recuperar o progresso.
- [ ] Simular perda de conexão, retomar pelo offset e conferir contagens.
- [ ] Falha de configd exibe pendência; nova tentativa não recria usuários.
- [ ] Encerrar remove prévia/credenciais sem excluir usuários.

### Segurança

- [ ] Usuário sem ACL não consegue abrir a tela nem chamar endpoints.
- [ ] POST sem token CSRF é recusado pelo framework.
- [ ] Token de prévia de outra sessão e API key são recusados.
- [ ] GET de importação/download/limpeza retorna 405.
- [ ] Campos malformados, fórmulas e arquivo acima do limite são rejeitados.
- [ ] Nome conflitante para mesma matrícula bloqueia confirmação.
- [ ] Aluno não recebe acesso administrativo à WebGUI ou ao SSH.
- [ ] CSV é entregue individualmente e armazenado somente em local autorizado.

### Portal Cativo

- [ ] Zona configurada na rede de teste e autenticador Local Database selecionado.
- [ ] Cliente recebe DHCP/DNS e alcança o portal.
- [ ] Senha correta autentica e libera acesso conforme regras existentes.
- [ ] Senha incorreta, usuário desabilitado e conta expirada são recusados.
- [ ] As regras e interfaces de produção não foram alteradas pelo plugin.

## Limites de escopo

Sem integração com ERP acadêmico, autorregistro público, exclusão em massa, envio de e-mail, redefinição automática de contas existentes ou gerenciamento de zonas/regras do Portal Cativo. Histórico persistente é o de configuração/auditoria do OPNsense; o relatório detalhado de importação é temporário. Não há transação única de toda a planilha nem recuperação de senha em claro após perda da sessão/CSV.

Uma entrega só está **homologada na VM** depois de concluir os itens aplicáveis acima. Compilação e testes simulados não substituem esse aceite.
