<?php

if ( class_exists('MyCondo_Routine_Post_Type') )
	return false;

class MyCondo_Routine_Post_Type {

	static $instance = null;
	static $options = array();
	static $taxonomies = array();
	static $endpoints = array();
	static $prefix = 'mycondo-routine-';

	const SLUG      = 'mycondo_routine';
	const META_KEY  = 'mycondo_routine_post_meta';
	const DEFAULT_REWRITE_SLUG = 'routine';
	const ROUTINE_HOOK = 'run_routine_hook';

	const HEADSTART_MINS = 30;
	const TAGS = 'mood_synonyms';
	static $headstart_actions = array( 'fadein', 'fadeout' );


	/**
	 * Our post type labels and settings
	 * @var array
	 */
	static $post_type_args = array(
			'label'               => self::SLUG,
			'labels'              => array(
				'name'                => 'Routines',
				'singular_name'       => 'Routine',
				'menu_name'           => 'Routines',
				'name_admin_bar'      => 'Routine',
				'parent_item_colon'   => 'Parent Routine:',
				'all_items'           => 'Routines',
				'add_new_item'        => 'Add New Routine',
				'add_new'             => 'Add New',
				'new_item'            => 'New Routine',
				'edit_item'           => 'Edit Routine',
				'update_item'         => 'Update Routine',
				'view_item'           => 'View Routine',
				'search_items'        => 'Search Routine',
				'not_found'           => 'Not found',
				'not_found_in_trash'  => 'Not found in Trash',
			),
			'description'         => 'Routine Post Type',
			'hierarchical'        => false,
			'supports' => array(
				'title',
				'slug',
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
			'menu_icon'           => 'dashicons-calendar',
			//'show_in_admin_bar'   => true,
			//'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
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
		add_filter( 'rwmb_meta_boxes', array(__CLASS__, 'mycondo_routine_metabox') );

		// Schedule our scheduler. Every week we run thourgh all published routines, get times and schedule dates
		if ( !wp_next_scheduled( 'routine_scheduler_event' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'daily', 'routine_scheduler_event');
		}

		// When routines are edited
		add_action( 'save_post', array( __CLASS__, 'routine_updated'), 10, 3 );
		add_action( 'routine_scheduler_event', array( __CLASS__, 'routine_scheduler') );
		add_action( self::ROUTINE_HOOK, array( __CLASS__, 'run_routine'), 10, 2 );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_schedules') );
		add_action( 'save_post', array( __CLASS__, 'update_ai_entities'), 10, 2 );
	}

	/**
	 * Register our post type
	 * @void
	 */
	static function register_post_type() {
		self::$post_type_args['taxonomies'] = self::$taxonomies;

		register_post_type( self::SLUG, self::$post_type_args );

		register_post_status('active', array(
            'label' => __( 'Active', 'mycondo' ),
            'protected' => true,
            'label_count' => _n_noop( 'Visitors logged in <span class="count">(%s)</span>', 'Visitors logged in <span class="count">(%s)</span>' ),
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
        ));

    	register_post_status('inactive', array(
            'label' => __( 'Inactive', 'mycondo' ),
            'protected' => true,
            //'label_count' => _n_noop( 'Visitors logged out <span class="count">(%s)</span>', 'Visitors logged out <span class="count">(%s)</span>' ),
            'show_in_admin_status_list' => true,
        ));
	}

	/**
	 * Every hour we check our routines and create re-occuring cron events from the settings
	 * In the case of the thermostat, we update it directly
	 * @return [type] [description]
	 */
	static function routine_scheduler() {
		//error_log("Running Scheduler");

		$routines = get_posts( array(
			'post_type' => MyCondo_Routine_Post_Type::SLUG,
			'post_status' => 'publish',
			'limit' => -1,
			)
		);

		$timezone = get_option('timezone_string');
		$timezone = !empty($timezone) ? $timezone : 'America/Toronto';
		date_default_timezone_set( $timezone );

		$scheduled_events = array();	
		foreach ($routines as $routine) {
			$post_id = $routine->ID;
			//error_log("Scheduling routine {$post_id}...");

			$scheduled_days = self::getRoutineDays( $post_id );
			$scheduled_time = self::getRoutineTime( $post_id );
			$action = self::getRoutineAction( $post_id );
			$mood = self::getRoutineMood( $post_id );

			// Lets add an action hook when each routine is scheduled so we can tap into it and schedule our thermostat
			do_action( 'wp_mycondo_routine_scheduler_pre', array( $post_id, $scheduled_days, $scheduled_time, $action, $mood ) );
			foreach (self::_get_days_of_week() as $day => $label) {
				$args = array( $post_id, $day );
				if ( in_array($day, $scheduled_days) ) :
					wp_clear_scheduled_hook( self::ROUTINE_HOOK, $args );
					// Set action for 15 minutes before
					$timestamp = strtotime( "this {$day} {$scheduled_time}" );
					if (time() > $timestamp) 
						continue;
					
					$run_timestamp = in_array($action, self::$headstart_actions) ? $timestamp - (self::HEADSTART_MINS * MINUTE_IN_SECONDS) : $timestamp;
								
					//error_log("This event is scheduled to go on next at " . "this {$day} {$scheduled_time}");
					$scheduled_events[] = array(
						'id' => $post_id,
						'timestamp' => $timestamp,
						'run_timestamp' => $run_timestamp,
						'day' => $day,
						'args' => $args,
					);
				else :
					wp_clear_scheduled_hook( self::ROUTINE_HOOK, $args );
				endif;	
			}
		}

		// Schedule all the events
		foreach ($scheduled_events as $event) {
			if ( ! wp_next_scheduled( self::ROUTINE_HOOK, $event['args'] ) ) {
				//wp_schedule_single_event( $event['timestamp'], self::ROUTINE_HOOK, array( $event['id'], $event['timestamp'] ) );
				wp_schedule_event( $event['run_timestamp'], 'weekly', self::ROUTINE_HOOK, $event['args'] );
			}	
		}

	}
	/**
	 * This runs the routine mood for anything that is in-time. Anything that needs to be done ahead of time i.e. thermo needs to
	 * hook into the action above 'wp_mycondo_routine_scheduler_pre' 
	 * @param  [int] $post_id     		
	 * @param  [string] $day_of_week 
	 * @return [null]              
	 */
	static function run_routine( $post_id, $day_of_week ) {
		error_log("RUNNING ROUTINE 30 mins before ID - " . $post_id . "");
		MyCondo_Mood_Post_Type::set_mood( self::getRoutineMood( $post_id ), self::getRoutineAction( $post_id ), self::getRoutineTime( $post_id ) );
	}

	static function routine_updated( $post_id, $post, $update ) {
		if ( isset($post->post_type) && ($post->post_type === self::SLUG) ) {
			self::routine_scheduler();
		}
	}

	static function get_routines() {
		$args = array(
			'post_type' => self::SLUG,
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'DESC',
		);

		$routines = get_posts($args);
		$entities = array();
		foreach ($routines as $routine) {
			$entity = array(
				'value' => strtolower(trim($routine->post_title)),
			);
			$tags = get_the_terms( $routine->ID, self::TAGS );
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
			$routine_eid = MyCondo_Admin::get_value('ai','routine_ai_eid');
			$routines = self::get_routines();
			return MyCondo_API_Methods::update_ai_entities( $routine_eid, $routines, "routines" );
		}
		
	}

	static function set_routine_by_name( $routine_name = 'morning', $date = 'tomorrow', $time = '8 am', $recurrence = false  ) {
		$args = array(
			'post_type' => self::SLUG,
			'posts_per_page' => 1,
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'DESC',
			'title' => $routine_name,
		);
		$routines = get_posts($args);
		if (empty($routines))
			return false;

		$routine = array_shift($routines);
		$date = !empty($date) ? $date : 'tomorrow';
		$weekday = date("l",strtotime( $date ));
		$days = self::getRoutineDays( $routine->ID );
		$days = !empty($days) ? $days : array();
		if (!empty($weekday)) {
			$weekday = strtolower( $weekday );
			if ( !in_array($weekday, $days) ) {
				self::setRoutineDay( $routine->ID, $weekday );
			}
		}		

		$time_to_set = date("H:i",strtotime( $time ));
		self::setRoutineTime( $routine->ID, $time_to_set );
		self::routine_scheduler();
		$resp_date = !empty($date) ? "{$date} at " : "";
		$response = "The {$routine_name} alarm was set for {$resp_date}{$time}.";
		return $response;
	}

	static function cancel_routine_by_name( $routine_name = 'morning', $date = 'tomorrow'  ) {
		$args = array(
			'post_type' => self::SLUG,
			'posts_per_page' => 1,
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'DESC',
			'title' => $routine_name,
		);
		$routines = get_posts($args);
		if (empty($routines))
			return false;

		$routine = array_shift($routines);
		$date = !empty($date) ? $date : 'tomorrow';
		$weekday = date("l",strtotime( $date ));
		$days = self::getRoutineDays( $routine->ID );
		$days = !empty($days) ? $days : array();
		if (!empty($weekday)) {
			$weekday = strtolower( $weekday );
			if ( in_array($weekday, $days) ) {
				self::deleteRoutineDay( $routine->ID, $weekday );
			}
		}		

		self::routine_scheduler();
		$response = "The {$routine_name} alarm was cancelled for {$date}.";
		return $response;
	}

	static function add_custom_schedules( $schedules ) {
		// add a 'weekly' schedule to the existing set
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __('Weekly')
		);
		return $schedules;
	}


