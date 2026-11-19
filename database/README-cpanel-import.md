# Instalar banco GeekHost (cPanel / DirectAdmin)

## Opção recomendada: instalador PHP (sem phpMyAdmin)

1. Crie o banco MySQL e o user `geek6923_banco` com permissão nele.
2. Coloque `.env` em `domains/geekhost.com.br/.env` com `DB_*` e:
   ```
   INSTALL_DB_KEY=geekhost-install-tmp
   ```
3. Envie `public_html/` (inclui `install-db.php` + `geekhost-cpanel-import.sql`).
4. Abra:
   `https://geekhost.com.br/install-db.php?key=geekhost-install-tmp`
5. Confira a lista de tabelas OK.
6. **Apague** `public_html/install-db.php` e `public_html/geekhost-cpanel-import.sql`.

## Alternativa: phpMyAdmin

Use **`database/geekhost-cpanel-import.sql`** (ou a cópia em `public_html/`).

1. Selecione o banco antes.
2. Aba **Importar** → arquivo `.sql` → Executar.
3. Não cole na aba SQL.

Se a mensagem do MySQL vier vazia (“Documentação”), quase sempre: banco não selecionado, user sem CREATE, ou editor juntou linhas.

## Admin padrão

- URL: `https://geekhost.com.br/admin/login`
- Email: `admin@geekhost.com.br`
- Senha: `GeekHost@Admin1`
