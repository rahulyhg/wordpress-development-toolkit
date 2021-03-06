<?php

namespace PHX_WP_DEVKIT\V_1_2\Admin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Class Product_Machine
 * @package WP_PHX_Dev_Kit\V_1_0\Admin
 */
class Product_Machine {

	/**
	 * Product_Machine constructor.
	 *
	 * @param string $filename
	 * @param string $origin_dir
	 * @param string $tmp_dir
	 * @param array $data
	 * @param array $config
	 */
	public function __construct( $filename, $origin_dir, $tmp_dir, $data, $config ) {
		add_filter( 'wp_phx_plugingen_file_contents', array( $this, 'process_file_contents_via_filter' ) );
		self::make_zip_download( $filename, $origin_dir, $tmp_dir, $data, $config );
	}

	/**
	 * Create a .ZIP download for the plugin generator
	 * @todo: abstract to use for multiple generators
	 *
	 * @param $filename
	 * @param $origin_dir
	 * @param $tmp_dir
	 * @param $data
	 * @param $config
	 */
	static function make_zip_download( $filename, $origin_dir, $tmp_dir, $data, $config ) {
		$data['mainFilename'] = $filename;
		$zip                  = new ZipArchive();

		$creation_success = $zip->open(
			trailingslashit( $tmp_dir ) . 'gen.zip',
			ZipArchive::CREATE && ZipArchive::OVERWRITE
		);

		// check we have filesystem write access
		if ( $creation_success ) {
			// write json containing configuration data
			$zip->addFromString( 'plugin-data.json', json_encode( array( 'data' => $data, 'config' => $config ) ) );
			// maybe include license
			if ( 'gpl' === $data['plugin_license'] ) {
				$zip->addFromString( 'LICENSE', file_get_contents( dirname( __FILE__ ) . '/templates/gpl.txt' ) );
			}
			// find every file within origin directory including nested files
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $origin_dir ) );
			foreach ( $iterator as $current_file ) {
				if ( !! stristr( strval( $current_file ), 'ds_store' ) ) {
					continue; // skip system files
				}
				if ( ( // no assets or simple build AND is .css, .js or class-assets.php, then skip
					   ! isset( $data['plugin_register_enqueue_assets'] )
				       || 'simple' === $data['plugin_arch_type']
				     )
				     && (
					     !! stristr( strval( $current_file ), '.css' )
				       || !! stristr( strval( $current_file ), '.js' )
				       || !! stristr( strval( $current_file ), 'class-assets.php' )
				     )
				) {
					continue;
				}
				// if simple AND isn't main.php, class-plugin.php or in wpphx dir, then skip
				if ( 'simple' === $data['plugin_arch_type']
				     && (
				     	! stristr( strval( $current_file ), 'main.php' )
					    && ! stristr( strval( $current_file ), 'class-plugin.php' )
					    && ! stristr( strval( $current_file ), 'wordpress-phoenix' )
				     )
				) {
					continue;
				}
				// VITAL FOR ALL FILES: give file relative path for zip
				$current_file_stub = str_replace( trailingslashit( $origin_dir ), '', $current_file );
				// Run WordPress Filter on File
				$processed_file = apply_filters( 'wp_phx_plugingen_file_contents', [
					'contents' => file_get_contents( $current_file ), // modified
					'filename' => $current_file_stub, // modified
					'data'     => $data, // data passthru, for read only
				] );
				// add maybe renamed, maybe rebuilt file to new zip
				if ( is_array( $processed_file ) && ! empty( $processed_file['contents'] ) && is_string( $processed_file['contents'] ) ) {
					$zip->addFromString( $processed_file['filename'], $processed_file['contents'] );
				}
			}

			// only run these operations for standard plugins
			if ( 'simple' !== $data['plugin_arch_type'] ) {
				// add empties to key directories
				$blank_file = '<?php ' . PHP_EOL . '// *gentle wave* not the code you\'re looking for..' . PHP_EOL;
				$idx        = '/index.php';
				$zip->addFromString( 'app' . $idx, $blank_file );
				$zip->addFromString( 'vendor' . $idx, $blank_file );
				$zip->addFromString( 'app/admin' . $idx, $blank_file );
				$zip->addFromString( 'app/assets' . $idx, $blank_file );
				$zip->addFromString( 'app/includes' . $idx, $blank_file );

				// include options panel
				if ( isset( $data['plugin_opts_panel'] ) && 'on' === $data['plugin_opts_panel'] ) {
					$zip->addFromString(
						'vendor/wordpress-phoenix/wordpress-options-builder-class/wordpress-phoenix-options-panel.php',
						file_get_contents( dirname( __FILE__ ) . '/templates/wpop.php' )
					);
				}
			}

			// close zip
			$zip->close();

			// Tell PHP we're gonna download the zip using headers
			header( 'Content-type: application/zip' );
			header( sprintf( 'Content-Disposition: attachment; filename="%s.zip"', $filename ) );

