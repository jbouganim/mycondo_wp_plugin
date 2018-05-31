<?php 

class MyCondo_Buzzer_Component {

	const BUZZER_OPTION_KEY = 'buzzer_state';

	function __construct() {

	}

	public static function set_buzzer( $state = false ) {
		if ( $state !== false ) {
		    $bool_state = (bool) ($state === 'on');
			$settings = get_option( MyCondo_Admin::SLUG );
			$value = $bool_state ? 'enabled' : 'disabled';
			$settings['twilio']['autoopen_enabled'] = array( $value );
			$admin = new MyCondo_Admin();
			$admin->set_options($settings);
			return true;
		}	
		
		return false;	    
	}


	public static function handle_buzzer() {

		$debug_mode = MyCondo_Admin::get_value_enabled('general','debug_mode');
		$forward_number = MyCondo_Admin::get_value('twilio','forward-number');
		$whitelist_value = MyCondo_Admin::get_value('twilio','whitelist');
		$whitelist = explode(",", $whitelist_value);

		if ( (! empty($_REQUEST['From']) && in_array($_REQUEST['From'], $whitelist)) || $debug_mode) :
			$state = MyCondo_Admin::get_value_enabled('twilio','autoopen_enabled');
			if ($state) {
				self::sendText($forward_number, "I opened the lobby door at 20 John for a visitor. -My Condo :)");
				self::sendDMTF();
			} else {
				self::forwardCall( $forward_number );
			}	
		// If it's not the buzzer than foward the call to me
		elseif ( !empty($_REQUEST['From']) ) :
			self::forwardCall( $forward_number );
		else :
			http_response_code(401);
		    die('Unauthorized');
		endif;
		
	}

	private static function forwardCall($number) {
		header("content-type: text/xml");
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<Response>';
		echo '<Dial>';
		echo "<Number>{$number}</Number>";
		echo '</Dial>';
		echo '</Response>';
		exit;
	}

	private static function sendDMTF() {
		$dmtf_sound = MyCondo_Admin::get_value('twilio','dmtf-sound');
		
		//Just open the door
		header("content-type: text/xml");
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<Response>';
		echo '<Pause length="1"/>';
		echo '<Play>'.$dmtf_sound.'</Play>';
		echo '<Pause length="2"/>';
		echo '<Play>'.$dmtf_sound.'</Play>';
		// if we want to manually send the digits, playing an mp3 with the DMTF proves to work more consistently
		//echo '<Play digits="www66666666666w6wwwwwww66www6"></Play>';
		echo '</Response>';
		exit;
	}

	private static function sendText($number, $message = '') {
		require_once AUTOLOAD_PHP;
		$account_sid = MyCondo_Admin::get_value('twilio','sid');
		$auth_token = MyCondo_Admin::get_value('twilio','token');
		$from_number = MyCondo_Admin::get_value('twilio','from-number');

		$client = new Twilio\Rest\Client($account_sid, $auth_token);
		$message = $client->messages->create(
		  $number, // Text this number
		  array(
		    'from' => $from_number, // From a valid Twilio number
		    'body' => $message
		  )
		);

		return $message->sid;
	}

}