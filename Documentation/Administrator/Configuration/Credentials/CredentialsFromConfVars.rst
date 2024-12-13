.. include:: ../../../Includes.rst.txt

.. _credentials-from-confvars:

============================================
Credentials from $GLOBALS['TYPO3_CONF_VARS']
============================================

Each activated payment method in the GiroCockpit has its own configuration.
These must be adopted accordingly for the payment methods that are to be offered in the shop.

.. NOTE::
   There is no setting for the test environment. This is done via the projects created in `GiroCockpit <https://www.girocockpit.de/>`_.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['cart_girosolutions'] = [
       'credentials' => [
           'creditCard' => [
               'password' => ''
               'merchantId' => ''
               'projectId' => ''
           ],

           'giropay' => [
               'password' => ''
               'merchantId' => ''
               'projectId' => ''
           ],

           'paydirekt' => [
               'password' => ''
               'merchantId' => ''
               'projectId' => ''
           ],

           'paypal' => [
               'password' => ''
               'merchantId' => ''
               'projectId' => ''
           ],
       ],
   ];

|

.. container:: table-row

   Property
      $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['cart_girosolutions'][<method>]
   Data type
      array
   Description
      Each activated payment method in the GiroCockpit has its own configuration.
      These must be adopted accordingly for the payment methods that are to be offered in the shop.

      * creditCard
      * giropay
      * paydirekt
      * paypal

.. container:: table-row

   Property
         $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['cart_girosolutions'][<method>]['password']
   Data type
         string
   Description
         The `Project Passphrase` for this payment method. You can find it in the credentials for shop integration section.

.. container:: table-row

   Property
         $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['cart_girosolutions'][<method>]['merchantId']
   Data type
         string
   Description
         The `Merchant ID` for this payment method. You can find it in the credentials for shop integration section.

.. container:: table-row

   Property
         $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['cart_girosolutions'][<method>]['projectId']
   Data type
         string
   Description
         The `Project ID` for this payment method. You can find it in the credentials for shop integration section.
