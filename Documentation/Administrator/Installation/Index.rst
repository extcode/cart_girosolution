.. include:: ../../Includes.rst.txt

.. _installation:

============
Installation
============

Installation using Composer
---------------------------

The recommended way to install the extension is by using `Composer <https://getcomposer.org/>`_.
In your Composer based TYPO3 project root, just do

`composer require extcode/cart-girosolution`.

Installation from TYPO3 Extension Repository (TER)
--------------------------------------------------

The extension can only be installed via composer. Installation via the TER is not possible.

Preparation: Include static TypoScript
--------------------------------------

The extension ships some TypoScript code which needs to be included.

#. Switch to the root page of your site.

#. Switch to the **Template module** and select *Info/Modify*.

#. Press the link **Edit the whole template record** and switch to the tab *Includes*.

#. Select **Shopping Cart - Girosolution** at the field *Include static (from extensions):*
