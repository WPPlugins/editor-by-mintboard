<?php
/**
 * Interface for accessing, storing, and editing plugin options
 * @package fork
 */

class Mintboard_Editor_Help {

	/**
	 * Hooks
	 */
	function __construct( &$parent ) {

		$this->parent = &$parent;
		add_action( 'admin_menu', array( $this, 'register_menu' ), 100 );

	}

	/**
	 * Register Settings menu
	 */
	function register_menu() {
		
		add_submenu_page( 'edit.php?post_type='.Mintboard_Editor::post_type, __( 'Help', Mintboard_Editor::text_domain ), __( 'Help', Mintboard_Editor::text_domain ), 'manage_options', 'help', array( $this, 'template_help' ) );
        
	}


	/**
	 * Callback to render options page
	 */
	function template_help() {

		$this->parent->template( 'help' );

	}


}