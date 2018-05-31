<?php
if ( class_exists('MyCondo_Admin') )
	return false;
/**
 * General Settings for the plugin
 * @author Jonathan Bouganim jonathanbouganim@gmail.com
 *
 */

class MyCondo_Admin {

	const SLUG = 'mycondo_settings';
	const HOOK_PREFIX = 'mycondo-settings_page_';
	const NOTICE_OPTION_KEY = 'mycondo_admin_notice';

	var $options = false;

	protected static $hook = '';
	protected static $values = array();
	protected static $general_setting_schema = array();

	function __construct() {
		$this->options = get_option( self::SLUG, self::$values );
	}

	function set_options($new_options = array()) {
		foreach ($new_options as $section => $sub_options) {
			foreach ($sub_options as $option_key => $value) {
				$original_value = !empty($this->options[ $section ][ $option_key ]) ? $this->options[ $section ][ $option_key ] : null;
				$new_value = $new_options[ $section ][ $option_key ];
				if ($original_value !== $new_value) {
					$this->options[ $section ][ $option_key ] = $new_value;
				}
			}
		}
		return update_option( self::SLUG, $this->options );
	}

	static function setup( $general_setting = array(), $ad_setting = array(), $player_setting = array(), $importer_setting = array(), $permalinks_setting = array(), $legacy_shortcode_setting = array(), $analytics_setting = array() )
	{
		self::$general_setting_schema = $general_setting;
		

		self::$values = get_option( self::SLUG, self::$values );
		add_action( 'admin_init', array( __CLASS__, '_register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, '_register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
		add_action( 'admin_notices', array( __CLASS__, 'create_notices') );

	}

	/**
	 *  Register settings and add permission for admins
	 */
	static function _register_settings() {
		register_setting( self::SLUG, self::SLUG, array( __CLASS__, '_validate' ) );
		//register_setting( self::SLUG, self::get_page_hook('player', false), array( __CLASS__, '_validate' ) );
		//register_setting( self::SLUG, self::get_page_hook('ad', false), array( __CLASS__, '_validate' ) );

		// Create unique capabilities
		$role = get_role( 'administrator' );
		$role->add_cap( self::SLUG );
	}

	static function get_page_hook($name = '', $with_prefix = true){
		return  $with_prefix ? sprintf(self::HOOK_PREFIX.self::SLUG.'_%s', $name) : sprintf(self::SLUG.'_%s', $name);
	}

	/**
	*  Add menu page
	*/
	static function _register_menu(){
		if ( current_user_can( self::SLUG ) ) {
			self::$hook = add_menu_page(
				'MyCondo',
				'MyCondo',
				'manage_options',
				 __CLASS__,
				 array( __CLASS__, 'main_page' ),
				 'dashicons-admin-home',
				 '2'
			);

			add_submenu_page(
				'MyCondo',
				'Settings',
				'Settings',
				'manage_options',
				__CLASS__,
				array( __CLASS__, 'main_page' )
			);

			// add_submenu_page(
			// 	'MyCondo_Admin',
			// 	'Player Settings',
			// 	'Player Settings',
			// 	'manage_options',
			// 	self::get_page_hook('player', false),
			// 	array( __CLASS__, 'player_page' )
			// );

		}
	}

	public static function add_notice($slug = false, $message = '', $type = 'error') {
		if ($slug === false)
			return false;

		$notice = array(
			'type' => $type,
			'message' => $message,
		);
		$notices = get_option( self::NOTICE_OPTION_KEY, array() );
		if (isset($notices[ $slug ])) {
			if (($notices[ $slug ]['message'] === $message) && ($notices[ $slug ]['type'] === $type))
				return false;
		}
		$notices[ $slug ] = $notice;
		delete_option( self::NOTICE_OPTION_KEY );
		add_option( self::NOTICE_OPTION_KEY, $notices, false, false );
	}

	public static function remove_notice( $slug ) {
		$notices = get_option( self::NOTICE_OPTION_KEY, array() );
		if (isset( $notices[ $slug ] )) {
			unset( $notices[ $slug ] );
			return update_option( self::NOTICE_OPTION_KEY, $notices);
		} else {
			return false;
		}
	}

	public static function create_notices() {
		$notices = get_option( self::NOTICE_OPTION_KEY, array() );
		foreach ($notices as $notice) {
			$class = $notice['type'] . " notice";
			$message = $notice['message'];
			echo"<div class=\"$class\"> <p>$message</p></div>";
		}

	}

	public static function enqueue_admin_scripts($hook) {
		wp_enqueue_style( 'mycondo-admin-styles', MYCONDO_ASSETS.'/css/admin-styles.css' );

		if (stripos($hook,'mycondo') === false)
			return;
		// first check that $hook_suffix is appropriate for your admin page
		wp_enqueue_script( 'mycondo-admin', MYCONDO_ASSETS.'/js/admin-script.js', array( 'wp-color-picker' ), false, true );
		wp_enqueue_script( 'media-upload');
		wp_enqueue_script( 'thickbox');
		wp_enqueue_script('jquery-ui-sortable');

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'thickbox');
	}

	public static function _validate( $values ) {
		if ( empty( $values ) || ! is_array( $values ) ) {
			$values = array();
		} else {
			self::$values = get_option( self::SLUG, self::$values );
			$values = array_merge(self::$values, $values);
		}
		do_action('mycondo_settings_on_save', $values);
		return $values;
	}

	public static function get_value( $section_id, $field_id, $default_value = null ) {
		if ( !empty( self::$values[ $section_id ][ $field_id ] ) ) {
			return self::$values[ $section_id ][ $field_id ];
		}
		else {
			return $default_value;
		}
	}

	public static function get_value_radio( $section_id, $field_id, $default_value = null ) {
		if ( !empty( self::$values[ $section_id ][ $field_id ][0] ) ) {
			return self::$values[ $section_id ][ $field_id ][0];
		}
		else {
			return $default_value;
		}
	}

	public static function get_value_enabled( $section_id, $field_id, $default_value = false ) {
		if ( isset( self::$values[ $section_id ][ $field_id ][0] ) ) {
			return (self::$values[ $section_id ][ $field_id ][0] === 'enabled');
		}
		else {
			return $default_value;
		}
	}

	public static function main_page() {
		$hook = self::SLUG;
		self::_render_page( __('Settings', MYCONDO_LOCALE ), self::$general_setting_schema );
	}


	public static function _render_page($title = '', $fields = array(), $hook = self::SLUG ) {
		$title = !empty( $title ) ? $title : esc_html_e( 'Settings', MYCONDO_LOCALE );
		?>
		<div class="wrap">
			<h2><?php echo $title ?></h2>
			<?php if( isset($_GET['settings-updated']) ) { ?>
				<div id="message" class="updated">
					<p><strong><?php _e('Settings saved.') ?></strong></p>
				</div>
			<?php } ?>
			<form id="importer-settings-form" action="options.php" method="post">
				<?php settings_fields( $hook ) ?>
				<?php foreach ( $fields as $section ) : ?>
					<h3 class="title"><?php echo esc_html( $section['title'] ) ?></h3>
					<?php if (isset($section['description'])) { ?>
					<p class="section_description"><?php echo esc_html( $section['description'] ) ?></p>
					<?php } ?>
					<table class="form-table">
						<tbody>
							<?php foreach ( $section['fields'] as $field ) :
								$id    = sprintf( '%s_%s_%s', self::SLUG, $section['id'], $field['id'] );
								$name  = sprintf( '%s[%s][%s]', self::SLUG, $section['id'], $field['id'] );
								$value = self::get_value( $section['id'], $field['id'] );
								if ( isset($field['choices']) && !is_array( $field['choices'] ) ) {
									$field['choices'] = method_exists(__CLASS__, $field['choices']) ? call_user_func( array(__CLASS__, $field['choices']) ) : $field['choices'];
								}

								if (isset($field['elements'])) {
									if (strpos($field['elements'], "::") !== false) {
										$callback = explode("::", $field['elements']);
										$field['elements'] = method_exists($callback[0], $callback[1]) ? call_user_func( array($callback[0], $callback[1]) ) : false;
									} elseif (is_string($field['elements'])) {
										$field['elements'] = function_exists($field['elements']) ? call_user_func( $field['elements']) : false;
									} else {
										$field['elements'] = false;
									}
								}
							?>
								<tr valign="top">
									<?php printf(
										'<th scope="row"><label for="%s">%s</label></th>',
										esc_attr( $id ),
										esc_html( $field['label'] )
									) ?>
									<td>
										<?php if ( 'checkbox' === $field['type'] ) :
											if ( is_null( $value ) ) { $value = array(); }
											if ( is_string( $value ) ) { $value = explode(",", $value); }
										?>
											<fieldset id="<?php echo esc_attr( $id ) ?>">
												<?php foreach ( $field['choices'] as $check_value => $check_label ) : ?>
													<?php printf(
														'<label><input type="checkbox" id="%s" name="%s[]" value="%s"%s /> %s</label><br />',
														esc_attr( $id ),
														esc_attr( $name ),
														esc_attr( $check_value ),
														checked( in_array( $check_value, $value ), true, false ),
														esc_html( $check_label )
													) ?>
												<?php endforeach; ?>
											</fieldset>


										<?php elseif ( 'textarea' === $field['type'] ) : ?>
											<?php
											printf('<textarea id="%s" name="%s">%s</textarea>',
												esc_attr( $id ),
												esc_attr( $name ),
												esc_attr( $value )
												);
											 ?>
										<?php elseif ( 'upload_image' === $field['type'] ) : ?>
											<?php printf(
												'<p><input type="text" id="%s" name="%s" value="%s" class="image_url" /><button type="button" class="rdm_image_upload" value="Upload Image">Upload Image</button></p>',
												esc_attr( $id ),
												esc_attr( $name ),
												esc_attr( $value )
											) ;
											if (!empty($value)) :
												$watermark_width = MyCondo_Admin::get_value('js_appearance', 'watermark_width', "60");
												printf('Preview:&nbsp;&nbsp;&nbsp;<img src="%s" width="%s"/ class="%s">', $value, $watermark_width, $name);
											endif;
											?>
										<?php elseif ( 'upload_file' === $field['type'] ) : ?>
											<?php printf(
												'<p><input type="text" id="%s" name="%s" value="%s" class="image_url" /><button type="button" class="rdm_image_upload" value="Upload Image">Choose File</button></p>',
												esc_attr( $id ),
												esc_attr( $name ),
												esc_attr( $value )
											) ;
	
											?>
												
										<?php elseif ( 'color_picker' === $field['type'] ) : ?>
											<?php printf(
												'<input type="text" id="%s" name="%s" value="%s" class="colpicker" />',
												esc_attr( $id ),
												esc_attr( $name ),
												esc_attr( $value )
											) ?>

										<?php elseif ( 'dropdown' === $field['type'] ) : ?>
										<?php if ( is_null( $value ) ) { $value = array(); } ?>
											<fieldset id="<?php echo esc_attr( $id ) ?>">
												<?php printf('<select name="%s" id="%s">', esc_attr( $name ), esc_attr( $id ) ); ?>
												<?php foreach ( $field['choices'] as $option_value => $option_label ) : ?>
													<?php printf(
														'<option value="%s"%s >%s</option>',
														esc_attr( $option_value ),
														selected( $option_value == $value, true, false ),
														esc_html( $option_label )
													) ?>
												<?php endforeach; ?>
												<?php printf('</select>'); ?>
											</fieldset>

										<?php elseif ( 'radio' === $field['type'] ) : ?>
											<?php if ( is_null( $value ) ) { $value = array(); } ?>
											<?php if ( is_string( $value ) ) { $value = array_fill( 0, 1, $value ); } ?>
											<fieldset id="<?php echo esc_attr( $id ) ?>">
												<?php foreach ( $field['choices'] as $check_value => $check_label ) : ?>
													<?php printf(
														'<label><input type="radio" id="%s" name="%s[]" value="%s"%s /> %s</label><br />',
														esc_attr( $id ),
														esc_attr( $name ),
														esc_attr( $check_value ),
														checked( in_array( $check_value, $value ), true, false ),
														esc_html( $check_label )
													) ?>
												<?php endforeach; ?>
											</fieldset>
										<?php elseif ( 'sortable' === $field['type'] && $field['elements'] != false ) : ?>
											<div class="sortable-container">
												<ul class="sortable">
													<?php
														foreach ($field['elements'] as $data) {
															// Some cases we were getting WP_Term instead of an array, ensure we have our proper type here
															$data = !is_array($data) ? (array) $data : $data;
															echo '<li ';
															printf(' data-term_id="%d"', $data['term_id']);
															echo '>' . $data['name'] . '</li>';
														}
													?>
												</ul>
												<?php
													$elements = wp_list_pluck($field['elements'], "term_id");
													printf('<input type="hidden" id="%s" name="%s" value="%s" />',
														esc_attr( $id ),
														esc_attr( $name ),
														json_encode( $elements )
													);
												?>
											</div>
										<?php elseif ( 'select_post' === $field['type'] ) : ?>
											<?php printf(
												'<p id="select-wrap"><span class="title">%s</span> <a href="#" class="select-item">%s</a>%s</p>',
												! empty( $value ) ? esc_html( get_the_title( $value ) ) : '',
												esc_html__( 'Select', MYCONDO_LOCALE ),
												sprintf(
													'<input type="hidden" id="%s" name="%s" value="%s" />',
													esc_attr( $id ),
													esc_attr( $name ),
													esc_attr( $value )
												)
											) ?>
										<?php elseif ( 'multiple_set' === $field['type'] ) : ?>
										<div class='multiple-set-parent'>
											<div class="multiple-set">
											<?php
											//form the div template to be used for multiple values
											$div_to_use =  "<div class='m-fieldset-template'
											data-index='index-placeholder'
											id='{$id}'>";
											foreach($field['fields'] as $field_data) :
												$field_name = $field_data['label'];
												$field_slug = $field_data['id'];
												$field_type = $field_data['type'];
												$div_to_use .= "<label>{$field_name}</label>&nbsp;&nbsp;&nbsp;";
												$div_to_use .= "<input type='{$field_type}'
												name='{$name}[index-placeholder][{$field_slug}]'
												value='{$field_slug}-value-placeholder'\></br></br>";
											endforeach;
											$div_to_use .= "<button class='button button-secondary rdm-option-remove-button'>Remove</button>";
											$div_to_use .= "</div>";

											$values = $value;
											if(count($values) > 0):
												foreach($value as $i => $field_values) :
													//replace the placeholders wih the actual values
													$div_to_use_html = str_replace('index-placeholder',$i,$div_to_use);
													foreach($field['fields'] as $field_data) :
														$field_slug = $field_data['id'];
														$field_value = !empty( $values[$i][$field_slug] ) ? $values[$i][$field_slug] : '';

														$div_to_use_html = str_replace
														("{$field_slug}-value-placeholder",
															$field_value,
															$div_to_use_html);
													endforeach;
													echo str_replace('m-fieldset-template','m-fieldset',
														$div_to_use_html);
												endforeach;
											endif;
											 ?>
											</div>
											<?php
												//print the template for js
												echo $div_to_use;
											?>
											<button class="button button-secondary rdm-option-add-button">Add</button>
										</div>
										<?php else : ?>
											<?php printf(
												'<input type="%s" id="%s" name="%s" value="%s" class="regular-text" />',
												esc_attr( $field['type'] ),
												esc_attr( $id ),
												esc_attr( $name ),
												esc_attr( $value )
											) ?>
										<?php endif; ?>

										<?php if ( ! empty( $field['description'] ) ) : ?>
											<p class="description"><?php echo ( $field['description'] ) ?></p>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>
				<?php submit_button() ?>
			</form>
		</div>
		<?php
	}   
}

?>
