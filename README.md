# Oracle Upgrade Checks — WordPress Plugin

Plugin WordPress para análise de relatórios gerados pelo **Oracle AutoUpgrade**, cruzando os checks encontrados com uma base de conhecimento de verificações.

---

## 🚀 Funcionalidades

- **Upload de relatório** — suporta `check_upgrade.log` e `check_patching.log`
- **Destaque automático** — checks encontrados no relatório ficam marcados em verde na lista
- **Paginação de PDBs** — visualização de ambientes CDB com múltiplos PDBs
- **Filtros** — por severidade, etapa, tipo de correção, categoria e status no relatório
- **Checks desconhecidos** — detecta e alerta sobre checks não cadastrados
- **Notificação Telegram** — aviso automático quando checks desconhecidos são encontrados
- **Painel Admin** — CRUD completo, importação/exportação JSON, histórico de ocorrências

---

## 📋 Como usar

1. Execute o AutoUpgrade em modo Analyze:
   ```bash
   java -jar autoupgrade.jar -config config.cfg -mode analyze
   ```

2. Localize o relatório gerado:
   ```
   /home/oracle/autoupgrade/log/<DB>/<DB>/100/prechecks/<db>_preupgrade.log
   ```

3. Acesse a página do plugin no WordPress e faça upload do arquivo

4. Os checks encontrados serão destacados em verde na lista

---

## 🔧 Instalação

1. Faça o download do plugin (`.zip`)
2. No WordPress Admin, vá em **Plugins → Adicionar Novo → Enviar Plugin**
3. Ative o plugin
4. Use o shortcode `[oracle_upgrade_checks]` em qualquer página ou post

### Shortcode com filtros predefinidos
```
[oracle_upgrade_checks severity="ERROR"]
[oracle_upgrade_checks stage="PRE"]
```

---

## ⚙️ Configuração do Telegram

1. No WordPress Admin, vá em **Oracle Checks → ⚙️ Configurações**
2. Informe o **Token do Bot** e o **Chat ID**
3. Clique em **Enviar mensagem de teste** para confirmar

Para obter o Chat ID, envie uma mensagem para seu bot e acesse:
```
https://api.telegram.org/botSEU_TOKEN/getUpdates
```

---

## 📁 Estrutura do projeto

```
oracle-upgrade-checks-v4/
├── oracle-upgrade-checks.php       # Plugin principal
└── assets/
    ├── oracle-checks-default.json  # Base de dados (269 checks)
    ├── oracle-checks.css           # Estilos frontend
    ├── oracle-checks.js            # Placeholder (lógica está inline no PHP)
    ├── oracle-admin.css            # Estilos painel admin
    └── oracle-admin.js             # JS painel admin
```

---

## 🗄️ Dados

- **Base de checks:** `wp-content/oracle-checks-data.json` (gerado na ativação)
- **Log de desconhecidos:** `wp-content/oracle-checks-unknown-log.json`
- **Configurações Telegram:** salvas no banco de dados do WordPress (`wp_options`)

> ⚠️ Esses arquivos estão no `.gitignore` — nunca são versionados.

---

## 🛠️ Desenvolvimento

### Pré-requisitos
- WordPress 5.0+
- PHP 7.4+

### Contribuindo
1. Fork o repositório
2. Crie uma branch: `git checkout -b feature/minha-feature`
3. Commit: `git commit -m 'Adiciona minha feature'`
4. Push: `git push origin feature/minha-feature`
5. Abra um Pull Request

---

## 📄 Licença

GPL-2.0+