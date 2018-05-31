<?php 

class MyCondo_Thermostat_Component extends Home_Component {

	var $api;
	var $deviceId;
	var $locationId;

	var $session;
	var $fadeTime;
	var $accessToken;
	var $hw_consumer_key;

	var $honeywellService;
	var $currentUri;

	var $mood_temp;
	var $mood_hold;
	var $mood_mode;

	static $periods = array( 'Wake', 'Home', 'Away', 'Sleep');
	static $modes = array( "Cool", "Heat", "Off" );
	static $holds = array( "NoHold", "PermanentHold", "TemporaryHold", "HoldUntil" );
	
	const HW_ACCESS_TOKEN_KEY = 'hw_access_token_key';
	const HEADSTART_MINS = 15;
	const THERMO_HOOK = "thermo_schedule_hook";

	function __construct( $mood_id = false ) {

		if (!empty($_GET['honeywell-authorized'])) {
			add_action('admin_notices', array($this, 'authorized_notice'));
		}

		require_once AUTOLOAD_PHP;

		$uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
		$this->currentUri = $uriFactory->createFromSuperGlobalArray($_SERVER);
		$this->currentUri->setQuery('');

		/** @var $serviceFactory \OAuth\ServiceFactory An OAuth service factory. */
		$serviceFactory = new \OAuth\ServiceFactory();
		$this->hw_consumer_key = MyCondo_Admin::get_value('weather','hw_consumer_key');
		// Session storage
		$storage = new \OAuth\Common\Storage\Session();
		// Setup the credentials for the requests
		$credentials = new \OAuth\Common\Consumer\Credentials(
		    $this->hw_consumer_key,
		    MyCondo_Admin::get_value('weather','hw_consumer_secret'),
		    $this->currentUri->getAbsoluteUri()
		);

		// Instantiate the Honeywell service using the credentials, http client and storage mechanism for the token
		/** @var $spotifyService Spotify */
		$this->honeywellService = $serviceFactory->createService('honeywell', $credentials, $storage);
		//$this->getToken();
		$this->accessToken = get_transient( self::HW_ACCESS_TOKEN_KEY );
		if ($this->accessToken === false) {
			$this->getToken();
			//add_action('admin_notices', array($this, 'no_access_notice'));
			//return;	
		}

		$this->deviceId = MyCondo_Admin::get_value('weather','device_id');
		$this->locationId = MyCondo_Admin::get_value('weather','location_id');
		$this->fadeTime = MyCondo_Admin::get_value('weather','fade_time', self::HEADSTART_MINS);

		if (!empty($mood_id)) {
			$this->mood_temp = MyCondo_Mood_Post_Type::getThermoTemp( $mood_id );
			$this->mood_mode = MyCondo_Mood_Post_Type::getThermoMode( $mood_id );
			$this->mood_hold = MyCondo_Mood_Post_Type::getThermoHold( $mood_id );
		}
		
	}

	public static function setup() {
		add_action( __CLASS__.'_hook', array(__CLASS__, 'set_mood_hook'), 10, 2 );
		//add_action(self::THERMO_HOOK, array( __CLASS__, 'set_thermostat') );
		//add_action( 'wp_mycondo_routine_scheduler_pre', array(__CLASS__, 'thermo_handler_scheduler'), 10, 3 );
	}

	public function no_access_notice(){
		 echo '<div class="notice notice-warning">
		     <p>You are not authorized for Honeywell API, please click <a href="/wp-json/condo-api/honeywell-redirect?honeywell-auth=go">here to authorize</a>.</p>
		 </div>';
	}

	public function authorized_notice() {
		echo '<div class="notice notice-success is-dismissible">
		     <p>Successfully authorized Honeywell API.</p>
		 </div>';
	}

	/**
	 * [set_thermo_temp description]
	 * {
   	 *	"mode": "Heat", // Heat, Cool, Off
     *	"heatSetpoint": 62,
   	 *	"coolSetpoint": 80,
     *	"thermostatSetpointStatus": "TemporaryHold" // NoHold, PermanentHold, TemporaryHold, HoldUntil
	 *	}
	 * @param [int|string] $new_temp in celcuius
	 */
	public function set_temp( $new_temp = false, $mode = "Cool", $hold = "TemporaryHold" ) {
		$mode = !empty($mode) ? $mode : "Cool";
		$hold = !empty($hold) ? $hold : "TemporaryHold";
		$query = array( 'apikey' => $this->hw_consumer_key, 'locationId' => $this->locationId );
		$uri = "devices/thermostats/{$this->deviceId}?".http_build_query($query);
		$body = array(
			"mode" => $mode,
			"heatSetpoint" => $new_temp,
			"coolSetpoint" => $new_temp,
			"thermostatSetpointStatus" => $hold,
		);
		$body = json_encode($body);
		$headers = array( 'Content-Type' => 'application/json' );
		$result = json_decode($this->honeywellService->request($uri, 'POST', $body, $headers, $this->accessToken), true);
		$code = $this->honeywellService->getResponseCode();
		return ($code === 200);
	}

