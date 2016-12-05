<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
);

$dispatcher->connect(
    'Extcode\Cart\Utility\OrderUtility',
    'handlePaymentAfterOrder',
    'Extcode\CartGirosolution\Service\Payment',
    'handlePayment'
);

if (TYPO3_MODE == 'FE') {
    $TYPO3_CONF_VARS['FE']['eID_include']['notifyGiroSolution'] = 'EXT:cart_girosolution/Classes/Utility/eIDDispatcher.php';
    $TYPO3_CONF_VARS['FE']['eID_include']['redirectGiroSolution'] = 'EXT:cart_girosolution/Classes/Utility/eIDDispatcher.php';
}
