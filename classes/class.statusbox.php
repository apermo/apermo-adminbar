<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You cannot pass... I am a servant of the server admin, wielder of PHP. You cannot pass. The dark web will not avail you, kiddie of script. Go back to where you came from! You cannot pass.' );
}

class ApermoAdminBarMetabox {

	private $statusses = array();

	private $statusbox_entries = array();

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		add_action( 'admin_bar_menu', array( $this, 'init_statusbox_entries' ), 1 );

		add_action( 'admin_bar_menu', array( $this, 'add_statusbox' ), 1 );

		add_action( 'wp_enqueue_scripts', array( $this, 'admin_bar_js' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_bar_js' ) );

	}

	public function init() {
		$statusses = get_post_statuses();
		$statusses['future'] = __( 'Scheduled' );
		$this->statusses = apply_filters( 'apermo-adminbar-statusses', $statusses );
	}

	public function admin_bar_js() {
		if ( is_admin_bar_showing() || is_admin() ) {
			wp_enqueue_script( 'apermo-adminbar-statusbox', plugins_url( '/../js/statusbox.js', __FILE__ ), array( 'jquery' ), '', true );
		}
	}

	public function add_statusbox_entry( $id, $label, $info ) {
		$this->statusbox_entries[ $id ] = array(
			'label' => $label,
			'info'	=> $info,
		);
	}

	public function init_statusbox_entries() {
		global $post;
		if ( ! is_object( $post ) ) {
			return;
		}
		$user = get_userdata( $post->post_author );

		//Todo: Add Link to profile
		$this->add_statusbox_entry( 'author', __( 'Author' ), $user->user_nicename );
		$this->add_statusbox_entry( 'post_status_nice', __( 'Status' ), $this->statusses[ $post->post_status ] );

		switch ( $post->post_status ) {
			case 'future':
				$label = __( 'Scheduled for', 'apermo-adminbar' );
				break;
			case 'publish':
				$label = __( 'Published on', 'apermo-adminbar' );
				break;
			default:
				$label = __( 'Date', 'apermo-adminbar' );
				break;
		}

		if ( 'draft' !== $post->post_status ) {
			$this->statusbox_entries['post_date'] = array(
				'label' => $label,
				'info' 	=> get_the_date( 'd.m.Y H:i', $post ),
			);
		}

		$this->statusbox_entries['last_modified'] = array(
			'label' => __( 'Last Modified' ),
			'info' 	=> get_the_modified_date( 'd.m.Y H:i' ),
		);


		$this->statusbox_entries['last_modified_by'] = array(
			'label' => __( 'Modified by', 'apermo-adminbar' ),
			'info' 	=> get_the_modified_author(),
		);
	}

	/**
	 * Adds the Status Box on the Right of the AdminBar
	 *
	 * @param $wp_admin_bar
	 */
	public function add_statusbox( $wp_admin_bar ) {
		// Don't add for users without the edit_posts capability.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( is_admin() ) {
			$screen = get_current_screen();
		}
		if ( ( ! is_admin() && is_singular() ) || ( is_object( $screen ) && 'post' == $screen->base ) ) {
			$class = is_admin() ? ' has-static' : 'static has-static';

			$this->statusbox_entries = apply_filters( 'apermo-adminbar-statusbox-entries', $this->statusbox_entries );

			if ( ! count( $this->statusbox_entries ) ) {
				return;
			}

			global $post;

			$wp_admin_bar->add_node( array(
				'id'		=> 'metabox',
				'title'		=> '<span class="post_status ' . $post->post_status . '"></span> ' . __( 'Post information', 'apermo-adminbar' ),
				'parent'	=> 'top-secondary',
				'href'		=> false,
				'meta'		=> array(
					'class' => $class,
				),
			) );

			foreach ( $this->statusbox_entries as $key => $value ) {
				$wp_admin_bar->add_node( array(
					'id'		=> 'metabox_sub_' . $key,
					'title'		=> '<span class="label">' . $value['label'] . ':</span>' . $value['info'],
					'parent'	=> 'metabox',
				) );
			}
		}
	}
}


