<?php
defined('TYPO3_MODE') or die();

// configure plugins

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Extcode.cart_girosolution',
    'Cart',
    [
        'Order\Payment' => 'redirect, notify',
    ],
    [
        'Order\Payment' => 'redirect, notify',
    ]
);

// configure signal slots

$dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
);
$dispatcher->connect(
    \Extcode\Cart\Utility\PaymentUtility::class,
    'handlePayment',
    \Extcode\CartGirosolution\Utility\PaymentUtility::class,
    'handlePayment'
);

// exclude parameters from cHash

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcReference';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcMerchantTxId';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcBackendTxId';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcAmount';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcCurrency';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcResultPayment';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcHash';