			// read and unset temporary file
			readfile( $tmp_dir . '/gen.zip' );
			unlink( $tmp_dir . '/gen.zip' );
			die();
		} else {
			$zip->close();
			unlink( $tmp_dir . '/gen.zip' );
			wp_die( 'ZipArchive failed to create temporary zip. Probably need to chmod the directory permissions.' );
		}
	}

	function process_file_contents_via_filter( $file ) {
		if (
			empty( $file )
			|| ! is_array( $file )
			|| ! isset( $file['contents'] )
			|| ! isset( $file['filename'] )
			|| ! isset( $file['data'] )
		) {
			return $file;
		}

		$file['contents'] = self::process_file_contents( $file );
		$file['filename'] = self::process_filename( $file );

		return $file;
	}

	static function process_file_contents( $file ) {
		$contents = $file['contents'];
		$d        = $file['data'];
		$filename = $file['filename'];

		$contents       = str_ireplace( '<%= NAME %>', $d['plugin_name'], $contents );
		$contents       = str_ireplace( '<%= PRIMARY_NAMESPACE %>', $d['plugin_primary_namespace'], $contents );
		$contents       = str_ireplace( '<%= SECONDARY_NAMESPACE %>', $d['plugin_secondary_namespace'], $contents );
		$sanitized_name = sanitize_title_with_dashes( $d['plugin_name'] );
		$contents       = str_ireplace( '<%= SLUG %>', $sanitized_name, $contents );
		$contents       = str_ireplace( '<%= PKG %>', str_ireplace( '-', '_', ucwords( $sanitized_name ) ), $contents );

		if ( 'main.php' === $filename || 'README.md' === $filename ) {
			$contents = str_ireplace( '<%= AUTHORS %>', $d['plugin_authors'], $contents );
			$contents = str_ireplace( '<%= TEAM %>', ! empty( $d['plugin_teamorg'] ) ? ' - ' . $d['plugin_teamorg'] : '', $contents );
			$contents = str_ireplace( '<%= LICENSE_TEXT %>', self::version_text( $d ), $contents );
			$contents = str_ireplace( '<%= VERSION %>', ! empty( $d['plugin_ver'] ) ? $d['plugin_ver'] : '0.1.0', $contents );
			$contents = str_ireplace( '<%= DESC %>', $d['plugin_description'], $contents );
			if ( ! empty( $d['plugin_url'] ) ) {
				$url = $d['plugin_url'];
			} elseif ( ! empty( $d['plugin_repo_url'] ) ) {
				$url = $d['plugin_repo_url'];
			} else {
				$url = '';
			}
			$contents = str_ireplace( '<%= GITHUB_URL %>', $d['plugin_repo_url'], $contents );
			$contents = str_ireplace( '<%= URL %>', $url, $contents );
			$contents = str_ireplace( '<%= YEAR %>', current_time( "Y" ), $contents );
			$contents = str_ireplace( '<%= CURRENT_TIME %>', current_time( 'l jS \of F Y h:i:s A' ), $contents );
			$contents = str_ireplace( '<%= GENERATOR_VERSION %>', $d['generator_version'], $contents );

			$panel_str = '';
			if ( isset( $d['plugin_opts_panel'] ) && 'on' === $d['plugin_opts_panel'] ) {
				$panel_str = "
				
// Load Options Panel
if ( ! class_exists( 'WPOP\\V_3_1\\\Page' ) ) {
	include_once  trailingslashit( dirname( __FILE__ ) )  . 'vendor/wordpress-phoenix/wordpress-options-builder-class/wordpress-phoenix-options-panel.php';
}";
			}
			$contents = str_ireplace( '<%= INSTANTIATE_OPTIONS_PANEL %>', $panel_str, $contents );
		}

		if ( stripos( $filename, 'class-plugin.php' ) ) {
			if ( 'simple' === $d['plugin_arch_type'] ) {
				$includes_init = '';
				$admin_init    = '';

			} else {
				$includes_init = 'new Includes\Init(
					trailingslashit( $this->installed_dir ),
					trailingslashit( $this->installed_url ),
					$this->version
				);';
				$admin_init    = 'new Admin\Init(
					trailingslashit( $this->installed_dir ),
					trailingslashit( $this->installed_url ),
					$this->version
				);';
			}

			$contents = str_ireplace( '<%= INCLUDES_INIT %>', $includes_init, $contents );
			$contents = str_ireplace( '<%= ADMIN_INIT %>', $admin_init, $contents );
		}

		if ( ! isset( $d['plugin_register_enqueue_assets'] ) && stripos( $filename, 'class-init.php' ) ) {
			$initStr = '
		// handle global assets
		new Assets(
			$this->installed_dir,
			$this->installed_url,
			$version
		);';
			$contents = str_ireplace( $initStr, '', $contents );
			$authInit = '
		// handle authenticated stylesheets and scripts
		new Auth_Assets(
			$this->installed_dir,
			$this->installed_url,
			$this->version
		);';
			$contents = str_ireplace( $authInit, '', $contents );
		}

		return $contents;
	}

	static function version_text( $data ) {
		if ( isset( $data['plugin_license'] ) ) {
			switch ( $data['plugin_license'] ) {
				case 'private':
					return 'Private. Do not distribute. Copyright ' . date( "Y" ) . ' All Rights Reserved.';
					break;
				case 'gpl':
				default:
					return 'GNU GPL v2.0+';
					break;
			}
		} else {
			return 'Unlicensed [Error in plugin generation].';
		}
	}

	static function process_filename( $file ) {
		if ( 'main.php' === $file['filename'] ) {
			return $file['data']['mainFilename'] . '.php';
		}

		if ( stripos( $file['filename'], '.css' ) || stripos( $file['filename'], '.js' ) ) {
			return str_ireplace( 'plugin-', $file['data']['mainFilename'] . '-', $file['filename'] );
		}

		return $file['filename'];
	}
}
