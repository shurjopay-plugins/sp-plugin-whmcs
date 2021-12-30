<?php
// *************************************************************************
// *                                                                       *
// * WHMCS - The Complete Client Management, Billing & Support Solution    *
// * Copyright (c) WHMCS Ltd. All Rights Reserved,                         *
// * Version:8.3.2 (8.3.2-release.1)                                      *
// * BuildId:dac69f7.159                                                  *
// * Build Date:25 Nov 2021                                               *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: info@whmcs.com                                                 *
// * Website: http://www.whmcs.com                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.  This software  or any other *
// * copies thereof may not be provided or otherwise made available to any *
// * other person.  No title to and  ownership of the  software is  hereby *
// * transferred.                                                          *
// *                                                                       *
// * You may not reverse  engineer, decompile, defeat  license  encryption *
// * mechanisms, or  disassemble this software product or software product *
// * license.  WHMCompleteSolution may terminate this license if you don't *
// * comply with any of the terms and conditions set forth in our end user *
// * license agreement (EULA).  In such event,  licensee  agrees to return *
// * licensor  or destroy  all copies of software  upon termination of the *
// * license.                                                              *
// *                                                                       *
// * Please see the EULA file for the full End User License Agreement.     *
// *                                                                       *
// *************************************************************************

	if (!defined("WHMCS")) {
		die("This file cannot be accessed directly");
	}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @return array
 */
	function shurjopay_MetaData()
	{
		return array(
			'DisplayName' => 'ShurjoPay',
			'APIVersion' => '2.1', // Use API Version 1.1
			'DisableLocalCredtCardInput' => true,
			'TokenisedStorage' => false,
		);
	}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * @return array
 */
	function shurjopay_config()
	{
		return array(
			// the friendly display name for a payment gateway should be
			// defined here for backwards compatibility
			'FriendlyName' => array(
				'Type' => 'System',
				'Value' => 'ShurjoPay',
			),
			// a text field type allows for single line text input
			'merchantName' => array(
				'FriendlyName' => 'Merchant Name/ID',
				'Type' => 'text',
				'Size' => '25',
				'Default' => '',
				'Description' => 'Enter your merchant ID here',
			),
			// a password field type allows for masked text input
			'merchantPassword' => array(
				'FriendlyName' => 'Merchant Password',
				'Type' => 'password',
				'Size' => '25',
				'Default' => '',
				'Description' => 'Enter Merchant Password here',
			),
			'uniqeID' => array(
				'FriendlyName' => 'Merchant prefix Unique ID',
				'Type' => 'text',
				'Size' => '25',
				'Default' => '',
				'Description' => 'Enter your unique id prefix here',
			),

			'returnUrl' => array(
				'FriendlyName' => 'Merchant Return/Callback Url',
				'Type' => 'text',
				'Size' => '25',
				'Default' => '',
				'Description' => 'Enter your Return/Callback Url here',
			),
			'merchantIP' => array(
				'FriendlyName' => 'Merchant IP',
				'Type' => 'text',
				'Size' => '25',
				'Default' => '',
				'Description' => 'Enter your merchant ip here',
			),

			// the yesno field type displays a single checkbox option
			'testMode' => array(
				'FriendlyName' => 'Test Mode',
				'Type' => 'yesno',
				'Options' => array(
                '1' => 'Sandbox',
                '2' => 'Live',
            ),
				'Description' => 'Tick to enable test mode',
			),

		);
	}

