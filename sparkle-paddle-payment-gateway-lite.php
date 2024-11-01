<?php
/**
 * Plugin Name: Sparkle Paddle Payment Gateway - Lite
 * Description: Simplify your digital payments using Paddle - Addon for Easy Digital Downloads and WooCommerce
 * Plugin URI: https://paddleintegration.com/
 * Author: Sparkle WP Themes
 * Author URI: https://sparklewpthemes.com
 * Requries at least: 4.0
 * Tested up to: 6.2
 * Version: 1.0.3
 * Text Domain: sparkle-paddle-payment-gateway-lite
 * Domain Path: languages
 * Network: false
 *
 * @package Sparkle Paddle Payment Gateway
 * @author sparklewpthemes
 * @category Core
 */

/*
* Copyright (C) 2021  SparkleWPThemes
*/

defined( 'ABSPATH' ) or die( 'No Scrpit Kiddies Please!' );

if( !class_exists( 'Sparkle_Paddle_Payment_Gateway_Lite' ) ){
    class Sparkle_Paddle_Payment_Gateway_Lite{
        
        protected static $instance  = null;
        public $name                = "Sparkle Paddle Payment Gateway Lite";
        public $version             = '1.0.3';

        /**
         * Plugin initialize with requried actions
         */
        public function __construct(){
            $this->define_plugin_constants();
            $this->load_plugin_textdomain();
            $this->enqueue_scripts();
            $this->includes();
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
         * Check Plugin Dependencies and show admin notice
         * Initialize Plugin Class
         * @return notice or instance of class
         * @since 1.0.0
         */
        public static function check_plugin_dependency(){
            //Firstly, check if a dependency plugin - Easy Digital Downloads or WooCommerce is active or not.
            $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
            if ( in_array( 'easy-digital-downloads/easy-digital-downloads.php', $active_plugins ) || in_array( 'woocommerce/woocommerce.php', $active_plugins ) ){
                return Sparkle_Paddle_Payment_Gateway_Lite::get_instance();
            }else{
                add_action( 'admin_notices', array( 'Sparkle_Paddle_Payment_Gateway_Lite', 'install_plugin_admin_notice' ) );
		        return;
            }
        }        

        /**
         * Admin Notice
         * @return string
         * @since 1.0.0
         */
        public static function install_plugin_admin_notice() {
            ?>
            <div class="error">
                <p><?php esc_html_e( 'Sparkle Paddle Payment Gateway is enabled but not effective. It requires WooCommerce or Easy Digital Download in order to work.', 'sparkle-paddle-payment-gateway-lite' ); ?></p>
            </div>
            <?php
        }

        /**
        * Define plugins contants
        * @since 1.0.0
        */
        private function define_plugin_constants(){
            defined( 'SPPG_PATH' ) or define( 'SPPG_PATH', plugin_dir_path( __FILE__ ) );
            defined( 'SPPG_DIR_PATH' ) or define( 'SPPG_DIR_PATH', plugin_dir_url( __FILE__ ) );
            defined( 'SPPG_IMG_DIR' ) or define( 'SPPG_IMG_DIR', plugin_dir_url( __FILE__ ) . 'assets/images/' );
            defined( 'SPPG_JS_DIR' ) or define( 'SPPG_JS_DIR', plugin_dir_url( __FILE__ ) . 'assets/js' );
            defined( 'SPPG_CSS_DIR' ) or define( 'SPPG_CSS_DIR', plugin_dir_url( __FILE__ ) . 'assets/css' );
        }

        /**
         * Loads plugin text domain
         * @since 1.0.0
         */
        private function load_plugin_textdomain(){
            load_plugin_textdomain( 'sparkle-paddle-payment-gateway-lite', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' ); 
        }

        /**
         * Includes the plugins required scripts - JS and CSS files
         * @since 1.0.0
         */
        private function enqueue_scripts(){
            add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
        }

        /**
         * Register plugin's stylesheets and jquery scripts
         * @since 1.0.0
         */
        public function register_frontend_assets(){
            //required for both EDD and WooCommerce
            wp_register_script( 'sparkle-paddle', 'https://cdn.paddle.com/paddle/paddle.js', null,  null, false );
            wp_enqueue_script('sparkle-paddle');
        }

        /**
         * Plugin's required files
         * @since 1.0.0
         */
        private function includes(){
            
            require_once( SPPG_PATH . "includes/class_sparkle_sppg_library.php" );
            $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
            
            if( in_array( 'easy-digital-downloads/easy-digital-downloads.php', $active_plugins ) ){
                require_once ( SPPG_PATH . "includes/edd_plugin_init.php" );
            }

            if( in_array( 'woocommerce/woocommerce.php', $active_plugins ) ){
                require_once( SPPG_PATH . "includes/woo_plugin_init.php" );
            }   
        }

    } // End of the class - Sparkle_Paddle_Payment_Gateway_Lite
}
add_action( 'plugins_loaded', array ( 'Sparkle_Paddle_Payment_Gateway_Lite', 'check_plugin_dependency' ), 0 );