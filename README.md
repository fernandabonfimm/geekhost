# GeekHost

Site institucional em PHP com páginas de apresentação, perfis de hospedagem, contato e gerador de link/QR code para WhatsApp.

## Executar localmente

Instale o PHP 8.0 ou superior e, na raiz do projeto, execute:

```powershell
php -S 127.0.0.1:8000 -t public
```

Depois abra `http://127.0.0.1:8000`.

## Publicar no cPanel e substituir o WordPress

Faça a troca com um backup verificado e, se possível, em um horário de pouco acesso. Os passos abaixo substituem os arquivos servidos pelo domínio; não apagam automaticamente a conta ou o banco antigo.

### 1. Confirme o domínio e os requisitos

- No cPanel, abra **Domínios** e confirme qual é a raiz de documentos do domínio. Para o domínio principal costuma ser `public_html`; domínios adicionais podem usar outra pasta.
- Em **MultiPHP Manager**, selecione PHP 8.0 ou superior para esse domínio.
- Ative/renove o certificado SSL antes de usar o site em produção. Os links temporários exigem `https://` fora de `localhost`.

### 2. Faça backup do WordPress

- Abra **File Manager** e habilite **Settings > Show Hidden Files (dotfiles)** para localizar também o `.htaccess`.
- Faça um arquivo compactado de todos os arquivos da instalação atual, incluindo `wp-config.php`, `wp-content`, `wp-admin`, `wp-includes` e arquivos ocultos. Guarde o backup fora da raiz pública, por exemplo na pasta inicial da conta, nunca dentro de `public_html`.
- No **phpMyAdmin**, exporte o banco de dados usado pelo WordPress e guarde o `.sql` junto ao backup, também fora da raiz pública.
- Baixe uma cópia do backup para seu computador e confirme que o arquivo compactado e o SQL existem antes de substituir qualquer arquivo. Não publique nem compartilhe esses backups.

### 3. Envie os arquivos GeekHost

- No computador, compacte **o conteúdo da pasta `public/`**, não a pasta `public` como uma pasta externa. Ao abrir o ZIP, `index.php` deve estar na primeira camada, junto de `contato.php`, `planos.php`, `gerador.php`, `assets/` e `includes/`.
- No File Manager, abra a raiz de documentos confirmada no passo 1. Remova ou mova os arquivos antigos do WordPress somente depois do backup verificado. O `.htaccess` antigo pode conter regras de rewrite do WordPress; guarde-o no backup e não o mantenha ativo sem revisar.
- Envie o ZIP para a raiz do domínio e use **Extract**. O resultado correto é `public_html/index.php`, e não `public_html/public/index.php`.
- Confira se `assets/` e `includes/` também estão diretamente nessa raiz. Mantenha as permissões padrão da hospedagem (normalmente pastas `755` e arquivos `644`); não use `777`.

### 4. Configure o ambiente PHP em privado

O site lê as configurações com `getenv()`. Use o recurso de variáveis de ambiente do cPanel/hospedagem, se disponível, ou peça ao suporte da hospedagem para configurá-las para o PHP do domínio. O método varia conforme Apache/PHP-FPM. Não coloque o telefone ou o segredo em `index.php`, não os deixe no repositório e não presuma que `SetEnv` em `.htaccess` será repassado ao PHP-FPM.

Configure:

- `GEEKHOST_CONTACT_EMAIL`: `contato@geekhost.com.br` (já é o padrão do projeto; configure a variável apenas se quiser substituí-lo).
- `GEEKHOST_WHATSAPP`: telefone internacional com país e DDD, somente dígitos. Ele é usado pelo redirecionador `whatsapp.php`; não é escrito nos links HTML.
- `GEEKHOST_PUBLIC_URL`: URL pública canônica do site, por exemplo `https://seudominio.com.br`, sem barra final, query string ou fragmento.
- `GEEKHOST_LINK_SECRET`: segredo aleatório de pelo menos 32 bytes para assinar os links temporários. Gere localmente com `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` e cadastre diretamente na configuração privada do servidor. Não use o mesmo segredo em outros projetos.

Se o cPanel não oferecer um método para disponibilizar variáveis via `getenv()`, peça ao provedor instruções para definir essas quatro variáveis no ambiente PHP-FPM/Apache do domínio. Sem `GEEKHOST_WHATSAPP`, os botões de suporte/planos retornam indisponível; sem `GEEKHOST_PUBLIC_URL` e `GEEKHOST_LINK_SECRET`, a criação de links temporários fica desativada.

### 5. Teste antes de encerrar a migração

- Abra a página inicial, planos e contato usando o domínio com HTTPS. Confirme se imagens, fontes, CSS e JavaScript carregam sem erros.
- Em contato, confira o e-mail exibido e teste o botão de suporte. Nos planos, confirme que cada botão abre o WhatsApp com a mensagem correspondente; o número deve continuar ausente do HTML da página.
- Em `gerador.php`, gere um link e QR code de teste. Confira se o QR aponta para seu domínio em `/ir.php` e se o destino abre o WhatsApp. Links gerados duram 7 dias e, depois, redirecionam para `planos.php`.
- O formulário de contato usa `mailto:`: ele abre o aplicativo de e-mail do visitante, não envia a mensagem ao servidor.
- Só remova o backup quando o site novo estiver validado e você tiver confirmado que pode restaurar o WordPress, se necessário.

## Configurar canais de atendimento

Defina as variáveis de ambiente antes de iniciar o servidor:

- `GEEKHOST_CONTACT_EMAIL`: endereço usado para preparar mensagens de contato; o padrão é `contato@geekhost.com.br`.
- `GEEKHOST_WHATSAPP`: telefone comercial em formato internacional, com código do país e DDD, somente dígitos. É necessário para habilitar os redirecionamentos de suporte/planos; o número não aparece nos links HTML.
- `GEEKHOST_PUBLIC_URL`: URL pública base do site, por exemplo `https://seudominio.com.br`.
- `GEEKHOST_LINK_SECRET`: segredo aleatório com pelo menos 32 bytes, usado para assinar os links temporários. Gere um valor com `php -r "echo bin2hex(random_bytes(32));"` e mantenha-o somente na configuração de ambiente do servidor.

O formulário de contato usa `mailto:`; ele não envia dados para um servidor. Para receber mensagens sem depender do aplicativo de e-mail do visitante, conecte um backend de envio com proteção contra spam e validação no servidor.

## Links temporários do WhatsApp

Configure `GEEKHOST_PUBLIC_URL` e `GEEKHOST_LINK_SECRET` antes de iniciar o servidor. O gerador envia o número e a mensagem ao endpoint PHP, que cria um token assinado sem armazená-los em banco de dados. O link curto e o QR code funcionam por 7 dias; depois, os acessos são direcionados para `planos.php`.

Nesta versão não há integração com checkout ou consulta de assinatura. Portanto, todos os links gerados expiram após 7 dias, mesmo que o visitante contrate um plano nesse intervalo. Use HTTPS em produção e não publique o segredo no repositório.

## Personalizar a oferta

Os preços na página de planos são os valores de renovação fornecidos, com os totais semestrais e anuais explícitos. Confirme os valores de contratação inicial, recursos e condições de teste e portabilidade antes de publicar. A imagem do hero e as fontes são carregadas de serviços externos; substitua-as por assets locais se o site precisar funcionar sem conexão com esses serviços.

O QR code é gerado no navegador com QRCode.js carregado do cdnjs. Ele contém o link temporário assinado do site, que redireciona ao WhatsApp durante o período de validade.