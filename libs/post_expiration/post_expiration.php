<?php
/**
 * @package WPized Post Expiration
 * @author Jonathan Bouganim
 * @version 2.1
 */

require_once(dirname(__FILE__) . '/../metabox.class.php');
require_once(dirname(__FILE__) . '/../theme_config.php');

if (!class_exists('MyCondo_WPized_Post_Expiration')) {
	class MyCondo_WPized_Post_Expiration {
		const EXPIRY_POST_META_KEY = '_wpized_post_expiry';
		const EXPIRED_POST_META_KEY = '_wpized_post_expired';
		const SCHEDULED_HOOK = 'wpized_post_expiry';
        const MYCONDO_ROUTINE_POST_TYPE = "mycondo_routine"; // post type used for plugin to be hardcoded in. 
                
		static public $options = array();

		/**
		 * Function which activates the plugin
		 * @param {array} $options passed in from config.php
		 */
		static function setup( $options = array() ){
			MyCondo_WPized_Post_Expiration_Metabox::$default_args = array(
				'title' => __('Expiration', MYCONDO_LOCALE),
				'context' => 'side',
				'priority' => 'low',
			);
			self::$options = MyCondo_WPized_Theme_Config::recursive_array_merge_assoc(
				array(
					'expired_post_status' => 'private',
					'post_types' => array_fill_keys( array_merge(get_post_types(array( 'public' => true )), array(self::MYCONDO_ROUTINE_POST_TYPE => self::MYCONDO_ROUTINE_POST_TYPE)), true ),
					'metabox_args' => MyCondo_WPized_Post_Expiration_Metabox::$default_args,
					'display_format' => get_option('date_format') . ' ' . get_option('time_format'),
				),
				self::$options,
				(MyCondo_WPized_Theme_Config::is_assoc_array($options) ? $options : array())
			);
			MyCondo_WPized_Post_Expiration_Metabox::$default_args = self::$options['metabox_args'];

			add_action( self::SCHEDULED_HOOK, array( __CLASS__, 'handle_post_expiration' ) );

			// Associate the metabox with the desired post types
			if (MyCondo_WPized_Theme_Config::is_assoc_array(self::$options['post_types'])) {
				$post_types = array_keys(array_filter(self::$options['post_types']));
			}
			else {
				$post_types = array_values(self::$options['post_types']);
			}
			// Associate the metabox with the desired post types
			foreach( $post_types as $post_type ){
				$metabox_args = array_merge(
					self::$options['metabox_args'],
					array(
						'page' => $post_type,
					)
				);
				$metabox = new MyCondo_WPized_Post_Expiration_Metabox($metabox_args);
				$metabox->register();
			}

			add_filter( 'display_post_states', array( __CLASS__, '_filter_display_post_states_to_include_expiration' ) );
		}

		static function format_relative_remaining_seconds($remaining_seconds) {
			if ($remaining_seconds >= 24 * 60 * 60) {
				$remaining_time = sprintf(__('Expires in ~%0.1f day(s)', MYCONDO_LOCALE), $remaining_seconds / 24 / 60 / 60);
			}
			else if ($remaining_seconds >= 60 * 60) {
				$remaining_time = sprintf(__('Expires in ~%0.1f hour(s)', MYCONDO_LOCALE), $remaining_seconds / 60 / 60);
			}
			else {
				$remaining_time = sprintf(__('Expires in ~%0.1f minute(s)', MYCONDO_LOCALE), $remaining_seconds / 60);
			}
			return $remaining_time;
		}

		/**
		 * @hook {filter} display_post_states
		 */
		static function _filter_display_post_states_to_include_expiration($post_states) {
			global $post;
			if (empty($post)) {
				return $post_states;
			}

			$expired_time = self::get_post_expired_datetime($post->ID);
			$expiry_time = self::get_post_expiry_datetime($post->ID);
			if ($expired_time) {
				$post_states[] = __('Expired', MYCONDO_LOCALE);
			}
			else if($expiry_time) {
				$remaining_seconds = (int)$expiry_time->format('U') - time();
				if ($remaining_seconds < 0)  {
					$post_states[] = __('Post expiration failed', MYCONDO_LOCALE);
				}
				else {
					$datetime_el = sprintf('<time datetime="%s" title="%s">%s</time>',
						$expiry_time->format('c'),
						$expiry_time->format(self::$options['display_format']),
						self::format_relative_remaining_seconds($remaining_seconds)
					);
					$post_states[] = $datetime_el;
				}
			}

			return $post_states;
		}

		/**
		 * Get the datetime that a post actually expired, set to the current timezone (timezone_string option)
		 * @param {null|int} $post_id
		 * @return {null|DateTime}
		 */
		static function get_post_expiry_datetime( $post_id = null ){
			return self::_get_postmeta_timestamp_as_datetime($post_id, self::EXPIRY_POST_META_KEY);
		}

		/**
		 * Get the datetime that a post is to expire, set to the current timezone (timezone_string option)
		 * @param {null|int} $post_id
		 * @return {null|DateTime}
		 */
		static function get_post_expired_datetime( $post_id = null ){
			return self::_get_postmeta_timestamp_as_datetime($post_id, self::EXPIRED_POST_META_KEY);
		}

		static function _get_postmeta_timestamp_as_datetime($post_id, $meta_key) {
			$datetime = null;
			try {
				$post = get_post($post_id);
				if (!empty($post)) {
					$timestamp = get_post_meta($post->ID, $meta_key, true);
					if (is_numeric($timestamp)) {
						$datetime = new DateTime('@' . $timestamp, new DateTimeZone('UTC'));
					}
					else if (!empty($timestamp)) {
						$datetime = new DateTime($timestamp);
					}
					if ($datetime && get_option('timezone_string')) {
						$datetime_tz = new DateTimeZone(get_option('timezone_string'));
						$datetime->setTimezone($datetime_tz);
					}
				}
			}
			catch(Exception $e) {
				$msg = sprintf('%s in %s: %s', get_class($e), __FUNCTION__, $e->getMessage());
				error_log($msg);
			}
			return $datetime;
		}

		/**
		 * @param {int} $post_id
		 * @param {int|DateTime|null} $time if null, then the expiration is removed
		 */
		static function set_post_expiration($post_id, $time) {
			if ($time instanceof DateTime) {
				$time->setTimezone(new DateTimeZone('UTC'));
				$time = (int)$time->format('U');
			}
			assert(is_numeric($post_id));
			assert(is_numeric($time) || empty($time));

			$post = get_post($post_id);
			if (empty($post)) {
				throw new MyCondo_WPized_Post_Expiration_Exception('Supplied post_id does not correspond to a post', E_NOTICE);
			}

			$scheduled_args = array(
				'id' => $post->ID,
			);

			// Clear out any existing scheduled expiration
			$next_scheduled_timestamp = wp_next_scheduled(self::SCHEDULED_HOOK, $scheduled_args);
			if ($next_scheduled_timestamp) {
				wp_unschedule_event($next_scheduled_timestamp, self::SCHEDULED_HOOK, $scheduled_args );
			}

			// Set expiration time
			if ( !empty($time) ) {
				if ($time < time()) {
					throw new MyCondo_WPized_Post_Expiration_Exception(__('You failed to provide a date that will occur in the future.', MYCONDO_LOCALE));
				}
				update_post_meta($post->ID, self::EXPIRY_POST_META_KEY, $time);
				wp_schedule_single_event($time, self::SCHEDULED_HOOK, $scheduled_args);
			}
			// Remove expiration time
			else {
				delete_post_meta($post->ID, self::EXPIRY_POST_META_KEY);
			}
			delete_post_meta($post->ID, self::EXPIRED_POST_META_KEY);
		}

		/**
		 * Post expiry hook and function
		 * @action wpized_post_expiry
		 */
		static function handle_post_expiration($post_id) {
			$post = get_post($post_id);
			if (empty($post)) {
				return;
			}
			$expiration_datetime = self::get_post_expiry_datetime($post_id);
			assert(!empty($expiration_datetime));
			assert($expiration_datetime instanceof DateTime);
			$post = get_post($post_id);
			$post->post_status = self::$options['expired_post_status'];
			wp_update_post($post);
			update_post_meta($post_id, self::EXPIRED_POST_META_KEY, time());
		}
	}
}

