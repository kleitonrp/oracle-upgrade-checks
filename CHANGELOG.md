# Changelog

Todas as mudanças relevantes deste plugin são documentadas aqui.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o versionamento segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [4.3.0] — 2026-09-10

### Alterado
- **Etapa "Validação" removida.** O Oracle AutoUpgrade só emite dois valores
  no campo `Stage` dos relatórios — `PRECHECKS` e `POSTCHECKS` —, confirmado
  contra 4 relatórios reais (`db11g`, `db19c`, `orcl`, `orcl_old`), que têm
  apenas as seções `BEFORE UPGRADE` e `AFTER UPGRADE`. A opção "Validação"
  nunca casava com nada: filtrar por ela sempre devolvia lista vazia.
- Os 6 checks marcados como `VALIDATION` passam a `PRE`: `PURGE_RECYCLEBIN`,
  `DICTIONARY_STATS`, `INVALID_SYS_TABLEDATA`, `INVALID_USR_TABLEDATA`,
  `DEFAULT_RESOURCE_LIMIT` e `DATA_MINING_OBJECT`. `PURGE_RECYCLEBIN` e
  `DICTIONARY_STATS` aparecem nos relatórios como `PRECHECKS`; os demais são
  pré-upgrade pela natureza da verificação. A base fica com 240 PRE e 29 POST.
- `VALIDATION` continua mapeado em `STAGE_LBL`/`STAGE_CLS` para que bases
  antigas que ainda tenham esse valor gravado sigam sendo exibidas.

### Adicionado
- O workflow de release agora confere a integridade do pacote antes de
  publicar: presença dos arquivos críticos da biblioteca (incluindo
  `vendor/Parsedown.php`), declaração da classe `Parsedown` e validade da
  base de checks — no repositório e dentro do zip montado. É a falha que
  deixou a v4.2.0 sair quebrada.

## [4.2.1] — 2026-09-10

### Corrigido
- **Erro fatal em "Ver detalhes" e "Verificar atualizações".** O
  `.gitignore` do projeto excluía `vendor/` (pensado para dependências do
  Composer), o que silenciosamente deixou de fora `vendor/Parsedown.php`
  da biblioteca de atualização. Sem essa classe, a conversão das notas do
  release em HTML causava *Uncaught Error: Class "Parsedown" not found*.
  A regra passa a ignorar apenas o `vendor/` da raiz, preservando o de
  `lib/`.

## [4.2.0] — 2026-09-10

### Corrigido
- **Edição de itens não persistia.** O botão *Salvar* do formulário apenas
  alterava o array em memória e exibia *"clique em Salvar para persistir"*.
  Ao voltar para a aba *Lista*, os dados eram recarregados do servidor por
  cima da edição, descartando-a silenciosamente — a mensagem de sucesso
  aparecia, mas o item permanecia inalterado.
- Erros de salvamento (nonce expirado, falha de rede) agora são exibidos;
  antes passavam despercebidos.
- Remoção de item também persiste imediatamente.

### Adicionado
- Atualização automática pelo painel do WordPress, via GitHub Releases
  ([plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) v5.7).
- Aviso ao sair da página com importação pendente.
- Publicação automatizada por GitHub Actions ao criar uma tag `v*`.

### Alterado
- Header do plugin passa a declarar `4.2.0`, agora alinhado a `OUC_VERSION`
  (declarava `4.0.0` enquanto a constante era `4.1.0`).
- Metadados completados: `Plugin URI`, `Author URI`, `Requires at least`,
  `Requires PHP` e `Text Domain`.
- `Author` deixa de ser o placeholder "Seu Nome".

## [4.1.0] e anteriores

Distribuídas manualmente como `oracle-upgrade-checks-v4-fix*.zip`, sem
histórico de alterações registrado.

[4.3.0]: https://github.com/kleitonrp/oracle-upgrade-checks/releases/tag/v4.3.0
[4.2.1]: https://github.com/kleitonrp/oracle-upgrade-checks/releases/tag/v4.2.1
[4.2.0]: https://github.com/kleitonrp/oracle-upgrade-checks/releases/tag/v4.2.0