	/**
	 * Callback for `manage_edit-{$post_type}-colummns` filter. This callback
	 * will add new column to indicate the post category
	 *
	 * @param int $post_id Post ID
	 * @return array Columns
	 * @filter manage_edit-{$post_type}-colummns
	 */
	public static function on_manage_edit_columns( $columns ) {
		unset( $columns[ 'author' ] );
		return $columns;
	}

	static function mycondo_routine_metabox( $meta_boxes ) {
		$meta_boxes[] = array(
			'id' => 'routine_settings',
			'title' => esc_html__( 'Actions', 'mycondo' ),
			'post_types' => array( self::SLUG ),
			'context' => 'advanced',
			'priority' => 'high',
			'autosave' => false,
			'fields' => array(
				array(
					'id' => self::$prefix . 'action',
					'name' => esc_html__( 'Action', 'mycondo' ),
					'type' => 'select',
					'placeholder' => esc_html__( 'Select an Item', 'mycondo' ),
					'options' => array(
						'on' => 'Turn On',
						'off' => 'Turn Off',
						'fadein' => 'Fade On',
						'fadeout' => 'Fade Off',
					),
				),
				array(
					'id' => self::$prefix . 'routine_mood',
					'type' => 'post',
					'name' => esc_html__( 'Mood', 'mycondo' ),
					'desc' => esc_html__( 'Set the mood you want for this action on.', 'mycondo' ),
					'post_type' => 'mycondo_mood',
					'field_type' => 'select_advanced',
					//'parent' => true,
				),
			),
		);

		// Schedule
		$meta_boxes[] = array(
			'id' => 'routine_schedule',
			'title' => esc_html__( 'Schedule', 'mycondo' ),
			'post_types' => array( self::SLUG ),
			'context' => 'advanced',
			'priority' => 'high',
			'autosave' => false,
			'fields' => array(
				array(
					'id' => self::$prefix . 'time',
					'type' => 'time',
					'name' => esc_html__( 'Time', 'mycondo' ),
					'inline' => true,
				
				),
				array(
					'id' => self::$prefix . 'days',
					'name' => esc_html__( 'Days', 'mycondo' ),
					'type' => 'checkbox_list',
					'options' => self::_get_days_of_week(),
				),
			),
		);

		return $meta_boxes;
	}

