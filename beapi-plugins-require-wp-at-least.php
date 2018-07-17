<?php
/**
 * Plugin name: Be API Plugins Require WP at Least
 * Description: Stop installing plugins that are not compatible with your WP version
 * Author: Be API
 * Author URI: https://beapi.fr
 * Version: 1.0.2
 */
defined( 'DB_USER' )
	or die ( '~No~' );

define( 'BEAPI_PRAL_URL', plugin_dir_url( __FILE__ ) );
define( 'BEA_PRAL_VERSION', '1.0.2' );

/**
 * That class adds some warning on plugin page
 * if any plugin is incompatible with current wp version
 * according to its readme.txt
 *
 * @author Julien Maury
 */
class Beapi_Plugins_Require_WP_At_Least {

	/**
	 * @var string $at_least
	 */
	protected $at_least;


	/**
	 * Run wp hooks
	 * @return bool
	 * @author Julien Maury
	 */
	public function add_hooks() {

		if ( ! $this->is_allowed_to_see() ) {
			return false;
		}

		add_filter( 'admin_init', [ $this, 'load_i18n' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );// same hook in multisite
		add_filter( 'plugin_action_links', [ $this, 'plugin_action_links' ], 10, 2 );// same hook in multisite
		add_filter( 'beapi_pral_is_allowed', [ $this, 'is_allowed_to_see_in_multisite' ] );
		add_filter( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * @param $hook_suffix
	 *
	 * @return bool
	 * @author Julien Maury
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {

		if ( 'plugins.php' !== $hook_suffix ) {
			return false;
		}

		wp_enqueue_style( 'atleast', BEAPI_PRAL_URL . 'css/atleast.css' );
		wp_enqueue_script(
			'atleast',
			BEAPI_PRAL_URL . 'js/atleast.js',
			['jquery'],
			BEA_PRAL_VERSION,
			true
		);
	}

	/**
	 * Add translation files
	 * @author Julien Maury
	 */
	public function load_i18n() {
		load_plugin_textdomain(
			'beapi-pral',
			false,
			basename( dirname( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * This hooks allows us to delete action links
	 * such as "activate" for each plugin that is not compatible
	 * with current WP version
	 *
	 * @param $links
	 * @param $plugin_file
	 *
	 * @return mixed
	 * @author Julien Maury
	 */
	public function plugin_action_links( $links, $plugin_file ) {
		if ( ! $this->is_compatible_with_current_wp( $plugin_file ) ) {
			// if not compatible then unset link that allows user to activate the plugin
			unset( $links['activate'] );
		}

		return $links;
	}

	/**
	 * @param $plugin_meta
	 * @param $plugin_file
	 *
	 * @return array
	 * @author Julien Maury
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {

		if ( ! $this->is_compatible_with_current_wp( $plugin_file ) ) {

			$warning = [
				$this->get_warning_message(),
			];

			return array_merge( $plugin_meta, $warning );
		}

		return $plugin_meta;
	}

	/**
	 * @return mixed
	 * @author Julien Maury
	 */
	public function is_allowed_to_see() {
		return (bool) apply_filters( 'beapi_pral_is_allowed', current_user_can( 'manage_options' ) );
	}

	/**
	 * Makes the plugin multisite compatible
	 * @param $is_allowed
	 *
	 * @return bool
	 * @author Julien Maury
	 */
	public function is_allowed_to_see_in_multisite( $is_allowed ) {
		return is_multisite() ? is_super_admin() : $is_allowed;
	}

	/**
	 * @param $file
	 *
	 * @return bool
	 * @author Julien Maury
	 */
	protected function is_compatible_with_current_wp( $file ) {

		$README = $this->get_readme_path( $file );

		if ( empty( $README ) ) {
			return true;
		}

		$this->at_least = $this->get_require_at_least( $README );


		if ( empty( $this->at_least ) ) {
			return true;
		}

		return (bool) apply_filters(
			'beapi_pral_is_compatible',
			version_compare( $GLOBALS['wp_version'], $this->at_least, '>' ),
			$this->at_least,
			$GLOBALS['wp_version']
		);
	}

	/**
	 * @return string
	 * @author Julien Maury
	 */
	protected function get_warning_message() {
		return '<span class="warning-alert-incompatible dashicons-before dashicons-warning"> '
		       . sprintf(
		       	    esc_html__( 'This plugin is incompatible with your current version of WordPress ! You have WordPress %s and you need at least WordPress %s.', 'beapi-pral' ),
			       $GLOBALS['wp_version'],
		            $this->at_least
		       )
		       . ' </span>';
	}

	/**
	 * There are some cases where the readme file
	 * is uppercase
	 * @param $file
	 *
	 * @return bool|string
	 * @author Julien Maury
	 */
	protected function get_readme_path( $file ) {
		$dir = WP_PLUGIN_DIR . '/' . dirname( $file ) . '/';

		// annoying plugins with upper and lower cases...
		if ( file_exists( $dir . 'README.txt' ) ) {
			$readme = 'README.txt';
		} elseif ( file_exists( $dir . 'readme.txt' ) ) {
			$readme = 'readme.txt';
		} else {
			return false;
		}

		return $dir . $readme;
	}

	/**
	 * Use built in wp function
	 * to get data from readme.txt
	 * @param $readme_path
	 *
	 * @return bool
	 * @author Julien Maury
	 */
	protected function get_require_at_least( $readme_path ) {
		/**
		 * @see https://developer.wordpress.org/reference/functions/get_file_data/
		 */
		$plugin_data = get_file_data( $readme_path, [ 'requires' => 'Requires at least' ] );

		if ( empty( $plugin_data['requires'] ) ) {
			return false;
		}

		return $plugin_data['requires'];
	}

}

add_action( 'plugins_loaded', function () {
	( new Beapi_Plugins_Require_WP_At_Least() )->add_hooks();
} );