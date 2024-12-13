.. include:: ../../Includes.rst.txt

========================================================
Breaking: #14 - Credential Configuration from TypoScript
========================================================

Description
===========

With the introduction of the possibility to save the credentials in `$GLOBALS['TYPO3_CONF_VARS']`,
the credentials are no longer loaded directly from `plugin.tx_cartgirosolution.<method>`,
but from `plugin.tx_cartgirosolution.credentials.<method>`.

Affected Installations
======================

This affects all projects.

Migration
=========

Since TypoScript is not a good option to save the credentials, it is recommended to switch to the configuration in $GLOBALS['TYPO3_CONF_VARS'] and then load the passwords from ENV variables if necessary.

Otherwise, the TypoScript configuration can simply be adapted and the credentials grouped in `credentials`.

.. index:: Frontend, API