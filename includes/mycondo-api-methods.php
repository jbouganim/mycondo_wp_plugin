<?php

class MyCondo_API_Methods {

	const API_AI_ENDPOINT = "https://api.api.ai/v1/";
	const API_AI_VERSION = "20150910";

	public static function ai_handler() {
		$debug_mode = MyCondo_Admin::get_value_enabled('general','debug_mode');
		$jauth_password = MyCondo_Admin::get_value('ai','jauth_password');
		$request_headers = getallheaders();

		if ( ( $debug_mode ) || ( !empty( $request_headers['J-Auth'] ) && ($request_headers['J-Auth']  === $jauth_password) ) ) 
		{

		    $entityBody = file_get_contents('php://input');
		    $json = json_decode($entityBody, true);

		    $intent = !empty( $json['result']['action'] ) ? $json['result']['action'] : false;
		    $params = !empty( $json['result']['parameters'] ) ? $json['result']['parameters'] : false;
		    $contextParams = !empty( $json['result']['contexts'][0]['parameters'] ) ? $json['result']['contexts'][0]['parameters'] : false;
		    $incomplete = !empty( $json['result']['actionIncomplete'] ) ? (bool) $json['result']['actionIncomplete'] : false;

		    // If this is a chained command, the params won't be in params but contextParams, we just need one
		    $params = !empty($params) ? $params : $contextParams;

		    if ( $incomplete ) {
		    	exit;
		    	//self::send_ai_response( 'incomplete' );
		    }

		    if ($intent === 'buzzer.set') {
		    	$state = !empty( $params['state'] ) ? $params['state'] : false;
		    	$result = MyCondo_Buzzer_Component::set_buzzer( $state );
		    	if ($result === true) {
		    		self::send_ai_response("The buzzer was set to {$state}.");
		    	} else {
		    		self::send_ai_response("There was an issue setting the buzzer to {$state}.");
		    	}
		    }

		    if ($intent === 'mood.get') {
		    	$mood_name = $params['moods'];
		    	$moods = MyCondo_Mood_Post_Type::get_moods();
		    	if (!empty($moods)) {
		    		$mood_list = wp_list_pluck($moods, 'value');
		    		$mood_list = implode(", ", $mood_list);
		    		self::send_ai_response("Available moods are {$mood_list}.");
		    	} else {
		    		self::send_ai_response("There are no moods to list.");
		    	}
		    }

		    if ($intent === 'routine.get') {
		    	$routine_name = $params['routines'];
		    	$routines = MyCondo_Routine_Post_Type::get_routines();
		    	if (!empty($routines)) {
		    		$routine_list = wp_list_pluck($routines, 'value');
		    		$routine_list = implode(", ", $routine_list);
		    		self::send_ai_response("Available routines are {$routine_list}.");
		    	} else {
		    		self::send_ai_response("There are no routines to list.");
		    	}
		    }

		    if ($intent === 'mood.set') {
		    	$mood_name = $params['moods'];
		    	$result = MyCondo_Mood_Post_Type::set_mood_by_name( $mood_name );
		    	if ($result === true) {
		    		self::send_ai_response("The mood {$mood_name} was set.");
		    	} else {
		    		self::send_ai_response("There was an issue setting the mood {$mood_name}");
		    	}		    	
		    }

		    if ($intent === 'alarm.get') {
		    	$routine_name = $params['routines'];
		    	$routines = MyCondo_Routine_Post_Type::get_routines();
		    	$result = MyCondo_routine_Post_Type::set_routine_by_name( $routine_name );
		    	if ($result === true) {
		    		self::send_ai_response("The routine {$routine_name} was set.");
		    	} else {
		    		self::send_ai_response("There was an issue setting the routine {$routine_name}");
		    	}		    	
		    }

		    if ($intent === 'alarm.confirm') {
		    	$date = $params['date'];
		    	$time = $params['time'];
		    	$routine_name = $params['routines'];
		    	$recurrence = $params['recurrence'];

		    	$response = MyCondo_Routine_Post_Type::set_routine_by_name( $routine_name, $date, $time, $recurrence );
		    	if (!empty($response)) {
		    		self::send_ai_response( $response );
		    	} else {
		    		self::send_ai_response("There was an issue setting the routine {$routine_name}");
		    	}		    	
		    }

		    if ($intent === 'alarm.off') {
		    	$date = $params['date'];
		    	$routine_name = $params['routines'];

		    	$response = MyCondo_Routine_Post_Type::cancel_routine_by_name( $routine_name, $date );
		    	if (!empty($response)) {
		    		self::send_ai_response( $response );
		    	} else {
		    		self::send_ai_response("There was an issue cancelling the routine {$routine_name}");
		    	}		    	
		    }

		   else {
		    	$response = ('unauthorized');
		    	return new WP_REST_Response( $response, 404 );
		    }

		    

		} else {
		    http_response_code(401);
		    die('Unauthorized');
		}
	}

