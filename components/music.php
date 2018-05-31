<?php 

class MyCondo_Music_Component extends Home_Component {

	var $api;
	var $deviceId;
	var $session;
	var $fadeTime;
	var $mood_volume;
	var $mood_playlist;

	const REFRESH_TOKEN = 'spotify-refresh-token';
	const HEADSTART_SECONDS = 30;

	function __construct( $mood_id = false ) {
		$session = self::loadAPI();
		$accessToken = self::getToken($session);
		if (empty($accessToken)) {
			add_action('admin_notices', array($this, 'no_access_notice'));
			return;
		}

		if (!empty($_GET['spotify-authorized'])) {
			add_action('admin_notices', array($this, 'authorized_notice'));
		}

		if (!empty($mood_id)) {
			$this->mood_volume = MyCondo_Mood_Post_Type::getMusicVolume( $mood_id );
			$this->mood_playlist = MyCondo_Mood_Post_Type::getMusicPlaylist( $mood_id );
		}

		$this->api = new SpotifyWebAPI\SpotifyWebAPI();
		// Set our new access token on the API wrapper
		$this->api->setAccessToken($accessToken);
		$this->deviceId = MyCondo_Admin::get_value('music','spotify_dot_device_id');
		$this->fadeTime = MyCondo_Admin::get_value('music','fade_time', self::HEADSTART_SECONDS);
	}

	function no_access_notice(){
		 echo '<div class="notice notice-warning">
		     <p>You are not authorized for Spotify API, please click <a href="/wp-json/condo-api/spotify-authorize">here to authorize</a>.</p>
		 </div>';
	}

	function authorized_notice() {
		echo '<div class="notice notice-success is-dismissible">
		     <p>Successfully authorized Spotify.</p>
		 </div>';
	}

	public static function setup() {
		add_action( __CLASS__.'_hook', array(__CLASS__, 'set_mood_hook'), 10, 2 );
	}

	public static function set_mood_hook( $function_name = '', $mood_id = false ) {
		$comp = new self( $mood_id );
		$comp->{$function_name}();
	}

	public static function loadAPI() {
		require_once AUTOLOAD_PHP;
		$client_id = MyCondo_Admin::get_value('music','spotify_client_id');
		$client_secret = MyCondo_Admin::get_value('music','spotify_client_secret');
		$redirect_path = MyCondo_Admin::get_value('music','spotify_redirect_path');

		$redirect_uri = home_url(add_query_arg(array(),  $redirect_path));

		$session = new SpotifyWebAPI\Session(
		    $client_id,
		    $client_secret,
		    $redirect_uri
		);

		return $session;
	}

	public static function getToken( $session ) {
		$key = 'spotiy_access_token';
		$accessToken = get_transient( $key );
		if ( false !== $accessToken )
			return $accessToken;	

		// Lets get refresh token
		$refreshToken = MyCondo_Admin::get_value('music','spotify_refresh_token');
		if ( empty($refreshToken) ) {
			return false;
		}
		
		$session->refreshAccessToken($refreshToken);
		$accessToken = $session->getAccessToken();
		$expires_in = $session->getTokenExpiration() - time();
		set_transient( $key, $accessToken, $expires_in );
		return $accessToken;
	}

	public static function test_play_music() {
		$music = new self(); //MyCondo_Music_Component
		echo $music->play( 'spotify:user:craigilla:playlist:0G79Op19kpfU5VYBtnd3RH', 0, 100 );
		exit;
	}

	public static function get_headstart() {
		return MyCondo_Admin::get_value('music','fade_time', self::HEADSTART_SECONDS);
	}

	public static function redirect_uri() {
		$session = self::loadAPI();
		$api = new SpotifyWebAPI\SpotifyWebAPI();

		if (isset($_GET['code'])) {
		    $session->requestAccessToken($_GET['code']);
		    $api->setAccessToken($session->getAccessToken());
		    $refreshToken = $session->getRefreshToken();

		    $new_settings = array();
			$new_settings['music']['spotify_refresh_token'] = $refreshToken;
			$admin = new MyCondo_Admin();
			$admin->set_options($new_settings);
			$success_url = admin_url() . "?spotify-authorized=1";
			
			
			header('Location: ' . $success_url);
		    //print_r($api->me());
		    exit;

		} else {
		   self::request_access();
		}
	}

