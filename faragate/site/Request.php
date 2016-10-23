<?php
ini_set("soap.wsdl_cache_enabled", 0);	
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');
require_once('../../../core/includes/master.inc.php');
$pluginConfig   = pluginHelper::pluginSpecificConfiguration('faragate');
$pluginSettings = $pluginConfig['data']['plugin_settings'];
$faragate_merchant    = '';
if (strlen($pluginSettings)){
    $pluginSettingsArr = json_decode($pluginSettings, true);
    $faragate_merchant       = $pluginSettingsArr['faragate_merchant'];
    $faragate_sandbox        = $pluginSettingsArr['faragate_sandbox'];
    $faragate_currency       = $pluginSettingsArr['faragate_currency'];
}
if (!isset($_REQUEST['days'])){
    coreFunctions::redirect(WEB_ROOT . '/index.html');
}
// require login
if (!isset($_REQUEST['i'])){
    $Auth->requireUser(WEB_ROOT.'/login.'.SITE_CONFIG_PAGE_EXTENSION);
    $userId    = $Auth->id;
    $username  = $Auth->username;
    $userEmail = $Auth->email;
}
else
{
    $user = UserPeer::loadUserByIdentifier($_REQUEST['i']);
    if (!$user)
    {
        die('همچین کاربری وجود ندارد');
    }

    $userId    = $user->id;
    $username  = $user->username;
    $userEmail = $user->email;
}
$days = (int) (trim($_REQUEST['days']));
$fileId = null;
if (isset($_REQUEST['f'])){
    $file = file::loadByShortUrl($_REQUEST['f']);
    if ($file){
        $fileId = $file->id;
    }
}
// create order entry
$orderHash = MD5(time() . $userId);
$amount    = intval(constant('SITE_CONFIG_COST_FOR_' . $days . '_DAYS_PREMIUM'));
$order     = OrderPeer::create($userId, $orderHash, $days, $amount, $fileId);
$return_url = urldecode(PLUGIN_WEB_ROOT . '/' . $pluginConfig['data']['folder_name'] . '/site/Verify.php').'?custom='.urlencode($orderHash);
if ($order)
{
	//start of faragate
	if ( !class_exists( 'nusoap_client' ) ) 
		include('nusoap.php');
	
	$Amount = $amount;
	$Description = 'خرید اکانت '.intval($days).' روزه کاربر '.$username; 
	$Email = isset($userEmail) ? $userEmail : ''; 
	
	if ($faragate_currency == 'irr')
		$Amount = ceil($Amount/10);

	$Parameters = array(
		'SandBox'			  => ($faragate_sandbox == 'yes'),
		'MerchantCode'  	  => $faragate_merchant,
		'PriceValue'   		  => $Amount,
		'ReturnUrl'    		  => $return_url,
		'InvoiceNumber'		  => $order->id,
		'CustomQuery'   	  => array(),
		'CustomPost'          => array('order_hash^' . urlencode($orderHash) ),
		'PaymenterName'       => $username,
		'PaymenterEmail' 	  => $Email,
		'PaymenterMobile' 	  => '',
		'PluginName' 		  => 'YetiShare',
		'PaymentNote'		  => $Description,
		'ExtraAccountNumbers' => null,
		'Bank'				  => '',
	);
	
	
	try {
		
		$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));
		
		$result = $client->PaymentRequest(
											array(
													'MerchantID' 	=> $faragate_merchant,
													'Amount' 		=> $Amount,
													'Description' 	=> 'Invoice ID: '. $order->id,
													'Email' 		=> $Email,
													'Mobile' 		=> '',
													'CallbackURL' 	=> $return_url
												)
										);
		
		if ( $result->Status == 100){
			
			include(SITE_TEMPLATES_PATH.'/upgrade.html');	
		
			$Payment_URL =  'https://www.zarinpal.com/pg/StartPay/' . $result->Authority.'/ZarinGate';

			if ( ! headers_sent() ) header('Location: ' . $Payment_URL ); exit;
			die('<script type="text/javascript">window.location="' .$Payment_URL. '";</script>'); exit;
			
		}
		else {
			$Fault  = isset($result->Status) ? $Request['Status'] : '';
			$Message = isset($result->Status) ? $Request['Message'] : ''; //not already , maybe later
		}
	}
	catch( Exception $ex){
		$Message = $ex->getMessage();
	}
	
	
	if ( !empty($Fault) && $Fault ) {
	
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
			
		die('در حین پرداخت خطای رو به رو رخ داده است : '.$Response);
	}
	//end of faragate
}
