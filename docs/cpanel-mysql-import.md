# cPanel / DirectAdmin — MySQL + Admin Geek

## Arquivo SQL
No repositório: `database/geekhost-cpanel-import.sql`

## Passos
1. No MySQL do cPanel/DA, crie o banco (ex.: `geekhost`) e o usuário `geek6923_banco` com permissão total nele.
2. Importe `geekhost-cpanel-import.sql` no banco selecionado (phpMyAdmin).
3. Coloque `.env` **fora** de `public_html` com:

```
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=geekhost
DB_USER=geek6923_banco
DB_PASS=<senha MySQL>
GEEKHOST_ADMIN_EMAILS=admin@geekhost.com.br,diretoria@geekhost.com.br,geekhostdigital@gmail.com
```

4. Acesse `/admin/login`

## Admin padrão (seed)
- Email: `admin@geekhost.com.br`
- Senha: `Senha@123`
- Role: `super_admin`

Troque a senha após o primeiro acesso.

## Observação
A senha MySQL **não** vai no SQL nem no git — só no `.env` do servidor.
