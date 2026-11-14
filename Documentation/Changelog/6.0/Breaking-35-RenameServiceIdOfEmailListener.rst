.. include:: ../../Includes.rst.txt

=======================================================
Breaking: #35 - Rename Service Id of the Email Listener
=======================================================

See `Pull Request 35 <https://github.com/extcode/cart_girosolution/pull/35>`__

Description
===========

After a payment with Girosolution the extension dispatches its own
``Extcode\CartGirosolution\Event\Order\FinishEvent``. The email listener of
EXT:cart is registered for this event, so the mails to the buyer and the
seller are sent.

The service of this listener had the id
``Extcode\CartGirosolution\EventListener\Order\Finish\Email``, which looked
like a class of this extension, but no such class exists. The service id was
renamed to ``cart-girosolution--event-listener--order--finish--email``.

The identifier of the event listener
``cart-girosolution--order--finish--email`` is unchanged.

Affected Installations
======================

Installations which reference the service by its id, e.g. to replace or to
remove the listener in their own service configuration.

Migration
=========

Use the new service id
``cart-girosolution--event-listener--order--finish--email``. Listeners which
use the identifier of the event listener in ``before`` or ``after`` do not
need to be changed.

.. index:: Frontend
