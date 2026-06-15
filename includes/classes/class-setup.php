<?php
/**
 * The setup plugin class, this will return register the post type and other needed items.
 *
 * @package flutterwave\payment_forms
 */

namespace flutterwave\payment_forms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Settings class.
 */
class Setup {

	/**
	 * Constructor: Registers the custom post type on WordPress 'init' action.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );
		add_action( 'plugin_action_links_' . PFF_FLUTTERWAVE_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'admin_head', [ $this, 'admin_menu_icon_style' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

    /**
     * Registers the custom post type 'flutterwave_form'.
     */
    public function register_post_type() {
        $labels = [
            'name'                  => esc_html__( 'Flutterwave Forms', 'pff-flutterwave' ),
            'singular_name'         => esc_html__( 'Flutterwave Form', 'pff-flutterwave' ),
            'add_new'               => esc_html__( 'Add New', 'pff-flutterwave' ),
            'add_new_item'          => esc_html__( 'Add Flutterwave Form', 'pff-flutterwave' ),
            'edit_item'             => esc_html__( 'Edit Flutterwave Form', 'pff-flutterwave' ),
            'new_item'              => esc_html__( 'Flutterwave Form', 'pff-flutterwave' ),
            'view_item'             => esc_html__( 'View Flutterwave Form', 'pff-flutterwave' ),
            'all_items'             => esc_html__( 'All Forms', 'pff-flutterwave' ),
            'search_items'          => esc_html__( 'Search Flutterwave Forms', 'pff-flutterwave' ),
            'not_found'             => esc_html__( 'No Flutterwave Forms found', 'pff-flutterwave' ),
            'not_found_in_trash'    => esc_html__( 'No Flutterwave Forms found in Trash', 'pff-flutterwave' ),
            'parent_item_colon'     => esc_html__( 'Parent Flutterwave Form:', 'pff-flutterwave' ),
            'menu_name'             => esc_html__( 'Flutterwave Forms', 'pff-flutterwave' ),
		];

        $args = [
            'labels'                => $labels,
            'hierarchical'          => true,
            'description'           => esc_html__( 'Flutterwave Forms filterable by genre', 'pff-flutterwave' ),
            'supports'              => array( 'title', 'editor' ),
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
			'show_in_rest'          => false,
            'menu_position'         => 5,
            'menu_icon'             => PFF_FLUTTERWAVE_PLUGIN_URL . '/assets/images/logo.png',
            'show_in_nav_menus'     => true,
            'publicly_queryable'    => true,
            'exclude_from_search'   => false,
            'has_archive'           => false,
            'query_var'             => true,
            'can_export'            => true,
            'rewrite'               => false,
            'comments'              => false,
            'capability_type'       => 'post',
		];
        register_post_type( 'flutterwave_form', $args );
    }

	/**
	 * Constrain admin menu icon size so the logo doesn't overflow the sidebar.
	 */
	public function admin_menu_icon_style() {
		echo '<style>#adminmenu #menu-posts-flutterwave_form .wp-menu-image img{width:20px;height:20px;padding:7px 0 0;object-fit:contain;}</style>';
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'pff-flutterwave', false, PFF_FLUTTERWAVE_PLUGIN_PATH . '/languages/' );
	}

	/**
	 * Add a link to our settings page in the plugin action links.
	 */
	public function add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . admin_url( 'edit.php?post_type=flutterwave_form&page=settings') . '">' . esc_html__( 'Settings', 'pff-flutterwave' ) . '</a>',
		);
		return array_merge( $settings_link, $links );
	}

	/**
	 * Enqueues our admin css.
	 */
	public function admin_enqueue_styles( $hook ) {
		if ( $hook != 'flutterwave_form_page_submissions' && $hook != 'flutterwave_form_page_settings' ) {
			return;
		}
		wp_enqueue_style( PFF_FLUTTERWAVE_PLUGIN_NAME,  PFF_FLUTTERWAVE_PLUGIN_URL . '/assets/css/flutterwave-admin.css', array(), PFF_FLUTTERWAVE_VERSION, 'all' );
	}

	/**
	 * Enqueue the Administration scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script( PFF_FLUTTERWAVE_PLUGIN_NAME, PFF_FLUTTERWAVE_PLUGIN_URL . '/assets/js/flutterwave-admin.js', array( 'jquery' ), PFF_FLUTTERWAVE_VERSION, false );
	}

	/**
	 * Enqueues our frontend styles
	 */
	public function enqueue_styles() {
        wp_enqueue_style( PFF_FLUTTERWAVE_PLUGIN_NAME . '-style', PFF_FLUTTERWAVE_PLUGIN_URL . '/assets/css/pff-flutterwave.css', array(), PFF_FLUTTERWAVE_VERSION, 'all' );
        wp_enqueue_style( PFF_FLUTTERWAVE_PLUGIN_NAME . '-font-awesome', PFF_FLUTTERWAVE_PLUGIN_URL . '/assets/css/font-awesome.min.css', array(), PFF_FLUTTERWAVE_VERSION, 'all' );
    }

	/**
	 * Enqueue the frontend scripts.
	 */
	public function enqueue_scripts() {

		$page_content = get_the_content();
		if ( ! has_shortcode( $page_content, 'pff-flutterwave' ) && ! has_shortcode( $page_content, 'flutterwave_form' ) ) {
			return;
		}

		wp_enqueue_script( 'blockUI', PFF_FLUTTERWAVE_PLUGIN_URL . '/assets/js/jquery.blockUI.min.js', array( 'jquery', 'jquery-ui-core' ), PFF_FLUTTERWAVE_VERSION, true );

		// Flutterwave Inline checkout script (v3).
		wp_register_script( 'Flutterwave', 'https://checkout.flutterwave.com/v3.js', false, PFF_FLUTTERWAVE_VERSION, true );
		wp_enqueue_script( 'Flutterwave' );

		wp_enqueue_script( PFF_FLUTTERWAVE_PLUGIN_NAME . '-public', PFF_FLUTTERWAVE_PLUGIN_URL . '/assets/js/flutterwave-public.js', array( 'jquery' ), PFF_FLUTTERWAVE_VERSION, true );

		$helpers = new Helpers();
		$js_args = [
			'key'          => $helpers->get_public_key(),
			'fee'          => $helpers->get_fees(),
			'logo'         => PFF_FLUTTERWAVE_PLUGIN_URL . '/assets/images/logo.png',
			'sitename'     => get_bloginfo( 'name' ),
			'confirmNonce' => wp_create_nonce( 'pff-flutterwave-confirm' ),
			'supportEmail' => get_option( 'admin_email' ),
			'homeUrl'      => home_url( '/' ),
			'i18n'         => [
				'contactSupport' => esc_html__( 'Contact Support', 'pff-flutterwave' ),
				'goHome'         => esc_html__( 'Return Home', 'pff-flutterwave' ),
			],
		];
		wp_localize_script( PFF_FLUTTERWAVE_PLUGIN_NAME . '-public', 'pffSettings', $js_args , PFF_FLUTTERWAVE_VERSION, true );
	}
}
