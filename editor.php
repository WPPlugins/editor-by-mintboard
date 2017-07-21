<?php

/*

Plugin Name: Editor by Mintboard
Plugin URI: http://mintboard.com
Description: Upgrade your content workflow. Collaborate with your editorial team right inside WordPress.
Author: Mintboard
Author URI: http://mintboard.com/
Version: 0.1.2
License: GPLv2 or later
Text Domain: editor-by-mintboard

Copyright 2016-2017 Mintboard

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

require_once dirname( __FILE__ ) . '/includes/capabilities.php';
require_once dirname( __FILE__ ) . '/includes/wp-copy.php';
require_once dirname( __FILE__ ) . '/includes/help.php';
require_once dirname( __FILE__ ) . '/includes/admin.php';
require_once dirname( __FILE__ ) . '/includes/bulk-actions.php';
require_once dirname( __FILE__ ) . '/includes/media.php';
require_once dirname( __FILE__ ) . '/includes/revisions.php';
require_once dirname( __FILE__ ) . '/includes/diff.php';
require_once dirname( __FILE__ ) . '/includes/preview.php';

class Mintboard_Editor_Summary
{
	const regular_summary = array(
    	'title_changed' => array('Post title changed', 'post title'),
    	'slug_changed' => array('Post slug changed', 'post slug'),
    	'content_changed' => array('Post content changed', 'post content'),
    	'excerpt_changed' => array('Post excerpt changed', 'post excerpt'),
    	'thumb_changed' => array('Featured image changed', 'post featured image'),
	);

	const media_summary = array(
		'title_changed' => array('Image title changed', 'image title'),
		'excerpt_changed' => array('Image caption changed', 'image caption'),
		'alt_changed' => array('Image alt text changed', 'image alt text'),
		'content_changed' => array('Image description changed', 'image description'),
		'image_changed' => array('Image file changed', 'image file'),
	);

	public $has_parent = false;

	public $title_changed = false;
	public $content_changed = false;
	public $excerpt_changed = false;
	public $slug_changed = false;
	public $thumb_changed = false;
	public $image_changed = false;
	public $alt_changed = false;

	public static function yes_no($value)
	{
		return __($value ? 'Yes':'No', Mintboard_Editor::text_domain); // also appears at line 79 below
	}
}

class Mintboard_Editor {

	const post_type = 'mintboard_fork';
	const text_domain = 'editor-by-mintboard';
	const supported_post_types = array('post', 'page'); // TODO: supports: post, page, media
	const post_type_support = 'mintboard_fork'; //key to register when adding post type support
	const fields = array(
		'post_title',
		'post_content',
		'post_excerpt',
		'post_title',
		'post_name',
	); //post fields to map from post to fork
    
	const copy_media_meta = array(
		'_wp_attachment_image_alt',
		'_wp_attached_file',
		'_wp_attachment_metadata',
	);

	const tag_fork_type = 'fork_type';
    
	public $version = '0.2';
    
	/**
	 * Register initial hooks with WordPress core
	 */
	function __construct() {

		$this->capabilities = new Mintboard_Editor_Capabilities( $this );
		$this->options = new Mintboard_Editor_Help( $this );
		$this->preview = new Mintboard_Editor_Preview( $this );

		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'init', array( $this, 'add_post_type_support'), 999  );
        
		add_filter( 'the_title', array( $this, 'title_filter'), 10, 3 );
		add_action( 'save_post_'.self::post_type, array( $this, 'new_post' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'delete_post' ) );
		add_action( 'before_delete_post', array( $this, 'before_delete_post' ) );
		add_action( 'trashed_post', array( $this, 'trashed_post' ) );
		add_action( 'untrashed_post', array( $this, 'untrashed_post' ) );
		add_action( 'transition_post_status', array( $this, 'intercept_publish' ), 0, 3 );
	}

	function get_post_type(){
		return self::post_type;
	}

	/**
	 * Pseudo-lazy loading of back-end functionality
	 */
	function action_init() {

		if ( !is_admin() )
			return;

		$this->admin = new Mintboard_Editor_Admin( $this );
		$this->bulk_actions = new Mintboard_Editor_Bulk_Actions( $this );
		$this->revisions = new Mintboard_Editor_Revisions( $this );
		$this->diff = new Mintboard_Editor_Diff( $this );
        
        if(in_array('media', Mintboard_Editor::supported_post_types)) {
            $this->media = new Mintboard_Editor_Fork_Media( $this );
        }
        
	}

	/**
	 * Register custom post type
	 */
	function register_cpt() {

		$labels = array(
			'name'               => __( 'Forks', Mintboard_Editor::text_domain ),
			'singular_name'      => __( 'Fork', Mintboard_Editor::text_domain ),
			'add_new'            => __( 'Add Fork', Mintboard_Editor::text_domain ),
			'add_new_item'       => __( 'Add Fork', Mintboard_Editor::text_domain ),
			'edit_item'          => __( 'Edit Fork', Mintboard_Editor::text_domain ),
			'new_item'           => __( 'New Fork', Mintboard_Editor::text_domain ),
			'view_item'          => __( 'View Fork', Mintboard_Editor::text_domain ),
			'search_items'       => __( 'Search Forks', Mintboard_Editor::text_domain ),
			'not_found'          => __( 'No forks found', Mintboard_Editor::text_domain ),
			'not_found_in_trash' => __( 'No forks found in Trash', Mintboard_Editor::text_domain ),
			'parent_item_colon'  => __( 'Parent Fork:', Mintboard_Editor::text_domain ),
			'menu_name'          => __( 'Editor', Mintboard_Editor::text_domain ),
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => true,
			'supports'            => array( 'title', 'editor', 'author', 'revisions', 'thumbnail', 'excerpt' ),
			'public'              => true,
			'show_ui'             => true,
			'show_in_nav_menus'   => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'query_var'           => true,
			'can_export'          => true,
			'rewrite'             => true,
			'map_meta_cap'        => true,
			'capability_type'     => array( 'fork', 'forks' ),
			'menu_icon'           => plugins_url( '/assets/images/icon.png', __FILE__ ),
		);

		register_post_type( self::post_type, $args );

		$status_args = array(
			'label' => __( 'Merged', Mintboard_Editor::text_domain ),
			'public' => true,
			'exclude_from_search' => true,
			'label_count' => _n_noop( 'Merged <span class="count">(%s)</span>', 'Merged <span class="count">(%s)</span>' ),
		);
 
		register_post_status( 'merged', $status_args );
	}

	/**
	 * Load a template. MVC FTW!
	 * @param string $template the template to load, without extension (assumes .php). File should be in templates/ folder
	 * @param args array of args to be run through extract and passed to tempalate
	 */
	function template( $template, $args = array() ) {
		extract( $args );

		if ( !$template ) {
			return false;
		}

		$path = dirname( __FILE__ ) . "/templates/{$template}.php";
		$path = apply_filters( 'mintboard_editor_template', $path, $template );

		include $path;
		return true;
	}

	/**
	 * Returns an array of post type => bool to indicate whether the post type(s) supports forking
	 * All post types will be included
	 * @param bool $filter whether to return all post types (false) or just the ones toggled (true)
	 * @return array an array of post types and their forkability
	 */
	function get_post_types( $filter = false ) {

		$post_types = array();

		foreach ( $this->get_potential_post_types() as $pt )
			$post_types[ $pt->name ] = array_search( $pt->name, self::supported_post_types ) >= 0;

		if ( $filter )
			$post_types = array_keys( array_filter( $post_types ) );

		$post_types = apply_filters( 'mintboard_editor_post_types', $post_types, $filter );

		return  $post_types;

	}

	/**
	 * Returns an array of post type objects for all registered post types other than fork
	 * @param return array array of post type objects
	 */
	function get_potential_post_types() {

		$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
		unset( $post_types[Mintboard_Editor::post_type] );
		return $post_types;

	}

	/**
	 * Registers post_type_support for forking with all active post types on load
	 */
	function add_post_type_support() {

		foreach ( $this->get_post_types() as $post_type => $status )
			if ( $status == true )
				add_post_type_support( $post_type, self::post_type_support );

	}

	/**
	 * Checks if there is a fork for a given post
	 * @param int $parent_id the post_id of the parent post to check
	 * @return int|bool the fork id or false if no fork exists
	 */
	function fork_exists($parent_id, $status=array( 'draft', 'pending' )) {

		$args = array(
			'post_type' => Mintboard_Editor::post_type,
			'post_status' => $status,
			'post_parent' => (int) $parent_id,
		);


		$posts = get_posts( $args );

		if ( empty( $posts ) )
			return false;

		return reset( $posts )->ID;
	}

	/**
	 * Returns an filterable list of fields to copy from original post to the fork
	 */
	function get_fork_fields() {

		return apply_filters( 'mintboard_editor_fields', self::fields );

	}

	static function is_media_post($post) {
		if(!$post) {
			return false;
		}
		$parent = get_post($post);
		if($parent) {
			return $parent->post_type == 'attachment';
		}
		return false;
	}

	static function is_media_fork($fork) {
		if(!is_object($fork)) {
			$fork = get_post($fork);
		}
		if(!$fork) {
			wp_die(__('Fork not found', Mintboard_Editor::supported_post_types));
		}
		if($fork->post_type != self::post_type)
		{
			return false;
		}
		// NOTE: parentless fork cannot be of a media type
		return self::is_media_post($fork->post_parent);
	}

	/**
	 * Main forking function
	 * @param int|object $p the post_id or post object to fork
	 * @param string the nicename of author to fork post as
	 * @return int the ID of the fork
	 */
	function fork( $p = null, $author = null, $new_type='post' ) {
		global $post;

		if ( $p == null )
			$p = $post;

		if ( !is_object( $p ) )
			$p = get_post( $p );

		if ( !$p )
			return false;

		if ( $author == null )
			$author = wp_get_current_user()->ID;

		//bad post type, enable via forks->options
		if ( !post_type_supports( $p->post_type, self::post_type_support ) )
			wp_die( __( 'That post type does not support forking', Mintboard_Editor::supported_post_types ) );

		//hook into this cap check via map_meta cap
		// for custom capabilities
		if ( !user_can( $author, 'fork_post', $p->ID ) )
			wp_die( __( 'You are not authorized to fork that post', Mintboard_Editor::supported_post_types ) );

		//there is already a fork, just return the existing ID
		if ( $fork_id = $this->fork_exists( $p->ID ) ) {
			return $fork_id;
		}

		//set up base fork data array
		$fork = array(
			'post_type' => Mintboard_Editor::post_type,
			'post_author' => $author,
			'post_status' => 'draft',
			'post_parent' => $p->ID,
			'post_mime_type' => $p->post_mime_type,
		);

		//copy necessary post fields over to fork data array
		$copy_slug = false;
		foreach ( $this->get_fork_fields() as $field ) {
			if($field == 'post_name') {
				$copy_slug = true;
			}
			else {
				$fork[$field] = $p->$field;
			}
		}
		$thumb_id = get_post_meta( $p->ID, '_thumbnail_id', true );

		$fork_id = wp_insert_post( $fork );

		//something went wrong
		if ( !$fork_id )
			return false;

		if(!empty($thumb_id)) {
			update_post_meta( $fork_id, '_thumbnail_id', $thumb_id);
		}
		if($copy_slug) {
			// NOTE: do not insert slug into wp_insert_post params, doesn't work
			wp_update_post(array('ID' => $fork_id, 'post_name' => $p->post_name));
		}
		//note: $p = parent post object
		if(self::is_media_fork($fork_id)) {
			foreach(self::copy_media_meta as $meta) {
				self::copy_post_meta($p->ID, $fork_id, $meta);
			}
			Mintboard_Editor_Fork_Media::remove_pending_image($fork_id);
		}
		do_action( 'mintboard_editor_fork', $fork_id, $p, $author);

		return $fork_id;

	}

	/**
	 * Given a fork, gets the name of the parent post
	 * @param int|object $fork the fork ID or or object (optional, falls back to global $post)
	 * @return string the name of the parent post
	 */
	function get_parent_name( $fork = null ) {
		global $post;

		if ( $fork == null )
			$fork = $post;

		if ( !is_object( $fork ) )
			$fork = get_post( $fork );

		if ( !$fork ) {
			return '';
		}

		$parent = get_post( $fork->post_parent );

		$author = get_user_by( 'id', $parent->post_author );

		$name =  $author->user_nicename . ' &#187; ';
		$name .= get_the_title( $parent );

		return $name;

	}

	/**
	 * Given a fork, returns the true name of the fork, filterless
	 * @param int|object $fork the fork ID or or object (optional, falls back to global $post)
	 * @return string the name of the fork
	 */
	function get_fork_name( $fork = null ) {
		global $post;

		if ( $fork == null )
			$fork = $post;

		if ( !is_object( $fork ) )
			$fork = get_post( $fork );

		if ( !$fork ) {
			return '';
		}

		$author = new WP_User( $fork->post_author );
		$parent = get_post( $fork->post_parent );

		$name = $author->user_nicename . ' &#187; ';
		remove_filter( 'the_title', array( $this, 'title_filter') );
		$name .= get_the_title( $parent->ID );
		add_filter( 'the_title', array( $this, 'title_filter'), 10, 3 );

		return $name;

	}

 	/**
 	 * Filter fork titles
 	 * @param string $title the post title
 	 * @param int $id the post ID
 	 * @return string the modified post title
 	 */
 	function title_filter( $title, $id = 0 ) {

 		if ( get_post_type( $id ) != Mintboard_Editor::post_type )
 			return $title;

 		return $this->get_fork_name( $id );


 	}

	function new_post($post_id, $post, $update) {
		if(!isset($_REQUEST[self::tag_fork_type]) || !$update || ($post->post_status == 'auto-draft')) {
			return;
		}
		$t = 'post';
		if($_REQUEST[self::tag_fork_type] == 'page') {
			$t = 'page';
		}
		add_post_meta($post_id, self::tag_fork_type, $t);
	}

 	/**
 	 * When post is deleted, delete forks
	 * Do not touch attachments unless we delete permanently
	 * 
 	 * @param int $post_id the parent post
 	 */
 	function delete_post( $post_id ) {
	 	//post delete
	 	if ( !get_post( $post_id ) )
	 		return;

		$fork_id = $this->fork_exists($post_id, array('trash', 'draft', 'pending'));
	 	if ($fork_id) {
			wp_delete_post($fork_id, true);
		}
 	}

	/**
	 * Deletes media fork images
	 *
	 * @param int $post_id
	 */
	function before_delete_post( $post_id ) {
		if ( get_post_type( $post_id ) == self::post_type) {
			$this->delete_attachments($post_id, true);
			Mintboard_Editor_WP::delete_attached_files($post_id);
			Mintboard_Editor_Fork_Media::remove_pending_image($post_id);
		}
	}

	function trashed_post( $post_id ) {
		$fork_id = $this->fork_exists($post_id);
		if ($fork_id) {
			wp_trash_post($fork_id);
		}
	}

	function untrashed_post( $post_id ) {
		$fork_id = $this->fork_exists($post_id, 'trash');
		if ($fork_id) {
			wp_untrash_post($fork_id);
		}
	}

	static function normalizeNewlines($str)	{
		$ret = str_replace("\r\n", "\n", $str);
		return str_replace("\r", "\n", $ret);
	}

	// TODO: move to separate class along with the summary methods
	static function compare_fork_strings($post_string, $fork_string)
	{
		if (empty($fork_string)) {
			return false;
		}
		return $fork_string != $post_string;
	}

	// TODO: move to separate class
	static function get_summary($fork_id) {
		$fork = get_post($fork_id);
		if(!$fork) {
			die ("Fork not found");
		}
		$summary = new Mintboard_Editor_Summary();
		if(!$fork->post_parent) {
			return $summary;
		}
		$post = get_post($fork->post_parent);
		if(!$post) {
			die ("Post not found");
		}
		$summary->has_parent = true;
		// HACK: unify line breaks, Wordpress seem to be parsing them as he wants all the time
		$summary->content_changed = self::normalizeNewlines($fork->post_content) !=
			self::normalizeNewlines($post->post_content);
		$summary->excerpt_changed = self::normalizeNewlines($fork->post_excerpt) !=
			self::normalizeNewlines($post->post_excerpt);

		// NOTE: draft post_name is empty unless changed
		$summary->slug_changed = self::compare_fork_strings($post->post_name, $fork->post_name);
		$summary->title_changed = self::compare_fork_strings($post->post_title, $fork->post_title);
		$summary->thumb_changed = !self::metas_equal($fork_id, $fork->post_parent, '_thumbnail_id');
		$summary->image_changed = !self::metas_equal($fork_id, $fork->post_parent, '_wp_attached_file');
		$summary->alt_changed = !self::metas_equal($fork_id, $fork->post_parent, '_wp_attachment_image_alt');

		return $summary;
	}

	static function metas_equal($post_id1, $post_id2, $tag) {
		return get_post_meta( $post_id1, $tag, true ) ==
			get_post_meta( $post_id2, $tag, true );

	}

 	/**
 	 * Delete thumbnail that is only in use with this post
	 * 
 	 * @param int $post_id the parent post
 	 */
	function delete_attachments($post_id, $force) {
		$thumb_id = get_post_meta( $post_id, '_thumbnail_id', true );
		if(empty($thumb_id))
		{
			return;
		}
		$mq_args = array(
			'meta_query' => array(
				array(
					'key'     => '_thumbnail_id',
					'value'   => $thumb_id,
					'compare' => '='
				)
			),
			'post_type' => array ('post', Mintboard_Editor::post_type),
		);
		$mq = new WP_Query( $mq_args );
		if (($mq->post_count) < 2) {
			wp_delete_attachment($thumb_id, $force);
		}
	}

	function insert($fork) {
		if ( !is_object( $fork ) ) {
			$fork = get_post( $fork );
		}
		if(!$fork) {
			wp_die(__('Fork not found', Mintboard_Editor::supported_post_types));
		}
		if ( !current_user_can( 'publish_fork', $fork->ID ) ) {
			wp_die( __( 'You are not authorized to publish forks', Mintboard_Editor::supported_post_types ) );
		}
		$data = array(
			'post_content' => $fork->post_content,
			'post_excerpt' => $fork->post_excerpt,
			'post_title' => $fork->post_title,
			'post_name' => $fork->post_name,
			'post_author' => $fork->post_author,
			'post_status' => 'publish',
			'_thumbnail_id' => get_post_meta($fork->ID, '_thumbnail_id', true),
			'post_type' => get_post_meta($fork->ID, Mintboard_Editor::tag_fork_type, true),
		);

		// TODO: a placeholder for a do_action hook

		$ret = wp_insert_post( $data );
		if(!$ret) {
			return $ret;
		}
		wp_delete_post ( $fork->ID );
		return $ret;
	}

	/**
	 * Merges a fork's content back into its parent post
	 * @param int $fork_id the ID of the fork to merge
	 */
	function merge( $fork ) {

		if ( !is_object( $fork ) ) {
			$fork = get_post( $fork );
		}
		// TODO: extract checks
		if(!$fork) {
			wp_die(__('Fork not found', Mintboard_Editor::supported_post_types));
		}
		if(!$fork->post_parent)
		{
			return $this->insert($fork);
		}

		if ( !current_user_can( 'publish_fork', $fork->ID ) ) {
			wp_die( __( 'You are not authorized to merge forks', Mintboard_Editor::supported_post_types ) );
		}

		$post = get_post( $fork->post_parent );
		if(!$post) {
			wp_die(__('Post not found', Mintboard_Editor::supported_post_types));
		}
		$is_media = self::is_media_fork($fork);

		$update = array(
			'ID' => $fork->post_parent,
			'post_content' => $fork->post_content,
			'post_excerpt' => $fork->post_excerpt,
			//'post_mime_type' => $fork->post_mime_type,
		);

		if(!empty($fork->post_title)) {
			if($fork->post_title != $post->post_title) {
				$update['post_title'] = $fork->post_title;
			}
		}
		// NOTE: this param is for keep_post_name function
		$keep_post_name = true;
		$update['post_name'] = $post->post_name;
		if(!$is_media && !empty($fork->post_name)) {
			if($fork->post_name != $post->post_name) {
				$update['post_name'] = $fork->post_name;
				$keep_post_name = false;
				add_post_meta($post->ID, '_wp_old_slug', $post->post_name);
			}
		}

		// Note: $merge_author = id of user who's doing the merge
		$merge_author = wp_get_current_user()->ID;
		do_action( 'mintboard_editor_merge', $fork, $merge_author );

		// HACK: wp updates slug even if it is not changed
		if($keep_post_name) {
			add_filter('wp_insert_attachment_data', array($this, 'keep_post_name'), 10, 2);
			add_filter('wp_insert_post_data', array($this, 'keep_post_name'), 10, 2);
		}
		$ret = wp_update_post( $update );
		if($keep_post_name) {
			remove_filter('wp_insert_attachment_data', array($this, 'keep_post_name'));
			remove_filter('wp_insert_post_data', array($this, 'keep_post_name'));
		}
		if(!$ret) {
			return $ret;
		}
		if($is_media) {
			foreach(self::copy_media_meta as $meta) {
				if(($meta != '_wp_attached_file') && ($meta != '_wp_attachment_metadata')) {
					self::copy_post_meta($fork->ID, $fork->post_parent, $meta);
				}
			}
			$uploadpath = wp_get_upload_dir();
			$filename = path_join($uploadpath['basedir'], get_post_meta($fork->post_parent, '_wp_attached_file', true));
			$fork_filename = path_join($uploadpath['basedir'], get_post_meta($fork->ID, '_wp_attached_file', true));
			if($filename != $fork_filename) {
				delete_post_meta($fork->post_parent, '_wp_attachment_metadata');
				Mintboard_Editor_WP::delete_attached_files($fork->post_parent);
				@rename($fork_filename, $filename);
				$metadata = wp_generate_attachment_metadata($fork->post_parent, $filename);
				wp_update_attachment_metadata($fork->post_parent, $metadata);
				Mintboard_Editor_WP::delete_attached_files($fork->ID);
				wp_cache_delete($fork->post_parent);
			}
		}
		else {
			$thumb_id = get_post_meta($fork->ID, '_thumbnail_id', true);
			$original_thumb_id = get_post_meta($fork->post_parent, '_thumbnail_id', true);
			if ($thumb_id != $original_thumb_id) {
				if (empty($thumb_id)) {
					delete_post_meta($fork->post_parent, '_thumbnail_id');
				} else {
					update_post_meta($fork->post_parent, '_thumbnail_id', $thumb_id);
				}
			}
		}
		wp_delete_post ( $fork->ID );
		return $ret;
	}

	// TODO: move to utils
	static function copy_post_meta($src_id, $dest_id, $tag) {
		$m = get_post_meta($src_id, $tag, true);
		if(!empty($m)) {
			update_post_meta($dest_id, $tag, $m);
		}
		else {
			delete_post_meta($dest_id, $tag);
		}
	}

	/**
	 * Intercept the publish action and merge forks into their parent posts
	 */
	function intercept_publish( $new, $old, $post ) {

		if ( wp_is_post_revision( $post ) )
			return;

		if ( $post->post_type != self::post_type )
			return;

		if ( $new != 'publish' )
			return;

		$post = $this->merge( $post->ID );

		wp_safe_redirect( admin_url( "post.php?action=edit&post={$post}&message=6" ) );
		exit();

	}

	function keep_post_name($data, $postarr) {
		// NOTE: slug generation is copy/paste from wp_insert_post
		$data['post_name'] = sanitize_title($postarr['post_name']);
		return $data;
	}
}


$fork = new Mintboard_Editor();
