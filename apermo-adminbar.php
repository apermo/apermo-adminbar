<?php
/**
 * Apermo AdminBar
 *
 * @package apermo-adminbar
 *
 * @wordpress-plugin
 * Plugin Name: Apermo AdminBar
 * Plugin URI: https://wordpress.org/plugins/apermo-adminbar/
 * Version: 1.1.2
 * Description: A simple plugin that enhances the AdminBar with navigation links between your different stages, a statusbox about the current post and keyboard shortcuts to hide or show the adminbar
 * Author: Christoph Daum
 * Author URI: http://apermo.de/
 * Text Domain: apermo-adminbar
 * Domain Path: /languages/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Apermo AdminBar
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
	 * Indicator if the sites were loaded from a filter.
	 *
	 * @var bool
	 */
	private $is_from_filter = false;

	/**
	 * Indicator if DomainMapping from WordPress MU Domain Mapping for Mulitsite is active
	 *
	 * @var bool
	 */
	private $domain_mapping = false;

	/**
	 * ApermoAdminBar constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->load_translation();

		//Check if domain_mapping is active.
		if ( $wpdb->dmtable === $wpdb->base_prefix . 'domain_mapping' ) {
			$this->domain_mapping = true;
		}

		if ( ! is_admin() && apply_filters( 'apermo-adminbar-watermark', true ) ) {
			require_once __DIR__ . '/classes/class.watermark.php';
			new ApermoAdminBarWatermark();
		}

		if ( apply_filters( 'apermo-adminbar-statusbox', true ) ) {
			require_once __DIR__ . '/classes/class.statusbox.php';
			new ApermoAdminBarMetabox();
		}

		if ( apply_filters( 'apermo-adminbar-keycodes', true ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'js_keycodes' ) );
		}

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ) , array( $this, 'plugin_action_links' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'sort_admin_colors' ), 99 );

		add_action( 'admin_enqueue_scripts', array( $this, 'color_scheme' ), 99 );

		//has to be loaded as early as possible to ensure that the css does not overwrite theme css.
		add_action( 'admin_bar_init', array( $this, 'color_scheme' ), 1 );

		add_filter( 'get_user_option_admin_color', array( $this, 'filter_admin_color' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'options_reading' ) );

		add_filter( 'pre_option_blog_public', array( $this, 'blog_public' ), 99, 2 );
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param	mixed $links Plugin Action links.
	 * @return	array
	 */
	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'options-general.php?page=apermo_adminbar' ) . '" title="' . esc_attr( __( 'View Apermo AdminBar Settings', 'apermo-adminbar' ) ) . '">' . __( 'Settings', 'wp-marketeer' ) . '</a>',
		);

		return array_merge( $action_links, $links );
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
		 * 'key_for_form' => array( 'label' => 'Readable Label', 'descroption' => 'Short description', 'robots' => 'yes'|'no'|false )
		 */
		$types = array(
			'dev' => array(
				'label' => __( 'Development Site', 'apermo-adminbar' ),
				'description' => __( 'Your development site, probably a local version on the development machine', 'apermo-adminbar' ),
				'default' => 'sunrise',
				'robots' => 'yes',
				'whitelist' => array(),
			),
			'staging' => array(
				'label' => __( 'Staging Site', 'apermo-adminbar' ),
				'description' => __( 'Your staging site, for testing and other purposes', 'apermo-adminbar' ),
				'default' => 'blue',
				'robots' => 'yes',
				'whitelist' => array(),
			),
			'live' => array(
				'label' => __( 'Live Site', 'apermo-adminbar' ),
				'description' => __( 'Your production site', 'apermo-adminbar' ),
				'default' => 'fresh',
				'robots' => false,
				'whitelist' => array(),
			),
		);

		remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

		// Allow to add (or remove) further page types via filter.
		$this->allowed_page_types = apply_filters( 'apermo-adminbar-types', $types );
		// 'all' is reserved, it is used for the serialized data, and it would possibly create side effects.
		unset( $this->allowed_page_types['all'] );
		$this->load_sites();
		if ( count( $this->sites ) ) {
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_filter' ), 99 );

			$this->set_current();
		}
	}

	/**
	 * Filter for pre_option_blog_public
	 *
	 * @param $return
	 * @param $option
	 *
	 * @return boolean
	 */
	public function blog_public( $return, $option ) {
		if ( ! isset( $this->current ) ) {
			return $return;
		}

		switch ( $this->sites[ $this->current ]['robots'] ) {
			case 'yes':
				return 0;
				break;
			case 'no':
				return 1;
				break;
			default:
				return $return;
		}
	}

	/**
	 * Load Settings from Database
	 */
	private function load_sites() {
		// Check if a filter was added from within the theme.
		if ( has_filter( 'apermo-adminbar-sites' ) ) {
			$dummysites = array();
			foreach ( $this->allowed_page_types as $key => $allowed_page_type ) {
				$dummysites[ $key ]['name'] = $allowed_page_type['label'];
				$dummysites[ $key ]['color'] = $allowed_page_type['default'];
				$dummysites[ $key ]['url'] = '';
				$dummysites[ $key ]['robots'] = $allowed_page_type['robots'];
				$dummysites[ $key ]['whitelist'] = $allowed_page_type['whitelist'];

				if ( $this->domain_mapping ) {
					$dummysites[ $key ]['mapping_url'] = '';
				}
			}
			// Filter against a default set of sites and afterwards use the sanitize function.
			$this->is_from_filter = true;
			$this->sites = $this->sanitize( apply_filters( 'apermo-adminbar-sites', $dummysites ) );
		}
		// If the sites are still empty load the settings from the DB.
		if ( ! count( $this->sites ) ) {
			$this->is_from_filter = false;
			$this->sites = get_option( 'apermo_adminbar_sites', array() );
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
			$urls[] = $this->no_http_s( $site['url'] );

			//Multisite Domain Mapping Support.
			if ( isset( $site['mapping_url'] ) ) {
				$urls[] = $this->no_http_s( $site['mapping_url'] );
			}
			foreach ( $urls as $url ) {
				if ( $url && false !== strpos( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], $url ) ) {
					$this->current = $key;
					return;
				}
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

		register_admin_color_schemes();

		$this->admin_colors = $_wp_admin_css_colors;

		ksort( $this->admin_colors );

		if ( isset( $this->admin_colors['fresh'] ) ) {
			// Set Default ('fresh') and Light should go first.
			$this->admin_colors = array_filter( array_merge( array( 'fresh' => '', 'light' => '' ), $this->admin_colors ) );
		}

		$this->admin_colors = apply_filters( 'apermo-adminbar-colors', $this->admin_colors );
	}

	/**
	 * Load the AdminBar Color Scheme
	 */
	public function color_scheme() {
		if ( ! isset( $this->current ) ) {
			return;
		}

		$scheme = $this->admin_colors[ $this->sites[ $this->current ]['color'] ]->url;
		if ( current_user_can( 'edit_posts' ) && ( is_admin() || is_admin_bar_showing() ) ) {
			wp_enqueue_style( 'apermo-adminbar-colors', $scheme, array() );
			wp_enqueue_style( 'apermo-adminbar', plugins_url( 'css/style.css', __FILE__ ) );
		}
	}

	/**
	 * Filters the User defined admin_color and sets it to default.
	 *
	 * @return string
	 */
	public function filter_admin_color() {
		return 'fresh';
	}

	public function js_keycodes() {
		wp_enqueue_script( 'apermo-adminbar-js-keycodes', plugins_url( 'js/keycodes.js', __FILE__ ), array( 'jquery' ) );
	}

	/**
	 * Load the JS if on options-reading
	 */
	public function options_reading() {
		if ( ! isset( $this->current ) ) {
			return;
		}
		$screen = get_current_screen();

		if ( 'WP_Screen' == get_class( $screen ) && 'options-reading' == $screen->base && in_array( $this->sites[ $this->current ]['robots'], array( 'yes', 'no' ) ) ) {

			$data['disabled_message'] = __( '<strong>Notice:</strong> This feature has been disabled. See Settings -> Apermo AdminBar', 'apermo-adminbar' );
			wp_enqueue_script( 'apermo-adminbar', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery' ) );
			wp_localize_script( 'apermo-adminbar', 'apermo_adminbar', $data );
		}
	}

	/**
	 * Adds a spacer to the admin-bar
	 *
	 * Static on purpose, so that developers can add spacers to the admin-bar themselves without needing to copy the code
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP AdminBar.
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
	 * Filters the AdminBar to add the links between the different pages
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP AdminBar.
	 *
	 * @return void
	 */
	public function admin_bar_filter( $wp_admin_bar ) {
		if ( ! isset( $this->current ) ) {
			return;
		}

		// This feature is by default only for contributors or better.
		$caps_needed = apply_filters( 'apermo-adminbar-caps', 'edit_posts' );
		if ( ! current_user_can( $caps_needed ) ) {
			return;
		}

		$node_added = false;

		foreach ( $this->sites as $key => $site ) {
			// Check if there is a URL.
			if ( isset( $site['url'] ) && $site['url'] ) {
				// Makes no sense to add links to the site we are currently on.
				if ( $key !== $this->current ) {
					// Add the node to home of the other site.
					$base_url = $site['url'];
					if ( $this->domain_mapping && isset( $site['mapping_url'] ) && $site['mapping_url'] ) {
						$base_url = $site['mapping_url'];
					}

					//only go on if there is no whitelist, or user is whitelisted
					if ( ! count( $site['whitelist'] ) || in_array( get_current_user_id(), $site['whitelist'] ) ) {
						if ( ! $node_added ) {
							self::add_spacer( $wp_admin_bar );
						}
						$node_added = true;

						$wp_admin_bar->add_node( array(
							'id'		=> esc_attr( 'apermo_adminbar_menu_' . $key ),
							'title'		=> esc_html( $site['name'] ),
							'parent'	=> 'site-name',
							'href'		=> esc_url( $base_url ),
						) );
						// Check if we are on a different page than the homepage.
						if ( strlen( $this->get_request() ) > 1 ) {
							$wp_admin_bar->add_node( array(
								'id'		=> esc_attr( 'apermo_adminbar_menu_' . $key . '-same' ),
								'title'		=> esc_html( $site['name'] ) . ' ' . __( '(Same page)', 'apermo-adminbar' ),
								'parent'	=> 'site-name',
								'href'		=> esc_url( $base_url . $this->get_request() ),
							) );
						}
					}
				}
			}
		}

		if ( ! is_admin()  && $node_added ) {
			self::add_spacer( $wp_admin_bar );
		}
	}

	/**
	 * Get the Request Part that is not Subfolder for the WordPress installation.
	 */
	public function get_request() {
		if ( ! isset( $this->current ) ) {
			return;
		}

		$request = $this->no_http_s( esc_url_raw( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) );
		$url_types = array( 'url', 'mapping_url' );

		foreach ( $url_types as $url_type ) {
			$url = $this->no_http_s( $this->sites[ $this->current ][ $url_type ] );
			if ( 0 === strpos( $request, $url ) ) {
				return substr( $request, strlen( $url ) );
			}
		}

		if ( is_multisite() ) {
			$base_url = get_site_url( get_current_blog_id() );
		} else {
			$base_url = get_site_url();
		}
		$base_url = $this->no_http_s( $base_url );

		if ( 0 === strpos( $request, $base_url ) ) {
			return substr( $request, strlen( $base_url ) );
		}

		return esc_url_raw( $_SERVER['REQUEST_URI'] );
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
				<h1><?php esc_html_e( 'Apermo AdminBar', 'apermo-adminbar' ); ?></h1>
				<?php
				if ( $this->is_from_filter ) {
				?>
				<div id="setting-error-settings_updated" class="error settings-error notice">
					<p><strong><?php printf( __( 'The Filter %s is active, probably within your theme. These settings have been disabled.', 'apermo-adminbar' ), '<em>"apermo-adminbar-sites"</em>' ); ?></strong></p>
				</div>
				<fieldset disabled="disabled">
					<?php
					} else {
					?><fieldset><?php
						}
						settings_fields( 'apermo_adminbar' );
						do_settings_sections( 'apermo_adminbar' );
						submit_button();
						?>
					</fieldset>
			</form>
			<p class="clear"><strong>*) <?php esc_html_e( 'Sites without URL will not be saved to the database, name and color scheme will be dropped.', 'apermo-adminbar' ); ?></strong></p>
		</div>
		<div class="clear"></div>
		<?php
	}

	/**
	 * Adds the Settings Page to the Menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page( __( 'Apermo AdminBar', 'apermo-adminbar' ), __( 'Apermo AdminBar', 'apermo-adminbar' ), 'manage_options', 'apermo_adminbar', array( $this, 'options_page' ) );
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
					return esc_html( $data['description'] );
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
				'apermo_adminbar_sites_' . $key . '_robots',
				__( 'Search Engine Visibility' ),
				array( $this, 'robots_render' ),
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

			if ( $this->domain_mapping ) {
				add_settings_field(
					'apermo_adminbar_sites_' . $key . '_mapping_url',
					__( 'Mapping URL (Multisite)', 'apermo-adminbar' ),
					array( $this, 'mapping_url_render' ),
					'apermo_adminbar',
					'apermo_adminbar_sites_section_' . $key,
					array( 'key' => $key, 'data' => $data )
				);
			}

			add_settings_field(
				'apermo_adminbar_sites_' . $key . '_color',
				__( 'Color Scheme', 'apermo-adminbar' ),
				array( $this, 'color_render' ),
				'apermo_adminbar',
				'apermo_adminbar_sites_section_' . $key,
				array( 'key' => $key, 'data' => $data )
			);
		}

		add_settings_section(
			'apermo_adminbar_sites_section_serialized',
			__( 'Export/Import', 'apermo-adminbar' ),
			function(){},
			'apermo_adminbar'
		);
		add_settings_field(
			'apermo_adminbar_sites_import',
			__( 'Import', 'apermo-adminbar' ),
			array( $this, 'import_render' ),
			'apermo_adminbar',
			'apermo_adminbar_sites_section_serialized'
		);

		add_settings_field(
			'apermo_adminbar_sites_export',
			__( 'Export', 'apermo-adminbar' ),
			array( $this, 'export_render' ),
			'apermo_adminbar',
			'apermo_adminbar_sites_section_serialized'
		);
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
	 * Checkbox for robots
	 *
	 * @param array $args Arguments, especially the key for the input field.
	 */
	public function robots_render( $args ) {
		$setting = $this->sites[ $args['key'] ]['robots'];
		if ( ! in_array( $setting, array( 'yes', 'no' ) ) ) {
			$setting = 'default';
		}
		echo  __( 'Discourage search engines from indexing this site' ) . '<br>';
		echo '<label><input type="radio" id="apermo_adminbar_sites_' . esc_attr( $args['key'] ) . '_robots_yes" name="apermo_adminbar_sites[' . $args['key'] . '][robots]" value="yes" ' . checked( 'yes', $setting, false ) . '>' . __( 'Yes', 'apermo-adminbar' ) . '</label><br>';
		echo '<label><input type="radio" id="apermo_adminbar_sites_' . esc_attr( $args['key'] ) . '_robots_no" name="apermo_adminbar_sites[' . $args['key'] . '][robots]" value="no" ' . checked( 'no', $setting, false ) . '>' . __( 'No', 'apermo-adminbar' ) . '</label><br>';
		echo '<label><input type="radio" id="apermo_adminbar_sites_' . esc_attr( $args['key'] ) . '_robots_default" name="apermo_adminbar_sites[' . $args['key'] . '][robots]" value="default" ' . checked( 'default', $setting, false ) . '>' . sprintf( __( 'Use <a href="%s">Settings -> Read</a>', 'apermo-adminbar' ), get_site_url() . '/wp-admin/options-reading.php' ) . '</label><br>';
		echo '<p class="description">';
		if ( file_exists( ABSPATH . '/robots.txt' ) ) {
			_e( '<strong>Warning:</strong> This will only work, if you did not place a custom "robots.txt" into your root.', 'apermo-adminbar' );
			echo '<br>';
			_e( 'There is a robots.txt in your filesystem.', 'apermo-adminbar' );
		} else {
			_e( '<strong>Notice:</strong> This will only work, if you did not place a custom "robots.txt" into your root.', 'apermo-adminbar' );
			echo '<br>';
			_e( 'There was no robots.txt detected.', 'apermo-adminbar' );
		}
		echo '</p>';
	}

	/**
	 * Input for URL
	 *
	 * @param array $args Arguments, especially the key for the input field.
	 */
	public function url_render( $args ) {
		$setting = $this->sites[ $args['key'] ]['url'];
		echo '<input type="url" id="apermo_adminbar_sites_' . esc_attr( $args['key'] ) . '_url" name="apermo_adminbar_sites[' . $args['key'] . '][url]" placeholder="http://..." value="' . esc_attr( $setting ) . '" class="regular-text">*';
	}

	/**
	 * Input for URL
	 *
	 * @param array $args Arguments, especially the key for the input field.
	 */
	public function mapping_url_render( $args ) {
		$setting = $this->sites[ $args['key'] ]['mapping_url'];
		echo '<input type="url" id="apermo_adminbar_sites_' . esc_attr( $args['key'] ) . '_mapping_url" name="apermo_adminbar_sites[' . $args['key'] . '][mapping_url]" placeholder="http://..." value="' . esc_attr( $setting ) . '" class="regular-text">';
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
	 * Show the input for the serialized data, and the data themselves.
	 */
	public function import_render() {
		?>
		<label for="apermo_adminbar_sites_import"><?php esc_html_e( 'If you want import the setting from another site, just copy the code from the export section into this textarea. For advanced users, you can use the filter "apermo-adminbar-sites", see the readme for an example.', 'apermo-adminbar' ); ?></label>
		<p><strong style="color: #c00"><?php esc_html_e( 'Notice:', 'apermo-adminbar' ); ?></strong> <?php esc_html_e( 'If you enter a valid import in here, it will overwrite all settings above.', 'apermo-adminbar' ); ?></p>
		<textarea name="apermo_adminbar_sites[all]" rows="10" cols="50" id="apermo_adminbar_sites_import" class="large-text code"></textarea>
		<?php
	}

	/**
	 * Show the input for the serialized data, and the data themselves.
	 */
	public function export_render() {
		?>
		<?php if ( count( $this->sites ) ) { ?>
			<p><?php esc_html_e( 'Copy this this text into the import textarea of another site.', 'apermo-adminbar' ); ?></p>
			<pre style="max-width: 400px; white-space: pre-wrap;"><?php echo serialize( $this->sites ); ?></pre>
		<?php } else { ?>
			<p><?php esc_html_e( 'Nothing to export', 'apermo-adminbar' ); ?></p>
		<?php }
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

		if ( $input['all'] ) {
			$all = unserialize( trim( $input['all'] ) );
			if ( is_array( $all ) ) {
				$output = $this->sanitize( $all );

				if ( is_array( $output ) && count( $output ) ) {
					return $output;
				}
			}

			unset( $input['all'] );
		}

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

				//Multisite support, only the input field is conditional, so that this could still be set with a filter
				$data['mapping_url'] = trim( esc_url_raw( $data['mapping_url'] ), '/' );

				$data['robots'] = in_array( $data['robots'], array( 'yes', 'no' ) ) ? $data['robots'] : false;

				if ( is_array( $data['whitelist'] ) ) {
					foreach ( $data['whitelist'] as &$value ) {
						$value = (int) $value;
					}
					$data['whitelist'] = array_filter( $data['whitelist'] );
				} else {
					$data['whitelist'] = array();
				}

				// It only makes sense to save, if there is a URL, otherwise just drop it.
				if ( $data['url'] ) {
					$output[ $key ] = $data;
				}
			}
		}

		return $output;
	}

	/**
	 * Removes the https?:// from the beginning of a URL
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function no_http_s( $url ) {
		return trim( substr( $url, strpos( $url, '://' ) + 3 ), '/' );
	}
}

// Run boy, run!
add_action( 'plugins_loaded', function () {
	new ApermoAdminBar();
} );