	public static function alexa_handler() {
		$debug_mode = MyCondo_Admin::get_value_enabled('general','debug_mode');
		$jauth_password = MyCondo_Admin::get_value('ai','jauth_password');
		$request_headers = getallheaders();

		$entityBody = file_get_contents('php://input');
	    $json = json_decode($entityBody, true);

	    $intent = !empty( $json['result']['action'] ) ? $json['result']['action'] : false;
	    $params = !empty( $json['result']['parameters'] ) ? $json['result']['parameters'] : false;
	    $contextParams = !empty( $json['result']['contexts'][0]['parameters'] ) ? $json['result']['contexts'][0]['parameters'] : false;
	    $incomplete = !empty( $json['result']['actionIncomplete'] ) ? (bool) $json['result']['actionIncomplete'] : false;

	}

	public static function is_user_logged_in() {
		return is_user_logged_in();
	}

	public static function send_ai_response( $text_response = '', $speech_response = '' ) {
		$speech_response = !empty( $speech_response ) ? $speech_response : $text_response;
		header('Content-Type: application/json');
		$response = array('speech' => $speech_response, 'displayText' => $text_response);
		echo json_encode($response);
		exit;
	}

	public static function send_alexa_response( $text_response = '', $speech_response = '' ) {
		status_header( 200 );
		header('application/json;charset=UTF-8');
		$speech_response = !empty( $speech_response ) ? $speech_response : $text_response;

		$response = array(
			"version" => "1.0",
			"response" => array(
			  "outputSpeech" => array(
			    "type" => "PlainText",
			    "text" => $text_response,
			   // "ssml" => "",
			  ),
			),    
		);		
		echo json_encode($response);
		exit;
	}

	public static function update_host_ip() {
		$debug_mode = MyCondo_Admin::get_value_enabled('general','debug_mode');
		$jauth_password = MyCondo_Admin::get_value('ai','jauth_password');
		$request_headers = getallheaders();

		if ( ( $debug_mode ) || ( !empty( $request_headers['J-Auth'] ) && ($request_headers['J-Auth']  === $jauth_password) ) ) 
		{	
			$ip = !empty($_POST['address']) ? trim($_POST['address']) : '';
			if (filter_var($ip, FILTER_VALIDATE_IP)) {
				$new_settings = array();
				$new_settings['general']['home_external_ip'] = $ip;
				$admin = new MyCondo_Admin();
				$admin->set_options($new_settings);
			    return new WP_REST_Response('updated', 200);
			} else {
			    return new WP_REST_Response('invalid or no ip passed', 500);
			}

			
		    return new WP_REST_Response('updated', 200);

		} else {
			return new WP_REST_Response('Unauthorized', 401);
		}
	}

