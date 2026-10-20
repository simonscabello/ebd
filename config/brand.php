<?php

/*
 * A marca do produto num lugar só (nome, assinatura, cores do navegador).
 * Trocar a marca é mudar este arquivo, resources/js/lib/brand.ts e os
 * arquivos de public/brand e public/icons (gerados por docs/brand/gerar-assets.mjs).
 */
return [
    'name' => 'Didaché',
    'tagline' => 'Ensino bíblico em comunidade',
    // Cores do navegador e do app instalado: a paleta do app (verde sálvia e papel).
    'theme_color' => '#2f5f53',
    // No app instalado (Android), o fundo do manifesto também pinta a barra de navegação
    // do sistema. Mesma cor da barra de status: fica certo no tema claro e no escuro.
    'background_color' => '#2f5f53',

    // "Apoie a Didaché": o app é gratuito e mantido por ofertas voluntárias via Pix.
    // Chave aleatória; nome (até 25) e cidade (até 15) vão no QR Code, sem acento.
    'pix' => [
        'key' => '3d43655c-6fd3-456d-aded-5e05d7bf15ac',
        'merchant_name' => 'Didache',
        'merchant_city' => 'SAO PAULO',
    ],
];
