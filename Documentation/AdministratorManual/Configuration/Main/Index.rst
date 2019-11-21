.. include:: ../../../Includes.txt

Main Configuration
==================

Each activated payment method in the GiroCockpit has its own configuration.
These must be adopted accordingly for the payment methods that are to be offered in the shop.

.. NOTE::
   There is no setting for the test environment. This is done via the projects created in `GiroCockpit <https://www.girocockpit.de/>`_.

.. code-block:: typoscript

   plugin.tx_cartgirosolution {
       creditCard {
           password =
           merchantId =
           projectId =
       }

       giropay {
           password =
           merchantId =
           projectId =
       }

       paydirekt {
           password =
           merchantId =
           projectId =
       }

       paypal {
           password =
           merchantId =
           projectId =
       }
   }

|

.. container:: table-row

   Property
      plugin.tx_cartgirosolution.method
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
         plugin.tx_cartgirosolution.method.password
   Data type
         string
   Description
         The `Project Passphrase` for this payment method. You can find it in the credentials for shop integration section.

.. container:: table-row

   Property
         plugin.tx_cartgirosolution.method.merchantId
   Data type
         string
   Description
         The `Merchant ID` for this payment method. You can find it in the credentials for shop integration section.

.. container:: table-row

   Property
         plugin.tx_cartgirosolution.method.projectId
   Data type
         string
   Description
         The `Project ID` for this payment method. You can find it in the credentials for shop integration section.
