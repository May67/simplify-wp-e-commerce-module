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
require_once(WPSC_FILE_PATH . '/wpsc-merchants/library/simplifycommerce/Simplify.php');

// Using WPSC API v2.0
$nzshpcrt_gateways[$num]['api_version'] = 2.0;
$nzshpcrt_gateways[$num]['name'] = __('Simplify Commerce v1.0.1', 'wpsc');
$nzshpcrt_gateways[$num]['image'] = WPSC_URL . '/images/cc.gif';
$nzshpcrt_gateways[$num]['internalname'] = 'wpsc_merchant_simplify';
$nzshpcrt_gateways[$num]['class_name'] = 'wpsc_merchant_simplify';
$nzshpcrt_gateways[$num]['form'] = "form_simplify";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_simplify";
$nzshpcrt_gateways[$num]['is_exclusive'] = true;
$nzshpcrt_gateways[$num]['payment_type'] = "simplify";
$nzshpcrt_gateways[$num]['display_name'] = __('Simplify Commerce', 'wpsc');

$error = '';

/**
 * Simplify merchant class
 *
 * http://wp-e-commerce.googlecode.com/svn/trunk/wpsc-includes/merchant.class.php
 *
 */
class wpsc_merchant_simplify extends wpsc_merchant
{

    var $name = '';

    function __construct($purchase_id = null, $is_receiving = false)
    {
        $this->name = __('Simplify Commerce', 'wpsc');
        parent::__construct($purchase_id, $is_receiving);
    }

    /**
     * Builds the payment request
     * @access public
     */
    function construct_value_array()
    {
        // request parameters
        $token = $_POST['simplifyToken'];
        $paymentAmount = $this->cart_data['total_price'] * 100; // amount is in cents
        $paymentId = $this->cart_data['session_id'];

        $request = array(
            'amount' => $paymentAmount,
            'token' => $token,
            'description' => 'Wp-E-Commerce Invoice: ' . $paymentId,
            'currency' => wpsc_get_currency_code(),
            'reference' => $paymentId
        );

        $this->collected_gateway_data = $request;
    }

    /**
     * Submit the payment request
     * @access public
     */
    function submit()
    {

        // set the Simplify API key, LIVE or SANDBOX
        if (get_option('simplify_api_mode') == "simplify_api_mode_live") {
            Simplify::$publicKey = get_option("simplify_live_public_key");
            Simplify::$privateKey = get_option("simplify_live_private_key");
        } else {
            Simplify::$publicKey = get_option("simplify_sandbox_public_key");
            Simplify::$privateKey = get_option("simplify_sandbox_private_key");
        }

        // data from construct_value_array()
        $request = $this->collected_gateway_data;

        // debugging
        foreach ($request as $key => $value) {
            error_log($key . '=' . $value);
        }

        try {

            $PENDING = 2;
            $SUCCESSFUL = 3;
            $DECLINED = 6;

            // send payment to simplify
            $response = Simplify_Payment::createPayment($request);

            error_log($response);

            if ($response->paymentStatus == 'APPROVED') {
                $this->set_authcode($response->authCode);
                $this->set_transaction_details($response->id, $SUCCESSFUL);
            } else if ($response->paymentStatus == 'DECLINED') {
                $this->set_transaction_details($response->id, $DECLINED);
            } else {
                $this->set_transaction_details($response->id, $PENDING);
            }

            $this->go_to_transaction_results($this->cart_data['session_id']);

        } catch (Exception $e) {

            $this->set_error_message(__('There was an error posting your payment.', 'wpsc'));
            if ($e instanceof Simplify_BadRequestException && $e->hasFieldErrors()) {

                foreach ($e->getFieldErrors() as $fieldError) {
                    $this->set_error_message(ucwords($fieldError->getFieldName()) . ' ' . strtolower($fieldError->getMessage()));
                }
            }
            $this->return_to_checkout();
            exit();
        }
    }
}

/**
 * Handles updates to the Simplify plugin settings
 */
function submit_simplify()
{

    if (isset($_POST['Simplify']['simplify_api_mode']))
        update_option('simplify_api_mode', $_POST['Simplify']['simplify_api_mode']);

    if (isset($_POST['Simplify']['live_public_key']))
        update_option('simplify_live_public_key', $_POST['Simplify']['live_public_key']);

    if (isset($_POST['Simplify']['live_private_key']))
        update_option('simplify_live_private_key', $_POST['Simplify']['live_private_key']);

    if (isset($_POST['Simplify']['sandbox_public_key']))
        update_option('simplify_sandbox_public_key', $_POST['Simplify']['sandbox_public_key']);

    if (isset($_POST['Simplify']['sandbox_private_key']))
        update_option('simplify_sandbox_private_key', $_POST['Simplify']['sandbox_private_key']);

    return true;
}

/**
 * Build the HTML form for the Simplify plugin settings
 */
