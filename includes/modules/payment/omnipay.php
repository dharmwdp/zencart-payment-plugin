<?php
//require 'omnipay/omnipay/Omnipay.php';
require(__DIR__ . '/omnipay/omnipay/Omnipay.php');
use Omnipay\Api\Api;
// File protection
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal Access');
}
$_SESSION['payment_attempt'] = 0;
require(__DIR__ . '/omnipay/OmnipayStageOrder.php');
class omnipay {

    public $code, $version, $title, $description, $enabled, $order_status,$api,$apiMode;
    private $test_username;
    private $test_password;
    private $test_secret;
    private $username;
    private $password;
    private $secret;
    private $form_action_url;
    private $payment_mode;
    private $card;
    private $secret_key;


    function __construct() {
        global $order, $db;
        $this->code = 'omnipay';
        $this->version = MODULE_PAYMENT_OMNIPAY_ADMIN_TITLE;
        $this->description = MODULE_PAYMENT_OMNIPAY_ADMIN_DESCRIPTION;
        $this->test_username = MODULE_PAYMENT_OMNIPAY_TEST_MERCHANT_ID ?: 'psp_test.paasy3u5.cGFhc3kzdTU2NGViZA==';
        $this->test_password = MODULE_PAYMENT_OMNIPAY_TEST_PASSWORD ?: 'OVNHR3dHaDd5ZnpGN0ExcnByUmdPQVprNzliZUhMbmR3bVJCSUp3alFyUT0=';
        $this->test_secret = MODULE_PAYMENT_OMNIPAY_MERCHANT_TEST_SECRET ?: '89eb5f3beb06a663a81c0c5a392fdb97';
        $this->username = MODULE_PAYMENT_OMNIPAY_MERCHANT_ID ?: '';
        $this->password = MODULE_PAYMENT_OMNIPAY_PASSWORD ?: '';
        $this->secret = MODULE_PAYMENT_OMNIPAY_MERCHANT_SECRET ?: '';

        // Set payment form action
        $this->payment_mode = !empty(MODULE_PAYMENT_OMNIPAY_PAYMENT_MODE)?MODULE_PAYMENT_OMNIPAY_PAYMENT_MODE:'Test';
        $this->apiMode = 0;
        $this->secret_key = $this->test_secret;
        if(strtolower($this->payment_mode) == 'test'){
            $this->api = new Api($this->test_username, $this->test_password, $this->apiMode); 
        }else{
            $this->apiMode = 0;
            $this->secret_key = $this->secret;
            $this->api = new Api($this->username, $this->password, $this->apiMode);
        } 
        $this->form_action_url = $this->form_url();
        // Perform checks and disable module if required config is missing
        $this->enabled = $this->valid_setup();
        // Set display title for admin or customer
        $this->title = $this->module_title();
        if (IS_ADMIN_FLAG === true && !$this->enabled){
            $warning = MODULE_PAYMENT_OMNIPAY_ADMIN_WARNING;
            $this->title .= "<span class=\"alert\" title=\"$warning\">" . substr($warning, 0, 32) . "...</span>";
        }

        // Set display order
        $this->sort_order = MODULE_PAYMENT_OMNIPAY_SORT_ORDER;

        if ((int)MODULE_PAYMENT_OMNIPAY_ORDER_PENDING_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_OMNIPAY_ORDER_PENDING_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }
    }

    /**
     * Is the server running securely?
     * Either check that we are running SSL with the setting defined
     * as ENABLE_SSL or eitherway if it's currently running at all
     */
    function is_https() {
        return (defined('ENABLE_SSL') && strtolower(ENABLE_SSL) == 'true') || ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')|| (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') || (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'));
    }

