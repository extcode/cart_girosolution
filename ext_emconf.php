<?php

$EM_CONF['cart_girosolution'] = [
    'title' => 'Cart - Girosolution',
    'description' => 'Shopping Cart(s) for TYPO3 - Girosolution Payment Provider',
    'category' => 'services',
    'author' => 'Daniel Gohlke',
    'author_email' => 'ext@extco.de',
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
    'version' => '5.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
            'cart' => '9.0.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
