<?php
    # Required File Includes
    include("../../../init.php");
    include("../../../includes/functions.php");
    include("../../../includes/gatewayfunctions.php");
    include("../../../includes/invoicefunctions.php");
    
    $gatewaymodule = "shurjopay"; # Enter your gateway module name here replacing template

    $GATEWAY = getGatewayVariables($gatewaymodule);
    if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback
    if (!isset($_POST)) die("No Post Data To Validate!");
    
	$systemurl = $GATEWAY['systemurl'];
    $isLive    = $GATEWAY["testMode"];
    $merchantName = $GATEWAY['merchantName'];
	$merchantPassword = $GATEWAY['merchantPassword'];

    if (!isset($_REQUEST['order_id']) || empty($_REQUEST['order_id'])) 
    {
    	logTransaction($GATEWAY["name"], json_encode($_POST), "Unsuccessful"); # Save to Gateway Log: name, data array, status
	    header("Location: ".$systemurl."/clientarea.php?action=services"); /* Redirect browser */
	    exit();
    }
    else
	{
		$order_id = $_REQUEST["order_id"];
		$token_raw_data = get_sp_token_in_callback($GATEWAY["testMode"],$merchantName,$merchantPassword);
		$token_data = json_decode($token_raw_data);

		$verified_raw_data = verification($GATEWAY["testMode"],$token_data->token,$order_id);
		$verified_data = json_decode($verified_raw_data);

		// If transaction already verified
		if( $verified_data->sp_code	 == '208' ) {
			logTransaction($GATEWAY["name"], $verified_data, "Already verified"); # Save to Gateway Log: name, data array, status    
	        header("Location: ".$systemurl."clientarea.php?action=invoices"); /* Redirect browser */	 
		    exit();
		}

		//Retrieve data returned from payment gateway callback  	
		$verified_data = array_shift($verified_data);	
		$transactionId = $returnID = $verified_data->customer_order_id;
		$spliteID    = explode('_',$returnID);
		$invoiceid   = $spliteID[1];
		$bank_tx_id  = $verified_data->bank_trx_id;
		$sp_code_des = $verified_data->sp_massage;
		$txnAmount   = number_format($verified_data->amount,2);
		$method      = $verified_data->method;

		$orderData = mysql_fetch_assoc(select_query('tblinvoices', 'total', array("id" => $invoiceid)));
		$order_amount = $orderData['total'];

		if( $verified_data->sp_code	 == '1000' && ($order_amount == $txnAmount)) {
			$status = 'success';
		} else {
			$status = 'failed';
		}			
		
		//$invoiceid = checkCbInvoiceID($invoiceid,$method); # Checks invoice ID is a valid invoice number or ends processing
		$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing

        $orderStatus = mysql_fetch_assoc(select_query('tblinvoices', 'status', array("id" => $invoiceid)));        
		if($orderStatus['status'] == "Paid") {
		    logTransaction($GATEWAY["name"],  array("Gateway Response" => $verified_data, "Validation Response" => json_decode($results, true), "Response" => "Already Paid"), "Successful"); # Save to Gateway Log: name, data array, status
		    header("Location: ".$systemurl."clientarea.php?action=services"); /* Redirect browser */	        
		    exit();
		}
		
		checkCbTransID($transactionId); # Checks transaction number isn't already in the database and ends processing if it does
		
		if ($status=="success") {
			$fee = 0;
		    addInvoicePayment($invoiceid, $transactionId, $txnAmount, $fee, $gatewaymodule);
		    logTransaction($GATEWAY["name"], $verified_data, "Successful"); # Save to Gateway Log: name, data array, status		 
	        header("Location: ".$systemurl."viewinvoice.php?id=".$invoiceid); /* Redirect browser */	  
		    exit();
		    
		} else {
		    logTransaction($GATEWAY["name"], $verified_data, "Unsuccessful"); # Save to Gateway Log: name, data array, status    
	        header("Location: ".$systemurl."clientarea.php?action=invoices"); /* Redirect browser */	        
	        exit();
		}

	}	
	
	/*
	* Get token
	* @ Request params bolean,credentials string
	* @ Return  token json string
	*/

	function get_sp_token_in_callback($isSandbox,$username,$password)
  	{

  		$curl = curl_init();

  		if($isSandbox == 'on') {
			$server_url = 'https://sandbox.shurjopayment.com';				
		} else {
			$server_url = 'https://engine.shurjopayment.com';
		}

  		$request_credential = array (
	        'username' => $username,
            'password' => html_entity_decode($password)
        );

  		$requestbodyJson = json_encode($request_credential);
  		curl_setopt_array($curl, [
  		  CURLOPT_URL => $server_url.'/api/get_token',
  		  CURLOPT_RETURNTRANSFER => true,
  		  CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_SSL_VERIFYHOST => false,
  		  CURLOPT_ENCODING => "",
  		  CURLOPT_MAXREDIRS => 10,
  		  CURLOPT_TIMEOUT => 30,
  		  CURLOPT_CUSTOMREQUEST => "POST",
  		  CURLOPT_POSTFIELDS => $requestbodyJson,
  		  CURLOPT_HTTPHEADER => [
  			"Content-Type: application/json"
  		  ],
  		]);
  		$response = curl_exec($curl);
  		$error = curl_error($curl);
  		curl_close($curl);
  		if ($error) {
  		  	$errror = "Curl error: ".json_encode($error);
	        logTransaction($GATEWAY["name"], $errror, "Unsuccessful"); # Save to Gateway Log: name, data array, status
  		} else {
  		  return $response;
  		}
  	}


  	/*
	*  Payment verification call
	*  @ Request params bolean, order id string
	*  @ Response json array
	*/

  	function verification($isSandbox,$token,$order_id)
  	{

  		if($isSandbox == 'on') {
			$server_url = 'https://sandbox.shurjopayment.com';	
		} else {
			$server_url = 'https://engine.shurjopayment.com';
		}

        $header=array(
            'Content-Type:application/json',
            'Authorization: Bearer '.$token  
        );
        $postFields = json_encode (
                array(
                    'order_id' => $order_id
                )
        );

        $curl = curl_init();
        curl_setopt_array($curl, [
  		  CURLOPT_URL => $server_url.'/api/verification',
  		  CURLOPT_RETURNTRANSFER => true,
  		  CURLOPT_ENCODING => "",
  		  CURLOPT_MAXREDIRS => 10,
  		  CURLOPT_TIMEOUT => 30,
  		  CURLOPT_CUSTOMREQUEST => "POST",
  		  CURLOPT_POSTFIELDS => $postFields,
  		  CURLOPT_HTTPHEADER => [
  			"Content-Type: application/json",
  			"Authorization: Bearer ". $token
  		  ],
  		]);

  		$response = curl_exec($curl);
	      $err = curl_error($curl);
	      curl_close($curl);
	      
	        if ($err) {
	          $errror = "Curl error: ".json_encode(curl_error($ch));
	            logTransaction($GATEWAY["name"], $errror, "Unsuccessful"); # Save to Gateway Log: name, data array, status
	    	    header("Location: ".$systemurl."/clientarea.php?action=services"); /* Redirect browser */
	    		exit();
	        } else {
	          return $response;
	         
	        }

        
  	}

 
?>