	public static function getRoutineMood( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'routine_mood', true );
	}

	public static function getRoutineAction( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'action', true );
	}

	public static function getRoutineDays( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'days' );
	}

	public static function getRoutineTime( $post_id = 0 ){
		return get_post_meta( $post_id, self::$prefix . 'time', true );
	}

	public static function setRoutineMood( $post_id = 0, $value ){
		return update_post_meta( $post_id, self::$prefix . 'routine_mood', $value );
	}

	public static function setRoutineAction( $post_id = 0, $value ){
		return update_post_meta( $post_id, self::$prefix . 'action', $value );
	}

	public static function setRoutineDay( $post_id = 0, $value ){
		return add_post_meta( $post_id, self::$prefix . 'days', $value );
	}

	public static function deleteRoutineDay( $post_id = 0, $value ){
		return delete_post_meta( $post_id, self::$prefix . 'days', $value );
	}

	public static function setRoutineTime( $post_id = 0, $value ){
		return update_post_meta( $post_id, self::$prefix . 'time', $value );
	}

	public static function _get_days_of_week() {
		return array(
			'monday' =>    'Monday',
		    'tuesday' =>    'Tuesday',
		    'wednesday' =>    'Wednesday',
		    'thursday' =>    'Thursday',
		    'friday' =>    'Friday',
		    'saturday' =>    'Saturday',
		    'sunday' =>    'Sunday',
		);
	}
	
}