	public static function get_ai_entities() {
		$dev_token = MyCondo_Admin::get_value('ai','developer_token');
		$url = self::parse_ai_endpoint( 'entities/574b02fe-deb1-4a1a-bf96-bd01b17ae376' );
		$response = wp_remote_post( $url, array(
			'method' => 'GET',
			'timeout' => 45,
			//'redirection' => 5,
			//'httpversion' => '1.0',
			//'blocking' => true,
			'headers' => array( 'Authorization' => "Bearer {$dev_token}" ),
			//'body' => array( 'username' => 'bob', 'password' => '1234xyz' ),
		    )
		);

		if ( is_wp_error( $response ) ) {
		   $error_message = $response->get_error_message();
		   echo "Something went wrong: $error_message";
		} else {
		   echo 'Response:<pre>';
		   var_dump( json_decode( $response['body'], true ) );
		  // print_r(  );
		   echo '</pre>';
		}
	}

	public static function get_ai_intents( $iid = '' ) {
		$dev_token = MyCondo_Admin::get_value( 'ai', 'developer_token' );
		$url = self::parse_ai_endpoint( 'intents/'.$iid );
		$response = wp_remote_post( $url, array(
			'method' => 'GET',
			'timeout' => 45,
			//'redirection' => 5,
			//'httpversion' => '1.0',
			//'blocking' => true,
			'headers' => array( 'Authorization' => "Bearer {$dev_token}" ),
			//'body' => array( 'username' => 'bob', 'password' => '1234xyz' ),
		    )
		);

		if ( is_wp_error( $response ) ) {
		   $error_message = $response->get_error_message();
		   echo "Something went wrong: $error_message";
		} else {
		   echo 'Response:<pre>';
		   var_dump( json_decode( $response['body'], true ) );
		  // print_r(  );
		   echo '</pre>';
		}
	}

	public static function update_ai_entities( $eid, $posts, $name = "moods" ) {
		$dev_token = MyCondo_Admin::get_value('ai','developer_token');
		$body = array(
			'id' => $eid,
			'name' => $name,
			'entries' => $posts,
		);

		$url = self::parse_ai_endpoint( 'entities/'.$eid );
		$response = wp_remote_post( $url, array(
			'method' => 'PUT',
			'timeout' => 45,
			'headers' => array( 'Authorization' => "Bearer {$dev_token}", 'Content-Type' => 'application/json; charset=utf-8' ),
			'body' => json_encode($body),
		    )
		);

		if ( is_wp_error( $response ) ) {
		   $error_message = $response->get_error_message();
		   error_log( "Something went wrong: $error_message" );
		   error_log(print_r($response, true));
		} else {
		   //error_log('Response:<pre>');
		  // error_log(print_r($response, true));
		}
	}

	public static function parse_ai_endpoint( $endpoint ) {
		return self::API_AI_ENDPOINT."{$endpoint}?v=".self::API_AI_VERSION;
	}

	public static function set_mood( $payload ) {
		$mood = !empty( $payload['result']['parameters']['moods'] ) ? $payload['result']['parameters']['moods'] : false;
		
		switch ($i) {
		    case 'sexy':
		        echo "i equals 0";
		        break;
		    case 1:
		        echo "i equals 1";
		        break;
		    case 2:
		        echo "i equals 2";
		        break;
		    default:
		       echo "i is not equal to 0, 1 or 2";
		}	


		if ( $mood !== false ) {
		    


		} else {
			http_response_code(201);
		    echo "Not done.";
		    exit;
		}	    
	}


	private static function sendResponse( $response, $header = false ) {
		if (!empty($header)) {
			header( $header );
		}

		echo $response;
		exit;
	}

}



if (!function_exists('getallheaders')) {
    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * @return string[string] The HTTP header key/value pairs.
     */
    function getallheaders()
    {
        $headers = array();
        $copy_server = array(
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        );
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $key = substr($key, 5);
                if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($copy_server[$key])) {
                $headers[$copy_server[$key]] = $value;
            }
        }
        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }
        return $headers;
    }
}