if (!function_exists('wpized_get_post_expiration_datetime')) {
	/**
	 * Get the expiration datetime for a post
	 * @param {null|int} $post_id
	 * @return {null|DateTime}
	 */
	function wpized_get_post_expiration_datetime( $post_id = null ){
		return MyCondo_WPized_Post_Expiration::get_post_expiry_datetime($post_id);
	}
}

if (!class_exists('WPized_Post_Expiration_Metabox')) {
	class MyCondo_WPized_Post_Expiration_Metabox extends MyCondo_WPized_MetaBox {
		static $default_args = array(); // Set in WPized_Post_Expiration::setup()

		function __construct($args = array()){
			parent::__construct(array_merge(self::$default_args, $args));
		}

		/**
		 * Display the meta box form
		 */
		function render($post){
			$datetime_str = '';
			$expiry_datetime = MyCondo_WPized_Post_Expiration::get_post_expiry_datetime($post->ID);
			if ($expiry_datetime) {
				$datetime_str = $expiry_datetime->format(MyCondo_WPized_Post_Expiration::$options['display_format']);
			}
			$expired_datetime = MyCondo_WPized_Post_Expiration::get_post_expired_datetime($post->ID);
			if ($expired_datetime) {
				$datetime_str = '';
			}

			?>
			<?php if ($expired_datetime): ?>
				<p>
					<?php
					$datetime_el = sprintf(
						'<time datetime="%s">%s</time>',
						$expired_datetime->format('c'),
						$expired_datetime->format(MyCondo_WPized_Post_Expiration::$options['display_format'])
					);
					?>
					<?php if ($expiry_datetime): ?>
						<?php
						$delay_seconds = (int)$expired_datetime->format('U') - (int)$expiry_datetime->format('U');
						?>
						<strong><em>
						<?php
						echo sprintf(
							__('Post expired at %s, that is %d second(s) after originally requested.', MYCONDO_LOCALE),
							$datetime_el,
							$delay_seconds
						); // xss ok
						?>
						</em></strong>
					<?php else: ?>
						<strong><em>
						<?php
						echo sprintf(
							__('Post expired at %s.', MYCONDO_LOCALE),
							$datetime_el
						); // xss ok
						?>
						</em></strong>
					<?php endif; ?>
				</p>
			<?php elseif($expiry_datetime): ?>
				<p>
					<?php $remaining_seconds = (int)$expiry_datetime->format('U') - time() ?>
					<em><time datetime="<?php echo esc_attr($expiry_datetime->format('c')) ?>"><?php echo esc_html(MyCondo_WPized_Post_Expiration::format_relative_remaining_seconds($remaining_seconds)) ?></time></em>
				</p>
			<?php endif; ?>
			<p>
				<input id="wpized_post_expiry_datetime" placeholder="<?php echo esc_attr(sprintf(__('Example: %s'), date(MyCondo_WPized_Post_Expiration::$options['display_format'], time() + (3600 * 24) ))) ?>" name="wpized_post_expiry_datetime" type="text" style="width: 100%" value="<?php echo esc_attr($datetime_str) ?>" />
			</p>
			<p class='howto'>
				<?php echo sprintf(
					__('Post will automatically expire (by being set to %1$s) at provided date/time; may provide relative times such as "tomorrow" or "5 days". The date is entered in the <a href="%2$s" title="Modify site timezone (opens in new window)" target="_blank">site timezone</a>, %3$s.', MYCONDO_LOCALE),
					MyCondo_WPized_Post_Expiration::$options['expired_post_status'],
					admin_url( '/options-general.php#timezone_string' ),
					get_option('timezone_string')
				); // xss ok ?>
			</p>
			<?php
		}

		/**
		 * Save the results of the meta box
		 */
		function save($post){
			$errors = array();
			try {
				if( isset($_POST['wpized_post_expiry_datetime']) ){
					$expiration_timestamp = null;
					if ( !empty($_POST['wpized_post_expiry_datetime']) ) {
						$tz_local = new DateTimeZone(get_option('timezone_string'));
						$expire_datetime_string = stripslashes($_POST['wpized_post_expiry_datetime']);
						$datetime = new DateTime($expire_datetime_string, $tz_local);
						$tz_utc = new DateTimeZone('UTC');
						$datetime->setTimezone($tz_utc);
						$expiration_timestamp = (int)$datetime->format('U');
					}
					MyCondo_WPized_Post_Expiration::set_post_expiration($post->ID, $expiration_timestamp);
				}
			}
			catch(Exception $e){
				$errors[] = $e->getMessage();
			}

			$this->set_field_errors( $errors );
			return empty($errors);
		}
	}
               
      class MyCondo_WPized_Post_Expiration_Exception extends Exception {}
       
      MyCondo_WPized_Post_Expiration::setup(); // let's get setup!       

}