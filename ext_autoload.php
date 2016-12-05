<?php

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('cart_girosolution');

return [
    'GiroCheckout_SDK_Request' => $extensionPath . 'Resources/Private/Library/GiroCheckout_SDK/GiroCheckout_SDK_Request.php',
    'GiroCheckout_SDK_Notify' => $extensionPath . 'Resources/Private/Library/GiroCheckout_SDK/GiroCheckout_SDK_Notify.php',
];
