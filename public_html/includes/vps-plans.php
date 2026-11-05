<?php

declare(strict_types=1);

$vpsPlans = [
    [
        'id' => 'vps-start',
        'name' => 'VPS Start',
        'tag' => 'Para começar',
        'price' => 'R$ 89,90',
        'period' => '/mês',
        'highlight' => false,
        'specs' => [
            '1 vCPU dedicada',
            '2 GB RAM',
            '40 GB NVMe SSD',
            '2 TB de banda',
        ],
        'features' => [
            'Acesso root',
            'IPv4 dedicado',
            'LiteSpeed',
            'SSL grátis',
            'Softaculous',
        ],
    ],
    [
        'id' => 'vps-plus',
        'name' => 'VPS Plus',
        'tag' => 'Mais escolhido',
        'price' => 'R$ 149,90',
        'period' => '/mês',
        'highlight' => true,
        'specs' => [
            '2 vCPU dedicadas',
            '4 GB RAM',
            '80 GB NVMe SSD',
            '3 TB de banda',
        ],
        'features' => [
            'Acesso root',
            'IPv4 dedicado',
            'LiteSpeed',
            'SSL grátis',
            'CloudLinux + CageFS',
            'Cloudflare CDN',
        ],
    ],
    [
        'id' => 'vps-pro',
        'name' => 'VPS Pro',
        'tag' => 'Alta demanda',
        'price' => 'R$ 249,90',
        'period' => '/mês',
        'highlight' => false,
        'specs' => [
            '4 vCPU dedicadas',
            '8 GB RAM',
            '160 GB NVMe SSD',
            '4 TB de banda',
        ],
        'features' => [
            'Acesso root',
            'IPv4 dedicado',
            'LiteSpeed',
            'SSL grátis',
            'CloudLinux + CageFS',
            'Cloudflare CDN',
            'Prioridade no suporte',
        ],
    ],
];

$vpsHighlights = [
    ['label' => 'NVMe SSD', 'text' => 'Disco ultrarrápido para sites e apps'],
    ['label' => 'LiteSpeed', 'text' => 'Webserver de alta performance'],
    ['label' => 'CloudLinux', 'text' => 'Isolamento e estabilidade'],
    ['label' => 'SSL grátis', 'text' => 'Certificados ilimitados'],
    ['label' => 'Softaculous', 'text' => 'Apps com 1 clique'],
    ['label' => 'CDN', 'text' => 'Cloudflare para acelerar entregas'],
];

$vpsUseCases = [
    'Sites e blogs de alto tráfego',
    'E-commerce e lojas online',
    'Aplicações web e APIs',
    'Ambientes de desenvolvimento',
    'Painéis e sistemas internos',
    'Projetos com necessidade de root',
];

$vpsFeatures = [
    ['title' => 'Desempenho NVMe', 'text' => 'Armazenamento SSD NVMe para resposta rápida em leitura e escrita.'],
    ['title' => 'Recursos dedicados', 'text' => 'CPU e memória reservadas para o seu projeto, sem dividir com dezenas de contas.'],
    ['title' => 'Acesso root', 'text' => 'Controle total do servidor para instalar, configurar e escalar como precisar.'],
    ['title' => 'LiteSpeed', 'text' => 'Servidor web otimizado para WordPress, PHP e aplicações modernas.'],
    ['title' => 'Segurança reforçada', 'text' => 'CloudLinux, CageFS e SSL grátis para isolar e proteger o ambiente.'],
    ['title' => 'CDN Cloudflare', 'text' => 'Entrega mais rápida e proteção extra na borda da rede.'],
    ['title' => 'Instalador Softaculous', 'text' => 'Suba WordPress e centenas de apps com poucos cliques.'],
    ['title' => 'Suporte GeekHost', 'text' => 'Atendimento humano pelo WhatsApp para orientar a contratação e o uso.'],
];

$vpsFaqs = [
    [
        'q' => 'O que é um servidor VPS?',
        'a' => 'Um VPS (Virtual Private Server) é um servidor virtual com recursos dedicados de CPU, memória e disco. Ele oferece mais controle e desempenho do que a hospedagem compartilhada tradicional.',
    ],
    [
        'q' => 'Qual a diferença entre VPS e hospedagem de sites?',
        'a' => 'Na hospedagem de sites, vários projetos compartilham a mesma estrutura. No VPS, você tem recursos reservados, acesso root e mais liberdade para configurar o ambiente conforme o projeto.',
    ],
    [
        'q' => 'Posso migrar meu site para um VPS GeekHost?',
        'a' => 'Sim. Fale com a equipe pelo WhatsApp para avaliarmos a migração e o plano mais adequado ao seu cenário.',
    ],
    [
        'q' => 'Os planos incluem SSL e instalador de apps?',
        'a' => 'Sim. Os planos VPS incluem SSL gratuito, LiteSpeed e Softaculous para facilitar a publicação de sites e aplicações.',
    ],
    [
        'q' => 'Como faço para contratar?',
        'a' => 'Escolha o plano na página, fale conosco pelo WhatsApp e nossa equipe confirma a configuração e a ativação do servidor.',
    ],
];
