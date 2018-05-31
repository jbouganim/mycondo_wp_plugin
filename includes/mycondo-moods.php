<?php

if ( class_exists('MyCondo_Mood_Post_Type') )
	return false;

class MyCondo_Mood_Post_Type {

	static $instance = null;
	static $options = array();
	static $taxonomies = array();
	static $endpoints = array();
	static $prefix = 'mycondo-';

	const SLUG      = 'mycondo_mood';
	const META_KEY  = 'mycondo_mood_post_meta';
	const DEFAULT_REWRITE_SLUG = 'mood';
	const TAGS = "mood_synonyms";

	const LIGHT_SCENE = 'light_scene_';
	const LIGHT_TEMP = 'light_temp_';

	/**
	 * Our post type labels and settings
	 * @var array
	 */
	static $post_type_args = array(
			'label'               => self::SLUG,
			'labels'              => array(
				'name'                => 'Moods',
				'singular_name'       => 'Mood',
				'menu_name'           => 'Moods',
				'name_admin_bar'      => 'Mood',
				'parent_item_colon'   => 'Parent Mood:',
				'all_items'           => 'Moods',
				'add_new_item'        => 'Add New Mood',
				'add_new'             => 'Add New',
				'new_item'            => 'New Mood',
				'edit_item'           => 'Edit Mood',
				'update_item'         => 'Update Mood',
				'view_item'           => 'View Mood',
				'search_items'        => 'Search Mood',
				'not_found'           => 'Not found',
				'not_found_in_trash'  => 'Not found in Trash',
			),
			'description'         => 'Mood Post Type',
			'hierarchical'        => false,
			'supports' => array(
				'title',
				'slug',
				'tags',
				//'editor', // Just consists of an [embed] shortcode
				//'author',
				//'thumbnail', // Automatically set via oembed-thumbnail-as-featured-image and brightcove oembed
				//'excerpt',
				//'post-formats',
				//'custom-fields',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 2,
			'menu_icon'           => 'dashicons-lightbulb',
			//'show_in_admin_bar'   => true,
			//'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite' => self::DEFAULT_REWRITE_SLUG,
			'taxonomies' => array(),
			'capability_type'     => 'post'
	);

	/**
	 * Set up our post type and meta box
	 * @param  array  $post_meta_schema Schema for the fields we need for post meta
	 * @void
	 */
	static function setup( $taxonomies = array() )
	{
		global $post;
		self::$taxonomies = $taxonomies;
		$has_rewrite_tag = false;
		$has_rewrite_tag_first = false;

		add_action( 'init', array(__CLASS__, 'register_post_type'), 11 );
		add_filter( 'rwmb_meta_boxes', array(__CLASS__, 'mycondo_mood_metabox') );
		add_action( 'save_post', array( __CLASS__, 'update_ai_entities'), 10, 2 );
		add_action( 'admin_init', array(__CLASS__, 'handle_test_request'), 10 );

		//add_filter( sprintf( 'manage_%s_posts_columns', self::SLUG ), array( __CLASS__, 'on_manage_edit_columns' ), 10, 1 );
	}

	/**
	 * Register our post type
	 * @void
	 */
	static function register_post_type() {
		self::$post_type_args['taxonomies'] = self::$taxonomies;

		register_post_type( self::SLUG, self::$post_type_args );
	}

	static function handle_test_request() {
		$test_modes = array('on', 'off', 'fadein','fadeout');
		$button_name = 'test_button_id';
		if ( !empty($_REQUEST['post_ID']) && !empty($_REQUEST[ $button_name ]) && in_array($_REQUEST[ $button_name ], $test_modes) ) {
			self::set_mood( $_REQUEST['post_ID'], $_REQUEST[ $button_name ], time() );
		}
	}

	static function get_moods() {
		$args = array(
			'post_type' => self::SLUG,
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'DESC',
		);

		$moods = get_posts($args);
		$entities = array();
		foreach ($moods as $mood) {
			$entity = array(
				'value' => strtolower(trim($mood->post_title)),
			);
			$tags = get_the_terms( $mood->ID, self::TAGS );
			if (!empty($tags)) {
				$synonyms = wp_list_pluck($tags, 'name');
				$entity['synonyms'] = $synonyms;
			}
			$entities[] = $entity;
		}

		return $entities;
	}

	static function update_ai_entities( $post_id = false, $post = false ) {
		if ( isset($post->post_type) && ($post->post_type === self::SLUG) ) {
			$mood_eid = MyCondo_Admin::get_value('ai','mood_ai_eid');
			$moods = self::get_moods();
			return MyCondo_API_Methods::update_ai_entities( $mood_eid, $moods );
		}
		
	}

	static function set_mood_by_name( $mood_name = '' ) {
		$args = array(
			'post_type' => self::SLUG,
			'posts_per_page' => 1,
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'DESC',
			'title' => $mood_name,
		);
		$moods = get_posts($args);

		if (empty($moods))
			return false;

		$mood = array_shift($moods);
		return self::set_mood( $mood->ID, 'on', time() );
	}

	/**
	 * Maybe we set every mood to be scheduled 30 minutes in advance. Once the set_mood function is called, we set single events based on 
	 * fadein/on and headstart functions for those classes
	 * So you have weekly events scehduled for routine/day, then you have single events scheduled for actually running it.
	 * i.e. scheduler is important
	 * @param string $mood_id             [description]
	 * @param string $action              [description]
	 * @param [type] $scheduled_timestamp [description]
	 */
	static function set_mood( $mood_id = '', $action = 'off', $scheduled_timestamp = null ) {
		set_time_limit(0);
		$timezone = get_option('timezone_string');
		$timezone = !empty($timezone) ? $timezone : 'America/Toronto';
		date_default_timezone_set( $timezone );

		$components = array( 'MyCondo_Music_Component', 'MyCondo_Lights_Component', 'MyCondo_Thermostat_Component' );

		if ( empty($mood_id) || empty($action) ) {
			throw new Exception("Error Processing Request. Cannot set mood {$mood_id} with action {$action}", 1);
		}		

		switch ($action) {
			case 'off':

				foreach ($components as $componentClassName) {
					$comp = new $componentClassName( $mood_id );
					$comp->turnOff();
				}
				// //Music
				// $music = new MyCondo_Music_Component( $mood_id ); //
				// $music_result = $music->turnOff();

				// // Lights
				// $lights_component = new MyCondo_Lights_Component( $mood_id );
				// $lights_component->turnOff();

				// // Weather
				// $thermostat = new MyCondo_Thermostat_Component( $mood_id );
				// $thermostat->turnOff();

				break;

			case 'on':
				foreach ($components as $componentClassName) {
					$comp = new $componentClassName( $mood_id );
					$comp->turnOn();
				}

				# code...
				break;

			case 'fadein':
				$timestamp = strtotime("today {$scheduled_timestamp}");

				foreach ($components as $componentClassName) {
					$headstart = $componentClassName::get_headstart();
					$timestamp = $headstart < 60 ? $timestamp : $timestamp - $headstart;
					wp_schedule_single_event( $timestamp, "{$componentClassName}_hook", array( 'fadeIn', $mood_id ) );
				}

				# code...
				break;
				
			case 'fadeout':
				$timestamp = strtotime("today {$scheduled_timestamp}");
					
				foreach ($components as $componentClassName) {
					$headstart = $componentClassName::get_headstart();
					$timestamp = $headstart < 60 ? $timestamp : $timestamp - $headstart;
					wp_schedule_single_event( $timestamp, "{$componentClassName}_hook", array( 'fadeOut', $mood_id ) );
				}

				break;			
		}

		return true;
	}

	static function mycondo_mood_metabox( $meta_boxes ) {
		//var_dump($_REQUEST);
		//die();

		//General
		$meta_boxes[] = array(
			'id' => 'ai',
			'title' => esc_html__( 'API AI Settings', 'mycondo' ),
			'context' => 'normal',
			'post_types' => array( 'mycondo_mood' ),
			'priority' => 'high',
			'autosave' => false,
			'fields' => array(
				array(
					'id' => self::$prefix . 'turnon',
					'name' => esc_html__( 'Turn On', 'mycondo' ),
					'std' => 'Test',
					'type' => 'custom_html',
					'callback' => function() {
						return '<button type="submit" value="on" class="button hide-if-no-js" name="test_button_id">Test</button>';
					},
				),
				array(
					'id' => self::$prefix . 'turnoff',
					'name' => esc_html__( 'Turn Off', 'mycondo' ),
					'std' => 'Test',
					'type' => 'custom_html',
					'callback' => function() {
						return '<button type="submit" value="off" class="button hide-if-no-js" name="test_button_id">Test</button>';
					},
				),
				array(
					'id' => self::$prefix . 'fadein',
					'name' => esc_html__( 'Fade In', 'mycondo' ),
					'std' => 'Test',
					'type' => 'custom_html',
					'callback' => function() {
						return '<button type="submit" value="fadein" class="button hide-if-no-js" name="test_button_id">Test</button>';
					},
				),
				array(
					'id' => self::$prefix . 'fadeout',
					'name' => esc_html__( 'Fade Out', 'mycondo' ),
					'std' => 'Test',
					'type' => 'custom_html',
					'callback' => function() {
						return '<button type="submit" value="fadeout" class="button hide-if-no-js" name="test_button_id">Test</button>';
					},
				),
			),
		);

		// Music
		$options = get_transient( 'spotify-playlists-options' );
		if ($options === false) {
			$music = new MyCondo_Music_Component();
			$playlists = $music->getPlaylists();
			
			if ( isset($playlists->items) && is_array($playlists->items) ) {
				foreach ($playlists->items as $playlist) {
					$options[ $playlist->uri ] = $playlist->name;
				}
				set_transient( 'spotify-playlists-options', $options, MINUTE_IN_SECONDS * 20 );
			} else {
				$options = array();
			}
		} 
		$meta_boxes[] = array(
			'id' => 'music',
			'title' => esc_html__( 'Music Settings', 'mycondo' ),
			'context' => 'normal',
			'post_types' => array( 'mycondo_mood' ),
			'priority' => 'high',
			'autosave' => false,
			'fields' => array(
				array(
					'id' => self::$prefix . 'playlist',
					'name' => esc_html__( 'Playlist', 'mycondo' ),
					'type' => 'select_advanced',
					'placeholder' => esc_html__( 'Select an Item', 'mycondo' ),
					'options' => $options,
				),
				array(
					'id' => self::$prefix . 'volume',
					'type' => 'number',
					'name' => esc_html__( 'Volume', 'mycondo' ),
					'placeholder' => esc_html__( '100', 'mycondo' ),
					'max' => '100',
				),
				// array(
				// 	'id' => self::$prefix . 'music_transition',
				// 	'type' => 'number',
				// 	'name' => esc_html__( 'Transition', 'mycondo' ),
				// 	'placeholder' => esc_html__( '30', 'mycondo' ),
				// 	'max' => '100',
				// ),
			),
		);	

		$lights = new MyCondo_Lights_Component();
		$rooms = $lights->getRooms();
		if (empty($rooms)) {
			$rooms = array();
		}

		// Set scene options
		$scenes = $lights->getScenes();
		$fields = array();
		foreach ($rooms as $name => $group_id) {
			$fields[] = array(
				'id' => self::$prefix . self::LIGHT_SCENE.$group_id,
				'name' => esc_html__( $name, 'mycondo' ),
				'type' => 'select_advanced',
				'placeholder' => esc_html__( 'Select an scene', 'mycondo' ),
				'options' => $scenes,
			);
		}

		// Light Temps
		foreach ($rooms as $name => $group_id) {
			$fields[] = array(
				'id' => self::$prefix . self::LIGHT_TEMP.$group_id,
				'type' => 'number',
				'name' => esc_html__( $name . ' Temperature', 'mycondo' ),
				'placeholder' => esc_html__( '31', 'mycondo' ),
				//'description' => 'Min 137 White. Max 500 Warm',
				'min' => '0', //137
				'max' => '100', // 500
			);
			$fields[] = array(
				'id' => self::$prefix . 'light_brightness_'.$group_id,
				'type' => 'number',
				'name' => esc_html__( $name . ' Brightness', 'mycondo' ),
				'placeholder' => esc_html__( '100', 'mycondo' ),
				'min' => '0',
				'max' => '100',
			);
		}
		$meta_boxes[] = array(
			'id' => 'lights',
			'title' => esc_html__( 'Light Settings', 'mycondo' ),
			'context' => 'normal',
			'post_types' => array( 'mycondo_mood' ),
			'priority' => 'high',
			'autosave' => false,
			'fields' => $fields,
		);

		// Weather
		// 
		//
		$modes = array_combine(MyCondo_Thermostat_Component::$modes, MyCondo_Thermostat_Component::$modes);
		$holds = array_combine(MyCondo_Thermostat_Component::$holds, MyCondo_Thermostat_Component::$holds);
		
		$meta_boxes[] = array(
			'id' => 'weather',
			'title' => esc_html__( 'Temperature Settings', 'mycondo' ),
			'context' => 'normal',
			'post_types' => array( 'mycondo_mood' ),
			'priority' => 'high',
			'autosave' => false,
			'fields' => array(
				array(
					'id' => self::$prefix . 'thermo_temp',
					'type' => 'number',
					'name' => esc_html__( 'Room Temperature', 'mycondo' ),
					'placeholder' => esc_html__( '22', 'mycondo' ),
					//'description' => 'Will start 20 minutes before',
					'min' => '16',
					'step' => '0.5',
					'max' => '32',
				),
				array(
					'id' => self::$prefix . 'thermo_mode',
					'type' => 'select_advanced',
					'name' => esc_html__( 'Mode', 'mycondo' ),
					'options' => $modes,
				),
				array(
					'id' => self::$prefix . 'thermo_hold',
					'type' => 'select_advanced',
					'name' => esc_html__( 'Hold', 'mycondo' ),
					'description' => 'Hold permanent or temporary',
					'options' => $holds,
				),
				
			),	
		);

		return $meta_boxes;
	}

	public static function getLightScenes( $post_id = 0, $group = 1 ){
		return get_post_meta( $post_id, self::$prefix . self::LIGHT_SCENE . $group, true );
	}

	public static function getLightTemps( $post_id = 0, $group = 1 ){
		return get_post_meta( $post_id, self::$prefix . self::LIGHT_TEMP . $group, true );
	}

	public static function getLightBrightness( $post_id = 0, $group = 1 ){
		return get_post_meta( $post_id, self::$prefix . 'light_brightness_' . $group, true );
	}

	public static function getMusicPlaylist( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'playlist', true );
	}

	public static function getMusicVolume( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'volume', true );
	}

	public static function getThermoTemp( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'thermo_temp', true );
	}

	public static function getThermoHold( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'thermo_hold', true );
	}

	public static function getThermoMode( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'thermo_mode', true );
	}


	// public static function getMusicTransition( $post_id = 0) {
	// 	return get_post_meta( $post_id = 0, self::$prefix . 'music_transition', true );	
	// }

}