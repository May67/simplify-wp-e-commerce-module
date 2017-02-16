<?php
/*
 * Copyright (c) 2013, MasterCard International Incorporated
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification, are 
 * permitted provided that the following conditions are met:
 * 
 * Redistributions of source code must retain the above copyright notice, this list of 
 * conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of 
 * conditions and the following disclaimer in the documentation and/or other materials 
 * provided with the distribution.
 * Neither the name of the MasterCard International Incorporated nor the names of its 
 * contributors may be used to endorse or promote products derived from this software 
 * without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY 
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES 
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT 
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, 
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
 * OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER 
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING 
 * IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF 
 * SUCH DAMAGE.
 */

/**
 * Simplify Commerce merchant gateway
 *
 * @author simplify.com
 *
 */

// Simplify Commerce PHP SDK 
require_once(WPSC_FILE_PATH.'/wpsc-merchants/library/simplifycommerce/Simplify.php');

// Using WPSC API v2.0
$nzshpcrt_gateways[$num]['api_version'] = 2.0;
$nzshpcrt_gateways[$num]['name'] = __( 'Simplify Commerce v1.0.1', 'wpsc' );
$nzshpcrt_gateways[$num]['image'] = WPSC_URL . '/images/cc.gif';
$nzshpcrt_gateways[$num]['internalname'] = 'wpsc_merchant_simplify';
$nzshpcrt_gateways[$num]['class_name'] = 'wpsc_merchant_simplify';
$nzshpcrt_gateways[$num]['form'] = "form_simplify";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_simplify";
$nzshpcrt_gateways[$num]['is_exclusive'] = true;
$nzshpcrt_gateways[$num]['payment_type'] = "simplify";
$nzshpcrt_gateways[$num]['display_name'] = __( 'Simplify Commerce', 'wpsc' );

$error = '';

/**
 * Simplify merchant class
 *
 * http://wp-e-commerce.googlecode.com/svn/trunk/wpsc-includes/merchant.class.php
 * 
 */
class wpsc_merchant_simplify extends wpsc_merchant {

	var $name = '';

	function __construct( $purchase_id = null, $is_receiving = false ) {
		$this->name = __( 'Simplify Commerce', 'wpsc' );
		parent::__construct( $purchase_id, $is_receiving );
	}

	/**
	 * Builds the payment request
	 * @access public
	 */
	function construct_value_array() {

		// Credit Card Data
		$token = $_POST['simplifyToken'];

		// request parameters
		$token         = $_POST['simplifyToken'];
		$paymentAmount = $this->cart_data['total_price']*100; // amount is in cents
		$paymentId    = $this->cart_data['session_id'];

		$request = array(
            'amount' => $paymentAmount,
            'token' => $token,
            'description' => 'Wp-E-Commerce Invoice: '.$paymentId,
            'currency' => 'USD',
            'reference' => $paymentId
	    );

		$this->collected_gateway_data = $request;
	}

	/**
	 * Submit the payment request
	 * @access public
	 */
	function submit() {

		// set the Simplify API key, LIVE or SANDBOX
		if ( get_option( 'simplify_api_mode' ) == "simplify_api_mode_live" ) {
			Simplify::$publicKey  = get_option("simplify_live_public_key");
			Simplify::$privateKey = get_option("simplify_live_private_key");
		}
		else {
			Simplify::$publicKey = get_option("simplify_sandbox_public_key");
			Simplify::$privateKey = get_option("simplify_sandbox_private_key");
		}

		// data from construct_value_array()
		$request = $this->collected_gateway_data;

		// debugging
		foreach ($request as $key => $value) {
		    error_log($key.'='.$value);
		}

		try{

			$PENDING = 2;
			$SUCCESSFUL = 3;
			$DECLINED = 6;

			// send payment to simplify
		    $response = Simplify_Payment::createPayment($request);

		    error_log($response);

		    if ($response->paymentStatus == 'APPROVED') {
		    	$this->set_authcode( $response->authCode );
		        $this->set_transaction_details( $response->id, $SUCCESSFUL );
		    }else if ($response->paymentStatus == 'DECLINED') {
		        $this->set_transaction_details( $response->id, $DECLINED );
		    }else{
		        $this->set_transaction_details( $response->id, $PENDING );
		    }

		    $this->go_to_transaction_results( $this->cart_data['session_id'] );

		}catch(Exception $e){

			$this->set_error_message(__('There was an error posting your payment.', 'wpsc'));
			if ($e instanceof Simplify_BadRequestException && $e->hasFieldErrors()) {

		        foreach ($e->getFieldErrors() as $fieldError) {
		            $this->set_error_message( ucwords($fieldError->getFieldName()).' '.strtolower($fieldError->getMessage()) );
		       	}
		    }
		    $this->return_to_checkout();
			exit();
			break;

		}
	}
}

