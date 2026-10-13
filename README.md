# GeekHost

Site institucional em PHP com páginas de apresentação, perfis de hospedagem, contato e gerador de link/QR code para WhatsApp.

## Executar localmente

Instale o PHP 8.0 ou superior e, na raiz do projeto, execute:

```powershell
php -S 127.0.0.1:8000 -t public
```

Depois abra `http://127.0.0.1:8000`.

## Configurar canais de atendimento

Defina as variáveis de ambiente antes de iniciar o servidor:

- `GEEKHOST_CONTACT_EMAIL`: endereço que será usado para preparar mensagens de contato no aplicativo de e-mail do visitante.
- `GEEKHOST_WHATSAPP`: telefone comercial em formato internacional. Use apenas os dígitos, incluindo código do país e DDD.

O formulário de contato usa `mailto:`; ele não envia dados para um servidor. Para receber mensagens sem depender do aplicativo de e-mail do visitante, conecte um backend de envio com proteção contra spam e validação no servidor.

## Personalizar a oferta

Os preços na página de planos são os valores de renovação fornecidos, com os totais semestrais e anuais explícitos. Confirme os valores de contratação inicial, recursos e condições de teste e portabilidade antes de publicar. A imagem do hero e as fontes são carregadas de serviços externos; substitua-as por assets locais se o site precisar funcionar sem conexão com esses serviços.

O QR code é gerado no navegador com QRCode.js carregado do cdnjs. O número e a mensagem do gerador não são enviados a um servidor por esta página.