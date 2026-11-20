<?php

/*
| Dados da assistência técnica exibidos para o cliente
| (mensagem de WhatsApp, página de acompanhamento e PDFs).
*/

return [
    'name' => env('SHOP_NAME', 'Assistência Técnica Demo'),
    'phone' => env('SHOP_PHONE', ''),
    'address' => env('SHOP_ADDRESS', ''),
    'document' => env('SHOP_DOCUMENT', ''), // CNPJ
];
