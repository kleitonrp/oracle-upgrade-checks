# Changelog

Todas as mudanças relevantes deste plugin são documentadas aqui.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o versionamento segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

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

[4.2.1]: https://github.com/kleitonrp/oracle-upgrade-checks/releases/tag/v4.2.1
[4.2.0]: https://github.com/kleitonrp/oracle-upgrade-checks/releases/tag/v4.2.0