	public static function set_mood_hook( $function_name = '', $mood_id = false ) {
		$comp = new self( $mood_id );
		$comp->{$function_name}();
	}

	public function turnOff() {
		return $this->set_temp( $this->mood_temp, $this->mood_mode, $this->mood_hold );
	}

	public function turnOn() {
		return $this->set_temp( $this->mood_temp, $this->mood_mode, $this->mood_hold );
	}

	public function fadeOut() {
		return $this->set_temp( $this->mood_temp, $this->mood_mode, $this->mood_hold );
	}

	public function fadeIn() {
		return $this->set_temp( $this->mood_temp, $this->mood_mode, $this->mood_hold );
	}

	public function set_thermo_schedule( $days, $time, $action, $mood ) {
		$query = array( 'apikey' => $this->hw_consumer_key, 'locationId' => $this->locationId, 'type' => 'regular' );
		$uri = "devices/schedule/{$this->deviceId}?".http_build_query($query);
		//var_dump($uri);
		$body = array(
			"deviceID" => $device_id,
			"scheduleType" => "Timed",
			"scheduleSubType" => "NA",
			"timedSchedule" => array(
			    "days" => array(
			        array(
			            "day" => "Monday",
			            "periods" => array(
			                array(
			                    "isCancelled" => false,
			                    "periodType" => "Wake",
			                    "startTime" => "06 =>00 =>00",
			                    "heatSetPoint" => 70,
			                    "coolSetPoint" => 78,
			                ),
			            ),
			        ),
			    ),
			),	    
		);


		$result = json_decode($this->honeywellService->request($uri, 'POST', $body), true);
	}

	public function getToken() {
		$refreshToken = MyCondo_Admin::get_value('weather','hw_refresh_token');
		$token = new OAuth\OAuth2\Token\StdOAuth2Token();
		$token->setRefreshToken( $refreshToken );
		$this->accessToken = $this->honeywellService->refreshAccessToken( $token );

		$expires_in = $this->accessToken->getEndOfLife() - time();
		set_transient( self::HW_ACCESS_TOKEN_KEY, $this->accessToken, intval( $expires_in ) );
		
	}

	public static function convertToCel($fahr)
    {
        $celsius=5/9*($fahr-32);
        return self::rndnum($celsius);
    }

    public static function convertToFahr($cel)
    {
        $fahrenheit=$cel*9/5+32;
        return ($fahrenheit);
    }

    public static function rndnum($num, $nearest = 0.5){
	    return ceil($num / $nearest) * $nearest;
	}

	public static function redirect_uri() {
		$api = new self();

		if (!empty($_GET['code'])) {
			//header('Content-type: text/html');
		    // This was a callback request from Honeywell, get the token
		    $api->honeywellService->requestAccessToken($_GET['code']);
		    // Send a request with it
		    $result = json_decode($api->honeywellService->request('token'), true);

		    if ( empty($result['access_token']) ) {
		    	throw new Exception("Could not fetch Honeywell access token");
		    }
		    $this->accessToken = $result['access_token'];
		    set_transient( self::HW_ACCESS_TOKEN_KEY, $this->accessToken, intval( $result['expires_in'] ) );

		    $new_settings = array();
			$new_settings['weather']['hw_refresh_token'] = $result['refresh_token'];
			$admin = new MyCondo_Admin();
			$admin->set_options($new_settings);
			$success_url = admin_url() . "?honeywell-authorized=1";
			header('Location: ' . $success_url);
		    exit;
		   
		} elseif (!empty($_GET['honeywell-auth']) && $_GET['honeywell-auth'] === 'go') {

		    $url = $api->honeywellService->getAuthorizationUri();
		    header('Location: ' . $url);
		    exit;
		} 
	}

	public function getSchedule() {
		$body = array( 'apikey' => $this->hw_consumer_key, 'locationId' => $this->locationId, 'type' => 'regular' );
		$uri = "devices/schedule/{$this->deviceId}?".http_build_query($body);
		//var_dump($uri);
		$result = json_decode($this->honeywellService->request($uri), true);
		return $result;
	}

	public function getLocations() {
		$body = array( 'apikey' => $this->hw_consumer_key );
		$uri = 'locations?'.http_build_query($body);
		$result = json_decode($this->honeywellService->request($uri), true);
		return $result;
	}

	public static function get_headstart() {
		return MyCondo_Admin::get_value('weather','fade_time', self::HEADSTART_MINS) * MINUTE_IN_SECONDS;
	}

}