# Deploy geekhost.com.br (DirectAdmin / cPanel)

## Path no servidor

Conta revenda típica ValueServer/DA:

```
/home/geek6923/domains/geekhost.com.br/public_html/   ← DocumentRoot (site)
/home/geek6923/domains/geekhost.com.br/.env           ← secrets (FORA do web)
```

- Domínio: `geekhost.com.br`
- Raiz pública: **`public_html`** (conteúdo de `public_html/` do repo)
- URL pública: `https://geekhost.com.br/` — **sem** `/public_html` na URL
- Admin: `https://geekhost.com.br/admin/login`
- Painel cliente: `https://geekhost.com.br/painel/`

O PHP carrega `.env` em `domains/geekhost.com.br/.env` (irmão de `public_html`).

## O que enviar

1. Conteúdo de `public_html/` → `domains/geekhost.com.br/public_html/`
2. Opcional: `database/geekhost-cpanel-import.sql` (import no phpMyAdmin)
3. `.env` em `domains/geekhost.com.br/.env` (nunca dentro de `public_html`)

## Asaas em produção

Já é automático no código (`geekhost_resolve_asaas_env`):

- Host `geekhost.com.br` ou `www.geekhost.com.br` **+ HTTPS** → **Asaas production**
- Qualquer outro host / HTTP / local → sandbox

No `.env` de produção preencha:

```
ASAAS_PRODUCTION_URL=https://api.asaas.com/v3
ASAAS_PRODUCTION_API_KEY=...
ASAAS_PRODUCTION_WEBHOOK_TOKEN=...
GEEKHOST_PUBLIC_URL=https://geekhost.com.br
```

Webhook Asaas (produção): `https://geekhost.com.br/api/webhooks/asaas`  
Header: `asaas-access-token` = `ASAAS_PRODUCTION_WEBHOOK_TOKEN`

`ASAAS_ENV=sandbox` no `.env` **não impede** production no domínio oficial HTTPS — a detecção por host/HTTPS tem prioridade.

## MySQL

```
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_NAME=geekhost
DB_USER=geek6923_banco
DB_PASS=...
```

Import: `database/geekhost-cpanel-import.sql`
