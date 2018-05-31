<?php 

class MyCondo_Lights_Component extends Home_Component {

	var $hueHost;
	var $hueUsername;
	var $client;
	var $fadeTime;
	var $isConnected = false;
	var $isAuthenticated = false;
	var $light_rooms;

	const HEADSTART_MINS = 10;

	function __construct( $mood_id = false ) {
		set_time_limit(3);

		require_once AUTOLOAD_PHP;
		$this->hueHost = MyCondo_Admin::get_value('lights','hue_host', '192.168.0.40');
		$this->hueUsername = MyCondo_Admin::get_value('lights','hue_username', 'LdCOceWgAnNAEDw9tchCqbwrK-lLD4t4qdh3oyC6');
		$this->fadeTime = MyCondo_Admin::get_value('lights','fade_time', self::HEADSTART_MINS);
		$this->client = new \Phue\Client($this->hueHost, $this->hueUsername);
		
		try {
			$ping = new \Phue\Command\Ping;
			$ping->send($this->client);
		} catch (Exception $e) {
			add_action('admin_notices', array($this, 'no_access_notice'));
			return false;
		}
		$this->isConnected = true;

		try {
			$authorized = new \Phue\Command\IsAuthorized;
			$authorized->send($this->client);
		} catch (Exception $e) {
			add_action('admin_notices', array($this, 'not_authorized_notice'));
			return false;
		}
		$this->isAuthenticated = true;

		if (!empty($mood_id)) {
			$rooms = $this->getRooms();
			$this->light_rooms = array();
			foreach ($rooms as $name => $group_id) {
				$light_temp = MyCondo_Mood_Post_Type::getLightTemps( $mood_id, $group_id );
				$light_brightness = MyCondo_Mood_Post_Type::getLightBrightness( $mood_id, $group_id );
				$light_scene = MyCondo_Mood_Post_Type::getLightScenes( $mood_id, $group_id );

				if ( empty($light_temp) && empty($light_brightness) && empty($light_scene) )
					continue;

				$light_temp = !empty($light_temp) ? self::convert_percent_to_temp( $light_temp ) : $light_temp;
				$this->light_rooms[ $group_id ] = array(
					'temp' => $light_temp,
					'brightness' => $light_brightness,
					'scene' => $light_scene,
				);

			}
		}

	}

	public static function setup() {
		add_action( __CLASS__.'_hook', array(__CLASS__, 'set_mood_hook'), 10, 2 );
	}

	/**
	 * Range is 153 - 500. We store percent in settings, let's convert that back
	 * @param  int $percent 
	 * @return int          
	 */
	public static function convert_percent_to_temp( $percent ) {
		return floor((($percent / 100) * 347) + 153);
	}

	public static function set_mood_hook( $function_name = '', $mood_id = false ) {
		$comp = new self( $mood_id );
		$comp->{$function_name}();
	}

	function no_access_notice(){
		 echo '<div class="notice notice-warning">
		     <p>No access to Philips Hue light API, please check the Hue Host and Username.</p>
		 </div>';
	}

	function not_authorized_notice(){
		 echo '<div class="notice notice-warning">
		     <p>Honeywell API can be pinged but you are not authorized, please provide proper Hue Username.</p>
		 </div>';
	}

	function authorized_notice() {
		echo '<div class="notice notice-success is-dismissible">
		     <p>Successfully authorized Spotify.</p>
		 </div>';
	}


	function getRooms() {
		$key = "hue_room_list";
		if ( false !== ($rooms = get_transient( $key )) )
			return $rooms;

		if ( ! $this->isConnected )
			return array();

		$rooms = array();
		$rooms[ 'Condo' ] = 0;
		$groups = $this->client->getGroups();

		foreach ($groups as $group) {
			$rooms[ $group->getName() ] = $group->getId();
		}
		

		set_transient( $key, $rooms, DAY_IN_SECONDS * 30 );
		return $rooms;
	}

	function setEffect( $effect = 'colorloop', $group = 0 ) {
		$x = new \Phue\Command\SetGroupState( $group );
		$y = $x->effect('colorloop');
		return $this->client->sendCommand($y);
	}

	public function turnOff() {
		// Lights
		foreach ($this->light_rooms as $group_id => $attr) {
			if ( !empty($attr['scene']) ) {
				$this->setScene( $attr['scene'], $group_id );
			} else {
				$temp = !empty( $attr['temp'] ) ? $attr['temp'] : 250;
				$brightness = !empty( $attr['brightness'] ) ? floor ( ( $attr['brightness']  / 100) * 255 ) : 0;
				$this->_turnOff( $group_id );
			}	
		}
	}

