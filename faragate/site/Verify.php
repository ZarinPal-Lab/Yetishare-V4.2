<?php
ini_set("soap.wsdl_cache_enabled", 0);	
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');
require_once('../../../core/includes/master.inc.php');
$pluginConfig   = pluginHelper::pluginSpecificConfiguration('faragate');
$pluginSettings = $pluginConfig['data']['plugin_settings'];
$faragate_merchant    = '';
$faragate_sandbox    = 'no';
$faragate_currency    = 'irt';
if (strlen($pluginSettings)){
	$pluginSettingsArr = json_decode($pluginSettings, true);
	$faragate_merchant       = $pluginSettingsArr['faragate_merchant'];
	$faragate_sandbox        = $pluginSettingsArr['faragate_sandbox'];
	$faragate_currency       = $pluginSettingsArr['faragate_currency'];
}
$paymentTracker = !empty($_REQUEST['custom']) ? urldecode($_REQUEST['custom']) : $_POST['order_hash'];
$order          = OrderPeer::loadByPaymentTracker($paymentTracker);
if ($order)
{	
	//start of faragate
	$Amount = intval($order->amount); 
	if ($faragate_currency == 'irr')
		$Amount    = $Amount/10;
	
	if ( !class_exists( 'nusoap_client' ) ) 
		include('nusoap.php');
	
		//Just input MerchantCode for verify
	$MerchantCode = $faragate_merchant;
	
	$InvoiceNumber = isset($_POST['InvoiceNumber']) ? $_POST['InvoiceNumber'] : '';
	$Transaction_ID = $Token = isset($_POST['Token']) ? $_POST['Token'] : '';
	
	if( isset($_GET['Status']) && $_GET['Status'] == 'OK' ) {
		try {
			
			$endpoint = ( $edd_options['zp_deserver'] == '1' ) ? 'https://de.zarinpal.com/pg/services/WebGate/wsdl' : 'https://ir.zarinpal.com/pg/services/WebGate/wsdl';
            $client = new SoapClient($endpoint, array('encoding' => 'UTF-8'));
            $result = $client->PaymentVerification(array(
                'MerchantID' => $MerchantCode,
                'Authority' => $_GET['Authority'],
                'Amount' => $Amount
            ));
            if ($result->Status == 100) {
				$Status = 'completed';
				$Fault = '';
			}else {
				$Status  = 'failed';
				$Fault   = isset($Request['Status']) ? $Request['Status'] : '';
				$Message = isset($Request['Message']) ? $Request['Message'] : ''; //not already , maybe later
			}
		}
		catch(Exception $ex){
			$Status  = 'failed';
			$Message = $ex->getMessage();
		}
	}
	else {
		$Status = 'failed';
		$Fault = isset($_POST['Status']) ? $_POST['Status'] : '';
	}
	$status = $Status;
	$transaction_id = $Transaction_ID;
	$fault = $Fault;
	//end of faragate
	
	if ( $status == 'completed' )
	{
		$extendedDays  = $order->days;
		$upgradeUserId = $order->upgrade_user_id;
		$orderId       = $order->id;
		$userId        = $order->user_id;
		$user   = $db->getRow("SELECT * FROM users WHERE id = " . (int) $userId . " LIMIT 1");
		$to_email = SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM ? SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM : SITE_CONFIG_REPORT_ABUSE_EMAIL;
		$to_email = $to_email ? $to_email : null;
		
		// log in payment_log
		$response_vars = "Transaction_Id (Token) => ". $transaction_id . "\n";
		$response_vars .= "InvoiceNumber => ". $InvoiceNumber . "\n";
		
		$dbInsert = new DBObject("payment_log", 
			array("user_id", "date_created", "amount",
			"currency_code", "from_email", "to_email", "description",
			"request_log", "payment_method")
        );
		$dbInsert->user_id = $userId;
		$dbInsert->date_created = date("Y-m-d H:i:s", time());
		$dbInsert->amount = $order->amount;
		$dbInsert->currency_code = SITE_CONFIG_COST_CURRENCY_CODE;
		$dbInsert->from_email = $user['email'];
		$dbInsert->to_email = $to_email;
		$dbInsert->description = $extendedDays . ' days extension';
		$dbInsert->request_log = $response_vars;
        $dbInsert->payment_method = 'ZarinPal';
		$dbInsert->insert();
		
		// make sure the order is pending
		if ($order->order_status == 'completed')
			header('Location: '.urldecode(WEB_ROOT . '/payment_complete.' . SITE_CONFIG_PAGE_EXTENSION));
		
		// update order status to paid
		$dbUpdate = new DBObject("premium_order", array("order_status"), 'id');
		$dbUpdate->order_status = 'completed';
		$dbUpdate->id = $orderId;
		$effectedRows = $dbUpdate->update();
		if ($effectedRows === false)
			die('متاسفانه در حین پرداخت خطایی رخ داده است');
		
		// extend/upgrade user
        $upgrade = UserPeer::upgradeUser($userId, $order->days);
        if ($upgrade === false)
			die('متاسفانه در حین پرداخت خطایی رخ داده است');

		// append any plugin includes
		pluginHelper::includeAppends('payment_ipn_paypal.php');
		header('Location: '.urldecode(WEB_ROOT . '/payment_complete.' . SITE_CONFIG_PAGE_EXTENSION));
	}
	else { 
	
		switch ($Fault) {
					
			case '-1' :
				$Response =	'نوع درخواست معتبر نیست .';
				break;
	
			case '-2' :
				$Response =	'مرچنت کد پذیرنده معتبر نیست .';
				break;

			case '-3' :
				$Response =	'شماره فاکتور معتبر نیست .';
				break;
	
			case '-4' :
				$Response =	'مقدار مبلغ معتبر نیست .';
				break;
	
			case '-5' :
				$Response =	'پست الکترونیکی پرداخت کننده معتبر نیست .';
				break;

			case '-6' :
				$Response =	'شماره موبایل پرداخت کننده معتبر نیست .';
				break;
	
			case '-7' :
				$Response =	'فیلدهای  Query String معتبر نیستند .';
				break;

			case '-8' :
				$Response =	'فیلدهای Post معتبر نیستند .';
				break;

			case '-9' :
				$Response =	'اطلاعات واریز به شماره حساب ها معتبر نیست .';
				break;

			case '-10' :
				$Response =	'کد درگاه معتبر نیست یا درگاه پذیرنده فعال نیست .';
				break;
	
			case '-11' :
				$Response =	'آدرس بازگشت به درگاه پذیرنده معتبر نیست .';
				break;
	
			case '-12' :
				$Response =	'مرچنت کد وارد شده برای این سایت نمی باشد .';
				break;
	
			case '-13' :
				$Response =	'آی پی درخواست کننده معتبر نیست .';
				break;
	
			case '-14' :
				$Response =	'بانک عامل معتبر نیست .';
				break;
	
			case '-15' :
				$Response =	'متاسفانه مشکلی در شبکه بانکی وجود دارد .';
				break;
	
			case '-16' :
				$Response =	'مقدار کلید پرداخت اشتباه است .';
				break;
	
			case '-17' :
				$Response =	'پرداخت قبلا استعلام شده و توسط بانک تایید نشده است .';
				break;
	
			case '-18' :
				$Response =	'پرداخت انجام نشده است .';
				break;
	
			case '-19' :
				$Response =	'پرداخت برگشت خورده است .';
				break;
	
			case '-20' :
				$Response =	'پرداخت توسط بانک تایید نشد .';
				break;

			case '-21' :
				$Response =	'پرداخت توسط بانک واریز نشد .';
				break;
				
			case '-22' :
				$Response =	'پرداخت برگشت زده شد .';
				break;
	
			case '-23' :
				$Response =	'پاسخی از بانک دریافت نشد .';
				break;
	
			case '-24' :
				$Response =	'اطلاعات پرداخت کننده معتبر نیست  (برای حالتی که با حساب فراگیت پرداخت انجام میشود) .';
				break;

			case '-25' :
				$Response =	'خطا در پایگاه داده درگاه رخ داده است .';
				break;
				
			default :
				$Response =	'تراکنش انجام نشد .';
				break;
		}
					
		die('در حین پرداخت خطای رو به رو رخ داده است : '.$Response .'<br/>'. header( 'Refresh:10; url='.urldecode(WEB_ROOT . '/upgrade.' . SITE_CONFIG_PAGE_EXTENSION) , true, 303));
			
	}
	
			
}
else
	die('متاسفانه در حین پرداخت خطایی رخ داده است');