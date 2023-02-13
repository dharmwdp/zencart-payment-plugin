<?php 
define('OMNIPAY_CALLBACK', 1);
require('includes/application_top.php');
//Load in payment library
require_once('includes/modules/payment/omnipay.php');
if (!empty($_POST) && !empty($_POST['id'])) {
	$order_id = omnipay::encrypt_decrypt('decrypt', $_POST['id']);
	$results = $db->Execute("SELECT * FROM ".TABLE_ORDERS." WHERE orders_id = \"" .intval($order_id). "\"");
	//print_r($_POST);die;
	if (!empty($results) && $_POST['status'] == 1 && $_POST['payment_status'] == 'APPROVED') {
		//Finally try to update our order through the callback
		$db->Execute("UPDATE " . TABLE_ORDERS . " SET " .
            "omnipay_transaction_id = \"" . $_POST['txn_id'] . "\", " .
            "omnipay_authorisation_code = " . $_POST['status'] . ", " .
            "omnipay_response_message = \"" . $_POST['payment_status'] . "\", " .
            "orders_status = " . MODULE_PAYMENT_OMNIPAY_ORDER_STATUS_ID . " " .
            "WHERE orders_id = ".intval($order_id)
        );
		$redirect_url = zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL', true, false);
	} else {
		//Otherwise the order failed
		$db->Execute("UPDATE " . TABLE_ORDERS . " SET " .
            "omnipay_transaction_id = \"" . $_POST['txn_id'] . "\", " .
            "omnipay_authorisation_code = " . $_POST['status'] . ", " .
            "omnipay_response_message = \"" . $_POST['payment_status'] . "\", " .
            "orders_status = " . MODULE_PAYMENT_OMNIPAY_ORDER_PENDING_STATUS_ID . " " .
            "WHERE orders_id = ".intval($order_id)
        );
		$errorMsg = !empty($_POST['errorText'])?$_POST['errorText']:MODULE_PAYMENT_OMNIPAY_CALLBACK_INVALID_RESPONSE_LOG;
		error_log($errorMsg);
		$messageStack->add_session('checkout_payment', $errorMsg, 'error');
		$redirect_url = zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false);
	}
} else {
	//Otherwise the keys could not be validated
	$errorMsg = !empty($_POST['errorText'])?$_POST['errorText']:MODULE_PAYMENT_OMNIPAY_VERIFY_ERROR;
	error_log($errorMsg);
	$messageStack->add_session('checkout_payment', $errorMsg, 'error');
	$redirect_url = zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false);
} 
//FIREFOX FIX
$redirect_url = str_replace('&amp;', '&', $redirect_url);
zen_redirect($redirect_url);
