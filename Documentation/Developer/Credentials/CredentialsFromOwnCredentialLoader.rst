.. include:: ../../Includes.rst.txt

.. _credentials-from-own-credentialloader:

=====================================
Credentials from own CredentialLoader
=====================================

If the credentials are to be saved in a different location, a separate configuration loader
can provide the configuration. It is sufficient if this implements the
`\Extcode\CartGirosolution\Configuration\CredentialLoaderInterface`.
The loader is automatically registered via the interface.
