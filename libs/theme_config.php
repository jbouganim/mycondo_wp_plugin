<?php
/**
 * @author : Jonathan Bouganim
 * This class loads and merges theme configurations, the child theme's config
 * on top of the parent theme, and recursively merges them so that the child
 * theme can override any item in the parent config.
 *
 * @todo Provide an admin page for managing the overrides, and resetting them to their default values
 */
if (!class_exists("MyCondo_WPized_Theme_Config")) {
    class MyCondo_WPized_Theme_Config {
            static public $array = array();
            static public $settings_page = array(
                    'sections' => array(),
                    'text_fields' => array( 'text', 'number', 'email', 'color', 'date', 'datetime', 'datetime-local', 'month', 'time', 'tel', 'url' ),
            );
            const SITE_CONFIG_OPTION_NAME = 'wpized_theme_config_overrides';

            /**
             * @param array $args
             *
             * @return array
             * @throws Exception
             */
            static function &init($args = array()){
                    $args = array(); 
                    $config_files = array(); 
                    $config_arrays = array(); 
                    $config_overrides_files = array(); 
                    
                    /* $args = array_merge(
                            array(
                                    'config_files' => array(
                                            TEMPLATEPATH . DIRECTORY_SEPARATOR . 'config.php',
                                            STYLESHEETPATH . DIRECTORY_SEPARATOR . 'config.php',
                                    ),
                                    'config_arrays' => array(),
                                    'load_config_from_db_option' => true,
                                    'config_overrides_files' => array(
                                            TEMPLATEPATH . DIRECTORY_SEPARATOR . 'config-overrides.php',
                                            STYLESHEETPATH . DIRECTORY_SEPARATOR . 'config-overrides.php',
                                    ),
                            ),
                            $args
                    );
                    extract($args); */

                    // Load the config files from disk
                    if ( !empty($config_files) && is_array($config_files) ) {
                            self::$array = self::extend( self::$array, self::merge_configs( $config_files ) );
                    }

                    // Load config arrays passed in
                    if (is_array($config_arrays)) {
                            foreach ($config_arrays as $config_array) {
                                    self::$array = self::extend( self::$array, $config_array );
                            }
                    }

                    // Override with DB option
                    if ($load_config_from_db_option) {
                            $site_config_array = get_option(self::SITE_CONFIG_OPTION_NAME, array());
                            if (!empty($site_config_array) && !self::is_assoc_array($site_config_array)) {
                                    error_log( sprintf('%s: Expected %s option to be an associative array', __METHOD__, self::SITE_CONFIG_OPTION_NAME) );
                            }
                            else {
                                    self::$settings_page['defaults'] = self::$array;
                                    self::$array = self::extend( self::$array, $site_config_array );
                                    self::$settings_page['values'] = $site_config_array;
                            }

                            // Load config override files to create the setting page
                            if ( !empty($config_overrides_files) && is_array($config_overrides_files) ) {
                                    $settings_sections = self::extend( self::$settings_page['sections'], self::merge_configs( $config_overrides_files ) );
                                    if ( !empty($settings_sections) ) {
                                            self::$settings_page['sections'] = $settings_sections;
                                            add_action( 'admin_menu', array( __CLASS__, '_settings_page_create_menu' ) );
                                            add_action( 'admin_init', array( __CLASS__, '_settings_page_register' ), 11 );
                                    }
                            }
                    }

                    return self::$array;
            }

            static function merge_configs( array $files ) {
                    $all_configs = array();
                    $required_configs = array();
                    foreach ($files as $config_file) {
                            $config_file = realpath($config_file);
                            if ( $config_file && !array_key_exists($config_file, $required_configs) ) {
                                    $config = require($config_file);
                                    if (!self::is_assoc_array($config)) {
                                            throw new Exception('Expected config PHP file to return an associative array: ' . $config_file);
                                    }
                                    $all_configs = self::extend( $all_configs, $config );
                                    $required_configs[$config_file] = true;
                            }
                    }

                    return $all_configs;
            }

            static function is_loaded() {
                    return !empty(self::$array);
            }

            /**
             * @return array
             * @throws Exception
             */
            static function &get_instance() {
                    if (func_num_args() !== 0) {
                            throw new Exception('get_instance allows no arguments');
                    }
                    return self::$array;
            }

            /**
             * Extend the config array with another array(s)
             * @param {array} Initial array to merge
             * @param {array} Variable list of arrays to merge
             * ...
             */
            static function extend( array $array1, array $array2 /*...*/) {
                    $args = func_get_args();
                    $array1 = array_shift( $args );
                    array_unshift( $args, $array1 );
                    return call_user_func_array(array( __CLASS__, 'recursive_array_merge_assoc' ), $args);
            }

            /**
             * Return true if the property is truthy (true, etc)
             * @param $name
             *
             * @return bool
             */
            static function defined($name) {
                    $none = new stdClass();
                    $value = self::get($name, $none);
                    if ($value === $none) {
                            return false;
                    }
                    if (is_null($value) || $value === false) {
                            return false;
                    }
                    return true;
            }

            /**
             * Convenience shortcut for obtaining a config value, supply a path to the
             * configuration desired (separated by '/') and optionally provide a default.
             * If the default is an associative array and the config points to an
             * associative array (and it most likely should), then the result will be
             * merged on top of the default array via recursive_array_merge_assoc
             *
             * @param string $name Path to the configuration setting desired
             * @param mixed $default What to return of the value does not exist
             *
             * @return mixed|null|void
             * @throws Exception
             */
            static function get($name, $default = null) {
                    if (!self::is_loaded()) {
                            throw new Exception('The configs have not yet been loaded.');
                    }

                    $name_parts = explode('/', $name);
                    $array = &self::$array;
                    $value = $default;
                    while (!empty($name_parts)) {
                            $name_part = array_shift($name_parts);
                            if (!array_key_exists($name_part, $array)) {
                                    break;
                            }
                            if (empty($name_parts)) {
                                    $value = $array[$name_part];
                            }
                            else {
                                    $array = &$array[$name_part];
                            }
                    }

                    // Merge the config array on top of the default array if they are both associative
                    if (self::is_assoc_array($default) && self::is_assoc_array($array)) {
                            $value = self::recursive_array_merge_assoc($default, $value);
                    }

                    if (function_exists('apply_filters')) {
                            $filter_name = sprintf('wpized_theme_config_%s', $name);
                            $value = apply_filters($filter_name, $value);
                    }
                    return $value;
            }

            /**
             * Test to see if a value is an associative array
             * @param mixed $value
             * @return bool
             */
            static function is_assoc_array($value) {
                    if (!is_array($value)) {
                            return false;
                    }
                    $has_index_key = in_array(true, array_map('is_int', array_keys($value)));
                    return !$has_index_key;
            }

            /**
             * Merge two associative arrays recursively
             * @return mixed
             * @throws Exception
             */
            static function recursive_array_merge_assoc(/*...*/){
                    if (func_num_args() < 2) {
                            throw new Exception('recursive_array_merge_assoc requires at least two args');
                    }
                    $arrays = func_get_args();
                    if (in_array(false, array_map( self::callback_method('is_assoc_array'), $arrays ))) {
                            throw new Exception('recursive_array_merge_assoc must be passed associative arrays (no numeric indexes)');
                    }
                    return array_reduce( $arrays, self::callback_method('_recursive_array_merge_assoc_two' ) );
            }

            /**
             * Merge two associative arrays recursively
             * @param array $a assoc array
             * @param array $b assoc array
             *
             * @return array
             * @todo Once PHP 5.3 is adopted, supply array() as $initial arg for array_reduce() and then change params $a and $b to array types
             */
            static protected function _recursive_array_merge_assoc_two($a, $b) {
                    if (is_null($a)) { // needed for array_reduce in PHP 5.2
                            return $b;
                    }

                    $merged = array();
                    $all_keys = array_merge(array_keys($a), array_keys($b));
                    foreach ($all_keys as $key) {
                            $value = null;

                            // If key only exists in a (is not in b), then we pass it along
                            if (!array_key_exists($key, $b)) {
                                    assert(array_key_exists($key, $a));
                                    $value = $a[$key];
                            }
                            // If key only exists in b (is not in a), then it is passed along
                            else if (!array_key_exists($key, $a)) {
                                    assert(array_key_exists($key, $b));
                                    $value = $b[$key];
                            }
                            // ** At this point we know that they key is in both a and b **
                            // If either is not an associative array, then we automatically chose b
                            else if (!self::is_assoc_array($a[$key]) || !self::is_assoc_array($b[$key])) {
                                    // @todo if they are both arrays, should we array_merge?
                                    $value = $b[$key];
                            }
                            // Both a and b's value are associative arrays and need to be merged
                            else {
                                    $value = self::recursive_array_merge_assoc($a[$key], $b[$key]);
                            }

                            // If the value is null, then that means the b array wants to delete
                            // what is in a, so only merge if it is not null
                            if (!is_null($value)) {
                                    $merged[$key] = $value;
                            }
                    }
                    return $merged;
            }

            /**
             * Remove false and null from an array
             * @param array $array
             *
             * @return array
             */
            static function filter_truthy(array $array){
                    return array_filter($array, array( __CLASS__, 'is_truthy' ));
            }

            /**
             * Return true if value is not null and it is not false
             *
             * @param mixed $value
             * @return bool
             */
            static function is_truthy($value) {
                    return !is_null($value) && $value !== false;
            }

            /**
             * Create the settings page menu
             */
            static function _settings_page_create_menu() {
                    self::$settings_page['hookname'] = add_options_page(
                            __('Site Config', WPIZED_LOCALE),
                            __('Site Config', WPIZED_LOCALE),
                            'manage_options',
                            'wpized_theme_config',
                            array( __CLASS__, '_settings_page_render_page' )
                    );
            }

            /**
             * Register the option key
             */
            static function _settings_page_register() {
                    register_setting( self::SITE_CONFIG_OPTION_NAME, self::SITE_CONFIG_OPTION_NAME, array( __CLASS__, '_settings_page_validate' ) );

                    foreach ( self::$settings_page['sections'] as $section_id => $section ) {
                            add_settings_section( $section_id, $section['title'], '', self::SITE_CONFIG_OPTION_NAME );
                            foreach ( $section['fields'] as $field_id => $field ) {
                                    $field['id'] = $field_id;
                                    $field['label_for'] = "_wpized_setting_field_{$section_id}__{$field_id}";
                                    if ( $field['type'] == 'wp_editor' ) {
                                            $field['label_for'] = str_replace( array( '_', '' ), '', $field['label_for'] );
                                    }
                                    $field['name'] = self::SITE_CONFIG_OPTION_NAME . "[{$section_id}][{$field_id}]";
                                    $field['value'] = !empty( self::$settings_page['values'][$section_id][$field_id] ) ? self::$settings_page['values'][$section_id][$field_id] : '';
                                    $field['default'] = !empty( self::$settings_page['defaults'][$section_id][$field_id] ) ? self::$settings_page['defaults'][$section_id][$field_id] : '';

                                    add_settings_field(
                                            $field_id,
                                            $field['label'],
                                            array( __CLASS__, '_settings_page_render_field' ),
                                            self::SITE_CONFIG_OPTION_NAME,
                                            $section_id,
                                            $field
                                    );
                            }
                    }
            }

            /**
             * Render the settings page
             *
             * @action wpized_theme_settings_page_form_before Do something before the settings form is printed
             * @action wpized_theme_settings_page_form_after Do something after the settings form is printed
             */
            static function _settings_page_render_page() {
                    ?>
                    <div class="wrap">
                            <?php screen_icon(); ?>
                            <h2><?php esc_html_e( 'Site Config', WPIZED_LOCALE ) ?></h2>
                            <?php do_action( 'wpized_theme_settings_page_form_before', self::$settings_page ) ?>
                            <form action="options.php" method="post">
                                    <?php settings_fields( self::SITE_CONFIG_OPTION_NAME ); ?>
                                    <?php foreach ( self::$settings_page['sections'] as $section_id => $section ) : $section['id'] = $section_id ?>
                                    <?php self::_settings_page_render_section( $section ) ?>
                                    <?php endforeach; ?>
                                    <?php submit_button() ?>
                            </form>
                            <?php do_action( 'wpized_theme_settings_page_form_after', self::$settings_page ) ?>
                    </div>
                    <?php
            }

            /**
             * Render the settings section
             *
             * @param array $section The section array
             * @action {wpized_theme_settings_page_section_before} Do something before each settings section is printed
             * @action {wpized_theme_settings_page_section_after} Do something after each settings section is printed
             */
            static function _settings_page_render_section( $section ) {
                    ?>
                    <?php do_action( 'wpized_theme_settings_page_section_before', $section ) ?>
                    <h3><?php echo esc_html( $section['title'] ) ?></h3>
                    <?php if ( !empty($section['description']) ) : ?>
                    <?php echo wpautop( $section['description'] ) // xss ok ?>
                    <?php endif; ?>
                    <table class="form-table">
                            <?php do_settings_fields( self::SITE_CONFIG_OPTION_NAME, $section['id'] ); ?>
                    </table>
                    <?php do_action( 'wpized_theme_settings_page_section_after', $section ) ?>
                    <?php
            }

            /**
             * Render the settings field
             *
             * @param array $field The field array
             * @action {wpized_theme_settings_page_field_before} Do something before each settings field is printed
             * @action {wpized_theme_settings_page_field_after} Do something after each settings field is printed
             */
            static function _settings_page_render_field( $field ) {
                    ?>

                    <?php do_action( 'wpized_theme_settings_page_field_before', $field ) ?>

                    <?php
                    if ( in_array( $field['type'], self::$settings_page['text_fields'] ) ) :
                    ?>
                    <input type="<?php echo esc_attr($field['type']) ?>" id="<?php echo esc_attr($field['label_for']) ?>" name="<?php echo esc_attr( $field['name'] ) ?>" value="<?php echo esc_attr( $field['value'] ) ?>" class="regular-text" />
                    <?php
                    elseif ( $field['type'] == 'textarea' ) :
                    ?>
                    <textarea id="<?php echo esc_attr($field['label_for']) ?>" name="<?php echo esc_attr( $field['name'] ) ?>" class="large-text code" cols="50" rows="10"><?php echo esc_textarea($field['value']) ?></textarea>
                    <?php
                    elseif ( $field['type'] == 'wp_editor' ) :
                    ?>
                    <?php
                            wp_editor(
                                    $field['value'],
                                    $field['label_for'],
                                    array( 'textarea_name' => esc_attr($field['name']) )
                            );
                    ?>
                    <?php
                    endif;

                    // Print description
                    $description = '';
                    if ( !empty($field['description']) ) {
                            $description .= esc_html($field['description']);
                    }
                    if ( !empty($field['default']) ) {
                            if ( !empty($description) ) {
                                    $description .= '<br />';
                            }
                            $description .= __('Default:', WPIZED_LOCALE) .' <code>'. $field['default'] . '</code>';
                    }

                    if ( !empty($description) ) :
                    ?>
                    <p class="description"><?php echo $description // xss ok ?></p>
                    <?php
                    endif;
                    ?>

                    <?php do_action( 'wpized_theme_settings_page_field_after', $field ) ?>

                    <?php
            }

            /**
             * Validate the settings values
             *
             * We need to remove the empty fields so that the values from the config file(s)
             * will be returned by the get() method when the value doesn't exist in the DB.
             *
             * @filter {wpized_theme_settings_page_values}
             */
            static function _settings_page_validate( $values ) {
                    foreach ( $values as $section_id => $fields ) {
                            $fields = array_filter( array_map( 'trim', $fields ) );
                            if ( empty($fields) ) {
                                    unset( $values[$section_id] );
                            }
                            else {
                                    $values[$section_id] = $fields;
                            }
                    }

                    return apply_filters( 'wpized_theme_settings_page_values', $values );
            }

            /**
             * Run some tests to verify the functionality in the class
             */
            static function test(){
                    assert(!self::is_assoc_array(array( 1, 2, 3 )));
                    assert(self::is_assoc_array(array( 'a' => 1, 'b' => 2, 'c' => 3 )));
                    assert(!self::is_assoc_array(array( 0, 'a' => 1, 'b' => 2, 'c' => 3 )));

                    $a = array(
                            'foo' => 1,
                            'qux' => 1,
                            'bar' => 1,
                    );
                    $b = array(
                            'foo' => 2,
                            'bar' => null,
                            'bad' => 2,
                    );
                    $ab = self::recursive_array_merge_assoc($a, $b);
                    assert($ab === array( 'foo' => 2, 'qux' => 1, 'bad' => 2 ));

                    $a = array(
                            'foo' => array( 1, 2, 3 ),
                            'qux' => 1,
                            'bar' => array(
                                    'a' => 1,
                                    'b' => 2,
                            ),
                    );
                    $b = array(
                            'foo' => 'noarray',
                            'bad' => 2,
                            'bar' => array(
                                    'c' => 3,
                            ),
                    );
                    $ab = self::recursive_array_merge_assoc($a, $b);
                    $ab_verified = array(
                            'foo' => 'noarray',
                            'qux' => 1,
                            'bar' => array(
                                    'a' => 1,
                                    'b' => 2,
                                    'c' => 3,
                            ),
                            'bad' => 2,
                    );
                    assert($ab === $ab_verified);

                    $a = array(
                            'foo' => 1,
                    );
                    $b = array(
                            'bar' => 2,
                            'baz' => 3,
                    );
                    $c = array(
                            'bar' => null,
                            'qux' => 4,
                    );
                    $abc = self::recursive_array_merge_assoc($a, $b, $c);
                    $abc_verified = array(
                            'foo' => 1,
                            'baz' => 3,
                            'qux' => 4,
                    );
                    assert($abc === $abc_verified);

                    self::$array = array(
                            'a' => array(
                                    'aa' => array(
                                            'aaa' => 'bingo',
                                    ),
                            ),
                            'b' => false,
                    );
                    assert(self::get('a/aa/aaa') === 'bingo');
                    assert(self::get('a/aa/bbb') === null);
                    assert(self::get('a/aa/bbb', 'fail') === 'fail');
            }

            static function callback_method($method_name) {
                    return array( __CLASS__, $method_name );
            }
    }


    if (__FILE__ === realpath($_SERVER['SCRIPT_NAME']) && in_array('--test', $argv)) {
            MyCondo_WPized_Theme_Config::test();
    }
}