function form_simplify()
{
    global $wpsc_gateways, $wpdb;

    $sandbox_mode_selected = '';
    $live_mode_selected = '';
    if (get_option('simplify_api_mode') == "simplify_api_mode_live")
        $live_mode_selected = 'checked="checked"';
    else
        $sandbox_mode_selected = 'checked="checked"';

    $output = '
	<tr>
		<td>
			<label for="simplify_signature">' . __('API mode:', 'wpsc') . '</label>
		</td>
		<td>
			<label for="simplify_api_mode_sandbox">' . __('Sandbox:', 'wpsc') . '</label>
			<input type="radio" name="Simplify[simplify_api_mode]" id="simplify_api_mode_sandbox" value="simplify_api_mode_sandbox" ' . $sandbox_mode_selected . '/>
			<span class="old-school">&nbsp;&nbsp;&nbsp;</span>
			<label for="simplify_api_mode_live">' . __('Live Mode:', 'wpsc') . '</label>
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
			<label for="simplify_sandbox_private_key">' . __('Sandbox - Private Key:', 'wpsc') . '</label>
		</td>
		<td>
			<input type="password" name="Simplify[sandbox_private_key]" id="simplify_sandbox_private_key" value="' . get_option("simplify_sandbox_private_key") . '" size="30" size="30" style="width:500px;"/>
		</td>
	</tr>
	<tr>
		<td>
			<label for="simplify_sandbox_public_key">' . __('Sandbox - Public Key:', 'wpsc') . '</label>
		</td>
		<td>
			<input type="text" name="Simplify[sandbox_public_key]" id="simplify_sandbox_public_key" value="' . get_option("simplify_sandbox_public_key") . '" size="30" size="30" style="width:500px;"/>
		</td>
	</tr>
	<tr>
		<td>
			<label for="simplify_live_private_key">' . __('Live - Private Key:', 'wpsc') . '</label>
		</td>
		<td>
			<input type="password" name="Simplify[live_private_key]" id="simplify_live_private_key" value="' . get_option("simplify_live_private_key") . '" size="30" size="30" style="width:500px;"/>
		</td>
	</tr>
	<tr>
		<td>
			<label for="simplify_live_public_key">' . __('Live - Public Key:', 'wpsc') . '</label>
		</td>
		<td>
			<input type="text" name="Simplify[live_public_key]" id="simplify_live_public_key" value="' . get_option("simplify_live_public_key") . '" size="30" style="width:500px;"/>
		</td>
	</tr>';

    return $output;
}

$years = $months = '';

if (in_array('wpsc_merchant_simplify', (array)get_option('custom_gateway_options'))) {

    $public_key = '';
    if (get_option('simplify_api_mode') == "simplify_api_mode_live")
        $public_key = get_option("simplify_live_public_key");
    else
        $public_key = get_option("simplify_sandbox_public_key");

    $optValue = $nzshpcrt_gateways[$num]['internalname'];
    $amount = _wpsc_get_checkout_info()['total_input'] * 100; // amount is in cents
    $description = 'Order Total';
    $currency = wpsc_get_currency_code();
    $name = _x( 'Store', 'main store page title', 'wp-e-commerce' );

    $output = "<script type=\"text/javascript\" src=\"https://www.simplify.com/commerce/simplify.pay.js\"></script>
	<script>
		jQuery(function ($) {
		    var _parseSearchQuery = function () {
                var params = {};
        
                window.location.hash.substring(2).split('&').forEach(function (param) {
                    var parts = param.split('=');
                    if (parts.length > 1) {
                        params[parts[0]] = parts[1];
                    }
                });
        
                return params;
            };
		    
		$(document).ready(function() {
		    var parameters = _parseSearchQuery();
		    var paymentForm = $('.wpsc_checkout_forms');
		    var checkbox = paymentForm.find('input[value=\"$optValue\"]');
		    var table = paymentForm.find('div.$optValue table');
		    var btn = $('.simplify-hosted-payment-btn');
            if(!btn.length){
                btn = $('<button class=\"simplify-hosted-payment-btn\" style=\"display:none;\">Buy Now</button>');
                paymentForm.append(btn);
            }
		    
		    table.hide();
		    checkbox.click(function(e) {
		        // We need to hide the table again but we must do it after the other handlers. 
		      window.setTimeout(function() {
		       table.hide(); 
		      },1);
		    });
		    
		    if(parameters.cardToken){
		        paymentForm.append('<input type=\"hidden\" name=\"simplifyToken\" value=\"'+parameters.cardToken+'\"/>');
                paymentForm.get(0).submit();
		    } else {
		        paymentForm.on('submit', function(e) {
		            if( checkbox.prop('checked')){
		                e.preventDefault();
		                var hostedParams = {
                            'sc-key': \"$public_key\",
                            name: \"$name\",
                            description: \"Order total\",
                            amount: \"$amount\",
                            currency: \"$currency\",
                            operation: 'create.token',
                            'redirect-url': window.location + '#'
                        };
                        for (var key in hostedParams) {
                            if (hostedParams.hasOwnProperty(key)) {
                                btn.attr('data-' + key, hostedParams[key]);
                            }
                        }
                        SimplifyCommerce.hostedPayments();
		                btn.click();
    		            return false;    
		            }
		        });
		    }
		});
    });
	</script>";

    $gateway_checkout_form_fields[$nzshpcrt_gateways[$num]['internalname']] = $output;

}
?>