    function update_status() {
        global $order, $db;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_OMNIPAY_ZONE > 0)) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_OMNIPAY_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function javascript_validation() {
        return false;
    }

    function selection() {
      return $this->draw_direct_form();
        /*if (MODULE_PAYMENT_OMNIPAY_CAPTURE_TYPE == 'Direct' || MODULE_PAYMENT_OMNIPAY_CAPTURE_TYPE == 'Direct V2') {
            return $this->draw_direct_form();
        } else {
            return array('id' => $this->code, 'module' => $this->title);
        }*/
    }

    /**
     * Check card details before sending them
     */
    function pre_confirmation_check() {
      return $this->card_data_check();
        /*if (MODULE_PAYMENT_OMNIPAY_CAPTURE_TYPE == 'Direct' || MODULE_PAYMENT_OMNIPAY_CAPTURE_TYPE == 'Direct V2') {
            return $this->card_data_check();
        }
        return false;*/
    }

    /**
     * Check anything before confirming to the next page
     */
    function confirmation() {
        return false;
    }

    /**
     * Create a request array for direct implementation
     * Used to send before a curl-request to the server
     */
    public function create_request($zf_order_id) {
        global $order, $db;        
        $card_holder=!empty($_SESSION['card']['card_holder'])?trim($_SESSION['card']['card_holder']):'';
        $card_number=!empty($_SESSION['card']['card_number'])?trim($_SESSION['card']['card_number']):'';
        $expiry_month=!empty($_SESSION['card']['expiry_month'])?trim($_SESSION['card']['expiry_month']):'';
        $expiry_year=!empty($_SESSION['card']['expiry_year'])?trim($_SESSION['card']['expiry_year']):'';
        $cvv = !empty($_SESSION['card']['cvv'])?trim($_SESSION['card']['cvv']):'';
        ////
        //$order_id = strftime("%d/%m/%y %H:%M") . " - " . self::generate_random_string();
        $order_id = $this->encrypt_decrypt('encrypt', $zf_order_id);
        $session = $_SESSION;
        unset($session['navigation']);
        unset($session['card']);
        //$session = addslashes(json_encode($session));
        // Delete any old sessions when creating any new one
        //$db->Execute("DELETE FROM omnipay_temp_carts WHERE omnipay_cdate <= NOW() - INTERVAL 2 HOUR");
        // Upload session that contains their cart to table called `omnipay_temp_carts`
        //$db->Execute("INSERT INTO omnipay_temp_carts (`omnipay_orderRef`, `omnipay_session`, `omnipay_orderID`) VALUES (\"$order_id\", \"$session\", NULL)");
        //
        $return_url = ($this->is_https() ? HTTPS_SERVER . DIR_WS_HTTPS_CATALOG : HTTP_SERVER . DIR_WS_CATALOG) . 'omnipay_callback.php';
        $requestArr = array(
            'customer' =>array(
                'name'=>$card_holder, 
                'email'=>$order->customer['email_address']
            ) ,
            'order'=>array(
                'id' =>$order_id,
                'amount'=>$order->info['total'], 
                'currency' => MODULE_PAYMENT_OMNIPAY_CURRENCY
            ),
            'sourceOfFunds' => array(
                'provided'=>array(
                    'card'=>array(
                        'number'=>$card_number,
                        'expiry'=>array(
                            'month'=>$expiry_month,
                            'year'=>$expiry_year
                        ), 
                        'cvv'=>$cvv
                    )
                ), 
                'cardType' => 'C'
            ), 
            'remark'=>array(
                'description'=>'This payment is done by card'
            ),
            'responseUrl'=>array(
				'successUrl'=>$return_url,
				'errorUrl'=>$return_url        
            )
        );
        //print_r($requestArr);die;
        return $requestArr;
    }

    

    /**
     * Before the order is created we need to make sure we
     * have either have paid from hosted integration from a
     * callback or from a redirect (if callback ever fails). Direct
     * implementation just processes the payment with a curl request.
     * Both implementations must process responses (from $_POST or curl)
     * before creating an order. Preventing an order being created, one
     * must add an error to messageStack and redirect back to the payment
     * page.
     *
     * We must also prevent any additional duplicate orders from being created
     * by using a spoofed class that will simply return the ID of the already
     * created order (e.g. from a callback).
     */
    function before_process() {
        //return false;             
    }    

    /*
     * A function called after the order is created (placed)
     * Here we want to stop any spoofs that we may have set
     * up earlier and to upload the responses to the database
     * NOTE: Only successful responses will be saved in an order
     * otherwise an order would never have been created if payment
     * has failed
     */
    function after_order_create($zf_order_id) {
        global $db, $messageStack;
        //zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false)); 
        $req = $this->create_request($zf_order_id);
        $encripted_result = $this->api->encryptDecrypt->create($req, $this->secret_key, 'encrypt');
        $param['trandata'] = $encripted_result['content']['apiResponse'];
        if($encripted_result['code'] == 200){
            $result = $this->api->payment->createPayment($param);
        }
        $_SESSION['cart']->reset(true);
        if(!empty($result) && !empty($result['status']) && $result['apiResponse']['verifyUrl']){
            $script = "<script>window.location = '".$result['apiResponse']['verifyUrl']."';</script>";
            echo $script;
            exit;
        }else{
            $errorMsg = !empty($result['errorText'])?$$result['errorText']:'Error During Payment';
            $messageStack->add_session('checkout_payment', $errorMsg, 'error');
			zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }        
    }

    /*
     * Returns what the module is called
     */
    function module_title() {
        // Set the title and description based on the mode the module is in: Admin or Catalog
        if ((defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true) || (!isset($_GET['main_page']) || $_GET['main_page'] == '')) {
            // In Admin mode
            return MODULE_PAYMENT_OMNIPAY_ADMIN_TITLE;
        } else {
            // In Catalog mode
            return MODULE_PAYMENT_OMNIPAY_CATALOG_TEXT_TITLE;
        }
    }

    /**
     * performs checks to make sure we have everything needed for payments
     *
     * @return bool
     */
    function valid_setup() {
        $isEnabled = MODULE_PAYMENT_OMNIPAY_STATUS == 'True';
        // Make sure that the OMNIPAY module is enable and that we're running HTTPS on a direct capture type
        return ($isEnabled && ((!empty(MODULE_PAYMENT_OMNIPAY_TEST_MERCHANT_ID) && !empty(MODULE_PAYMENT_OMNIPAY_TEST_PASSWORD) && !empty(MODULE_PAYMENT_OMNIPAY_MERCHANT_TEST_SECRET)) || (!empty(MODULE_PAYMENT_OMNIPAY_MERCHANT_ID) && !empty(MODULE_PAYMENT_OMNIPAY_PASSWORD) && !empty(MODULE_PAYMENT_OMNIPAY_MERCHANT_SECRET)))/* && $this->is_https()*/)?true:false;
    }

    
    /*
     * Returns different URL's for each integration for
     * the continue to payment button
     */
    function form_url() {
      $requestUrl = MODULE_PAYMENT_OMNIPAY_URL;
      if(strtolower($this->payment_mode) == 'test'){
        $requestUrl = MODULE_PAYMENT_OMNIPAY_URL.'test/payments';
      }else{
        $requestUrl = MODULE_PAYMENT_OMNIPAY_URL.'/payments';
      }
      return $requestUrl;
        /*switch (MODULE_PAYMENT_OMNIPAY_CAPTURE_TYPE) {
            case 'Hosted':
                return MODULE_PAYMENT_OMNIPAY_FORM_URL;
            case 'Modal':
                return MODULE_PAYMENT_OMNIPAY_MODAL_URL;
            case 'Direct':
            case 'Direct V2':
                return zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', true);
        }*/
    }

    /*
     * Draws input fields for card details when using direct integration
     */
    function draw_direct_form() {
        global $order;

        $ccnum = null;
        for ($i = 1; $i < 13; $i++) {
            $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B - (%m)', mktime(0, 0, 0, $i, 1, 2000)));
        }

        $today = getdate();
        for ($i = $today['year']; $i < $today['year'] + 15; $i++) {
            $expires_year[] = array('id' => strftime('%y', mktime(0, 0, 0, 1, 1, $i)), 'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)));
        }

        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

        $selection = array(
            'id'     => 'omnipay',
            'module' => $this->module_title(),
            'fields' => array(
                array(
                    'title' => MODULE_PAYMENT_OMNIPAY_CARD_HOLDER,
                    'field' => zen_draw_input_field('omnipay_card_holder', $order->billing['firstname'] . ' ' . $order->billing['lastname'], 'id="omnipay-cc-owner"' . $onFocus . ' autocomplete="off"'),
                    'tag'   => 'omnipay-cc-owner'
                ),
                array(
                    'title' => MODULE_PAYMENT_OMNIPAY_CARD_NUMBER,
                    'field' => zen_draw_input_field('omnipay_card_number', $ccnum, 'id="omnipay-cc-number"' . $onFocus . ' autocomplete="off"'),
                    'tag'   => $this->code . '-cc-number'
                ),
                array(
                    'title' => MODULE_PAYMENT_OMNIPAY_CARD_EXPIRE,
                    'field' => zen_draw_pull_down_menu('omnipay_card_expires_month', $expires_month, strftime('%m'), 'id="omnipay-cc-expires-month"' . $onFocus) . '&nbsp;' . zen_draw_pull_down_menu('omnipay_card_expires_year', $expires_year, '', 'id="omnipay-cc-expires-year"' . $onFocus),
                    'tag'   => 'omnipay-card-expires-month'
                ),
                array(
                    'title' => MODULE_PAYMENT_OMNIPAY_CARD_CVV,
                    'field' => zen_draw_input_field('omnipay_card_cvv', '', 'size="4" maxlength="4"' . ' id="omnipay-cc-cvv"' . $onFocus . ' autocomplete="off"') . ' ' . '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_POPUP_CVV_HELP) . '\')">' . MODULE_PAYMENT_OMNIPAY_CARD_CVV_HELP . '</a>',
                    'tag'   => 'omnipay-card-cvv'
                )
            )
        );

        return $selection;
    }
   
    /*
     * Draws hidden input fields for the checkout confirmation page
     * when using hosted integration
     */
    function draw_hosted_form_button() {
        $fields = $this->create_hosted_request();
        ksort($fields);
        $fields['signature'] = $this->create_signature($fields, $this->secret);
        $button_string = "";
        foreach (array_keys($fields) as $field) {
            $button_string .= zen_draw_hidden_field($field, $fields[$field]) . "\n";
        }
        return $button_string;
    }
    /*
     * Perform a basic check on card details provided to solve most
     * user errors rather than sending any invalid data
     */
    function card_data_check() {
        session_start();
        global $db, $messageStack;

        include(DIR_WS_CLASSES . 'cc_validation.php');

        $cc_validation = new cc_validation();
        $result        = $cc_validation->validate($_POST['omnipay_card_number'], $_POST['omnipay_card_expires_month'], $_POST['omnipay_card_expires_year']);
        $error         = '';
        switch ($result) {
            case -1:
                $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
                break;
            case -2:
            case -3:
            case -4:
                $error = TEXT_CCVAL_ERROR_INVALID_DATE;
                break;
            case false:
                $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
                break;
        }

        if (($result == false) || ($result < 1)) {
            $payment_error_return = 'payment_error=omnipay';
            $error_info2          = '&error=' . urlencode($error) . '&omnipay_card_holder=' . urlencode($_POST['omnipay_card_holder']) . '&omnipay_card_expires_month=' . $_POST['omnipay_card_expires_month'] . '&omnipay_card_expires_year=' . $_POST['omnipay_card_expires_year'];
            $messageStack->add_session('checkout_payment', $error . '<!-- [omnipay] -->', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }

        // if no error, continue with validated data:
        $this->card['card_type']    = $cc_validation->cc_type;
        $this->card['card_number']  = $cc_validation->cc_number;
        $this->card['expiry_month'] = $cc_validation->cc_expiry_month;
        $this->card['expiry_year']  = $cc_validation->cc_expiry_year;
        $_SESSION["card"] = ['card_type'=>$cc_validation->cc_type,'card_number'=>$cc_validation->cc_number, 'expiry_month'=>$cc_validation->cc_expiry_month, 'expiry_year'=>$cc_validation->cc_expiry_year,'card_holder'=>$_POST['omnipay_card_holder'],'cvv'=>$_POST['omnipay_card_cvv']];

    }
    /*
     * Make a curl request for direct integration
     * Returns array of response data
     */
    function make_request($url, $req) {
        $req['signature'] = $this->create_signature($req, $this->secret);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        parse_str(curl_exec($ch), $res);
        curl_close($ch);
        return $res;
    }
    /*
     * Create a signature from the array and key provided
     */
    function create_signature(array $data, $key) {
        if (!$key || !is_string($key) || $key === '' || !$data || !is_array($data)) {
            return null;
        }

        ksort($data);

        // Create the URL encoded signature string
        $ret = http_build_query($data, '', '&');

        // Normalise all line endings (CRNL|NLCR|NL|CR) to just NL (%0A)
        $ret = preg_replace('/%0D%0A|%0A%0D|%0A|%0D/i', '%0A', $ret);

        // Hash the signature string and the key together
        return hash('SHA512', $ret . $key);
    }
    /*
     * Generate a random string with an optional length specifed
     * (or otherwise 10 characters)
     */
    public static function generate_random_string($length = 10) {
        /**
         * Generate a random string of uppercase and lowercase letters
         * including numbers 0-9. Can be used to create seeds/ID"s etc
         */
        $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $randomString = "";
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
    /*
     * Returns a boolean value on whether an array has the keys
     * wanted from the $keys array
     */
    public static function has_keys($array, $keys) {
        foreach ($keys as $key){
            if(!array_key_exists($key, $array)) {
                return false;
            }
        }
        return true;
    }
    /*
     * Import a zen cart session
     */
    public static function import_session($session) {
        // Try to get the session back as best as possible
        unset($session['navigation']);
        unset($session['securityToken']);
        foreach(array_keys($session) as $key){
            $_SESSION[$key] = $session[$key];
        }
        $cart = $_SESSION['cart'];
        $_SESSION['cart'] = new shoppingCart();
        foreach(array_keys($cart) as $cartItem){
            $_SESSION['cart']->$cartItem = $cart[$cartItem];
        }
    }

    function admin_notification($zf_order_id)
    {
        global $db;

        $sql = "SELECT * FROM " . TABLE_ORDERS . " WHERE orders_id = $zf_order_id";

        $form_transaction_info = $db->Execute($sql);

        require(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/omnipay/omnipay_admin_notification.php');

        return $output;
    }
    /*
     * Do something after the payment and order process is complete
     */
    function after_process()
    {
        // Remove?
    }

    function _doRefund($oID, $amount = 'Full', $note = '')
    {

        global $db, $messageStack, $order;

        $transaction_info = $db->Execute("SELECT * FROM " . TABLE_ORDERS . " WHERE zen_order_id = '$oID'");

        if ($transaction_info->RecordCount() < 1) {
            $messageStack->add_session(MODULE_PAYMENT_OMNIPAY_TEXT_NO_MATCHING_ORDER_FOUND, 'error');
            $proceedToRefund = false;
        }

        // Check user ticked the confirm box
        if (isset($_POST['refconfirm']) && $_POST['refconfirm'] != 'on') {
            $messageStack->add_session(MODULE_PAYMENT_OMNIPAY_TEXT_REFUND_CONFIRM_ERROR, 'error');
            $proceedToRefund = false;
        }
        // Check user gave a valid refund amount
        if (isset($_POST['refamt']) && (float)$_POST['refamt'] == 0) {
            $messageStack->add_session(MODULE_PAYMENT_OMNIPAY_TEXT_INVALID_REFUND_AMOUNT, 'error');
            $proceedToRefund = false;
        }

        if(!$proceedToRefund){
            return false;
        }

        $req = array(
            "merchantID" => MODULE_PAYMENT_OMNIPAY_MERCHANT_ID,
            "action" => "REFUND",
            "type" => 1,
            "amount" => (float)$_POST['refamt'] * 100,
            'xref' => $transaction_info->fields['omnipay_xref'],
            'merchantData' => $this->version

        );

        $res = $this->make_request(MODULE_PAYMENT_OMNIPAY_DIRECT_URL,$req);

        if(isset($res['responseCode']) && $res['responseCode'] == 0){

            if($_POST['refamt'] == $order->info['total']){
                $new_order_status = MODULE_PAYMENT_OMNIPAY_REFUNDED_STATUS_ID;
            }elseif($order->info['total'] > $_POST['refamt']){
                $new_order_status = MODULE_PAYMENT_OMNIPAY_PART_REFUNDED_STATUS_ID;
            }else{
                $new_order_status = MODULE_PAYMENT_OMNIPAY_REFUNDED_STATUS_ID;
            }

            $sql_data_array= array(array('fieldName'=>'orders_id', 'value' => $oID, 'type'=>'integer'),
                array('fieldName'=>'orders_status_id', 'value' => MODULE_PAYMENT_OMNIPAY_REFUNDED_STATUS_ID, 'type'=>'integer'),
                array('fieldName'=>'date_added', 'value' => 'now()', 'type'=>'noquotestring'),
                array('fieldName'=>'comments', 'value' => MODULE_PAYMENT_OMNIPAY_REFUND_DEFAULT_MESSAGE." {$_POST['refamt']}", 'type'=>'string'),
                array('fieldName'=>'customer_notified', 'value' => 0, 'type'=>'integer'));
            $db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
            $db->Execute("update " . TABLE_ORDERS  . "
                  set orders_status = '" . (int)$new_order_status . "'
                  where orders_id = '" . (int)$oID . "'");

            $messageStack->add_session(sprintf(MODULE_PAYMENT_OMNIPAY_TEXT_REFUND_INITIATED, $res['transactionID'], $oID), 'success');

            return true;
        }else{
            $messageStack->add_session(MODULE_PAYMENT_OMNIPAY_TEXT_INVALID_REFUND_AMOUNT, 'error');
            return false;
        }
    }
    /*
     * Used to check an install of the configuration in the Database
     */
    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_OMNIPAY_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }
    /*
     * Setup process of the module
     * Making sure that all tables and settings are updated.
     */
    function install()
    {
        global $db;
        // General Config Options
        $background_colour = '#d0d0d0';
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Omnipay Module', 'MODULE_PAYMENT_OMNIPAY_STATUS', 'True', 'Do you want to accept Omnipay payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Payment Mode', 'MODULE_PAYMENT_OMNIPAY_PAYMENT_MODE', 'Test', 'Set payment mode test or Production', '6', '1', 'zen_cfg_select_option(array(\'Test\', \'Production\'), ', now())");
        //$db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Select Integration Method', 'MODULE_PAYMENT_OMNIPAY_CAPTURE_TYPE', 'Hosted', 'Do you want to use Direct (SSL Required), Hosted or Modal', '6', '2', 'zen_cfg_select_option(array(\'Hosted\', \'Modal\', \'Direct\', \'Direct V2\'), ', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Test ID', 'MODULE_PAYMENT_OMNIPAY_TEST_MERCHANT_ID', '', 'Merchant Test ID set in your mms', '6', '3', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Test Password', 'MODULE_PAYMENT_OMNIPAY_TEST_PASSWORD', '', 'Test password set in your mms', '6', '3', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Test Secret', 'MODULE_PAYMENT_OMNIPAY_MERCHANT_TEST_SECRET', '', 'Merchant test secret key as set in mms', '6', '4', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'MODULE_PAYMENT_OMNIPAY_MERCHANT_ID', '', 'Merchant ID set in your mms', '6', '3', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Password', 'MODULE_PAYMENT_OMNIPAY_PASSWORD', '', 'Password set in your mms', '6', '3', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Secret', 'MODULE_PAYMENT_OMNIPAY_MERCHANT_SECRET', '', 'Merchant secret key as set in mms', '6', '4', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Payment Name.', 'MODULE_PAYMENT_OMNIPAY_CATALOG_TEXT_TITLE', 'Omnipay', 'Name of payment method shown to customer', '6', '5', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Display Order.', 'MODULE_PAYMENT_OMNIPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '6', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Currency', 'MODULE_PAYMENT_OMNIPAY_CURRENCY', 'SAR', 'ISO currency', '6', '2', 'zen_cfg_select_option(array(\'SAR\'), ', now())");
        //$db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Currency ID.', 'MODULE_PAYMENT_OMNIPAY_CURRENCY_ID', '826', 'ISO currency number', '6', '7', now())");
        //$db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Country ID.', 'MODULE_PAYMENT_OMNIPAY_COUNTRY_ID', '826', 'ISO currency number', '6', '8', now())");
        //$db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Responsive Hosted Layout', 'MODULE_PAYMENT_OMNIPAY_RESPONSIVE_TYPE', 'True', 'Use responsive layout on a hosted form?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_OMNIPAY_ORDER_STATUS_ID', '2', 'Set the status of orders paid with this payment module to this value. <br /><strong>Recommended: Processing[2]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Unpaid Order Status', 'MODULE_PAYMENT_OMNIPAY_ORDER_PENDING_STATUS_ID', '1', 'Set the status of unpaid orders made with this payment module to this value. <br /><strong>Recommended: Pending[1]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Refund Order Status', 'MODULE_PAYMENT_OMNIPAY_REFUNDED_STATUS_ID', '1', 'Set the status of refunded orders to this value. <br /><strong>Recommended: Pending[1]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Partial Refund Order Status', 'MODULE_PAYMENT_OMNIPAY_PART_REFUNDED_STATUS_ID', '2', 'Set the status of partially refunded orders to this value. <br /><strong>Recommended: Processing[2]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $result = $db->Execute("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = '" . TABLE_ORDERS . "'
            AND TABLE_SCHEMA = '" . DB_DATABASE . "'
            AND COLUMN_NAME IN ('omnipay_transaction_id', 'omnipay_authorisation_code', 'omnipay_response_message')
        ");

        if (intval($result->fields['COUNT(*)']) < 1) {
            $db->Execute("ALTER TABLE " . TABLE_ORDERS . "
                ADD COLUMN `omnipay_transaction_id` VARCHAR(128) NULL,
                ADD COLUMN `omnipay_authorisation_code` VARCHAR(128) NULL,
                ADD COLUMN `omnipay_response_message` TEXT NULL
            ");
        }

        //$db->Execute("CREATE TABLE IF NOT EXISTS omnipay_temp_carts (omnipay_orderRef VARCHAR(64) NOT NULL, omnipay_session TEXT NOT NULL, omnipay_cdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, omnipay_orderID int NULL)");
        $background_colour = '#eee';
    }
    /*
     * Uninstallation process of the module
     */
    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
    /*
     * The settings that this module provides
     */
    function keys()
    {
        return array(
            'MODULE_PAYMENT_OMNIPAY_STATUS',
            'MODULE_PAYMENT_OMNIPAY_PAYMENT_MODE',
            'MODULE_PAYMENT_OMNIPAY_TEST_MERCHANT_ID',
            'MODULE_PAYMENT_OMNIPAY_TEST_PASSWORD',
            'MODULE_PAYMENT_OMNIPAY_MERCHANT_TEST_SECRET',
            'MODULE_PAYMENT_OMNIPAY_MERCHANT_ID',
            'MODULE_PAYMENT_OMNIPAY_PASSWORD',
            'MODULE_PAYMENT_OMNIPAY_MERCHANT_SECRET',
            'MODULE_PAYMENT_OMNIPAY_CATALOG_TEXT_TITLE',
            'MODULE_PAYMENT_OMNIPAY_SORT_ORDER',
            'MODULE_PAYMENT_OMNIPAY_CURRENCY',
            'MODULE_PAYMENT_OMNIPAY_ORDER_STATUS_ID',
            'MODULE_PAYMENT_OMNIPAY_ORDER_PENDING_STATUS_ID',
            'MODULE_PAYMENT_OMNIPAY_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_OMNIPAY_PART_REFUNDED_STATUS_ID'
        );
    }

    public static function encrypt_decrypt($action, $string)
	{
		$output = false;
		$encrypt_method = "AES-256-CBC";
		//This is my secret key
		$secret_key = '5b7cfd2937f2681f1d9139e5963312a39266ce52df93ded48f93d0f10b3c35ba29narpat';
		//This is my secret iv
		$secret_iv = '566ce52df93ded48f93d0f10b3c35bab7cfd2937f2681f1d9139e5963312a392bishnoi';
		// hash
		$key = hash('sha256', $secret_key);

		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
		$iv = substr(hash('sha256', $secret_iv), 0, 16);
		if ($action == 'encrypt') {
			$output = openssl_encrypt("$string", $encrypt_method, $key, 0, $iv);
			$output = base64_encode($output);
		} else if ($action == 'decrypt') {
			$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
		}
		return $output;
	}

}
