<?php

class MyCondo_API_Handler {

	const KEY_SALT = "json_request_signature";
	const KEY_PREFIX = "api";
	const API_NAMESPACE = "condo-api";

	static $endpoints = array();
	static $options = array();

	function __construct() {

	}

	public static function setup($options = array()) {

		self::$options = $options;

		//if (!is_user_logged_in()) {
		//	remove_action( 'rest_api_init', 'create_initial_rest_routes', 0 );
		//}
		// Our caching
		// Caches the response from the called function
		add_filter( 'rest_pre_serve_request', array(__CLASS__, 'cache_json_api_response'), 11, 4 );
		// Serves up if a transient is stored
		add_filter( 'rest_pre_dispatch', array(__CLASS__, 'serve_json_api_cached_response'), 11, 3 );

		//remove_filter( 'json_endpoints', array( $wp_json_posts, 'register_routes' ), 0 );
		add_action( 'rest_api_init', array(__CLASS__, 'register_routes'), 20 );
	}

	/**
	 * Add the route to the list of endpoints
	 * @param  array $routes endpoints
	 * @return array endpoints
	 */
	public static function register_routes() {
		foreach (self::$options['endpoints'] as $path => $options) {
			//$options

			register_rest_route( self::API_NAMESPACE, $path, $options);
		}
	}

	// For our post type return custom post meta
	public static function custom_json_api_prepare_post( $post_response, $post, $context ) {   
	    $meta = get_post_meta( $post['ID'] );
	    $post_response['meta_field_name'] = $meta;
	    unset( $post_response['author'] );
	    return $post_response;
	}


	public static function cache_json_api_response($served, $result, $request, $server) {
		if ( is_a($result, 'WP_REST_Response') )
			return $served;

	    $cache_key = self::getCacheKey($request);
	    // If this request has not already been cached, then set a transient and continue    
	    if ( false === ( $json_response = get_transient( $cache_key ) ) && is_string( $result ) ) {
	        set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
	    }
	    // Unless we want to not serve the content always return the same response here
	    return $served;
	}

	/**
	 * Strictly just checks if the transient per the request signature (method, path, params). If it's found
	 * serves it up, if not, it returns null and follows through with the rest of the API request.	
	 * @param  [null] $result  empty as no result is produced yet
	 * @param  [type] $server  WP_REST_Server
	 * @param  [type] $request WP_REST_Request
	 * @return [json|null] 
	 */
	public static function serve_json_api_cached_response( $result = null, $server = null, $request = null ) {
		if (isset($_GET['nocache'])) {
			return null;
		}

	    // @Todo Any other edge control or etag headers?
	    $cache_key = self::getCacheKey( $request );

	    // We check if an existing transient exists
	    if ( false === ( $result = get_transient( $cache_key ) ) ) {
	        // We do nothing and continue on `cache_json_api_response` will catch the result after it is produced to set the transient
	        return null;
	    } 

	    self::send_cache_headers( $cache_key );

	    // We continue here if we found result in our cache	
	    // Let's send back the same headers, status and data   
	    if (isset($result->headers)) :
	        // Headers
	        foreach ($result->headers as $name => $value) {
	            header( "{$name}: {$value}" );
	        }
	    endif;
	    // Status code
	    if (isset($result->status)) :    
	        status_header( $result->status );
	    endif;

	    // Now the content if it's a WP_JSON_Response
	    if (isset($result->data)) :    
	        // Add a cache attribute to the data 
	        $result->data['cached'] = true;
	        return $result->data;
	    endif;

	    // Any other type of content
	    if (!empty($result) && (is_array($result))) :
	    	$result['cached'] = true;
	    	return $result;
	    endif;	

	    // Last scenario just return nothing so it can dispatch the call
	    return null;
	}

	/**
	 * Produces a unique signature based on the request. Should be under 40 characters for transient key.
	 * @param  [type] $request WP_REST_Request
	 * @return [string]
	 */
	public static function getCacheKey( $request = null ) {
	    return self::KEY_PREFIX . md5( sprintf('%s:%s:%s:%s', self::KEY_SALT, $request->get_route(), $request->get_method(), json_encode( $request->get_params() ) ) );
	}

	/**
	 * Send the cache headers. We use if-none-match to ensure we can optimize edge caching.
	 * @param  string $etag 
	 * @void
	 */
	public static function send_cache_headers($etag = '') {
		

		header('Cache-Control: max-age=300, must-revalidate');

		//header( 'Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
		//header( 'Edge-Control: no-store, no-cache, max-age=0, must-revalidate' );
		if ( $etag === @$_SERVER['HTTP_IF_NONE_MATCH'] ) {
			status_header(304);
			exit;
		}

		//status_header(200);
		header('ETag: ' . $etag);
	}

	/*
	old way of catching our endpoint, not being used anymore
	 */
	public static function request($template) {
		foreach (self::$endpoints as $endpoint => $function) {
			$endpoint = preg_quote( $endpoint, '#' );
			$regex = sprintf('#^/%s/?(\?|$)#', $endpoint);
			$match = preg_match($regex, $_SERVER['REQUEST_URI'] );
			if ($match) {
				header( 'Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
				header( 'Edge-Control: no-store, no-cache, max-age=0, must-revalidate' );


				$template_path = locate_template( array('partials/singular-alert.php', 'partials/singular.php'), $load = false, $require_once = false );
				$template_mtime = filemtime($template_path);
				$etag = md5(sprintf('%d:%s:%d', get_the_ID(), get_the_modified_time('c'), $template_mtime));
				if ( $etag === @$_SERVER['HTTP_IF_NONE_MATCH'] ) {
					status_header(304);
					exit;
				}

				// Send the response with the etag
				status_header(200);
				header('ETag: ' . $etag);
				wpized_load_partial('singular', 'alert');

				call_user_func( $function );
				exit;
			}
		}
	}
}
?>