/**
 * Handles updates to the Simplify plugin settings
 */
function submit_simplify() {
	
	if ( isset( $_POST['Simplify']['simplify_api_mode'] ) )
		update_option( 'simplify_api_mode', $_POST['Simplify']['simplify_api_mode'] );

	if ( isset( $_POST['Simplify']['live_public_key'] ) )
		update_option( 'simplify_live_public_key', $_POST['Simplify']['live_public_key'] );

	if ( isset( $_POST['Simplify']['live_private_key'] ) )
		update_option( 'simplify_live_private_key', $_POST['Simplify']['live_private_key'] );

	if ( isset( $_POST['Simplify']['sandbox_public_key'] ) )
		update_option( 'simplify_sandbox_public_key', $_POST['Simplify']['sandbox_public_key'] );

	if ( isset( $_POST['Simplify']['sandbox_private_key'] ) )
		update_option( 'simplify_sandbox_private_key', $_POST['Simplify']['sandbox_private_key'] );

	return true;
}

/**
 * Build the HTML form for the Simplify plugin settings
 */
function form_simplify() {
	global $wpsc_gateways, $wpdb;

	$sandbox_mode_selected = '';
	$live_mode_selected = '';
	if ( get_option( 'simplify_api_mode' ) == "simplify_api_mode_live" )
		$live_mode_selected = 'checked="checked"';
	else
		$sandbox_mode_selected = 'checked="checked"';

	$output = '
	<tr>
		<td>
			<label for="simplify_signature">' . __( 'API mode:', 'wpsc' ) . '</label>
		</td>
		<td>
			<label for="simplify_api_mode_sandbox">' . __( 'Sandbox:', 'wpsc' ) . '</label>
			<input type="radio" name="Simplify[simplify_api_mode]" id="simplify_api_mode_sandbox" value="simplify_api_mode_sandbox" ' . $sandbox_mode_selected . '/>
			<span class="old-school">&nbsp;&nbsp;&nbsp;</span>
			<label for="simplify_api_mode_live">' . __( 'Live Mode:', 'wpsc' ) . '</label>
			<input type="radio" name="Simplify[simplify_api_mode]" id="simplify_api_mode_live" value="simplify_api_mode_live" ' . $live_mode_selected . '/>
		</td>
	</tr>
	<tr>
		<td>
		</td>
		<td>
			<p class="description">
				In Sandbox mode, all payments are simulated. In Live mode, payments are real. 
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<label for="simplify_sandbox_private_key">' . __( 'Sandbox - Private Key:', 'wpsc' ) . '</label>
		</td>
		<td>
			<input type="password" name="Simplify[sandbox_private_key]" id="simplify_sandbox_private_key" value="' . get_option( "simplify_sandbox_private_key" ) . '" size="30" size="30" style="width:500px;"/>
		</td>
	</tr>
	<tr>
		<td>
			<label for="simplify_sandbox_public_key">' . __( 'Sandbox - Public Key:', 'wpsc' ) . '</label>
		</td>
		<td>
			<input type="text" name="Simplify[sandbox_public_key]" id="simplify_sandbox_public_key" value="' . get_option( "simplify_sandbox_public_key" ) . '" size="30" size="30" style="width:500px;"/>
		</td>
	</tr>
	<tr>
		<td>
			<label for="simplify_live_private_key">' . __( 'Live - Private Key:', 'wpsc' ) . '</label>
		</td>
		<td>
			<input type="password" name="Simplify[live_private_key]" id="simplify_live_private_key" value="' . get_option( "simplify_live_private_key" ) . '" size="30" size="30" style="width:500px;"/>
		</td>
	</tr>
	<tr>
		<td>
			<label for="simplify_live_public_key">' . __( 'Live - Public Key:', 'wpsc' ) . '</label>
		</td>
		<td>
			<input type="text" name="Simplify[live_public_key]" id="simplify_live_public_key" value="' . get_option( "simplify_live_public_key" ) . '" size="30" style="width:500px;"/>
		</td>
	</tr>';

	return $output;
}

