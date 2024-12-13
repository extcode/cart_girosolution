<?php

defined('TYPO3') or die();

use Extcode\CartGirosolution\Controller\Order\PaymentController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// configure plugins

ExtensionUtility::configurePlugin(
    'CartGirosolution',
    'Cart',
    [
        PaymentController::class => 'redirect, notify',
    ],
    [
        PaymentController::class => 'redirect, notify',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

// exclude parameters from cHash

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcReference';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcMerchantTxId';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcBackendTxId';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcAmount';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcCurrency';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcResultPayment';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gcHash';
