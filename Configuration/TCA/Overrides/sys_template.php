<?php
defined('TYPO3_MODE') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

call_user_func(function () {
    ExtensionManagementUtility::addStaticFile(
        'cart_girosolution',
        'Configuration/TypoScript',
        'Shopping Cart - Girosolution'
    );
});