/**
 * Payment link.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return json 
 */
	function shurjopay_link($params)
	{
		// Gateway Configuration Parameters
		$merchantName = $params['merchantName'];
		$merchantPassword = $params['merchantPassword'];
		$isSandbox = $params['testMode'];    
		$uniqeID = $params['uniqeID'];
		$returnUrl = $params['returnUrl'];
		$merchantIP = get_client_ip();
		
		 // System Parameters
		$companyName = $params['companyname'];
		$systemUrl = $params['systemurl'];
		$returnUrl = $params['returnurl'];
		$langPayNow = $params['langpaynow'];
		$moduleDisplayName = $params['name'];
		$moduleName = $params['paymentmethod'];
		$whmcsVersion = $params['whmcsVersion'];
		

		// Invoice Parameters
		$invoiceId = $params['invoiceid'];
		$description = $params["description"];
		$amount = $params['amount'];
		$currencyCode = $params['currency'];
		$returnUrl          = $systemUrl . 'viewinvoice.php?id=' . $invoiceId;
		$callback_url = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';
		$postfields['return_url'] = $returnUrl;


		$uniq_transaction_key=$uniqeID.time().'_'.$invoiceId;
		
		// check if pay now button pressed				
		// if( isset($_POST['order_id']) && !empty($_POST['order_id']) )
		// {
			// curl_request_post($isLive, $form_data);
			// exit("hello!");
		// }



		// Get token
		$token_raw_data = get_sp_token($isSandbox,$merchantName,$merchantPassword);
		$token_data = json_decode($token_raw_data);
		// Send payload
		$requestbodyJson= json_encode ( 
      array(
        'token' => $token_data->token,
        'store_id' => $token_data->store_id, 
        'prefix' => $uniqeID,                              
        'currency' => 'BDT',//$params['currency'],
        'return_url' => $callback_url,
        'cancel_url' => $callback_url,
        'amount' => $amount,                
        // Order information
        'order_id' => (string) $uniq_transaction_key,
        'discsount_amount' => 0,
        // Customer information
        'client_ip' => $merchantIP,                
        'customer_name' => $params['clientdetails']['firstname']." ".$params['clientdetails']['lastname'],
        'customer_phone' => $params['clientdetails']['phonenumber'],
        'customer_email' => $params['clientdetails']['email'],
        'customer_address' => $params['clientdetails']['address1']." ".$params['clientdetails']['address2'],                
        'customer_city' => $params['clientdetails']['city'],
        'customer_state' => $params['clientdetails']['state'],
        'customer_postcode' => 	$params['clientdetails']['state'],
        'customer_country' => $params['clientdetails']['country'],
        'custome1' => $params['description']
      )
    );
		// Redirect to gateway
		$response = send_payment($isSandbox,$token_data->token,$requestbodyJson);
		$response_decoted = json_decode($response);
		if(isset($response_decoted->checkout_url) && !empty($response_decoted->checkout_url))
		{
				header("Location: {$response_decoted->checkout_url}");
				exit;
		}
		else
		{
			 $errror = "Checkout url not found!";
	     logTransaction($GATEWAY["name"], $errror, "Unsuccessful");
		}

	}

	/*
	*
	*
	*
	*/

	function get_client_ip()
	{
	    $ipaddress = '';
	    if (isset($_SERVER['HTTP_CLIENT_IP']))
	        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
	    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
	        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
	    else if (isset($_SERVER['HTTP_X_FORWARDED']))
	        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
	    else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
	        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
	    else if (isset($_SERVER['HTTP_FORWARDED']))
	        $ipaddress = $_SERVER['HTTP_FORWARDED'];
	    else if (isset($_SERVER['REMOTE_ADDR']))
	        $ipaddress = $_SERVER['REMOTE_ADDR'];
	    else
	        $ipaddress = '127.0.0.1';
	    return $ipaddress;
	}

	/*
	*
	*
	*
	*/

	function get_sp_token($isSandbox,$username,$password)
  {
  		
  		$curl = curl_init();

  		if( $isSandbox == "on")
			{
				$server_url = 'https://sandbox.shurjopayment.com';				

			}
			else
			{
				$server_url = 'https://engine.shurjopayment.com';				
			}

  		$request_credential = array(
              'username' => $username,
              'password' => $password
          );
  		$requestbodyJson = json_encode($request_credential);
  		curl_setopt_array($curl, [
  		  CURLOPT_URL => $server_url.'/api/get_token',
  		  CURLOPT_RETURNTRANSFER => true,
  		  CURLOPT_ENCODING => "",
  		  //CURLOPT_MAXREDIRS => 10,
  		  //CURLOPT_TIMEOUT => 30,
  		  CURLOPT_CUSTOMREQUEST => "POST",
  		  CURLOPT_POSTFIELDS => $requestbodyJson,
  		  CURLOPT_HTTPHEADER => [
  			"Content-Type: application/json"
  		  ],
  		]);
  		$response = curl_exec($curl);
  		$err = curl_error($curl);
  		curl_close($curl);
  		if ($err) {
  		  echo "cURL Error #:" . $err;
  		} else {
  		  return $response;
  		}
  }

  function send_payment($isSandbox,$token,$requestbodyJson) 
	{


    if(empty($requestbodyJson))
    {
        return false;
    }

    if($isSandbox == 'on')
		{
			$server_url = 'https://sandbox.shurjopayment.com';				

		}
		else
		{
			$server_url = 'https://engine.shurjopayment.com';				
		}

    $curl  = curl_init();
    $array = json_decode($token, true);
     curl_setopt_array($curl, [
      CURLOPT_URL => $server_url."/api/secret-pay",
      CURLOPT_RETURNTRANSFER => true,
      // CURLOPT_SSL_VERIFYPEER => true,
      // CURLOPT_SSL_VERIFYHOST => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      // CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $requestbodyJson,
      CURLOPT_HTTPHEADER => [
        "Content-Type: Application/json",
        "Authorization: Bearer ". $token
      ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
      if ($err) {
        return "Error #:" . $err;
      } else {
        return $response;       
      }
	}


  function verify_payment($isSandbox,$orderid)
	{
  
    if(empty($orderid))
    {
      return false;
    } 

    if($isSandbox == 'on')
		{
			$server_url = 'https://sandbox.shurjopayment.com';	
		}
		else
		{
			$server_url = 'https://engine.shurjopayment.com';				
		}

    $curl  = curl_init();
    $token = get_token($username,$password);
    $array = json_decode($token, true);
    curl_setopt_array($curl, [
      CURLOPT_URL => $server_url."/api/verification",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => "{\n\"order_id\":\"".$orderid."\"\n}",
      CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ". $array['token'],
        "Content-Type: application/json"
      ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      echo $response;
    }
	}

	function webhook()
	{

	}
