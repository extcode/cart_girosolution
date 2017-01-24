<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Cart - Girosolution',
    'description' => 'Shopping Cart(s) for TYPO3 - Girosolution Payment Provider',
    'category' => 'services',
    'author' => 'Daniel Lorenz',
    'author_email' => 'ext.cart.girosolution@extco.de',
    'author_company' => 'extco.de UG (haftungsbeschränkt)',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '0.4.0',
    'constraints' => [
        'depends' => [
            'typo3' => '6.2.0-8.99.99',
            'php' => '5.4.0',
            'cart' => '2.3.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
