<?php

/*
 * Always replace POST action because Zen Cart often thinks
 * the variable is for itself (and will want a session) but it's not!
 *
 * As found in /includes/init_includes/init_sanitize.php (Line 30).
 * It checks to see if $_GET['action'] or $_POST['action'] are
 * present and if the session is valid by checking securityToken and
 * redirecting to a defined constant FILENAME_TIME_OUT as time_out
 * a code word for time_out for a URL (e.g /index.php?main_page=time_out)
 */
//Unset the variable since Zen Cart uses this for itself.
require('includes/configure.php');
if(!empty($_GET)){
	$status = $getData['status'] = !empty($_GET['status'])?$_GET['status']:1;
	$txn_id = $getData['txn_id'] = !empty($_GET['txn_id'])?$_GET['txn_id']:'';
	$id = $getData['id'] = !empty($_GET['id'])?$_GET['id']:'09/02/23 08:05 - YeaB6l6B30';
	$payment_status=$getData['payment_status']=!empty($_GET['payment_status'])?$_GET['payment_status']:"";
	$currency = $getData['currency'] = !empty($_GET['currency'])?$_GET['currency']:'SAR';
	$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$action_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? HTTPS_SERVER . DIR_WS_HTTPS_CATALOG : HTTP_SERVER . DIR_WS_CATALOG) . 'omnipay_success.php';
	echo '<form id="silentPost" action="'.$action_url.'" method="post" target="_self">
		<input type="hidden" name="status" id="status" value="'.$status.'">
		<input type="hidden" name="txn_id" id="txn_id" value="'.$txn_id.'">
		<input type="hidden" name="id" id="id" value="'.$id.'">
		<input type="hidden" name="payment_status" id="payment_status" value="'.$payment_status.'">
		<input type="hidden" name="currency" id="currency" value="'.$currency.'">
		<noscript>
			<input type="submit" value="Continue">
		</noscript>
	</form>
	<script>
		window.setTimeout(function () {
		document.forms.silentPost.submit();
		}, 0);
	</script>';
	exit;
}
?>