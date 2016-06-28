<?php
/**
 * Apermo Adminbar
 *
 * @package apermo-adminbar
 *
 * @wordpress-plugin
 * Plugin Name: Apermo Admin Bar
 * Version: 0.9.1
 * Description: A simple plugin that allows you to add custom links to the admin bar, navigation between your live and dev systems
 * Author: Christoph Daum
 * Author URI: http://apermo.de/
 * Text Domain: apermo-adminbar
 * Domain Path: /languages/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Apermo Admin Bar
 * Copyright (C) 2016, Christoph Daum - info@apermo.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class ApermoAdminBar {
	/**
	 * Contains the known sites
	 *
	 * @var array
	 */
	private $sites = array();

	/**
	 * Static Counter for spacers
	 *
	 * @var int
	 */
	private static $spacer_count;

	/**
	 * Containing the current site
	 *
	 * @var string
	 */
	private $current;

	/**
	 * Contains the allowed page types
	 *
	 * @var array
	 */
	private $allowed_page_types = array();

	/**
	 * Private copy of $_wp_admin_css_colors
	 *
	 * @var array
	 */
	private $admin_colors = array();

	/**
	 * ApLiveDevAdminBar constructor.
	 */
	public function __construct() {
		$this->sites = get_option( 'apermo_adminbar_sites', array() );
		$this->load_translation();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_init', array( $this, 'sort_admin_colors' ), 99 );

		add_action( 'init', array( $this, 'init' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'color_scheme' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'color_scheme' ), 99 );
	}

	/**
	 * Loading Textdomain
	 *
	 * Thanks to @kau-boy
	 */
	public function load_translation() {
		load_plugin_textdomain( 'apermo-adminbar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Called on init-hook
	 */
	public function init() {
		/**
		 * Entry format
		 *
		 * 'key_for_form' => array( 'label' => 'Readable Label', 'descroption' => 'Short description' )
		 */
		$types = array(
			'dev' => array(
				'label' => __( 'Development Site', 'apermo-adminbar' ),
				'description' => __( 'Your development site, probably a local version on the development machine', 'apermo-adminbar' ),
				'default' => 'sunrise',
			),
			'staging' => array(
				'label' => __( 'Staging Site', 'apermo-adminbar' ),
				'description' => __( 'Your staging site, for testing and other purposes', 'apermo-adminbar' ),
				'default' => 'blue',
			),
			'live' => array(
				'label' => __( 'Live Site', 'apermo-adminbar' ),
				'description' => __( 'Your production site', 'apermo-adminbar' ),
				'default' => 'fresh',
			),
		);

		remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

		// Allow to add (or remove) further page types via filter.
		$this->allowed_page_types = apply_filters( 'apermo-adminbar-types', $types );
		if ( count( $this->sites ) ) {
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_filter' ), 99 );

			$this->set_current();
		}
	}

	/**
	 * Set $this->current for later use
	 *
	 * @return void
	 */
	private function set_current() {
		foreach ( $this->sites as $key => $site ) {
			// Just give me the domain + everything that follows.
			$url = trim( substr( $site['url'], strpos( $site['url'], '://' ) + 3 ), '/' );
			if ( $url && false !== strpos( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], $url ) ) {
				$this->current = $key;
				return;
			}
		}
	}

	/**
	 * Sort the Admin colors
	 *
	 * Based on the function admin_color_scheme_picker( $user_id ) from WordPress Core
	 *
	 * @return void
	 */
	public function sort_admin_colors() {
		global $_wp_admin_css_colors;

		$this->admin_colors = $_wp_admin_css_colors;

		ksort( $this->admin_colors );

		if ( isset( $this->admin_colors['fresh'] ) ) {
			// Set Default ('fresh') and Light should go first.
			$this->admin_colors = array_filter( array_merge( array( 'fresh' => '', 'light' => '' ), $this->admin_colors ) );
		}

		$this->admin_colors = apply_filters( 'apermo-adminbar-colors', $this->admin_colors );
	}

	/**
	 * Load the Admin Bar Color Scheme
	 */
	public function color_scheme() {
		$scheme = $this->sites[ $this->current ]['scheme_url'];
		if ( current_user_can( 'edit_posts' ) && ( is_admin() || is_admin_bar_showing() ) ) {
			wp_enqueue_style( 'apermo-adminbar-colors', $scheme, array() );
			wp_enqueue_style( 'apermo-adminbar', plugins_url( 'css/style.css', __FILE__ ) );
		}
	}

	/**
	 * Adds a spacer to the admin-bar
	 *
	 * Static on purpose, so that developers can add spacers to the admin-bar themselves without needing to copy the code
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP Admin Bar.
	 */
	public static function add_spacer( $wp_admin_bar ) {
		$wp_admin_bar->add_node( array(
			'id'		=> 'spacer' . self::$spacer_count,
			'title'		=> '',
			'parent'	=> 'site-name',
			'href'		=> false,
			'meta'		=> array(
				'class' => 'spacer',
			),
		) );
		self::$spacer_count++;
	}

	/**
	 * Filters the Admin Bar to add the links between the different pages
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP Admin Bar.
	 *
	 * @return void
	 */
	public function admin_bar_filter( $wp_admin_bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			// This feature is only for contributors or better.
			return;
		}
		self::add_spacer( $wp_admin_bar );

		foreach ( $this->sites as $key => $site ) {
			// Check if there is a URL.
			if ( isset( $site['url'] ) && $site['url'] ) {
				// Makes no sense to add links to the site we are currently on.
				if ( $key !== $this->current ) {
					// Add the node to home of the other site.
					$wp_admin_bar->add_node( array(
						'id'		=> esc_attr( 'apermo_adminbar_menu_' . $key ),
						'title'		=> esc_html( $site['name'] ),
						'parent'	=> 'site-name',
						'href'		=> esc_url( $site['url'] ),
					) );
					// Check if we are on a different page than the homepage.
					// Todo: Will probably break if WordPress installed in a subdirectory.
					if ( strlen( $_SERVER['REQUEST_URI'] ) > 1 ) {
						$wp_admin_bar->add_node( array(
							'id'		=> esc_attr( 'apermo_adminbar_menu_' . $key . '-same' ),
							'title'		=> esc_html( $site['name'] ) . ' ' . __( '(Same page)', 'apermo-adminbar' ),
							'parent'	=> 'site-name',
							'href'		=> esc_url( $site['url'] . $_SERVER['REQUEST_URI'] ),
						) );
					}
				}
			}
		}
		if ( ! is_admin() ) {
			self::add_spacer( $wp_admin_bar );
		}
	}

	/**
	 * Options page callback
	 *
	 * @return void
	 */
	public function options_page() {
		?>
		<div class="wrap">
			<form action='options.php' method='post'>
				<h1><?php esc_html_e( 'Apermo Admin Bar', 'apermo-adminbar' ); ?></h1>
				<?php
				settings_fields( 'apermo_adminbar' );
				do_settings_sections( 'apermo_adminbar' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Adds the Settings Page to the Menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page( __( 'Apermo Admin Bar', 'apermo-adminbar' ), __( 'Apermo Admin Bar', 'apermo-adminbar' ), 'manage_options', 'apermo_adminbar', array( $this, 'options_page' ) );
	}

	/**
	 * Adds the Settings
	 */
	public function settings_init() {
		register_setting( 'apermo_adminbar', 'apermo_adminbar_sites', array( $this, 'sanitize' ) );

		foreach ( $this->allowed_page_types as $key => $data ) {
			add_settings_section(
				'apermo_adminbar_sites_section_' . $key,
				$data['label'],
				function( $data ) {
					echo esc_html( $data['description'] );
				},
				'apermo_adminbar'
			);

			add_settings_field(
				'apermo_adminbar_sites_' . $key . '_name',
				__( 'Name', 'apermo-adminbar' ),
				array( $this, 'name_render' ),
				'apermo_adminbar',
				'apermo_adminbar_sites_section_' . $key,
				array( 'key' => $key, 'data' => $data )
			);

			add_settings_field(
				'apermo_adminbar_sites_' . $key . '_url',
				__( 'URL', 'apermo-adminbar' ),
				array( $this, 'url_render' ),
				'apermo_adminbar',
				'apermo_adminbar_sites_section_' . $key,
				array( 'key' => $key, 'data' => $data )
			);

			add_settings_field(
				'apermo_adminbar_sites_' . $key . '_color',
				__( 'Color Scheme', 'apermo-adminbar' ),
				array( $this, 'color_render' ),
				'apermo_adminbar',
				'apermo_adminbar_sites_section_' . $key,
				array( 'key' => $key, 'data' => $data )
			);
		}
	}

	/**
	 * Adds a description to the section
	 */
	public function sites_callback() {
		esc_html_e( 'This section description', 'apermo-adminbar' );
	}

	/**
	 * Input for Name
	 *
	 * @param array $args Arguments, especially the key for the input field.
	 */
	public function name_render( $args ) {
		$setting = $this->sites[ $args['key'] ]['name'];
		echo '<input type="text" id="apermo_adminbar_sites_' . esc_attr( $args['key'] ) . '_name" name="apermo_adminbar_sites[' . $args['key'] . '][name]" placeholder="' . esc_attr( $args['data']['label'] ) . '" value="' . esc_attr( $setting ) . '" class="regular-text">';
	}

	/**
	 * Input for URL
	 *
	 * @param array $args Arguments, especially the key for the input field.
	 */
	public function url_render( $args ) {
		$setting = $this->sites[ $args['key'] ]['url'];
		echo '<input type="url" id="apermo_adminbar_sites_' . esc_attr( $args['key'] ) . '_url" name="apermo_adminbar_sites[' . $args['key'] . '][url]" placeholder="http://..." value="' . esc_attr( $setting ) . '" class="regular-text">';
	}

	/**
	 * Adding a Color Picker
	 * Based on the function admin_color_scheme_picker( $user_id ) from WordPress Core
	 *
	 * @param array $args Arguments, especially the key for the input field.
	 */
	public function color_render( $args ) {
		$key = $args['key'];
		$current_color = $this->sites[ $args['key'] ]['color'];

		if ( empty( $current_color ) || ! isset( $this->admin_colors[ $current_color ] ) ) {
			$current_color = $args['data']['default'];
		}
		?>
		<fieldset id="color-picker" class="scheme-list">
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Admin Color Scheme' ); ?></span></legend>
			<?php
			wp_nonce_field( 'save-color-scheme', 'color-nonce', false );
			foreach ( $this->admin_colors as $color => $color_info ) :

				?>
				<div class="color-option <?php echo ( $color === $current_color ) ? 'selected' : ''; ?>">
					<label><input name="apermo_adminbar_sites[<?php echo $key; ?>][color]" type="radio" value="<?php echo esc_attr( $color ); ?>" class="tog" <?php checked( $color, $current_color ); ?> />
						<?php echo esc_html( $color_info->name ); ?>
					</label>
					<table class="color-palette">
						<tr>
							<?php

							foreach ( $color_info->colors as $html_color ) {
								?>
								<td style="background-color: <?php echo esc_attr( $html_color ); ?>">&nbsp;</td>
								<?php
							}

							?>
						</tr>
					</table>
				</div>
				<?php

			endforeach;

			?>
		</fieldset>
		<?php
	}


	/**
	 * Sanitizes the input
	 *
	 * @param array $input The input forwarded from WordPress.
	 *
	 * @return mixed
	 */
	public function sanitize( $input ) {
		$output = array();

		// Check all incoming pages.
		foreach ( $input as $key => $data ) {
			// Probably useless, but safety is the mother of the Porzellankiste.
			$key = sanitize_key( $key );
			// Check if the incoming page exists, otherwise ignore.
			if ( array_key_exists( $key, $this->allowed_page_types ) ) {
				$data['name'] = esc_html( strip_tags( $data['name'] ) );

				if ( ! array_key_exists( $data['color'], $this->admin_colors ) ) {
					$data['color'] = $this->allowed_page_types[ $key ]['default'];
				}

				$data['url'] = trim( esc_url_raw( $data['url'] ), '/' );

				// Store the URL, so that we dont' need to init $_wp_admin_css_colors.
				$data['scheme_url'] = $this->admin_colors[ $data['color'] ]->url;

				$output[ $key ] = $data;
			}
		}

		return $output;
	}
}

// Run boy, run!
add_action( 'plugins_loaded', function () {
	new ApermoAdminBar();
} );

