<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Exit if accessed directly.
class Sparkle_Paddle_Payment_WOO_Gateway_Inline extends WC_Payment_Gateway {
	/**
	 * Class constructor
	 * @since 1.0.0
	*/
	public function __construct() {
		$this->id 			= 'sparkle_paddle_checkout_inline'; // Paddle Inline payment gateway ID
		$this->icon 		= SPPG_IMG_DIR . 'icon1.png'; // URL of the icon that will be displayed on checkout page near your gateway name.
		$this->has_fields 	= true;
		$this->method_title = esc_html__( 'Sparkle Paddle Payment Gateway - Inline', 'sparkle-paddle-payment-gateway-lite' );
		$this->method_description = esc_html__( 'Add Paddle payment inline gateway to checkout.', 'sparkle-paddle-payment-gateway-lite' ); // will be displayed on the options page

		// gateways can support subscriptions, refunds, saved payment methods
		$this->supports = array( 'products' );

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->enabled 			= $this->get_option( 'enabled' );
		$this->title 			= $this->get_option( 'title' );
		$this->description 		= $this->get_option( 'description' );
		$this->testmode 		= 'yes' === $this->get_option( 'testmode' );
		$this->new_icon 		= $this->get_option( 'new_icon' );
		$this->vendor_id 		= $this->get_option( 'vendor_id' );
		$this->auth_code 		= $this->get_option( 'auth_code' );
		$this->public_key 		= $this->get_option( 'public_key');
		$this->test_vendor_id 	= $this->get_option( 'test_vendor_id' );
		$this->test_auth_code 	= $this->get_option( 'test_auth_code' );
		$this->test_public_key 	= $this->get_option( 'test_public_key');

		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
 	* Paddle Inline gateway settings fields
 	* @since 1.0.0
	*/
	public function init_form_fields(){
		$ipn_url = home_url( '?sparkle_woo_paddle_payment_ins_listener=paddle' );
		$ins_url = home_url( '?sparkle_woo_paddle_payment_ins_listener=paddle' );
		$this->form_fields = array(
			'enabled' => array(
				'title'       => esc_html__( 'Enable/Disable', 'sparkle-paddle-payment-gateway-lite' ),
				'label'       => esc_html__( 'Enable Paddle Inline Gateway', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Check this option to enable this payment gateway.', 'sparkle-paddle-payment-gateway-lite' ),
				'default'     => 'no'
			),
			'title' => array(
				'title'       => esc_html__( 'Title', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'text',
				'description' => esc_html__( 'This controls the title which the user sees during checkout.', 'sparkle-paddle-payment-gateway-lite' ),
				'default'     => esc_html__( 'Paddle - Inline', 'sparkle-paddle-payment-gateway-lite' ),
				'desc_tip'    => false,
			),
			'description' => array(
				'title'       => esc_html__( 'Description', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'This controls the description which the user sees during checkout.', 'sparkle-paddle-payment-gateway-lite' ),
				'default'     => esc_html__( 'Pay with your credit card via Paddle payment gateway.', 'sparkle-paddle-payment-gateway-lite' ),
			),
			'new_icon' => array(
				'title'       => esc_html__( 'Icon Selection', 'sparkle-paddle-payment-gateway-lite' ),
				'description' => esc_html__( 'Please Select the Paddle Icon to show in Checkout Page.', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'select',
				'default'     => 'icon1',
				'options' => array(
          					'icon1' => 'Icon 1',
          					'icon2' => 'Icon 2',
          					'icon3' => 'Icon 3',
          					)
			),
			'vendor_id' => array(
				'title'       => esc_html__( 'Vendor ID(Required)', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'Please enter your Vendor ID from %1$s  Developers Tools> Authentication.%2$s', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://vendors.paddle.com/authentication' target='_blank'>" , "</a>" ),
				'desc_tip'    => false,
			),
			'auth_code' => array(
				'title'       => esc_html__( 'Auth Code(Required)', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'Please enter your Auth Code from %1$s  Developers Tools > Authentication - Generate Auth Code. %2$s', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://vendors.paddle.com/authentication' target='_blank' >", "</a>" ),
				'desc_tip'    => false,

			),
			'public_key' => array(
				'title'       => esc_html__( 'Public Key(Required)', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'textarea',
				'description' => sprintf( esc_html__( 'Please enter your Public Key from %1$s  Developer Tools > Public Key %2$s. This is required for the singnature validation and IPN response validation.', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://vendors.paddle.com/public-key' target='_blank' >", "</a>" ),
				'desc_tip' => false,
			),
			'ins_url' => array(
				'title'       => esc_html__( 'INS URL', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'In order for Paddle to function completely, you must configure your Paddle INS settings. Visit %1$s Account Dashboard %2$s. to configure them. Please add a webhook endpoint from the above readonly URL.', 'sparkle-paddle-payment-gateway-lite' ),
																	'<a href="https://vendors.paddle.com/alerts-webhooks" target="_blank" rel="noopener noreferrer">',
																	'</a>'
																),
				'default'     => $ipn_url,
				'custom_attributes' => array( 'readonly' => 'readonly' ),
			),
			'testmode' => array(
				'title'       => esc_html__( 'Test mode', 'sparkle-paddle-payment-gateway-lite' ),
				'label'       => esc_html__( 'Enable Test Mode', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Check this option if you are doing test payments using this Paddle Payment Gateway.', 'sparkle-paddle-payment-gateway-lite' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_vendor_id' => array(
				'title'       => esc_html__( 'Test Vendor ID', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'Please enter your Test Vendor ID from %1$s  Developers Tools> Authentication.%2$s', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://sandbox-vendors.paddle.com/authentication' target='_blank'>" , "</a>" ),
				'desc_tip'    => false,
			),
			'test_auth_code' => array(
				'title'       => esc_html__( 'Test Auth Code', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'Please enter your Test Auth Code from %1$s  Developers Tools > Authentication - Generate Auth Code. %2$s', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://sandbox-vendors.paddle.com/authentication' target='_blank' >", "</a>" ),
				'desc_tip'    => false,

			),
			'test_public_key' => array(
				'title'       => esc_html__( 'Test Public Key(Required)', 'sparkle-paddle-payment-gateway-lite' ),
				'type'        => 'textarea',
				'description' => sprintf( esc_html__( 'Please enter your Public Key from %1$s  Developer Tools > Public Key %2$s. This is required for the singnature validation and IPN response validation.', 'sparkle-paddle-payment-gateway-lite' ), "<a href='https://sandbox-vendors.paddle.com/public-key' target='_blank' >", "</a>" ),
				'desc_tip' => false,
			),
		);
	}

	/**
	 * Get the icon name from settings field and filter out the icon as per selection to display on checkouot page
	 * @since 1.0.0
	 */
    public function get_icon() {
    	$icon_name = $this->new_icon;
        $icon_url = SPPG_IMG_DIR.$icon_name.'.png';

        $icon = "<img src='". esc_url( $icon_url )."' alt='". esc_html__( 'Paddle Inline Icon', 'sparkle-paddle-payment-gateway-lite' ). "' />";
        
        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
    }

	/**
	 * Display of gateway description in the checkout page.
	 * @since 1.0.0
	*/
	public function payment_fields() {
		echo "<p>".esc_html__( $this->description )."</p>";
	}

	/*
	* Processing of the payment and returing the redirect url to 2checkout for processing the payment
	* @since 1.0.0
	* @param $order_id Order ID
	*/
	public function process_payment( $order_id ) {
	
		$vendor_id 	= sanitize_text_field( $this->vendor_id );
		$auth_code 	= sanitize_text_field( $this->auth_code );

		$test_vendor_id = sanitize_text_field( $this->test_vendor_id );
		$test_auth_code = sanitize_text_field( $this->test_auth_code );		

		$order = wc_get_order( $order_id );
		$order_received_url = $order->get_checkout_order_received_url();
		
		// Mark as on-hold ( Awaiting for payment to complete )
		$order->update_status( 'on-hold', esc_html__( 'Paddle(Sparkle): Awaiting Paddle for payment confirmation.', 'sparkle-paddle-payment-gateway-lite' ) );
		
		// Remove cart
		WC()->cart->empty_cart();

		// Return success redirect
		return array(
			'result'    => 'success',
			'redirect'  => $order_received_url
		);
	}
}