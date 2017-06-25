<?php
defined('_SECURE_') or die('Forbidden');

// hook_sendsms
// called by main sms sender
// return true for success delivery
// $smsc : smsc
// $sms_sender : sender mobile number
// $sms_footer : sender sms footer or sms sender ID
// $sms_to : destination sms number
// $sms_msg : sms message tobe delivered
// $gpid : group phonebook id (optional)
// $uid : sender User ID
// $smslog_id : sms ID
function africastalking_hook_sendsms($smsc, $sms_sender, $sms_footer, $sms_to, $sms_msg, $uid = '', $gpid = 0, $smslog_id = 0, $sms_type = 'text', $unicode = 0) {
	global $plugin_config;
	
	_log("enter smsc:" . $smsc . " smslog_id:" . $smslog_id . " uid:" . $uid . " to:" . $sms_to, 3, "africastalking_hook_sendsms");
	
	// override plugin gateway configuration by smsc configuration
	$plugin_config = gateway_apply_smsc_config($smsc, $plugin_config);
	
	$sms_sender = stripslashes($sms_sender);
	if ($plugin_config['africastalking']['module_sender']) {
		$sms_sender = $plugin_config['africastalking']['module_sender'];
	}
	
    $sms_footer = stripslashes($sms_footer);
	$sms_msg = stripslashes($sms_msg);
	$ok = false;
	
	_log("sendsms start", 3, "africastalking_hook_sendsms");
	
	if ($sms_footer) {
		$sms_msg = $sms_msg . $sms_footer;
	}

	if ($sms_sender && $sms_to && $sms_msg) {
				//create the url call
				$requestUrl = 'https://api.africastalking.com/version1/messaging';
				$params = array(
					'username' => $plugin_config['africastalking']['api_username'],
					'to'       => $sms_to,
					'message'  => $sms_msg,
				);
				$requestBody = http_build_query($params, '', '&');

				$curlHandle_ = curl_init($requestUrl);
				curl_setopt($curlHandle_, CURLOPT_POSTFIELDS, $requestBody);
				curl_setopt($curlHandle_, CURLOPT_POST, 1);
				curl_setopt($curlHandle_, CURLOPT_HTTPHEADER, array ('Accept: application/json', 'apikey:'.$plugin_config['africastalking']['api_password']));

				curl_setopt($curlHandle_, CURLOPT_TIMEOUT, 60);
				curl_setopt($curlHandle_, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($curlHandle_, CURLOPT_RETURNTRANSFER, true);

				$responseBody = curl_exec($curlHandle_);
					
				_log("response body from curl".$responseBody, 3, "africastalking_hook_sendsms");
					
				$responseInfo = curl_getinfo($curlHandle_);
					
				_log("responseInfo from curl".$responseInfo, 3, "africastalking_hook_sendsms");
				curl_close($curlHandle_);

				$resp = json_decode($responseBody); 
				if(count($resp->SMSMessageData->Recipients) > 0){
					$reslts =$resp->SMSMessageData->Recipients;
					foreach($reslts as $result) {
						if ($result->status == 'Sent') {
							_log("sent smslog_id:" . $smslog_id . " message_id:" .$result->messageId . " status:" . $result->status . " error:" . $c_error_text . " smsc:[" . $smsc . "]", 3, "africastalking_hook_sendsms");
							$db_query = "
								INSERT INTO " . _DB_PREF_ . "_gatewayAfricastalking (local_smslog_id,remote_smslog_id,status,error_text)
								VALUES ('$smslog_id','$result->messageId','$result->status','$c_error_text')";
							$id = @dba_insert_id($db_query);
							if ($id && ($c_status == 'Sent')) {
								$ok = true;
								$p_status = 0;
							} else {
								$p_status = 2;
							}
							dlr($smslog_id, $uid, $p_status);	
						} else {
							// even when the response is not what we expected we still print it out for debug purposes
							$resp = str_replace("\n", " ", $resp);
							$resp = str_replace("\r", " ", $resp);
							_log("failed smslog_id:" . $smslog_id . " resp:" . $resp . " smsc:[" . $smsc . "]", 3, "africastalking_hook_sendsms");			
						}  						
					}
				}
	      
    }

	if (!$ok) {
		$p_status = 2;
		dlr($smslog_id, $uid, $p_status);
	}
	
	_log("sendsms end", 3, "africastalking_hook_sendsms");
	
	return $ok;
}        

?>


 