	private function _turnOff( $group = 0 ) {
		$x = new \Phue\Command\SetGroupState( $group );
		$y = $x->on(false);
		return $this->client->sendCommand($y);
	}

	public function turnOn() {
		$result = false;
		
		// Lights
		foreach ($this->light_rooms as $group_id => $attr) {
			if ( !empty($attr['scene']) ) {
				$this->setScene( $attr['scene'],  $group_id );
			} else {
				$temp = !empty( $attr['temp'] ) ? $attr['temp'] : 250;
				if ( 0 === intval( $attr['brightness'] ) ) {
					$result = $this->_turnOff( $group_id );
				} else {
					$brightness = !empty( $attr['brightness'] ) ? floor ( ( $attr['brightness']  / 100) * 255 ) : 255;
					$result = $this->_turnOn( $group_id, $temp, $brightness );
				}
				
			}	
		}
		return $result;	
	}

	private function _turnOn( $group = 0, $temp = 250, $brightness = 255 ) {
		$x = new \Phue\Command\SetGroupState( $group );
		$y = $x->on(true)->colorTemp( intval($temp) )->brightness( $brightness );
		return $this->client->sendCommand($y);
	}

	function setScene( $sceneID = 'H9Oaoxcuswz0VJx', $group = 0 ) {
		$x = new \Phue\Command\SetGroupState( $group );
		$y = $x->scene( $sceneID );
		return $this->client->sendCommand($y);
	}

	function _fadeOut( $transition_time = false, $group = 0 ) {
		$transition_time = !empty($transition_time) ? $transition_time : self::get_headstart();
		$x = new \Phue\Command\SetGroupState( $group );
		$y = $x->transitionTime( $transition_time )->on(false);
		return $this->client->sendCommand($y);
	}

	public function fadeOut() {
		$headstart = MyCondo_Lights_Component::get_headstart();
		// Lights
		foreach ($this->light_rooms as $group_id => $attr) {
			$this->_fadeOut( $headstart, $group_id );
		}
	}

	public function fadeIn() {
		$headstart = MyCondo_Lights_Component::get_headstart();
		// Lights
		foreach ($this->light_rooms as $group_id => $attr) {
			$temp = !empty( $attr['temp'] ) ? $attr['temp'] : 250;
			if ( 0 === intval( $attr['brightness'] ) ) {
				$result = $this->_fadeOut( $headstart, $group_id );
			} else {
				$brightness = !empty( $attr['brightness'] ) ? floor ( ( $attr['brightness']  / 100) * 255 ) : 255;
				$this->_fadeIn( $headstart, $group_id, $temp, $brightness );
			}
		}
	}

	function _fadeIn( $transition_time = false, $group = 0, $temp = 250, $brightness = 255 ) {
		$transition_time = !empty($transition_time) ? $transition_time : self::get_headstart();
		$x = new \Phue\Command\SetGroupState( $group );
		$y = $x->transitionTime( $transition_time )->on(true)->colorTemp( intval($temp) )->brightness($brightness);
		return $this->client->sendCommand($y);
	}

	function setColor($temp = 250) {
		$x = new \Phue\Command\SetGroupState(0);
		$y = $x->colorTemp(intval($temp));
		return $this->client->sendCommand($y);
	}

	function getScenes() {
		if ( ! $this->isConnected )
			return array();

		$scenes = array();
		foreach ($this->client->getScenes() as $scene) {
			$scenes[ $scene->getId() ] = $scene->getName();
		}
		return $scenes;
	}

	function candleLights( $groups = 0 ) {
		set_time_limit(0);

		//echo 'Starting candle effect.', "\n";

		$groups = $client->getGroups(); 

		$group = $groups[2];

		while (true) {
		    // Randomly choose values
		    $brightness = rand(20, 60);
		    $colorTemp = rand(440, 450);
		    $transitionTime = rand(0, 3) / 10;
		    
		    // Send command
		    
		    //$x = new \Phue\Command\SetLightState(4);
		    $x = new \Phue\Command\SetGroupState($group);
		    $y = $x->brightness($brightness)
		        ->colorTemp($colorTemp)
		        ->transitionTime($transitionTime); 

		    $client->sendCommand($y);
		    
		    // Sleep for transition time plus extra for request time
		    usleep($transitionTime * 1000000 + 25000);
		}
	}

	public static function get_headstart() {
		return MyCondo_Admin::get_value('lights','fade_time', self::HEADSTART_MINS) * MINUTE_IN_SECONDS;
	}

}