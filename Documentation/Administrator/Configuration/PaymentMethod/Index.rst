.. include:: ../../../Includes.rst.txt

============================
Payment Method Configuration
============================

The payment method for Girosolution is configured like any other payment method. There are all configuration options
from Cart available.

.. code-block:: typoscript

   plugin.tx_cart {
       payments {
           ...
               options {
                   2 {
                       provider = GIROSOLUTION_CREDITCARD
                       processOrderCreateEvent = Extcode\CartGirosolution\Event\ProcessOrderCreateEvent
                       title = Girosolution - Credit Card
                       extra = 0.00
                       taxClassId = 1
                       status = open
                       available.from = 0.01
                   }
                   3 {
                       provider = GIROSOLUTION_GIROPAY
                       processOrderCreateEvent = Extcode\CartGirosolution\Event\ProcessOrderCreateEvent
                       title = Girosolution - giropay
                       extra = 0.00
                       taxClassId = 1
                       status = open
                       available.from = 0.01
                   }
                   4 {
                       provider = GIROSOLUTION_PAYDIREKT
                       processOrderCreateEvent = Extcode\CartGirosolution\Event\ProcessOrderCreateEvent
                       title = Girosolution - paydirekt
                       extra = 0.00
                       taxClassId = 1
                       status = open
                       available.from = 0.01
                   }
                   5 {
                       provider = GIROSOLUTION_PAYPAL
                       processOrderCreateEvent = Extcode\CartGirosolution\Event\ProcessOrderCreateEvent
                       title = Girosolution - paypal
                       extra = 0.00
                       taxClassId = 1
                       status = open
                       available.from = 0.01
                   }
               }
           ...
       }
   }

|

.. container:: table-row

   Property
      plugin.tx_cart.payments....options.n.provider
   Data type
      string
   Description
      Defines that the payment provider for Girosolution should be used.
      This information is mandatory and ensures that the extension Cart Girosolution takes control and for the authorization of payment the user forwards to the Girosolution site.

      Possible providers are:

      * GIROSOLUTION_CREDITCARD: Credit card
      * GIROSOLUTION_GIROPAY: giropay
      * GIROSOLUTION_PAYDIREKT: paydirekt
      * GIROSOLUTION_PAYPAL: PayPal

.. container:: table-row

   Property
      plugin.tx_cart.payments....options.n.processOrderCreateEvent
   Data type
      string
   Description
      Defines that the event class name for payment provider which will triggered in `Order::createAction()`.
      This information is mandatory.

.. IMPORTANT::

   **giropay** can **only** be used with the currency **EURO**.

.. IMPORTANT::

   **paydirekt** requires some data for the shipping address.

   * First name
   * Last name
   * ZIP
   * City
   * Country
   * Email

   The billing address data will be used for this purpose as long as no shipping address has been specified. For the optional shipping address, the same fields are mandatory except for the e-mail. The e-mail address is alternatively taken from the billing address.

   Since it is currently technically not possible to specify further conditions and validations for this payment method, it must be ensured during integration that these fields are generally mandatory if paydirekt is to be offered.

   The method also offers an age validation. This is currently not implemented.

.. NOTE::

   For more information and examples on how to configure payment methods, please refer to the
   `Payment Method section <https://docs.typo3.org/typo3cms/extensions/cart/6.5.0/AdministratorManual/Configuration/PaymentMethods/Index.html>`_
   in the cart documentation.
