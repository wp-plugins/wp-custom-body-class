<?php
/**
 * CustomBodyClass.
 * @package   CustomBodyClass
 * @author    Andrei Lupu <andrei.lupu@pixelgrade.com>
 * @license   GPL-2.0+
 * @link      http://andrei-lupu.com
 * @copyright 2014 Andrei Lupu
 */

/**
 * Plugin class.
 * @package   CustomBodyClass
 * @author    Andrei Lupu <andrei.lupu@pixelgrade.com>
 */
class CustomBodyClassPlugin {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 * @since   1.0.0
	 * @const   string
	 */
	protected $version = '0.0.2';

	/**
	 * Unique identifier for your plugin.
	 * Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
	 * match the Text Domain file header in the main plugin file.
	 * @since    1.0.0
	 * @var      string
	 */
	protected $plugin_slug = 'custom_body_class';

	/**
	 * Instance of this class.
	 * @since    1.0.0
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 * @since    1.0.0
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Path to the plugin.
	 * @since    1.0.0
	 * @var      string
	 */
	protected $plugin_basepath = null;

	public $display_admin_menu = false;

	protected $config;

	public $plugin_settings = null;

	/**
	 * Initialize the plugin by setting localization, filters, and administration functions.
	 * @since     1.0.0
	 */
	protected function __construct() {

		$this->plugin_basepath = plugin_dir_path( __FILE__ );
		$this->config          = self::config();

		$this->get_plugin_settings();


		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __FILE__ ) . 'custom_body_class.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		if ( isset( $this->plugin_settings['allow_edit_on_post_page'] ) && $this->plugin_settings['allow_edit_on_post_page'] ) {
			// add the metabox
			add_action( 'add_meta_boxes', array( $this, 'add_custom_body_class_meta_box' ) );
			add_action( 'save_post', array( $this, 'custom_body_class_save_meta_data' ) );
		}

		add_filter( 'body_class', array( $this, 'add_post_type_custom_body_class_in_front' ) );
	}

	/**
	 * Return an instance of this class.
	 * @since     1.0.0
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public static function config() {
		// @TODO maybe check this
		return include 'plugin-config.php';
	}

	/**
	 * Load the plugin text domain for translation.
	 * @since    1.0.0
	 */
	function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, basename( dirname( __FILE__ ) ) . '/lang/' );
	}

	function is_edit_page($new_edit = null){
		global $pagenow;
		//make sure we are on the backend
		if (!is_admin()) return false;


		if($new_edit == "edit")
			return in_array( $pagenow, array( 'post.php',  ) );
		elseif($new_edit == "new") //check for new post page
			return in_array( $pagenow, array( 'post-new.php' ) );
		else //check for either new or edit
			return in_array( $pagenow, array( 'post.php', 'post-new.php' ) );
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 */
	function add_plugin_admin_menu() {
		$this->plugin_screen_hook_suffix = add_options_page( __( 'Custom Body Class', $this->plugin_slug ), __( 'Custom Body Class', $this->plugin_slug ), 'manage_options', $this->plugin_slug, array(
			$this,
			'display_plugin_admin_page'
		) );
	}

	/**
	 * Render the settings page for this plugin.
	 */
	function display_plugin_admin_page() {
		include_once( 'views/admin.php' );
	}

	/**
	 * Add settings action link to the plugins page.
	 */
	function add_action_links( $links ) {
		return array_merge( array( 'settings' => '<a href="' . admin_url( 'options-general.php?page=custom_body_class' ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>' ), $links );
	}

	/**
	 * Adds a box to the main column on any post type checked in settings
	 */
	function add_custom_body_class_meta_box() {

		if ( ! isset( $this->plugin_settings['display_on_post_types'] ) || empty( $this->plugin_settings['display_on_post_types'] ) ) {
			return;
		}

		foreach ( $this->plugin_settings['display_on_post_types'] as $post_type => $val ) {

			// Make a nice metabox title
			$post_type_obj = get_post_type_object($post_type);
			$post_type_name = $post_type;
			if ( $post_type_obj !== null ) {
				$post_type_name = $post_type_obj->labels->singular_name;
			}

			add_meta_box(
				'custom_body_class',
				$post_type_name . __( ' classes', 'custom_body_class_txtd' ),
				array( $this, 'custom_body_class_meta_box_callback' ),
				$post_type,
				'side'
			);
		}
	}

	function custom_body_class_meta_box_callback( $post ) {
		// Add a nonce field so we can check for it later.
		wp_nonce_field( 'custom_body_class_meta_box', 'custom_body_class_meta_box_nonce' );

		/*
		 * Use get_post_meta() to retrieve an existing value
		 * from the database and use the value for the form.
		 */
		$value = get_post_meta( $post->ID, '_custom_body_class', true );

		echo '<label for="body_class_new_field">';
		_e( 'Add here your unique CSS class:', 'body_class_textdomain' );
		echo '</label> ';
		echo '<input type="text" id="custom_body_class_value" name="custom_body_class_value" value="' . esc_attr( $value ) . '" size="32" />';
	}

	/**
	 * When the post is saved, saves our custom data.
	 * @param int $post_id The ID of the post being saved.
	 */
	function custom_body_class_save_meta_data( $post_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */

		// Check if our nonce is set and if it's valid.
		if ( ! isset( $_POST['custom_body_class_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['custom_body_class_meta_box_nonce'], 'custom_body_class_meta_box' ) ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}

		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		/* OK, it's safe for us to save the data now. */

		// Make sure that it is set.
		if ( ! isset( $_POST['custom_body_class_value'] ) || empty( $_POST['custom_body_class_value'] ) ) {
			return;
		}

		global $post;
		$old_value = get_post_meta( $post_id, '_custom_body_class', true );
		$sanitized_value = sanitize_html_class( $_POST['custom_body_class_value'] );

		if ( ! empty( $sanitized_value ) ) {
			update_post_meta( $post_id, '_custom_body_class', $sanitized_value, $old_value);
		}
	}

	function add_post_type_custom_body_class_in_front( $classes ){

		global $post;

		if ( isset ( $post->ID ) ) {
			$value = sanitize_html_class( get_post_meta( $post->ID, '_custom_body_class', true ) );

			if  ( ! empty( $value ) ) {
				$classes[] = $value;
			}
		}

		// return the $classes array
		return $classes;
	}

	function ajax_no_access() {
		echo 'you have no access here';
		die();
	}

	function get_the_post_id( $id, $post_type = 'post' ) {
		if(function_exists('icl_object_id')) {
			return icl_object_id($id, $post_type, true);
		} else {
			return $id;
		}
	}

	public function get_plugin_settings() {

		if ( $this->plugin_settings === null ) {
			$this->plugin_settings = get_option( 'custom_body_class_settings' );
		}

		return $this->plugin_settings;
	}

	static function get_base_path() {
		return plugin_dir_path( __FILE__ );
	}
}
