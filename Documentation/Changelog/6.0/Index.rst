.. include:: ../../Includes.rst.txt

6.0 Changes
===========

**Table of contents**

.. contents::
   :local:
   :depth: 1

6.0.1
-----

Bugfixes
~~~~~~~~

*  Reject notifications for unknown transactions (`#42 <https://github.com/extcode/cart_girosolution/pull/42>`__)
*  Send the order emails only once (`#46 <https://github.com/extcode/cart_girosolution/pull/46>`__)

Tasks
~~~~~

*  Check spelling with typos (`#39 <https://github.com/extcode/cart_girosolution/pull/39>`__)
*  List the changes of each release in the changelog

6.0.0
-----

Compatibility
~~~~~~~~~~~~~

*  TYPO3 ^13.4 (was ^12.4)
*  PHP ~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0 (was ^8.1)
*  extcode/cart ^11.0 (was ^9.2)

Breaking
~~~~~~~~

*  :doc:`#35 - Rename Service Id of the Email Listener <Breaking-35-RenameServiceIdOfEmailListener>`

Bugfixes
~~~~~~~~

*  Respond to payment notification without template (`#35 <https://github.com/extcode/cart_girosolution/pull/35>`__)
*  Ignore payment providers without clearing type (`#35 <https://github.com/extcode/cart_girosolution/pull/35>`__)

.. toctree::
   :hidden:
   :glob:

   *-*
