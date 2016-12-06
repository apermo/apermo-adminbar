<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You cannot pass... I am a servant of the server admin, wielder of PHP. You cannot pass. The dark web will not avail you, kiddie of script. Go back to where you came from! You cannot pass.' );
}

class ApermoAdminBarWatermark {

	private $statusses = array();

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'watermark_js' ) );
		add_action( 'wp_footer', array( $this, 'watermark_localize' ) );
	}

	public function init() {
		$statusses = get_post_statuses();
		$statusses['future'] = __( 'Scheduled' );
		$this->statusses = apply_filters( 'apermo-adminbar-statusses', $statusses );
	}

	/**
	 * Load the JS if on options-reading
	 */
	public function watermark_js() {
		if ( ! is_admin() && is_singular() ) {
			global $post;
			if ( 'publish' !== $post->post_status ) {
				wp_enqueue_script( 'apermo-adminbar-watermark', plugins_url( '../js/watermark.js', __FILE__ ), array( 'jquery' ), '', true );
			}
		}
	}

	public function watermark_localize() {
		if ( ! is_admin() && is_singular() ) {
			global $post;
			if ( 'publish' !== $post->post_status ) {
				$data['post_status'] = $post->post_status;
				$data['post_status_nice'] = $this->statusses[ $post->post_status ];

				wp_localize_script( 'apermo-adminbar-watermark', 'apermo_adminbar_watermark', $data );
			}
		}
	}
}