$years = $months = '';

if ( in_array( 'wpsc_merchant_simplify', (array)get_option( 'custom_gateway_options' ) ) ) {

	$public_key = '';
	if ( get_option( 'simplify_api_mode' ) == "simplify_api_mode_live" )
		$public_key = get_option( "simplify_live_public_key" );
	else
		$public_key = get_option( "simplify_sandbox_public_key" );

	//generate year options
	$curryearShort = date('y');
	$curryearLong = date('Y');
	for ( $i = 0; $i < 10; $i++ ) {
		$years .= "<option value='" . $curryearShort . "'>" . $curryearLong . "</option>\r\n";
		$curryearShort++;
		$curryearLong++;
	}

	$output = "
	<script type='text/javascript' src='https://www.simplify.com/commerce/v1/simplify.js'></script>
	<script>
		jQuery(function ($) {

			function simplifyResponseHandler(data) {
	            var paymentForm = $('.wpsc_checkout_forms');
	            // Hide all previous errors
	            $('.error').hide();
	            // Check for errors
	            if (data.error) {
	                // Show any validation errors
	                if (data.error.code == 'validation') {
	                    var fieldErrors = data.error.fieldErrors;

	                    for (var i = 0; i < fieldErrors.length; i++) {
	                    	var id = '#'+fieldErrors[i].field.replace(/\./g,'_')
	                        $(id).show();
	                    }
	                }
	            } else {
	                var token = data['id'];
	                $('#simplify-token').val(token);
	                paymentForm.get(0).submit();
	            }
	        }
	        $(document).ready(function() {
	        	var paymentForm = $('.wpsc_checkout_forms');
	            paymentForm.on('submit', function() {
	                // Generate a card token & handle the response
	                SimplifyCommerce.generateToken({
	                    key: '".$public_key."',
	                    card: {
	                        number: $('#cc-number').val(),
	                        cvc: $('#cc-cvc').val(),
	                        expMonth: $('#cc-exp-month').val(),
	                        expYear: $('#cc-exp-year').val()
	                    }
	                }, simplifyResponseHandler);
	                // Prevent the form from submitting
	                return false;
	            });
	        });

		});
	</script>
	<tr>
	  <td colspan=2>
	     <h4>Credit Card Details</h4>
	  </td>
    </tr>
	<tr>
		<td style='width:151px'>" . __( 'Credit Card Number *', 'wpsc' ) . "</td>
		<td>
			<input placeholder='Credit Card Number' type='text' id='cc-number' class='text'/>
			<div id='card_number' class='error' style='color:#ff0000;display:none;'>" . __( 'Please enter a valid card mumber', 'wpsc' ) . "</div>
		</td>
	</tr>
	<tr>
		<td>" . __( 'Credit Card Expiry *', 'wpsc' ) . "</td>
		<td>
			<select id='cc-exp-month'>
			" . $months . "
			<option value='01'>01</option>
			<option value='02'>02</option>
			<option value='03'>03</option>
			<option value='04'>04</option>
			<option value='05'>05</option>
			<option value='06'>06</option>
			<option value='07'>07</option>
			<option value='08'>08</option>
			<option value='09'>09</option>
			<option value='10'>10</option>
			<option value='11'>11</option>
			<option value='12'>12</option>
			</select>
			<select class='wpsc_ccBox' id='cc-exp-year'>
			" . $years . "
			</select>
			<div id='card_expMonth' class='error' style='color:#ff0000;display:none;'>" . __( 'Expiry month is invalid', 'wpsc' ) . "</div>
			<div id='card_expYear' class='error' style='color:#ff0000;display:none;'>" . __( 'Expiry year is invalid', 'wpsc' ) . "</div>
		</td>
	</tr>
	<tr>
		<td>" . __( 'Security Code', 'wpsc' ) . "</td>
		<td>
			<input type='text' size='4' maxlength='4' id='cc-cvc'/>
			<input type='hidden' id='simplify-token' name='simplifyToken' value='' />
		</td>
	</tr>
";

$gateway_checkout_form_fields[$nzshpcrt_gateways[$num]['internalname']] = $output;

}
?>
