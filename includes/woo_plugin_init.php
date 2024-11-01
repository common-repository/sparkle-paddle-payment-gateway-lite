<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Exit if accessed directly.

if( !class_exists( 'Sparkle_Paddle_Payment_WOO_Init' ) ){
	class Sparkle_Paddle_Payment_WOO_Init{
		protected static $instance  = null;

		/**
		 * Plugin initialize with requried actions
		 * @since 1.0.0
		 */
		public function __construct(){
			$this->check_supported_currency();
			
			add_filter( 'woocommerce_payment_gateways', array( $this, 'gateway_class' ) );
			add_action( 'plugins_loaded', array( $this, 'init_gateway_classes' ) );

			add_action( 'woocommerce_review_order_before_submit', array( $this, 'check_gateway_field_settings_configured_or_not' ) );
			
			// success return url payment notification
			add_action( 'init', array( $this, 'listen_for_paddle_webhook_url' ) );
			
			//webhook alert ins payment notification
			add_action( 'init', array( $this, 'listen_for_paddle_ins' ) );

			add_action( 'woocommerce_thankyou_sparkle_paddle_checkout_inline', array( $this, 'sparkle_inline_paddle_gateway_payment' ), 10, 1 );
		}

		/**
		 * Check if currect currency is supported by paddle or not
		 * @since 1.0.0
		 * @return boolean
		 */
		private function check_supported_currency(){
		    $paddle_supported_currencies = array( 'ARS', 'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'INR', 'JPY', 'KRW', 'MXN', 'NOK', 'NZD', 'PLN', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD', 'ZAR' );

		    if ( in_array( strtoupper( get_woocommerce_currency() ), $paddle_supported_currencies ) ) {
		        return true;
		    }else {
		        add_action( 'admin_notices', array( $this, 'currency_not_supported_admin_notice' ) );
		        return false;
		    }
		}

		/**
		 * Display the admin notice about the unsupported currency selection.
		 * @since 1.0.0
		 * @return mixed
		 */
		public function currency_not_supported_admin_notice(){
			?>
			<div class='error'><p><?php
				_e( 'Paddle(Sparkle) WooCommerce: Unsupported currency! Your selected currency <code>' . strtoupper( get_woocommerce_currency() ) . '</code> is not supported yet by Paddle.', 'sparkle-paddle-payment-gateway-lite' ); ?>
			</p>
			</div>
			<?php 			    
		}

		/**
		 * Addition of paddle inline payment iframe to order received page
		 * @param  [int] $order_id WooCommerce order id
		 * @since 1.0.0
		 * @return [mixed]  returns paddle inline payment iframe
		 */
		function sparkle_inline_paddle_gateway_payment( $order_id ){

			$order = wc_get_order( $order_id );

			$order_received_url = $order->get_checkout_order_received_url();
			
			$status = $order->get_status();

			if( 'completed' == $status || 'processing' == $status ) {
				WC()->cart->empty_cart();
				return;
			}
			?>
			<div class="woocommerce-notice woocommerce-notice--success" ><?php esc_html_e( 'Thank you for your order. Please complete the payment using below paddle form.', 'sparkle-paddle-payment-gateway-lite' ); ?></div>
			<?php
			$order_data 	= $order->get_data(); // The Order data
			$currency 		= sanitize_text_field( $order_data['currency'] );
			$email          = is_email( $order_data['billing']['email'] ) ? sanitize_email( $order_data['billing']['email'] ) : '';
			$payment_method = $order->get_payment_method();
			
			//This is required for inline payment only so there is no other payment gateways.
			if( 'sparkle_paddle_checkout_inline' === $payment_method ){
				$gateways = WC()->payment_gateways->payment_gateways()['sparkle_paddle_checkout_inline'];
			}
			
			$testmode 	= sanitize_text_field( $gateways->settings['testmode'] );

			$vendor_id 	= sanitize_text_field( $gateways->settings['vendor_id'] );
			$auth_code 	= sanitize_text_field( $gateways->settings['auth_code'] );

			$test_vendor_id = sanitize_text_field( $gateways->settings['test_vendor_id'] );
			$test_auth_code = sanitize_text_field( $gateways->settings['test_auth_code'] );

			$product_name = array();
			
			$product_price = 0;
			foreach ( $order->get_items() as $item_key => $item ):
				$item_data    		= $item->get_data();
				$product_name[] 	= sanitize_text_field( $item_data['name'] );
				$product_id 		= intval( $item_data['product_id'] );
				$product_price += ( $item_data['subtotal'] + $item_data['total_tax'] );

				$img = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'single-post-thumbnail' );
				if( !empty($img) ){
					$image_url = $img[0];
				}else{
					$image_url = '';
				}
			endforeach;

			/*
			* coupon code discount minus. Loop through order coupon items
			*/
			$discount = 0;
			$order_items = $order->get_items('coupon');
			$order_discount_amount = 0;
			foreach( $order_items as $coupon_item_id => $coupon_item ){
				$order_discount_amount 	= wc_get_order_item_meta( $coupon_item_id, 'discount_amount', true );
				
				if( $order_discount_amount != 0 ){
					$discount += $order_discount_amount;					
				}
			}

			$calculated_price = $product_price - $discount;

			if( 'yes' == $testmode ) {
				$generate_pay_link_url 	= 'https://sandbox-vendors.paddle.com/api/2.0/product/generate_pay_link';
				$vendor_id 				= sanitize_text_field( $test_vendor_id );
				$auth_code 				= sanitize_text_field( $test_auth_code );
				$public_key 			= sanitize_text_field( $gateways->settings['test_public_key'] );
			}else{
				$generate_pay_link_url 	= 'https://vendors.paddle.com/api/2.0/product/generate_pay_link';
				$public_key 			= sanitize_text_field( $gateways->settings['public_key'] );
			}

			$json_string = json_encode( array( 'platform'=>'woo', 'order_id' => intval( $order_id ) ) );

			$body = array(
						'vendor_id' 		=> intval( $vendor_id ),
						'vendor_auth_code' 	=> sanitize_text_field( $auth_code ),
						'title' 			=> implode( ', ', $product_name ),
						'image_url' 		=> wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), array( '220','220' ),true )[0],
						'prices' 			=> array( $currency.':'. $calculated_price ),
						'customer_email' 	=> sanitize_email( $email ),
						'return_url' 		=> esc_url_raw( $order_received_url ),
						'webhook_url' 		=> get_bloginfo( 'url' ) . '?' . build_query( array(
										          'sparkle_woo_paddle_api' => 'true',
										          'order_id'            => intval( $order_id ),
										          'token'                 => md5( trim( $vendor_id ) )
										      ) ),
						'passthrough' => $json_string,
					);

			$params = array(
						'method' => 'POST',
						'timeout' => 30,
						'httpversion' => '1.1',
						'body' => $body,
					 );
		
			$request = wp_remote_post( $generate_pay_link_url, $params );
			
			if ( !is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
				$generate_pay_link = json_decode( $request['body'] ); 
			}

			WC()->cart->empty_cart();

			$override_url = esc_url_raw( $generate_pay_link->response->url );
			?>
			<div class="sparkle-checkout-container"></div>
			<script type="text/javascript">
				<?php if( $testmode == 'yes'){
					echo 'Paddle.Environment.set("sandbox");';
				}
				?>
				Paddle.Checkout.open({
					method: 'inline',
					override: '<?php echo esc_url_raw( $override_url ); ?>',
					// displayModeTheme: 'dark',
					allowQuantity: false,
					disableLogout: true,
					frameTarget: 'sparkle-checkout-container', // The className of your checkout <div>
					frameInitialHeight: 416,
					frameStyle: 'width:100%; min-width:312px; background-color: transparent; border: none;'    // Please ensure the minimum width is kept at or above 286px with checkout padding disabled, or 312px with checkout padding enabled. See "General" section under "Branded Inline Checkout" below for more information on checkout padding.
				});
			</script>
		 	<?php
		}

		/**
		 * Checks the required fields are configured or not
		 * @since 1.0.0
		 */
		public static function check_gateway_field_settings_configured_or_not(){
			$gateways_inline    = WC()->payment_gateways->payment_gateways()[ 'sparkle_paddle_checkout_inline' ];
			
			if( 'yes' === $gateways_inline->settings['enabled'] ){
				$required = 'false';
				if( 'yes' == $gateways_inline->settings['testmode'] ){
					if( '' === $gateways_inline->settings['test_vendor_id'] || '' === $gateways_inline->settings['test_auth_code'] || '' === $gateways_inline->settings['test_public_key'] ){
						$required = 'true';
					}

				}else{
					if( '' === $gateways_inline->settings['vendor_id'] || '' === $gateways_inline->settings['auth_code'] || '' === $gateways_inline->settings['public_key']){
						$required = 'true';
					}
				}
				if( 'true' == $required ){ ?>
					<div class="woocommerce-error" role="alert">
						<strong><?php esc_html_e( 'Error', 'sparkle-paddle-payment-gateway-lite' ); ?></strong>: <?php esc_html_e( 'Sparkle Paddle Inline Getway is not setup correctly. Please contact site administrator and notify about this error. The gateway will not work properly.', 'sparkle-paddle-payment-gateway-lite' ); ?>
					</div>
					<?php
				}
				?>
				<?php
			}

		}

		/** 
		 * Class instance
		 * @return instance of a class
		 * @since 1.0.0
		 */
		public static function get_instance(){
			if( null === self:: $instance ){
				self:: $instance = new self;
			}

			return self:: $instance;
		}

		/**
		* This action hook registers our PHP class as a WooCommerce payment gateway
		* @since 1.0.0
		* @return Array
		*/
		function gateway_class( $gateways ) {
			$gateways[] = 'Sparkle_Paddle_Payment_WOO_Gateway_Inline';
			return $gateways;
		}

		/**
		* Include required files
		* @since 1.0.0
		*/
		function init_gateway_classes() {
			require_once ( SPPG_PATH . "/includes/class_sparkle_paddle_payment_woo_gateway_inline.php" );
		}

		/**
		 * For paddle Webhook URL Response 
		 * An endpoint that will call with transaction information upon successful checkout
		 * @since 1.0.0
		 * @return string
		 **/
		function listen_for_paddle_webhook_url(){
			
			if( isset( $_GET['sparkle_woo_paddle_api'] ) && 'true' === $_GET['sparkle_woo_paddle_api'] ){
	      		
	      		$order_id = absint( $_GET['order_id'] );

				$order = wc_get_order( $order_id );

				$status = $order->get_status();

				$payment_method = $order->get_payment_method();

				if( 'completed' == $status ) {
					WC()->cart->empty_cart();
					exit;
				}
				
				if( 'sparkle_paddle_checkout_inline' === $payment_method ){
					$gateways = WC()->payment_gateways->payment_gateways()['sparkle_paddle_checkout_inline'];
				}
				
				$testmode 	= sanitize_text_field( $gateways->settings['testmode'] );

				$vendor_id 	= sanitize_text_field( $gateways->settings['vendor_id'] );
				$auth_code 	= sanitize_text_field( $gateways->settings['auth_code'] );

				$test_vendor_id 	= sanitize_text_field( $gateways->settings['test_vendor_id'] );
				$test_auth_code 	= sanitize_text_field( $gateways->settings['test_auth_code'] );


				if( 'yes' == $testmode ) {
					$vendor_id 	= sanitize_text_field( $test_vendor_id );
					$auth_code 	= sanitize_text_field( $test_auth_code );
					$public_key = sanitize_text_field( $gateways->settings['test_public_key'] );
				}else{
					$public_key = sanitize_text_field( $gateways->settings['public_key'] );
				}

				$token      = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : sanitize_text_field( $_GET['token'] );
			    $payment_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : intval( $_GET['order_id'] );

			    if( $token != md5( trim( $vendor_id ) ) )
			      exit( "Error" );

			  	WC()->cart->empty_cart();

			  	$order->payment_complete(); 
					
				// Add the note
				$note = sprintf( esc_html__( 'Paddle(Sparkle): Status changed to complete using paddle webhook success return URL. order reference id: %d', 'sparkle-paddle-payment-gateway-lite' ), $order_id );
				$order->add_order_note( $note );           
				exit;
			}
		}

		/**
		 * For WOO INS Response 
		 * @since 1.0.0
		 * @return string
		 **/
		function listen_for_paddle_ins(){

			if( !isset( $_GET['sparkle_woo_paddle_payment_ins_listener'] ) ){
				return;
			}

			if ( isset( $_GET['sparkle_woo_paddle_payment_ins_listener'] ) && $_GET['sparkle_woo_paddle_payment_ins_listener'] == 'paddle' ) {
				$php_obj = json_decode ( stripslashes_deep( $_POST['passthrough'] ) );

				$order_id = $php_obj->order_id;

				$order = wc_get_order( $order_id );

				$status = $order->get_status();
				
				$payment_method = $order->get_payment_method();

				if( 'sparkle_paddle_checkout_inline' === $payment_method ){
					$gateways = WC()->payment_gateways->payment_gateways()['sparkle_paddle_checkout_inline'];
				}

				$testmode = sanitize_text_field( $gateways->settings['testmode'] );

				if( 'yes' == $testmode ) {
					$public_key = isset( $gateways->settings['test_public_key'] ) ? sanitize_text_field( $gateways->settings['test_public_key'] ) : '';
				}else{
					$public_key = isset( $gateways->settings['public_key'] ) ? sanitize_text_field( $gateways->settings['public_key'] ) : '';
				}

				$alert_id 	= sanitize_text_field( $_POST['alert_id'] );
				$alert_name = sanitize_text_field( $_POST['alert_name'] );

				if( $alert_name == 'payment_succeeded' || $alert_name == 'payment_refunded' ){
					if ( !$order ) { die(); }

		        	switch( $_POST['alert_name'] ){
						//The payment_succeeded alert is fired when a payment is made into your Paddle account.
						case 'payment_succeeded':
							WC()->cart->empty_cart();

						  	$order->payment_complete(); 
								
							// Add the note
							$note = sprintf( esc_html__( 'Paddle(Sparkle): Payment Complete. The status is set using Paddle Webhook Alert INS URL. ', 'sparkle-paddle-payment-gateway-lite' ), $order_id );
							$order->add_order_note( $note );
							die();
						break;

						//The payment_refunded alert is fired when a payment is refunded.
						case 'payment_refunded':
							// Paddle transaction id.
	                        $paddle_order_id = $order_id;

							if ( 'completed' != $status ) {
		                        $note = sprintf( esc_html__( 'Got refund request, but payment havent completed yet. ', 'sparkle-paddle-payment-gateway-lite' ) );
								$order->add_order_note( $note );
	                        }

	                        if ( $paddle_order_id != $order->ID ) {
		                        $note = esc_html__( sprintf(
				                            'Get refund request, but transaction id %s did not match.',
				                            $paddle_order_id
				                        ), 'sparkle-paddle-payment-gateway-lite' );
								
								$order->add_order_note( $note );
		                    }

		                    $refund_type = sanitize_text_field( $_POST['refund_type'] );
		                    $refund_reason = sanitize_text_field( $_POST['refund_reason'] );

		                    switch ( strtolower( $refund_type ) ) {
		                        case 'full':
		                            // Process full payment_refunded alert
                    				$new_status = 'refunded';
									$status = $order->update_status( $new_status );

		                            if ( $status ) {
		                                $note = esc_html__( sprintf(
		                                    'Payment refunded (full) by paddle webhook for %s.',
		                                    $refund_reason
		                                ), 'sparkle-paddle-payment-gateway-lite' );
		                                $order->add_order_note( $note );
		                                die();
		                            } else {
		                                $note = esc_html__( sprintf(
		                                    'Payment can not refunded (full) by paddle webhook for %s.',
		                                    $refund_reason
		                                ), 'sparkle-paddle-payment-gateway-lite' );
		                                $order->add_order_note( $note );
		                                die();
		                            }
		                            break;

		                        case 'partial':
		                            // Process the partial payment_refunded alert
		                            $refund_amount = sanitize_text_field( $_POST['amount'] );

		                            if ( $order->update_status( 'refunded' ) ) {

		                                $note = esc_html__( sprintf(
		                                    'Payment refunded (partially) by paddle webhook for %s, amount %s.',
		                                    $refund_reason,
		                                    $refund_amount
		                                ), 'sparkle-paddle-payment-gateway-lite' );
										$order->add_order_note( $note );
		                                die();

		                            } else {

		                                $note = esc_html__( sprintf(
		                                    'Payment can not refunded (partially) by paddle webhook for %s, amount %s.',
		                                    $refund_reason,
		                                    $refund_amount
		                                ), 'sparkle-paddle-payment-gateway-lite' );
										$order->add_order_note( $note );
		                                die();
		                            }
		                            break;

		                        default:
		                            $note = __( sprintf(
				                                'Paddle(Sparkle): Webhook requested [%s]; Payment #%s requested to refund, but no action taken.',
				                                $alert_id,
				                                $payment_id
				                            ), 'sparkle-paddle-payment-gateway-lite' );
									
									$order->add_order_note( $note );

		                            die();
		                            break;
		                    }
						break;

						default:
						break;
					}
				}
			}
		}
	}
}
//get the instance of a class
Sparkle_Paddle_Payment_WOO_Init::get_instance();