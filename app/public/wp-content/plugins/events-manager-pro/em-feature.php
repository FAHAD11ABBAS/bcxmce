<?php
namespace EM;

/**
 * Feature detection plugin to see if something is enabled. Any add-on can register a feature so that checking for that feature is easy and standardized.
 *
 * For example, if we're registering QR codes, we do a check to see if it's enabled and when loading the feature we add:
 *
 * EM\Feature::register('qr');
 *
 * Now, anywhere we can check via EM\Feature::has('qr');
 *
 */
class Feature {
	
	public static function register ( $feature, $core = true ){
		// check it's not registered
		$filter_name = $core ? 'em_has_' . $feature : 'em_has_x_' . $feature;
		if ( !has_filter($filter_name) ) {
			add_filter( $filter_name, '__return_true' );
		}
	}
	
	public static function has ( $feature, $core = true ) {
		$filter_name = $core ? 'em_has_' . $feature : 'em_has_x_' . $feature;
		return apply_filters( $filter_name, false );
	}
	
}