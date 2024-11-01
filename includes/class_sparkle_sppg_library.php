<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Exit if accessed directly.
class Sparkle_SPPG_Library{
	/**
	 * Display the array in code format
	 * @since 1.0.0
	 * @return Pre formatted Array
	 */
	public static function print_array( $array ){
		echo "<pre>";
		print_r( $array );
		echo "</pre>";
	}

	/**
	 * Recursive sanitation for text or array
	 * 
	 * @param $array_or_string (array|string)
	 * @since  0.1
	 * @return mixed
	 */
	public static function sanitize_text_or_array_field( $array_or_string ) {
	    if( is_string( $array_or_string ) ){
	        $array_or_string = sanitize_text_field( $array_or_string );
	    }elseif( is_array( $array_or_string ) ){
	        foreach( $array_or_string as $key => &$value ) {
	            if ( is_array( $value ) ) {
	                $value = sanitize_text_or_array_field( $value );

	            }else{
	            	if( is_email( $value ) ){
 	                	$value = sanitize_email( $value );
	            	}else{
 	                	$value = sanitize_text_field( $value );
	            	}

	            }
	        }
	    }

	    return $array_or_string;
	}

	/**
	  * Sanitizes Multi Dimensional Array
	  * @param array $array
	  * @param array $sanitize_rule
	  * @return array
	  * @since 1.0.0
	  */
	public static function sanitize_array( $array = array(), $sanitize_rule = array() ){
		if ( ! is_array( $array ) || count( $array ) === 0 ) {
		 return array();
		}

		foreach ( $array as $k => $v ) {
		 if ( ! is_array( $v ) ) {

		     $default_sanitize_rule = (is_numeric( $k )) ? 'text' : 'html';
		     $sanitize_type = isset( $sanitize_rule[ $k ] ) ? $sanitize_rule[ $k ] : $default_sanitize_rule;
		     $array[ $k ] = self:: sanitize_value( $v, $sanitize_type );
		 }
		 if ( is_array( $v ) ) {
		     $array[ $k ] = self:: sanitize_array( $v, $sanitize_rule );
		 }
		}

		return $array;
	}

	/**
	* Sanitizes Value
	* @param type $value
	* @param type $sanitize_type
	* @return string
	* @since 1.0.0
	*/
	static function sanitize_value( $value = '', $sanitize_type = 'text' ){
		switch ( $sanitize_type ) {
		 case 'html':
		     $allowed_html = wp_kses_allowed_html( 'post' );
		     return wp_kses( $value, $allowed_html );
		     break;
		 default:
		     return sanitize_text_field( $value );
		     break;
		}
	}
}