.. include:: ../../../Includes.rst.txt

==================
Main Configuration
==================

Each activated payment method in the GiroCockpit has its own credentials.
These must be adopted accordingly for the payment methods that are to be offered in the shop.
The different possibilities for configuring the credentials are described in the :ref:`Credential <credentials>` section.

.. code-block:: typoscript

   plugin.tx_cartgirosolution {
       redirectTypeNum = 2278106
   }

|

.. container:: table-row

   Property
      plugin.tx_cartgirosolution.redirectTypeNum
   Data type
      int
   Description
      The redirectTypeNum is used to select the correct plugin when a user is redirected from the payment page back to the store.
   Default
      2278106
