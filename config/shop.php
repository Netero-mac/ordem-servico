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

    /*
    | Textos impressos nos comprovantes. ":days" é trocado pelos dias de garantia da OS.
    | IMPORTANTE: são textos-modelo. Revise com um advogado/contador antes de usar na loja.
    */

    'entry_terms' => [
        'Nenhum reparo é feito sem a aprovação do orçamento pelo cliente.',
        'Recomendamos fazer backup dos dados do aparelho antes do serviço.',
        'O cliente será avisado quando o aparelho estiver pronto. A retirada deve ser feita com a apresentação deste comprovante ou de documento com foto.',
        'Os acessórios entregues com o aparelho estão descritos acima; itens não listados não são de responsabilidade da loja.',
    ],

    'warranty_terms' => [
        'Garantia de :days dias a partir da data de entrega, cobrindo exclusivamente os serviços executados e as peças substituídas descritos nesta ordem de serviço.',
        'A garantia não cobre danos causados por queda, contato com líquidos, oxidação, mau uso ou intervenção de terceiros.',
        'Para acionar a garantia, apresente este comprovante.',
    ],
];
