<?php

namespace PHX_WP_DEVKIT\V_1_2;

use PHX_WP_DEVKIT\V_1_2\Admin;
use PHX_WP_DEVKIT\V_1_2\Includes;
use WPAZ_Plugin_Base\V_2_5\Abstract_Plugin;

/**
 * Class Plugin
 * @package Wordpress_development_toolkit
 */
class Plugin extends Abstract_Plugin {

	/**
	 * @var string
	 */
	public static $autoload_class_prefix = __NAMESPACE__;

	/**
	 * @var string
	 */
	public static $autoload_type = 'psr-4';

	/**
	 * @var int
	 */
	public static $autoload_ns_match_depth = 2;

	/**
	 * @var string
	 */
	protected static $current_file = __FILE__;

	/**
	 * @param mixed $instance
	 */
	public function onload( $instance ) {
	}

	/**
	 * Initialize public / shared functionality
	 */
	public function init() {
		do_action( get_called_class() . '_before_init' );
		new Includes\Init(
					trailingslashit( $this->installed_dir ),
					trailingslashit( $this->installed_url ),
					$this->version
				);
		do_action( get_called_class() . '_after_init' );
	}

	/**
	 * Initialize functionality only loaded for logged-in users
	 */
	public function authenticated_init() {
		if ( is_user_logged_in() ) {
			do_action( get_called_class() . '_before_authenticated_init' );
			new Admin\Init(
					trailingslashit( $this->installed_dir ),
					trailingslashit( $this->installed_url ),
					$this->version
				);
			do_action( get_called_class() . '_after_authenticated_init' );
		}
	}

	/**
	 * @return mixed|void
	 */
	protected function defines_and_globals() {
	}

} // END class Plugin
