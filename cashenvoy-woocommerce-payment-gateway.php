<?php
/*
	Plugin Name: CashEnvoy WooCommerce Payment Gateway
	Plugin URI:  http://www.cashenvoy.com
	Description: CashEnvoy Woocommerce Payment Gateway allows you to accept payment on your Woocommerce store via Visa Cards, Mastercards, Verve Cards and eTranzact.
	Version: 1.0.0
	Author: CashEnvoy
	Author URI: http://www.cashenvoy.com
	License: GPL-2.0+
 	License URI: http://www.gnu.org/licenses/gpl-2.0.txt

*/
if ( ! defined( 'ABSPATH' ) )
	exit;

add_action('plugins_loaded', 'woocommerce_cashenvoy_init', 0);

function woocommerce_cashenvoy_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Gateway class
 	 */
	class WC_CashEnvoy_Gateway extends WC_Payment_Gateway {

		public function __construct(){
			global $woocommerce;

			$this->id 					= 'cashenvoy_gateway';
    		$this->icon 				= apply_filters('woocommerce_cashenvoyway_icon', plugins_url( 'img/cashenvoy.png' , __FILE__ ) );
			$this->has_fields 			= false;
        	$this->liveurl 				= 'https://www.cashenvoy.com/sandbox/?cmd=cepay';
			$this->notify_url        	= WC()->api_request_url( 'WC_CashEnvoy_Gateway' );
        	$this->method_title     	= 'CashEnvoy Payment Gateway';
        	$this->method_description  	= 'CashEnvoy Payment Gateway allows you to receive Mastercard, Verve Card and Visa Card Payments On your Woocommerce Powered Site.';


			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();


			// Define user set variables
			$this->title 					    = $this->get_option( 'title' );
			$this->description 				    = $this->get_option( 'description' );
			$this->cashenvoyPayMerchantId 		= $this->get_option( 'cashenvoyPayMerchantId' );
            $this->cecustomerid 				= $this->get_option( 'customerid' );
            $this->mode 		                = $this->get_option( 'mode' );

                      //Actions
			add_action('woocommerce_receipt_cashenvoy_gateway', array($this, 'receipt_page'));
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// Payment listener/API hook
		add_action( 'woocommerce_api_wc_cashenvoy_gateway', array( $this, 'check_cashenvoy_response' ) );

			// Check if the gateway can be used
			if ( ! $this->is_valid_for_use() ) {
				$this->enabled = false;
			}
		}

		public function is_valid_for_use(){

			if( ! in_array( get_woocommerce_currency(), array('NGN') ) ){
				$this->msg = 'CashEnvoy doesn\'t support your store currency, set it to Nigerian Naira &#8358; <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc_settings&tab=general">here</a>';
				return false;
			}

			return true;
		}

        /**
         * Admin Panel Options
         **/
        public function admin_options(){
            echo '<h3>CashEnvoy Payment Gateway</h3>';
            echo '<p>CashEnvoy Payment Gateway allows you to accept payment through various channels such as Interswitch, Mastercard, Verve cards, eTranzact and Visa cards.</p>';


			if ( $this->is_valid_for_use() ){

	            echo '<table class="form-table">';
	            $this->generate_settings_html();
	            echo '</table>';
            }
			else{	 ?>
			<div class="inline error"><p><strong>CashEnvoy Payment Gateway Disabled</strong>: <?php echo $this->msg ?></p></div>

			<?php }
        }


	    /**
	     * Initialise Gateway Settings Form Fields
	    **/
		function init_form_fields(){
			$this->form_fields = array(
			'enabled' => array(
							'title' 			=> 	'Enable/Disable',
							'type' 				=> 	'checkbox',
							'label' 			=>	'Enable CashEnvoy Payment Gateway',
							'description' 		=> 	'Enable or disable the gateway.',
                    		'desc_tip'      	=> 	true,
							'default' 			=> 	'yes'
						),
						'mode' => array(
							'title' 			=> 	'Mode',
							'type' 				=> 	'checkbox',
							'label' 			=>	'Check to switch to live Mode',
							'description' 		=> 	'***Do this only when you have been told to do so by our support team***',
                    		'desc_tip'      	=> 	false,
							'default' 			=> 	'no'
						),
				 'title' => array(
								'title' 		=> 	'CheckOut Caption:',
								'type' 			=> 	'text',
								'description' 	=> 	'This controls the title which the user sees during checkout.',
                    			'desc_tip'      => 	false,
								'default' 		=>  'CashEnvoy Payment Gateway'
							),
                'customerid' => array(
                    'title' 		=> 	'Username',
                    'type' 			=> 	'text',
                    'description' 	=> 	'This will serve as your customer identification (Preferably your Email Address or any unique name).',
                    'desc_tip'      => 	false,
                    'default' 		=>  ''
                ),
				'description' => array(
								'title' 		=> 	'Description',
								'type' 			=> 	'textarea',
								'description' 	=> 	'This controls the description which the user sees during checkout.',
								'default' 		=> 	'Pay Via CashEnvoy: Accepts Interswitch, Mastercard, Verve cards, eTranzact and Visa cards.'
							),
				'cashenvoyPayMerchantId' => array(
								'title' 		=> 	'CashEnvoy Merchant ID',
								'type' 			=> 	'text',
								'description' 	=> 'Enter Your CashEnvoy Merchant ID, this can be gotten on your account page when you login on CashEnvoy' ,
								'default' 		=> '',
                    			'desc_tip'      => true
							)
			);
		}

		/**
		 * Get CasheEnvoy Args for passing to CashEnvoy
		**/
		function get_cashenvoy_args( $order ) {
			global $woocommerce;

			  $order_id 		= $order->id;
			  $ceamt	= $order->get_total();
              $cemertid	= $this->cashenvoyPayMerchantId;
			  $cememo     = "Payment for Order ID: $order_id on ". get_bloginfo('name');
              $cenurl  	=   add_query_arg( 'wc-api', 'WC_CashEnvoy_Gateway', home_url( '/' ) );
              $cecustomerid =           $this->cecustomerid;

            session_start();
            $transref ="woo".time().mt_rand(0,9999);
            $_SESSION['transref']= $transref;
            $_SESSION['urlmode']= $this->liveurl;
            $_SESSION['order_id'] = $order_id;
			// cashenvoy Args
			$cashenvoy_args = array(
                'ce_merchantid'          => $cemertid,
                'ce_transref'            =>  $transref,
                'ce_amount'              => $ceamt,
                'ce_customerid'          => $cecustomerid,
                'ce_memo'                => $cememo,
                'ce_notifyurl'           => $cenurl,
                'ce_window'              => "parent"
			);

			$cashenvoy_args = apply_filters( 'woocommerce_cashenvoy_args', $cashenvoy_args );
			return $cashenvoy_args;
		}

	    /**
		 * Generate the CashEnvoy Payment button link
	    **/
	    function generate_cashenvoy_form( $order_id ) {
			global $woocommerce;



			$order = new WC_Order( $order_id );

            //checking if its in the live mode or test mode to know what to URL to use for the payment

            if($this->mode == 'yes'){

                $this->liveurl =       'https://www.cashenvoy.com/webservice/?cmd=cepay';

            } else

            {
                $this->liveurl =        'https://www.cashenvoy.com/sandbox/?cmd=cepay';
            }


			$cashenvoy_adr = $this->liveurl;

       		$cashenvoy_args = $this->get_cashenvoy_args( $order );

			$cashenvoy_args_array = array();

			foreach ($cashenvoy_args as $key => $value) {

				$cashenvoy_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			return '<form action="'.esc_url( $cashenvoy_adr ).'" name="submit2cepay_form" method="post" id="cashenvoy_payment_form" target="_self">
					' . implode('', $cashenvoy_args_array).'
					<input type="submit" class="button-alt" id="submit_cashenvoy_payment_form" value="'.__('Make Payment', 'woocommerce').'" />
					<a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woocommerce').'</a>
				</form>';

		}

	    /**
	     * Process the payment and return the result
	    **/
		function process_payment( $order_id ) {

			$order = new WC_Order( $order_id );
	        return array(
	        	'result' => 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
	        );
		}

	    /**
	     * Output for the order received page.
	    **/
		function receipt_page( $order ) {
			echo '<p></br>'.__('Thank you for your order, please click the button below to make payment.', 'woocommerce').'</p>';
			echo $this->generate_cashenvoy_form( $order );
		}


		/**
		 * Verify a successful Payment!
		**/
		function check_cashenvoy_response( $posted ){
			global $woocommerce;
                function getStatus($transref,$mertid,$paymenturl,$type=''){
                    $request = 'mertid='.urlencode($mertid).'&transref='.urlencode($transref); //initialize the request variable
                     $url = $paymenturl;
                    $ch = curl_init(); //initialize curl handle
                    curl_setopt($ch, CURLOPT_URL, $url); //set the url
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //return as a variable
                    curl_setopt($ch, CURLOPT_POST, 1); //set POST method
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch); // grab URL and pass it to the browser. Run the whole process and return the response
                    curl_close($ch); //close the curl handle
                    return $response;
                }

            session_start();
            // this shows how you can retrieve the transaction status via the api using CURL
            $transref = $_SESSION['transref'];
            $order_idr = $_SESSION['order_id'];
            $paymenturl = esc_url($_SESSION['urlmode']);
            $paymenturll  = str_replace("cepay", "requery", $paymenturl)   ;

            $raw_response =  getStatus($transref,$this->cashenvoyPayMerchantId,$paymenturll) ;


           $data = explode('-',$raw_response); //this line had an error before this update
            $cnt = count($data);
// if the $raw_reponse does not explode to 3 parts then there is an error so we echo $raw_response to see what it is
            if($cnt!=3){   $raw_response; }

// the returned information is transref, status and amount respectively
            if($cnt==3)
            {
                $returned_transref = $data[0];
                $returned_status = $data[1];
                $returned_amount = $data[2];

            }

         //interpreting the code response
            switch($returned_status)
            {
                case C00:
                    $returned_status="Transaction Successful";
                    break;
                case C01:
                    $returned_status="User Cancellation";
                    break;
                case C02:
                     $returned_status="User cancellation by inactivity";
                    break;
                case C03:
                    $returned_status="No transaction record";
                    break;
                case C04:
                    $returned_status="Insufficient funds";
                    break;
                case C05:
                    $returned_status="Transaction failed. Contact support@cashenvoy.com for more information.";
                    break;
                default:
                    $returned_status="We can not process your transaction as at this time pls try again later";

            }

            $order_id = (int) $order_idr;

            $order = new WC_Order($order_id);


            $message = 'Thank you for shopping with us.<br />'.'Payment Status:  '.$returned_status.'</br>'.'Total Amount:  NGN'.$returned_amount.'</br>'.'Transaction Reference ID:    '.$returned_transref;

            if($returned_status ==  "Transaction Successful"){


                $order->update_status('Processing', '');

                $order->add_order_note('Payment Via CashEnvoy ');

                // Reduce stock levels
                $order->reduce_order_stock();

                 // Empty cart
                WC()->cart->empty_cart();

                // Reduce stock levels
                $order->reduce_order_stock();

                // Empty cart
                WC()->cart->empty_cart();
            $message_type = 'success';
            } else
            if($returned_status ==  "Insufficient funds"){

                $order->update_status('No Payment Received', '');

                $order->add_order_note('Payment Via CashEnvoy ');

                $message_type = 'notice';
            } else
            if($returned_status ==  "User Cancellation"){

                $message_type = 'error';
            }
            else{
                $message_type='error';
            }

            if ( function_exists( 'wc_add_notice' ) )
            {
                wc_add_notice( $message, $message_type );

            }
            else // WC < 2.1
            {
                $woocommerce->add_error( $message );

                $woocommerce->set_messages();
            }

            $redirect_url = get_permalink( wc_get_page_id('myaccount') );

            wp_safe_redirect( $redirect_url );
            exit;



        }





    }


	function _cashenvoy_message(){
		if( is_order_received_page() ){
			$order_id = absint( get_query_var( 'order-received' ) );

			$cashenvoy_message 	= get_post_meta( $order_id, '__cashenvoy_message', true );
			$message 			= $cashenvoy_message['message'];
			$message_type 		= $cashenvoy_message['message_type'];

			delete_post_meta( $order_id, '__cashenvoy_message' );

			if(! empty( $cashenvoy_message) ){
				wc_add_notice( $message, $message_type );
			}
		}
	}
	add_action('wp', '_cashenvoy_message');


	/**
 	* Add CashEnvoy Gateway to WC
 	**/
	function woocommerce_add_cashenvoy_gateway($methods) {
		$methods[] = 'WC_CashEnvoy_Gateway';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_cashenvoy_gateway' );


	/**
	 * only add the naira currency and symbol if WC versions is less than 2.1
	 */
	if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

		/**
		* Add NGN as a currency in WC
		**/
		add_filter( 'woocommerce_currencies', '_add_my_currency' );

		if( ! function_exists( '_add_my_currency' )){
			function _add_my_currency( $currencies ) {
			     $currencies['NGN'] = __( 'Naira', 'woocommerce' );
			     return $currencies;
			}
		}

		/**
		* Enable the naira currency symbol in WC
		**/
		add_filter('woocommerce_currency_symbol', '_add_my_currency_symbol', 10, 2);

		if( ! function_exists( '_add_my_currency_symbol' ) ){
			function _add_my_currency_symbol( $currency_symbol, $currency ) {
			     switch( $currency ) {
			          case 'NGN': $currency_symbol = '&#8358; '; break;
			     }
			     return $currency_symbol;
			}
		}
	}


	/**
	* Add Settings link to the plugin entry in the plugins menu for WC below 2.1
	**/
	if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

		add_filter('plugin_action_links', '_cashenvoy_plugin_action_links', 10, 2);

		function _cashenvoy_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
	        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_CashEnvoy_Gateway">Settings</a>';
		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	}
	/**
	* Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
	**/
	else{
		add_filter('plugin_action_links', '_cashenvoy_plugin_action_links', 10, 2);

		function _cashenvoy_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
		        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_cashenvoy_gateway">Settings</a>';
		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	}
}