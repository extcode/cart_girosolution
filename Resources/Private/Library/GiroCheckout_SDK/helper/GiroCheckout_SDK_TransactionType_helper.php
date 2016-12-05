<?php
/**
 * Helper class which manages api call instances
 *
 * @package GiroCheckout
 * @version $Revision: 158 $ / $Date: 2016-07-14 16:48:59 -0400 (Thu, 14 Jul 2016) $
 */
class GiroCheckout_SDK_TransactionType_helper {

  /*
   * returns api call instance
   *
   * @param String api call name
   * @return interfaceAPI
   */
  public static function getTransactionTypeByName($transType) {
    switch($transType) {
      //credit card apis
      case 'creditCardTransaction':           return new GiroCheckout_SDK_CreditCardTransaction();
      case 'creditCardCapture':               return new GiroCheckout_SDK_CreditCardCapture();
      case 'creditCardRefund':                return new GiroCheckout_SDK_CreditCardRefund();
      case 'creditCardGetPKN':                return new GiroCheckout_SDK_CreditCardGetPKN();
      case 'creditCardRecurringTransaction':  return new GiroCheckout_SDK_CreditCardRecurringTransaction();

      //direct debit apis
      case 'directDebitTransaction':                  return new GiroCheckout_SDK_DirectDebitTransaction();
      case 'directDebitGetPKN':                       return new GiroCheckout_SDK_DirectDebitGetPKN();
      case 'directDebitTransactionWithPaymentPage':   return new GiroCheckout_SDK_DirectDebitTransactionWithPaymentPage();
      case 'directDebitCapture':                      return new GiroCheckout_SDK_DirectDebitCapture();
      case 'directDebitRefund':                       return new GiroCheckout_SDK_DirectDebitRefund();

      //giropay apis
      case 'giropayBankstatus':               return new GiroCheckout_SDK_GiropayBankstatus();
      case 'giropayIDCheck':                  return new GiroCheckout_SDK_GiropayIDCheck();
      case 'giropayTransaction':              return new GiroCheckout_SDK_GiropayTransaction();
      case 'giropayIssuerList':               return new GiroCheckout_SDK_GiropayIssuerList();

      //iDEAL apis
      case 'idealIssuerList': return new GiroCheckout_SDK_IdealIssuerList();
      case 'idealPayment':    return new GiroCheckout_SDK_IdealPayment();
      case 'idealRefund':     return new GiroCheckout_SDK_IdealPaymentRefund();

      //PayPal apis
      case 'paypalTransaction': return new GiroCheckout_SDK_PaypalTransaction();

      //eps apis
      case 'epsBankstatus':	return new GiroCheckout_SDK_EpsBankstatus();
      case 'epsTransaction':	return new GiroCheckout_SDK_EpsTransaction();
      case 'epsIssuerList':	return new GiroCheckout_SDK_EpsIssuerList();

      //tools apis
      case 'getTransactionTool': return new GiroCheckout_SDK_Tools_GetTransaction();

      //GiroCode apis
      case 'giroCodeCreatePayment': return new GiroCheckout_SDK_GiroCodeCreatePayment();
      case 'giroCodeCreateEpc': 	return new GiroCheckout_SDK_GiroCodeCreateEpc();
      case 'giroCodeGetEpc': 	return new GiroCheckout_SDK_GiroCodeGetEpc();

      //Paydirekt apis
      case 'paydirektTransaction': return new GiroCheckout_SDK_PaydirektTransaction();
      case 'paydirektCapture': return new GiroCheckout_SDK_PaydirektCapture();
      case 'paydirektRefund': return new GiroCheckout_SDK_PaydirektRefund();
        
      //Sofort apis
      case 'sofortuwTransaction': return new GiroCheckout_SDK_SofortUwTransaction();

      //BlueCode apis
      case 'blueCodeTransaction': return new GiroCheckout_SDK_BlueCodeTransaction();
    }

    return null;
  }
}