	public static function request_access() {
		$session = self::loadAPI();
		 $options = [
		        'scope' => [
		            'user-read-email',
		            'user-modify-playback-state',
		            'playlist-read-private',
		            'user-read-playback-state',
		        ],
		    ];

		    header('Location: ' . $session->getAuthorizeUrl($options));
		    die();
	}

	public function play( $playlistID = '', $volume = false, $position = 0  ) {
		if (empty($playlistID)) {
			return $this->api->play();
		}

	    $result = $this->api->play($this->deviceId, 
	    	array(
	    		'context_uri' => $playlistID,
	    		'offset' => array(
	    			'position' => $position
	    			),
	    		)
	    );

	    if (!empty($volume)) {
	    	$this->setVolume( $volume );	
	    }

	    return $result;
	}

	public function turnOn() {
		if (empty($this->mood_playlist)) {
			$this->pause();
			return false;
		}

		return $this->play( $this->mood_playlist, $this->mood_volume );
	}

	public function turnOff() {
		return $this->pause();
	}

	public function fadeIn() {
		if (empty($this->mood_playlist)) {
			$this->_fadeOut();
			return false;
		}
		
		return $this->_fadeIn( $this->mood_playlist, $this->mood_volume );
	}

	private function _fadeIn( $playlistID = '', $volume = 100, $position = 0, $transition_time = false ) {
		set_time_limit(0);
		$transition_time = empty($transition_time) ? self::get_headstart() : $transition_time;

		if (empty($playlistID)) {
			return $this->api->play();
		}
		$i = 0;
		$this->setVolume( $i );
		$result = $this->api->play($this->deviceId, 
	    	array(
	    		'context_uri' => $playlistID,
	    		'offset' => array(
	    			'position' => $position
	    			),
	    		)
	    );

	    $fade_seconds = $transition_time;
	    $sleeptime = floor( $fade_seconds / (100 / 5) );

		while ($i < $volume) {
			$i = $i + 5;
			$i = ($i >= 0) && ($i <= 100) ? $i : 100;
			//error_log("Pumping volume to $i");
			$this->setVolume( $i );
			sleep($sleeptime);
		}
	    return $result;
	}

	public function fadeOut() {
		return $this->_fadeOut();
	}

	private function _fadeOut( $volume = 0, $transition_time = false ) {
		set_time_limit(0);
		$transition_time = empty($transition_time) ? self::get_headstart() : $transition_time;

		$player_info = $this->getPlayerInfo();
		if ( ! $player_info->is_playing )
			return false;

		$volume_percent = $player_info->device->volume_percent;
		
		$fade_seconds = $transition_time;
	    $sleeptime = floor( $fade_seconds / (100 / 5) );
	    $i = !empty( $volume_percent ) ? $volume_percent : 80;
		while ($i >= $volume) {
			//error_log("Dropping volume to $i");
			$this->setVolume( $i );
			sleep($sleeptime);
			$i = $i - 5;
		}

		$this->pause();
		$this->setVolume( $volume_percent );
	    return true;
	}

	public function pause() {
		return $this->api->pause( $this->deviceId );
	}

	public function getPlaylists( $limit = 50, $offset = 0 ) {
		$key = 'spotify_playlists_transient';
		if ( false !== ($playlists = get_transient( $key )) )
			return $playlists;

		if (empty($this->api))
			return array();

		$result = $this->api->getMyPlaylists( 
			array(
			'limit' => $limit,
			'offset' => $offset,
			) 
		);
		set_transient( $key, $result, HOUR_IN_SECONDS * 6 );
		return $result;
	}

	public function setVolume( $percent = '100' ) {
		$result = $this->api->changeVolume(
	    	array(
	    		'device_id' => $this->deviceId,
	    		'volume_percent' => $percent,
	    		)
	    	);
	}

	public function getPlayerInfo() {
		return $this->api->getMyCurrentPlaybackInfo();
	}

}