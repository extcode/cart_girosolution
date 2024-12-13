.. include:: ../../../Includes.rst.txt

.. _credentials:

===========
Credentials
===========

Each activated payment method in the GiroCockpit has its own configuration.
These must be adopted accordingly for the payment methods that are to be offered in the shop.

The TypoScript configuration loader is included for compatibility reasons, but may no longer be available in a later version
(TYPO3 v14). In this case, this loader must be implemented yourself. Migration to a different configuration loader
should not be a major hurdle.

If the credentials are to be saved in a different way, :ref:`Credentials from own CredentialLoader <credentials>` section
will describe how to provide the credentials implementing the `CredentialLoaderInterface`.

-----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Credentials from TYPO3_CONF_VARS <credentials-from-confvars>`

        Credentials can be read from $GLOBALS['TYPO3_CONF_VARS'].

    ..  card:: :ref:`Credentials from TypoScript <credentials-from-typoscript>`

        Credentials can be read from TypoScript plugin configuration.

.. toctree::
   :maxdepth: 2
   :titlesonly:

   CredentialsFromConfVars
   CredentialsFromTypoScript