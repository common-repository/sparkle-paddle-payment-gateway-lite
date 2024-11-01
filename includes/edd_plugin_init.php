<?php 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Exit if accessed directly.

if( !class_exists( 'Sparkle_Paddle_Payment_EDD_Init' ) ){

	class Sparkle_Paddle_Payment_EDD_Init{
		protected static $instance  = null;

		/**
		 * Class constructor
		 * @since 1.0.0
		*/
		public function __construct() {
			add_action( 'init', array( $this, 'check_supported_currency' ) );

			add_action( 'edd_sppg_inline_cc_form', array( $this, 'sparkle_sppg_cc_form' ) );

			add_filter( 'sparkle_edd_paddle_inline_label', array( $this, 'sparkle_edd_paddle_inline_label' ), 10, 3 );
			
			add_filter( 'edd_payment_gateways', array( $this, 'sparkle_edd_add_paddle_payment_checkbox' ) );
			add_filter( 'edd_accepted_payment_icons', array( $this, 'sparkle_edd_add_paddle_payment_icons' ) );
			add_filter( 'edd_settings_sections_gateways', array( $this, 'sparkle_edd_paddle_add_settings_section') );
			add_filter( 'edd_settings_gateways', array( $this, 'sparkle_edd_paddle_payment_add_settings' ) );
			add_action( 'edd_gateway_sppg_inline', array( $this, 'edd_paddle_process_payment_inline' ) );

			add_action( 'edd_payment_receipt_after_table', array( $this, 'edd_paddle_after_payment_processing_inline' ), 10, 2 );

			//ins payment notification
			add_action( 'init', array( $this, 'edd_listen_for_webhook_url' ) );
			add_action( 'init', array( $this, 'edd_listen_for_paddle_webhooks' ) );
			
            //notification for missing required fields for plugin to work properly.
            add_action( 'edd_purchase_form_before_submit', array( $this, 'sparkle_sppg_check_credentials' ) );
		}


		/**
		 * Check the required credentials and if any one is empty through error message
		 * @since 1.0.0 
		 * @return mixed
		 */
		function sparkle_sppg_check_credentials(){
			global $edd_options;
			
			if( edd_is_test_mode() ) {
				$vendor_id 	= sanitize_text_field( $edd_options['sparkle-paddle-payment-test-vendor-id'] );
				$auth_code 	= sanitize_text_field( $edd_options['sparkle-paddle-payment-test-auth-code'] );
				$public_key = sanitize_text_field( $edd_options['sparkle-paddle-payment-test-public-key'] );
            }else{
                $vendor_id 	= sanitize_text_field( $edd_options['sparkle-paddle-payment-vendor-id'] );
                $auth_code 	= sanitize_text_field( $edd_options['sparkle-paddle-payment-auth-code'] );
				$public_key = sanitize_text_field( $edd_options['sparkle-paddle-payment-public-key'] );
            }
			
			$payment_mode = isset( $_POST['edd_payment_mode'] ) ? sanitize_text_field( $_POST['edd_payment_mode'] ) : '';

			if( $payment_mode === 'sppg_inline' && ('' === $vendor_id || '' === $auth_code || '' === $public_key )  ){
				?>
				<div class="edd_errors edd-alert edd-alert-error"><p class="edd_error" id="edd_error_empty_merchant_code"><strong><?php esc_html_e( 'Error', 'sparkle-paddle-payment-gateway-lite' ); ?></strong>: <?php esc_html_e( 'Getway is not setup correctly. Please contact site administrator and notify about this error. The gateway will not work properly.', 'sparkle-paddle-payment-gateway-lite' ); ?> </p></div>
				<?php
			}
		}

		/**
		 * Check if currect currency is supported by paddle or not
		 * @since 1.0.0
		 * @return boolean
		 */
		function check_supported_currency(){
			$paddle_supported_currencies = array( 'ARS', 'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'INR', 'JPY', 'KRW', 'MXN', 'NOK', 'NZD', 'PLN', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD', 'ZAR' );

		    if ( in_array( strtoupper( edd_get_currency() ), $paddle_supported_currencies ) ) {
		        return true;
		    } else {
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
				_e( 'Paddle( Sparkle ) EDD: Unsupported currency! Your selected currency <code>' . strtoupper( edd_get_currency()) . '</code> is not supported yet by Paddle.', 'sparkle-paddle-payment-gateway-lite' ); ?>
			</p>
			</div>
			<?php		     			    
		}

		/**
		 * Returns paddle radio button label for inline checkout
		 * @since 1.0.0
		 * @return string
		 */
		function sparkle_edd_paddle_inline_label( $string ) {
			global $edd_options;
			$string  = ( isset( $edd_options['sparkle-paddle-payment-radio-button-label-inline'] ) && $edd_options['sparkle-paddle-payment-radio-button-label-inline'] !='') ? $edd_options['sparkle-paddle-payment-radio-button-label-inline'] : $string;
			return esc_html( $string );
		}

		/**
		 * Listen for paddle webhook callbacks
		 * @since 1.0.0
		 * @return [type] [description]
		*/
		function edd_listen_for_paddle_webhooks(){
			global $edd_options;

			if( edd_is_test_mode() ) {
				$public_key = isset( $edd_options['sparkle-paddle-payment-test-public-key'] ) ? sanitize_text_field( $edd_options['sparkle-paddle-payment-test-public-key'] ) : '';
			}else{
				$public_key = isset( $edd_options['sparkle-paddle-payment-public-key'] ) ?  sanitize_text_field( $edd_options['sparkle-paddle-payment-public-key'] ) : '';
			}

			if( isset( $_GET['sparkle_paddle_payment_ins_listener'] ) && $_GET['sparkle_paddle_payment_ins_listener'] == 'paddle' ){
				$alert_id 	= sanitize_text_field( $_POST['alert_id'] );
				$alert_name = sanitize_text_field( $_POST['alert_name'] );

				$payment_id = intval( $_POST['passthrough'] );
				$payment 	= edd_get_payment( $payment_id );

				if( $alert_name == 'payment_succeeded' || $alert_name == 'payment_refunded'  ){
					if ( !$payment ) {
		                $edd_log = esc_html__( sprintf(
		                    'Paddle(Sparkle): Webhook requested [%s]; EDD payment not found for #%s.',
		                    $alert_id,
		                    $payment_id
		                ), 'sparkle-paddle-payment-gateway-lite' );
		               
		               if( TRUE === edd_is_debug_mode() ){
		               		edd_debug_log( $edd_log, 1 );
						}

		               die();
		            }
				}
				
				switch( $_POST['alert_name'] ){
					//The payment_succeeded alert is fired when a payment is made into your Paddle account.
					case 'payment_succeeded':
						$payment_id = intval( $_POST['passthrough'] );
						
						edd_empty_cart();

						$new_status = 'publish';
						edd_update_payment_status( $payment_id, $new_status );

						$note = sprintf( esc_html__( 'Paddle(Sparkle): Status changed to complete using Paddle Webhook Alert INS URL as payment is successed.', 'sparkle-paddle-payment-gateway-lite' ) );
						edd_insert_payment_note( $payment_id, $note );	
					break;

					//The payment_refunded alert is fired when a payment is refunded.
					case 'payment_refunded':
						// Paddle transaction id.
                        $paddle_order_id = sanitize_text_field( $_POST['passthrough'] ?? null );

						if ( 'publish' != edd_get_payment_status( $payment_id ) ) {
                            
                            $payment->add_note( esc_html__( 'Got refund request, but payment havent completed yet.', 'sparkle-paddle-payment-gateway-lite' ) );

                            if( TRUE === edd_is_debug_mode() ){
								$log_message = esc_html__( sprintf(
	                                'Paddle(Sparkle): Webhook requested [%s]; Get refund request for payment #%s, but payment is not completed yet.',
	                                $alert_id,
	                                $payment_id
	                            ), 'sparkle-paddle-payment-gateway-lite' );
	                            
	                            edd_debug_log( $log_message );
							}  
							die();         
                        }


                        if ( $paddle_order_id != $payment->transaction_id ) {

	                        $payment->add_note( esc_html__( sprintf(
	                            'Get refund request, but transaction id %s did not match.',
	                            $paddle_order_id
	                        ), 'sparkle-paddle-payment-gateway-lite') );

	                        if( TRUE === edd_is_debug_mode() ){
								$log_message = esc_html__( sprintf(
		                            'Paddle(Sparkle): Webhook requested [%s]; Get refund request for payment %s, but transaction id %s do not match.',
		                            $alert_id,
		                            $payment_id,
		                            $paddle_order_id
		                        ), 'sparkle-paddle-payment-gateway-lite' );
		                        
		                        edd_debug_log($log_message);
							}
							die();
	                        
	                    }

	                    $refund_type = sanitize_text_field( $_POST['refund_type'] );
	                    $refund_reason = sanitize_text_field( $_POST['refund_reason'] )
	                    ;
	                    switch ( strtolower( $refund_type ) ) {
	                        case 'full':
	                            // Process full payment_refunded alert
	                            $payment->status = 'refunded';

	                            if ($payment->save()) {

	                                $payment->add_note( esc_html__(sprintf(
	                                    'Payment refunded (full) by paddle webhook for %s.',
	                                    $refund_reason
	                                ), 'sparkle-paddle-payment-gateway-lite') );

	                                if( TRUE === edd_is_debug_mode() ){
										$log_message = __( sprintf(
		                                    'Paddle(Sparkle): Webhook requested [%s]; Payment #%s refunded (full) for %s.',
		                                    $alert_id,
		                                    $payment_id,
		                                    $refund_reason
		                                ), 'sparkle-paddle-payment-gateway-lite' );
		                                
		                                edd_debug_log($log_message);
									}
	                                die();
	                            } else {
	                                $payment->add_note( esc_html__( sprintf(
	                                    'Payment can not refunded (full) by paddle webhook for %s.',
	                                    $refund_reason
	                                ), 'sparkle-paddle-payment-gateway-lite' ) );

	                                if( TRUE === edd_is_debug_mode() ){
										$log_message = esc_html__( sprintf(
		                                    'Paddle(Sparkle): Webhook requested [%s]; Payment #%s not refunded (full) for %s.',
		                                    $alert_id,
		                                    $payment_id,
		                                    $refund_reason
		                                ), 'sparkle-paddle-payment-gateway-lite');
		                                edd_debug_log( $log_message );
									}
	                                die();
	                            }
	                            break;

	                        case 'partial':
	                            // Process the partial payment_refunded alert
	                            $refund_amount = sanitize_text_field( $_POST['amount'] );

	                            if ( $payment->update_status( 'refunded' ) ) {

	                                $payment->add_note( esc_html__( sprintf(
	                                    'Payment refunded (partially) by paddle webhook for %s, amount %s.',
	                                    $refund_reason,
	                                    $refund_amount
	                                ), 'sparkle-paddle-payment-gateway-lite') );

	                                if( TRUE === edd_is_debug_mode() ){
										$log_message = esc_html__( sprintf(
		                                    'Paddle(Sparkle): Webhook requested [%s]; Payment #%s refunded (partially) for %s, amount %s',
		                                    $alert_id,
		                                    $payment_id,
		                                    $refund_reason,
		                                    $refund_amount
		                                ), 'sparkle-paddle-payment-gateway-lite');
		                                edd_debug_log( $log_message );
									}
	                                die();

	                            } else {

	                                $payment->add_note( esc_html__( sprintf(
	                                    'Payment can not refunded (partially) by paddle webhook for %s, amount %s.',
	                                    $refund_reason,
	                                    $refund_amount
	                                ), 'sparkle-paddle-payment-gateway-lite' ) );

	                                if( TRUE === edd_is_debug_mode() ){
										$log_message = esc_html__( sprintf(
		                                    'Paddle(Sparkle): Webhook requested [%s]; Payment #%s not refunded (partially) for %s, amount %s.',
		                                    $alert_id,
		                                    $payment_id,
		                                    $refund_reason,
		                                    $refund_amount
		                                ), 'sparkle-paddle-payment-gateway-lite' );

		                                edd_debug_log( $log_message );
									}
	                                die();
	                            }
	                            break;

	                        default:
	                        if( TRUE === edd_is_debug_mode() ){
	                            $log_message = __(sprintf(
	                                'Paddle(Sparkle): Webhook requested [%s]; Payment #%s requested to refund, but no action taken.',
	                                $alert_id,
	                                $payment_id
	                            ), 'sparkle-paddle-payment-gateway-lite' );
	                            
	                            edd_debug_log( $log_message );
							}

                            die();
                            break;
	                    }
					break;
					
					default:
						return;
					break;
				}
			}
		}

		/**
		* Working for webhook_url parameters
		* An endpoint that will call with transaction information upon successful checkout
		* @since 1.0.0
		*/ 
		function edd_listen_for_webhook_url(){
			if( isset( $_GET['sparkle_edd_paddle_api'] ) ){
	      		global $edd_options;

				$vendor_id = sanitize_text_field( $edd_options['sparkle-paddle-payment-vendor-id'] );
				$auth_code = sanitize_text_field( $edd_options['sparkle-paddle-payment-auth-code'] );

				$test_vendor_id = sanitize_text_field( $edd_options['sparkle-paddle-payment-test-vendor-id'] );
				$test_auth_code = sanitize_text_field( $edd_options['sparkle-paddle-payment-test-auth-code'] );
				
				if( edd_is_test_mode() ) {
					$vendor_id = sanitize_text_field( $test_vendor_id );
					$auth_code = sanitize_text_field( $test_auth_code );
					$public_key = sanitize_text_field( $edd_options['sparkle-paddle-payment-test-public-key'] );
				}else{
					$public_key = sanitize_text_field( $edd_options['sparkle-paddle-payment-public-key'] );
				}

			    $token      = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : sanitize_text_field( $_GET['token'] );
			    $payment_id = isset( $_POST['payment_id'] ) ? intval( $_POST['payment_id'] ) : intval( $_GET['payment_id'] );

			    if( $token != md5( trim( $vendor_id ) ) )
			      exit( "Error" );

			  	edd_empty_cart();

				$note = esc_html__( 'Paddle(Sparkle): Status changed to complete using webhook return URL.', 'sparkle-paddle-payment-gateway-lite' );
				edd_insert_payment_note( $payment_id, $note );
				
				$new_status = 'publish';
				edd_update_payment_status( $payment_id, $new_status );
			    exit();
			}
		}
		
		/**
		 * After payment processing paddle inline gateway
		 * @since 1.0.0
		 * @return inline checkout iframe 
		 */
		function edd_paddle_after_payment_processing_inline( $payment, $edd_receipt_args ){
			global $edd_options;
			if( $payment->post_status == 'publish' ){ return; }

			if( "Paddle(Sparkle) - Inline" == edd_get_gateway_admin_label( edd_get_payment_gateway( $payment->ID ) ) ){

			   if( edd_is_test_mode() ) {
				   $generate_pay_link_url = 'https://sandbox-vendors.paddle.com/api/2.0/product/generate_pay_link';
				   $vendor_id = sanitize_text_field( $edd_options['sparkle-paddle-payment-test-vendor-id'] );
				   $auth_code = sanitize_text_field( $edd_options['sparkle-paddle-payment-test-auth-code'] );
			   }else{
				   $generate_pay_link_url = 'https://vendors.paddle.com/api/2.0/product/generate_pay_link';
				   $vendor_id = sanitize_text_field( $edd_options['sparkle-paddle-payment-vendor-id'] );
				   $auth_code = sanitize_text_field( $edd_options['sparkle-paddle-payment-auth-code'] );
			   }

				
				$purchase_data = edd_get_purchase_session();
				
				foreach ( $purchase_data['cart_details'] as $item ) {
				   $product_name[] = sanitize_text_field( $item['name'] );
				   $product_id = intval( $item['id'] );
			   }

			   

			   $body = array(
				   'vendor_id' 		=> intval( $vendor_id ),
				   'vendor_auth_code' 	=> sanitize_text_field( $auth_code ),
				   'title' 			=> implode( ', ', $product_name ),
				   'quantity_variable' => 0,
				   'quantity'  		=> 1, 
				   'image_url' 		=> wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), array( '220','220' ),true )[0],
				   'prices' 			=> array( edd_get_currency().':'.$purchase_data['price'] ),
				   'customer_email' 	=> sanitize_email( $purchase_data['user_email'] ),
				   'return_url' 		=> get_permalink( $edd_options['success_page'] ),
				   'webhook_url' 		=> get_bloginfo('url') . '?' . build_query( array(
											   'sparkle_edd_paddle_api' => 'true',
											   'payment_id'            => intval( $payment->ID ),
											   'token'                 => md5( trim( $vendor_id ) )
										   ) ),
				   'passthrough' => intval( $payment->ID )
			   );

			   if( isset( $purchase_data['billing_country'] )) {
				   $body['customer_country'] = sanitize_text_field( $purchase_data['billing_country'] );
			   }
			   
			   $payment_id = null;
			   if( $payment and $payment->ID){
				   $payment_id = $payment->ID;
			   }

			   $params = array(
				   'method' => 'POST',
				   'timeout' => 30,
				   'httpversion' => '1.1',
				   'body' => $body,
			   );
			//    echo "<pre>";
			//    print_r($params);
			//    exit();
			   $request = wp_remote_post( $generate_pay_link_url, $params );
			   
			   if ( !is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
				   $generate_pay_link = json_decode( $request['body'] ); 
			   }
			   
			   edd_empty_cart();

			   $override_url = esc_url_raw( $generate_pay_link->response->url );
				?>
				<div class="sparkle-checkout-container"></div>
				<script type="text/javascript">
				   <?php if( edd_is_test_mode() ): ?>
					   Paddle.Environment.set('sandbox');
				   <?php endif; ?>
				   Paddle.Checkout.open({
					   method: 'inline',
					   override: '<?php echo esc_url_raw( $override_url ); ?>',
					   // displayModeTheme: 'light',
					   allowQuantity: false,
					   disableLogout: true,
					   frameTarget: 'sparkle-checkout-container', // The className of your checkout <div>
					   frameInitialHeight: 416,
					   frameStyle: 'width:100%; min-width:312px; background-color: transparent; border: none;'    // Please ensure the minimum width is kept at or above 286px with checkout padding disabled, or 312px with checkout padding enabled. See "General" section under "Branded Inline Checkout" below for more information on checkout padding.
				   });
			   </script>
				<?php
			}
	   	}

		/**
		 * After payment processing paddle inline gateway
		 * @since 1.0.0
		 * @return redirect to the success page.
		*/
		function edd_paddle_process_payment_inline( $purchase_data ){
			global $edd_options;

			if( edd_is_test_mode() ) {
				$vendor_id = sanitize_text_field( $edd_options['sparkle-paddle-payment-test-vendor-id'] );
				$auth_code = sanitize_text_field( $edd_options['sparkle-paddle-payment-test-auth-code'] );
			}else{
				$vendor_id = sanitize_text_field( $edd_options['sparkle-paddle-payment-vendor-id'] );
				$auth_code = sanitize_text_field( $edd_options['sparkle-paddle-payment-auth-code'] );
			}


			if(!$vendor_id ){
				esc_html_e( 'You need to enter your vendor id to make this plugin work.', 'sparkle-paddle-payment-gateway-lite' );
				die();
			}

			if(!$auth_code){
				esc_html_e( 'You need to enter your auth code to make this plugin work.<br />', 'sparkle-paddle-payment-gateway-lite' );
				die();
			}

			$payment_data = array(
				'price'         => sanitize_text_field( $purchase_data['price'] ),
				'date'          => sanitize_text_field( $purchase_data['date'] ),
				'user_email'    => sanitize_email( $purchase_data['user_email'] ),
				'purchase_key'  => sanitize_text_field( $purchase_data['purchase_key'] ),
				'currency'      => edd_get_currency(),
				'downloads'     => Sparkle_SPPG_Library:: sanitize_array( $purchase_data['downloads'] ),
				'cart_details'  => Sparkle_SPPG_Library:: sanitize_array( $purchase_data['cart_details'] ),
				'user_info'     => Sparkle_SPPG_Library:: sanitize_array( $purchase_data['user_info'] ),
				'status'        => 'pending',
			);

			//insert payment details to database and set status to pending
			// returns Payment ID if payment is inserted, false otherwise
			$payment = edd_insert_payment( $payment_data );
			
			wp_redirect( edd_get_success_page_uri() );
			exit;
		}

		/** 
		 * Class instance
		 * @since 1.0.0
		 * @return instance of a class
		 */
		public static function get_instance(){
			if( null === self:: $instance ){
				self:: $instance = new self;
			}

			return self:: $instance;
		}

		/**
		 * remove the Credit Card Info form as it will be entered in form.
		 * @since 1.0.0
		 * @return string
		*/
		function sparkle_sppg_cc_form(){
			// register the action to remove default CC form
			return;
		}
		
		/**
		 * Defaults for paddle gateways admin label and checkout label. Checkout label will be filter out using apply_filters.
		 * @param  [array] $gateways
		 * @since 1.0.0
		 * @return array
		 */
		function sparkle_edd_add_paddle_payment_checkbox( $gateways ){
			$gateways['sppg_inline'] 	= array(
												'admin_label'    => esc_html__( 'Paddle(Sparkle) - Inline', 'sparkle-paddle-payment-gateway-lite' ),
												'checkout_label' => apply_filters( 'sparkle_edd_paddle_inline_label', esc_html__( 'Paddle(sparkle) - Inline', 'sparkle-paddle-payment-gateway-lite' ) ),
											);
			$gateways['sppg_standard'] 	= array(
				'admin_label'    => esc_html__( 'Paddle - Standard(Available in Pro)', 'sparkle-paddle-payment-gateway-lite' ),
				'checkout_label' => apply_filters( 'sparkle_edd_paddle_standard_label', esc_html__( 'Paddle(sparkle) - Standard (Pro Only)', 'sparkle-paddle-payment-gateway-lite' ) ),
			);

			$gateways['sppg_overlay'] 	= array(
				'admin_label'    => esc_html__( 'Paddle - Overlay (Avaible In Pro)', 'sparkle-paddle-payment-gateway-lite' ),
				'checkout_label' => apply_filters( 'sparkle_edd_paddle_standard_label', esc_html__( 'Paddle(sparkle) - Standard (Pro Only)', 'sparkle-paddle-payment-gateway-lite' ) ),
			);
						
			return $gateways;
		}

		/**
		 * Addition of custom icons for our plugin's paddle payment gateways
		 * @param  [array] $icons 
		 * @since 1.0.0
		 * @return array
		 */
		function sparkle_edd_add_paddle_payment_icons( $icons ){
			$icons[ SPPG_IMG_DIR.'icon1.png' ] = esc_html__( 'Paddle - Icon 1', 'sparkle-paddle-payment-gateway-lite' ) ;
			$icons[ SPPG_IMG_DIR.'icon2.png' ] = esc_html__( 'Paddle - Icon 2', 'sparkle-paddle-payment-gateway-lite' ) ;
			$icons[ SPPG_IMG_DIR.'icon3.png' ] = esc_html__( 'Paddle - Icon 3', 'sparkle-paddle-payment-gateway-lite' ) ;
	    	return $icons;
		}
		
		/**
		 * Add plugin's Paddle section in payment gateways for configuration of plugin's settings
		 * @param  [array] $section
		 * @since 1.0.0
		 * @return array
		 */
		function sparkle_edd_paddle_add_settings_section( $sections ){
			$sections['sppg_edd_payment'] = esc_html__( 'Paddle(Sparkle)', 'sparkle-paddle-payment-gateway-lite' );
			return $sections;
		}
		
		/**
		 * Addion of the plugin's Paddle gateway settings fields.
		 * @param  [array] $settings
		 * @since 1.0.0
		 * @return array
		 */
		function sparkle_edd_paddle_payment_add_settings( $settings ){
			$sppg_payment_settings = array('sppg_edd_payment' => array(					
										array(
											'id' => 'sppg-payment-settings',
											'name' => '<strong>' . esc_html__( 'Paddle Settings', 'sparkle-paddle-payment-gateway-lite' ) . '</strong>',
											'desc' => esc_html__( 'Configure the Paddle Payment Gateway settings', 'sparkle-paddle-payment-gateway-lite' ),
											'type' => 'header',
										),
										array(
											'id' => 'sparkle-paddle-payment-vendor-id',
											'name' => esc_html__( 'Vendor ID', 'sparkle-paddle-payment-gateway-lite' ),
											'desc' => sprintf( esc_html__( 'Please enter your Vendor ID from %1$s Developers Tools > Authentication.%2$s', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://vendors.paddle.com/authentication' target='_blank' >", "</a>" ),
											'type' => 'text',
											'size' => 'regular',
										),
										array(
											'id' => 'sparkle-paddle-payment-auth-code',
											'name' => esc_html__( 'Auth Code', 'sparkle-paddle-payment-gateway-lite' ),
											'desc' => sprintf( esc_html__( 'Please enter your Auth Code from %1$s Developers Tools > Authentication - Generate Auth Code. %2$s', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://vendors.paddle.com/authentication' target='_blank' >", "</a>" ),
											'type' => 'text',
											'size' => 'regular',
										),
										array(
											'id' => 'sparkle-paddle-payment-public-key',
											'name' => esc_html__( 'Public Key', 'sparkle-paddle-payment-gateway-lite' ),
											'desc' => sprintf( esc_html__( 'Please enter your Public Key from %1$s Developer Tools > Public Key.%2$s', 'sparkle-paddle-payment-gateway-lite'), "<a href='https://vendors.paddle.com/public-key' target='_blank' >", "</a>" ),
											'type' => 'textarea',
											'size' => 'regular',
										),
										array(
											'id' => 'sparkle-paddle-payment-radio-button-label-inline',
											'name' => esc_html__( 'Paddle Radio Button Label For Inline', 'sparkle-paddle-payment-gateway-lite' ),
											'desc' => esc_html__( "Please enter the radio button label to be displayed in checkout page. If kept blank default label Paddle(sparkle) - Inline will be displayed in checkout page.", 'sparkle-paddle-payment-gateway-lite' ),
											'type' => 'text',
											'size' => 'regular',
										),
										array(
											'id'    => 'sparkle-paddle-webhook-description',
											'type'  => 'descriptive_text',
											'name'  => esc_html__( 'INS URL', 'sparkle-paddle-payment-gateway-lite' ),
											'desc'  =>
											'<p>' . sprintf(
												esc_html__( 'In order for Paddle to function completely, you must configure your Paddle INS settings. Visit %1$s Account Dashboard.%2$s to configure them. Please add a webhook endpoint for the URL below.', 'sparkle-paddle-payment-gateway-lite' ),
												'<a href="https://vendors.paddle.com/alerts-webhooks" target="_blank" rel="noopener noreferrer">',
												'</a>'
											) . '</p>' .
											'<p><strong>' . sprintf(
												esc_html__( 'Webhook URL: %s', 'sparkle-paddle-payment-gateway-lite' ),
												home_url( '?sparkle_paddle_payment_ins_listener=paddle' )
											) . '</strong></p>' .
											'<p>'
										),
										array(
											'id' => 'sppg-payment-test-settings',
											'name' => '<strong>' . esc_html__( 'Paddle Settings - Test Mode', 'sparkle-paddle-payment-gateway-lite' ) . '</strong>',
											'desc' => esc_html__( 'Configure the Paddle Payment Gateway settings for Test Mode', 'sparkle-paddle-payment-gateway-lite' ),
											'type' => 'header',
										),
										array(
											'id' => 'sparkle-paddle-payment-test-vendor-id',
											'name' => esc_html__( 'Vendor ID', 'sparkle-paddle-payment-gateway-lite' ),
											'desc' => sprintf( esc_html__( 'Please enter your Vendor ID from %1$s Developers Tools > Authentication.%2$s', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://sandbox-vendors.paddle.com/authentication' target='_blank' >", "</a>" ),
											'type' => 'text',
											'size' => 'regular',
										),
										array(
											'id' => 'sparkle-paddle-payment-test-auth-code',
											'name' => esc_html__( 'Auth Code', 'sparkle-paddle-payment-gateway-lite' ),
											'desc' => sprintf( esc_html__( 'Please enter your Auth Code from %1$s Developers Tools > Authentication - Generate Auth Code. %2$s', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://sandbox-vendors.paddle.com/authentication' target='_blank' >", "</a>" ),
											'type' => 'text',
											'size' => 'regular',
										),
										array(
											'id' => 'sparkle-paddle-payment-test-public-key',
											'name' => esc_html__( 'Public Key', 'sparkle-paddle-payment-gateway-lite' ),
											'desc' => sprintf( esc_html__( 'Please enter your Public Key from %1$s Developer Tools > Public Key.%2$s', 'sparkle-paddle-payment-gateway-lite'), "<a href='https://sandbox-vendors.paddle.com/public-key' target='_blank' >", "</a>" ),
											'type' => 'textarea',
											'size' => 'regular',
										),
									));

			return array_merge( $settings, $sppg_payment_settings );
		}
	}

	//Get the instance of a class
	Sparkle_Paddle_Payment_EDD_Init::get_instance();
}