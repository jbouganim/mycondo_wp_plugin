<?php

//plugin version
define('MYCONDO_PLUGIN_VERSION','0.1');

//basename
define('MYCONDO_PLUGIN', plugin_basename( __FILE__ ) );

//Plugin Name
define('MYCONDO_PLUGIN_NAME', trim( dirname( MYCONDO_PLUGIN ), '/'));

//Plugin directory
define('MYCONDO_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . MYCONDO_PLUGIN_NAME );

//Libs directory
define('MYCONDO_LIB', MYCONDO_PLUGIN_DIR . '/libs' );

//AL directory
define('AUTOLOAD_PHP', MYCONDO_PLUGIN_DIR . '/vendor/autoload.php' );

// Includes directory
define('MYCONDO_INC', MYCONDO_PLUGIN_DIR . '/includes' );

// Comp directory
define('MYCONDO_COMPONENTS', MYCONDO_PLUGIN_DIR . '/components' );

// Templates directory
define('MYCONDO_TEMPLATES', MYCONDO_PLUGIN_DIR . '/templates' );

// Local Media Path
define('MYCONDO_LOCAL_ASSETS_PATH', MYCONDO_PLUGIN_DIR . '/assets');

//Plugin URL
define('MYCONDO_PLUGIN_URL', WP_PLUGIN_URL . '/' . MYCONDO_PLUGIN_DIR );

// Media Path
define('MYCONDO_ASSETS', plugins_url(MYCONDO_PLUGIN_NAME). '/assets');

// Libs URL
define('MYCONDO_LIB_URL', plugins_url(MYCONDO_PLUGIN_NAME). '/libs');

//basename
define('MYCONDO_LOCALE', "mycondo");

/**
 * Config class which stores our mappings and default settings.
 */
if (class_exists("MyCondo_Config"))
	return false;

class MyCondo_Config {

	/**
	 * Admin page setting schema. Here we can add settings to the admin settings page.
	 */
	public static function get_setting_schema() {
		global $wp_locale;

		$fields = array(
			array(
				'id'     => 'general',
				'title'  => __( 'General', MYCONDO_LOCALE ),
				'fields' => array(
					array(
						'id'      => 'home_external_ip',
						'type'    => 'text',
						'label'   => __( 'Home External IP', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'debug_mode',
						'type'    => 'radio',
						'label'   => __( 'Enable Debug Mode ', MYCONDO_LOCALE ),
						'description' => __( 'No authentication required', MYCONDO_LOCALE ),
						'choices' => array( "enabled" => "Enabled", "disabled" => "Disabled" ),
					),
					
				),
			),
			array(
				'id'     => 'ai',
				'title'  => __( 'API.AI Keys', MYCONDO_LOCALE ),
				'fields' => array(
					array(
						'id'      => 'developer_token',
						'type'    => 'text',
						'default' => '',
						'label'   => __( 'API.ai Developer Token', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'jauth_password',
						'type'    => 'text',
						'label'   => __( 'API.ai Webhook Password', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'mood_ai_eid',
						'type'    => 'text',
						'default' => '',
						'label'   => __( 'API.ai Mood EID', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'routine_ai_eid',
						'type'    => 'text',
						'default' => '',
						'label'   => __( 'API.ai Routine EID', MYCONDO_LOCALE ),
					),
				),
			),


			array(
				'id'     => 'twilio',
				'title'  => __( 'Twilio', MYCONDO_LOCALE ),
				'fields' => array(
					array(
						'id'      => 'sid',
						'type'    => 'text',
						'label'   => __( 'Twilio Account ID', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'token',
						'type'    => 'text',
						'label'   => __( 'Twilio Account Token', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'from-number',
						'type'    => 'text',
						'label'   => __( 'Twilio From Number', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'forward-number',
						'type'    => 'text',
						'label'   => __( 'Twilio Forward Number', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'whitelist',
						'type'    => 'text',
						'description' => __( 'Only accept calls from these numbers, comma-seperated', MYCONDO_LOCALE ),
						'label'   => __( 'Twilio Whitelist', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'dmtf-sound',
						'type'    => 'upload_file',
						'label'   => __( 'Buzzer DMTF Sound', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'autoopen_enabled',
						'type'    => 'radio',
						'label'   => __( 'Enable Auto-open ', MYCONDO_LOCALE ),
						'description' => __( 'Automatically buzz in anyone', MYCONDO_LOCALE ),
						'choices' => array( "enabled" => "Enabled", "disabled" => "Disabled" ),
					),
				),
			),


			array(
				'id'     => 'music',
				'title'  => __( 'Music', MYCONDO_LOCALE ),
				'fields' => array(
					array(
						'id'      => 'spotify_client_id',
						'type'    => 'text',
						'label'   => __( 'Spotify Client ID', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'spotify_client_secret',
						'type'    => 'text',
						'label'   => __( 'Spotify Client Secret', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'spotify_redirect_path',
						'type'    => 'text',
						'label'   => __( 'Spotify Redirect Path', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'spotify_refresh_token',
						'type'    => 'text',
						'label'   => __( 'Spotify Refresh Token', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'spotify_dot_device_id',
						'default' => 'bd888278f62657fad6cbec9a8828ff3b1e21eaf2',
						'type'    => 'text',
						'label'   => __( 'Spotify Default Device ID', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'fade_time',
						'default' => '30',
						'type'    => 'text',
						'description' => 'Time in seconds to fade in / out',
						'label'   => __( 'Fade Time', MYCONDO_LOCALE ),
					),
				),
			),

			array(
				'id'     => 'lights',
				'title'  => __( 'Lights', MYCONDO_LOCALE ),
				'fields' => array(
					array(
						'id'      => 'hue_host',
						'type'    => 'text',
						'label'   => __( 'Hue Host IP', MYCONDO_LOCALE ),
						'description' => 'Include external port if nessecary ip:port'
					),
					array(
						'id'      => 'hue_username',
						'type'    => 'text',
						'label'   => __( 'Hue Username', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'fade_time',
						'default' => '30',
						'type'    => 'text',
						'description' => 'Time in minutes to fade in / out',
						'label'   => __( 'Fade Time', MYCONDO_LOCALE ),
					),
				),
			),

			array(
				'id'     => 'weather',
				'title'  => __( 'Weather', MYCONDO_LOCALE ),
				'fields' => array(
					array(
						'id'      => 'hw_consumer_key',
						'type'    => 'text',
						'label'   => __( 'Consumer Key', MYCONDO_LOCALE ),
						'description' => 'Honeywell API key',
					),
					array(
						'id'      => 'hw_consumer_secret',
						'type'    => 'text',
						'label'   => __( 'Consumer Secret', MYCONDO_LOCALE ),
						'description' => 'Honeywell API secret',
					),
					array(
						'id'      => 'hw_refresh_token',
						'type'    => 'text',
						'label'   => __( 'Refersh Token', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'fade_time',
						'default' => '30',
						'type'    => 'text',
						'description' => 'Time in minutes to fade in / out',
						'label'   => __( 'Fade Time', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'honeywell_redirect_path',
						'type'    => 'text',
						'label'   => __( 'Honeywell Redirect Path', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'headstart',
						'default' => '15',
						'type'    => 'text',
						'description' => 'Time in minutes to pre-start',
						'label'   => __( 'Headstart Time', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'location_id',
						'default' => '305384',
						'type'    => 'text',
						'description' => 'Default Location ID',
						'label'   => __( 'Location ID', MYCONDO_LOCALE ),
					),
					array(
						'id'      => 'device_id',
						'default' => 'LCC-00D02DC3FD88',
						'type'    => 'text',
						'description' => 'Default Thermostat Device ID',
						'label'   => __( 'Device ID', MYCONDO_LOCALE ),
					),
				),
			),

		);
		return $fields;
	}

	public static function get_api_endpoints() {
		$endpoints =  array(
				// Sample Endpoints
				'/ai-handler' =>
					array(
						'callback' => array('MyCondo_API_Methods', 'ai_handler'),
						'methods'  => WP_REST_Server::CREATABLE,
						'args'     => array(),
					),
				'/alexa-handler' =>
					array(
						'callback' => array('MyCondo_API_Methods', 'alexa_handler'),
						'methods'  => WP_REST_Server::CREATABLE,
						'args'     => array(),
					),	
				'/buzzer' =>
					array(
						'callback' => array('MyCondo_Buzzer_Component', 'handle_buzzer'),
						'methods'  => WP_REST_Server::CREATABLE,
						'args'     => array(),
					),
				'/spotify-redirect' =>
					array(
						'callback' => array('MyCondo_Music_Component', 'redirect_uri'),
						'methods'  => WP_REST_Server::ALLMETHODS,
						'args'     => array(),
					),
				'/spotify-authorize' =>
					array(
						'callback' => array('MyCondo_Music_Component', 'request_access'),
						'methods'  => WP_REST_Server::READABLE,
						'args'     => array(),
					),
				'/honeywell-redirect' =>
					array(
						'callback' => array('MyCondo_Thermostat_Component', 'redirect_uri'),
						'methods'  => WP_REST_Server::READABLE,
						'args'     => array(),
					),			
				'/update-host' =>
					array(
						'callback' => array('MyCondo_API_Methods', 'update_host_ip'),
						'methods'  => WP_REST_Server::CREATABLE,
						'args'     => array(),
					),				
			);

		return $endpoints;
	}

	public static function get_moods() {
		$moods = array(
			'sexy' => array(
				'music' => array( 'playlist' => '', 'volume' => false ),
				'lights' => array( array( 'mood' => 'happy', 'rooms' => array(1,2,3) ) ),
				'temp' => array( 'temp' => 23, 'fan' => 'auto' ),
				'lock' => array( 'locked' => true ),
				'tv' => array( 'on' => true ),
			),

			'morning' => array(
				'music' => array( 'playlist' => '', 'volume' => false ),
				'lights' => array( array( 'mood' => 'happy', 'rooms' => array(1,2,3) ) ),
				'temp' => array( 'temp' => 23, 'fan' => 'auto' ),
				'lock' => array( 'locked' => true ),
				'tv' => array( 'on' => true ),
			),


			);
	}

	/**
	 * Test to see if a value is an associative array
	 * @param mixed $value
	 * @return bool
	 */
	static function is_assoc_array($value) {
		if (!is_array($value)) {
			return false;
		}
		$has_index_key = in_array(true, array_map('is_int', array_keys($value)));
		return !$has_index_key;
	}

	/**
	 * Merge two associative arrays recursively
	 * @return mixed
	 * @throws Exception
	 */
	static function recursive_array_merge_assoc(/*...*/){
		if (func_num_args() < 2) {
			throw new Exception('recursive_array_merge_assoc requires at least two args');
		}
		$arrays = func_get_args();
		if (in_array(false, array_map( self::callback_method('is_assoc_array'), $arrays ))) {
			throw new Exception('recursive_array_merge_assoc must be passed associative arrays (no numeric indexes)');
		}
		return array_reduce( $arrays, self::callback_method('_recursive_array_merge_assoc_two' ) );
	}

	/**
	 * Merge two associative arrays recursively
	 * @param array $a assoc array
	 * @param array $b assoc array
	 *
	 * @return array
	 * @todo Once PHP 5.3 is adopted, supply array() as $initial arg for array_reduce() and then change params $a and $b to array types
	 */
	static protected function _recursive_array_merge_assoc_two($a, $b) {
		if (is_null($a)) { // needed for array_reduce in PHP 5.2
			return $b;
		}

		$merged = array();
		$all_keys = array_merge(array_keys($a), array_keys($b));
		foreach ($all_keys as $key) {
			$value = null;

			// If key only exists in a (is not in b), then we pass it along
			if (!array_key_exists($key, $b)) {
				assert(array_key_exists($key, $a));
				$value = $a[$key];
			}
			// If key only exists in b (is not in a), then it is passed along
			else if (!array_key_exists($key, $a)) {
				assert(array_key_exists($key, $b));
				$value = $b[$key];
			}
			// ** At this point we know that they key is in both a and b **
			// If either is not an associative array, then we automatically chose b
			else if (!self::is_assoc_array($a[$key]) || !self::is_assoc_array($b[$key])) {
				// @todo if they are both arrays, should we array_merge?
				$value = $b[$key];
			}
			// Both a and b's value are associative arrays and need to be merged
			else {
				$value = self::recursive_array_merge_assoc($a[$key], $b[$key]);
			}

			// If the value is null, then that means the b array wants to delete
			// what is in a, so only merge if it is not null
			if (!is_null($value)) {
				$merged[$key] = $value;
			}
		}
		return $merged;
	}

	static function callback_method($method_name) {
		return array( __CLASS__, $method_name );
	}

}
?>
