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
 * Requires at least: 5.3
 * Requires PHP: 5.6.20
 */

require_once __DIR__ . '/classes/class-apermoadminbar.php';

function apermo_adminbar_init() {
	$admin_bar = new ApermoAdminBar();

	add_action( 'admin_menu', array( $admin_bar, 'add_admin_menu' ) );
	add_action( 'admin_init', array( $admin_bar, 'settings_init' ) );
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $admin_bar, 'plugin_action_links' ) );

	add_action( 'init', array( $admin_bar, 'init' ) );
	add_action( 'init', array( $admin_bar, 'sort_admin_colors' ), 99 );

	add_action( 'admin_enqueue_scripts', array( $admin_bar, 'color_scheme' ), 99 );

	//has to be loaded as early as possible to ensure that the css does not overwrite theme css.
	add_action( 'admin_bar_init', array( $admin_bar, 'color_scheme' ), 1 );

	add_filter( 'get_user_option_admin_color', array( $admin_bar, 'filter_admin_color' ) );

	add_action( 'admin_enqueue_scripts', array( $admin_bar, 'options_reading' ) );

	add_filter( 'pre_option_blog_public', array( $admin_bar, 'blog_public' ), 99, 2 );
}

// Run boy, run!
add_action( 'plugins_loaded', 'apermo_adminbar_init' );
