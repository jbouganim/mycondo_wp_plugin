<?php

if ( class_exists('MyCondo') )
	return false;

class MyCondo {

	function __construct() {

	}

	function run() {
		add_action('init', array(__CLASS__, 'setup_admin_pages'), 0);
		add_action('init', array(__CLASS__, 'setup_post_types_and_taxonomies'));
		MyCondo_Synonym_Tax::setup();
		MyCondo_Mood_Post_Type::setup();
		MyCondo_Routine_Post_Type::setup();
	}

	static function setup_admin_pages() {
		// Load our admin settings page
		MyCondo_Admin::setup(
			MyCondo_Config::get_setting_schema()
		);
	}

	static function setup_post_types_and_taxonomies() {
			// Finally Setup our custom post type
			MyCondo_API_Handler::setup( array('endpoints' => MyCondo_Config::get_api_endpoints() ) );
			MyCondo_Thermostat_Component::setup();
			MyCondo_Music_Component::setup();
			MyCondo_Lights_Component::setup();
	}

}





