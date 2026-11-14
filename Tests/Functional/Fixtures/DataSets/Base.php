<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Domain\Repository\PageRepository;

return [
    'pages' => [
        [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Cart',
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'slug' => '/',
            'is_siteroot' => 1,
        ],
        [
            'uid' => 2,
            'pid' => 1,
            'title' => 'Orders',
            'doktype' => PageRepository::DOKTYPE_SYSFOLDER,
            'slug' => '/orders',
        ],
    ],
    'sys_template' => [
        [
            'uid' => 1,
            'pid' => 1,
            'title' => 'Root',
            'root' => 1,
            'clear' => 3,
            'include_static_file' => implode(',', [
                'EXT:cart/Configuration/TypoScript',
                'EXT:cart_girosolution/Configuration/TypoScript',
            ]),
            'constants' => implode(PHP_EOL, [
                'plugin.tx_cart.settings.cart.pid = 1',
                'plugin.tx_cart.settings.order.pid = 2',
            ]),
            'config' => implode(PHP_EOL, [
                'plugin.tx_cartgirosolution.credentials.creditCard {',
                '    merchantId = 1234',
                '    projectId = 5678',
                '    password = credit-card-secret',
                '}',
            ]),
        ],
    ],
];
