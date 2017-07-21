<?php
/**
 * Forking administrative functions
 * @package fork
 */

class Mintboard_Editor_Admin {

	const filter_prefix = 'fork_filter_';
	const filter_posts = 'post';
	const filter_pages = 'page';
	const filter_media = 'media';
	const filter_tag = 'fork_filter';

	/**
	 * Hook into WordPress API on init
	 */
	function __construct( &$parent ) {

		$this->parent = &$parent;
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_init', array( $this, 'fork_callback' ) );
		add_action( 'admin_init', array( $this, 'merge_callback' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_ajax_fork', array( $this, 'ajax' ) );
		add_action( 'admin_ajax_fork_merge', array( $this, 'ajax' ) );
		add_filter( 'admin_body_class', array( $this, 'add_fork_css_class' ), 10, 1 );
        
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );

		// TODO: move submenu to a separate class
		add_action('admin_menu', array($this, 'add_menu_items'));
		add_filter('admin_title', array($this, 'filter_title'), 10, 2);
		add_filter('submenu_file', array($this, 'filter_menu'), 10, 2);
		add_filter('posts_where', array($this, 'filter_fork_types'), 10, 2);
		add_action('admin_footer', array($this, 'filter_js'));
		$this->redirect_fork_types();
	}
    
	/**
	 * Add metaboxes to post edit pages
	 */
	function add_meta_boxes() {
		global $post;

		if(empty($post)) {
			return;
		}

		if (Mintboard_Editor::post_type == $post->post_type ) {
			$post_type_object = get_post_type_object($post->post_type);
			$thumbnail_support = current_theme_supports( 'post-thumbnails', $post->post_type ) &&
			                     post_type_supports( $post->post_type, 'thumbnail' );
			$is_media = Mintboard_Editor::is_media_fork($post);
			remove_meta_box( 'submitdiv', Mintboard_Editor::post_type, 'side' );
			add_meta_box( 'fork', 'Fork', array( $this, 'fork_meta_box' ), Mintboard_Editor::post_type, 'side', 'high' );
			if (current_user_can('publish_fork', $post->ID)) {
				add_meta_box('summary', 'Fork Summary', array(
					$this,
					'summary_meta_box'
				), Mintboard_Editor::post_type, 'side', 'high');
			}
			if ( ! $is_media && $thumbnail_support && current_user_can('upload_files')) {
				add_meta_box('postimagediv', esc_html($post_type_object->labels->featured_image),
					'post_thumbnail_meta_box', null, 'side', 'low');
			}

			if (post_type_supports($post->post_type, 'excerpt')) {
				add_meta_box('postexcerpt', __('Excerpt'),
					'post_excerpt_meta_box', null, 'normal', 'low');
			}
		}

		if ( $post->post_status == 'auto-draft' ) {
			return;
		}

		if(($post->post_type == 'attachment') && !wp_attachment_is_image($post->ID)) {
			return;
		}

		if ( post_type_supports( $post->post_type, Mintboard_Editor::post_type_support ) ) {
			add_meta_box( 'fork', 'Fork', array( $this, 'post_meta_box' ), $post->post_type, 'side', 'high' );
		}
	}


	/**
	 * Callback to listen for the primary fork action
	 */
	function fork_callback() {

		if ( !isset( $_GET['fork_action'] ) )
			return;

		$post = intval($_GET['fork_action']);
		if(!$this->check_media_fork_referer($post)) {
			check_admin_referer(Mintboard_Editor::post_type.'-fork_' . $post);
		}

		$fork = $this->parent->fork($post);
		if ( !$fork )
			return;

		wp_safe_redirect( admin_url( "post.php?post=$fork&action=edit" ) );
		exit();

	}

	function check_media_fork_referer($post) {
		if(!Mintboard_Editor::is_media_post($post)) {
			return false;
		}
		$adminurl = strtolower(admin_url());
		$referer = strtolower(wp_get_referer());
		$result = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce($_REQUEST['_wpnonce'], Mintboard_Editor_Fork_Media::action_fork_media) : false;
		return $result && ( strpos( $referer, $adminurl ) === 0 );
	}

	/**
	 * Callback to listen for the primary merge action
	 */
	function merge_callback() {

		if ( !isset( $_GET['merge_action'] ) )
			return;

		check_admin_referer( Mintboard_Editor::post_type.'-merge_' . intval( $_GET['merge'] ) );

		$this->parent->merge( (int) $_GET['merge_action'] );

		exit();

	}


	/**
	 * Callback to render post meta box
	 */
	function post_meta_box( $post ) {

		$fork = $this->parent->fork_exists($post->ID);
		if($fork) {
			$fork = get_post($fork);
		}
		$this->parent->template( 'post-meta-box', compact( 'post', 'fork' ) );

	}


	/**
	 * Callback to render fork meta box
	 */
	function fork_meta_box( $post ) {

		$parent = $this->parent->revisions->get_previous_revision( $post );

		$this->parent->template( 'fork-meta-box', compact( 'post', 'parent' ) );
	}


	/**
	 * Callback to render summary meta box
	 */
	function summary_meta_box( $post ) {
		$parent = $this->parent->revisions->get_previous_revision( $post );

		$this->parent->template( 'summary-meta-box', compact( 'post', 'parent' ) );
	}


	/**
	 * Registers update messages
	 * @param array $messages messages array
	 * @returns array messages array with fork messages
	 */
	function update_messages( $messages ) {
		global $post, $post_ID;

		$messages[Mintboard_Editor::post_type] = array(
			1 => __( 'Fork updated.', Mintboard_Editor::text_domain ),
			2 => __( 'Custom field updated.', Mintboard_Editor::text_domain ),
			3 => __( 'Custom field deleted.', Mintboard_Editor::text_domain ),
			4 => __( 'Fork updated.', Mintboard_Editor::text_domain ),
			5 => isset($_GET['revision']) ? sprintf( __( 'Fork restored to revision from %s', Mintboard_Editor::text_domain ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Fork published. <a href="%s">Download Fork</a>', Mintboard_Editor::text_domain ),
			7 => __( 'Fork saved.', Mintboard_Editor::text_domain ),
			8 => __( 'Fork submitted.', Mintboard_Editor::text_domain ),
			9 => __( 'Fork scheduled for:', Mintboard_Editor::text_domain ),
			10 => __( 'Fork draft updated.', Mintboard_Editor::text_domain ),
		);

		return $messages;
	}


	/**
	 * Enqueue javascript and css assets on backend
	 */
	function enqueue() {

		$post_types = $this->parent->get_post_types( true );
		$post_types[] = Mintboard_Editor::post_type;

		if ( !in_array( get_current_screen()->post_type, $post_types ) )
			return;

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		//js
		wp_enqueue_script( Mintboard_Editor::text_domain, plugins_url( "/assets/js/admin{$suffix}.js", dirname( __FILE__ ) ), 'jquery', $this->parent->version, true );

		//css
		wp_enqueue_style( Mintboard_Editor::text_domain, plugins_url( "/assets/css/admin{$suffix}.css", dirname( __FILE__ ) ), null, $this->parent->version );
	}


	/**
	 * Add additional actions to the post row view
	 */
	function row_actions( $actions, $post ) {

		if ( post_type_supports( get_post_type( $post ), Mintboard_Editor::post_type_support ) ) {
			$fork = $this->parent->fork_exists($post->ID);
			if($fork) {
				$actions[] = '<a href="' . wp_nonce_url( admin_url( "post.php?post={$fork}&action=edit" ), Mintboard_Editor::post_type.'-fork_' . $post->ID ) . '">' . __( 'Edit fork', Mintboard_Editor::text_domain ) . '</a>';
			}
			else {
				$actions[] = '<a href="' . wp_nonce_url( admin_url( '?fork_action='.$post->ID ), Mintboard_Editor::post_type.'-fork_' . $post->ID ) . '">' . __( 'Fork', Mintboard_Editor::text_domain ) . '</a>';
			}
		}

		if ((Mintboard_Editor::post_type == get_post_type( $post )) && current_user_can( 'publish_fork', $post->ID )) {
			$summary = Mintboard_Editor::get_summary($post->ID);
			$summary_info = array();
			if(!$summary->has_parent) {
				$summary_info[] = __('none, this is a completely new fork', Mintboard_Editor::text_domain);
			}
			else {
				$actions[] = '<a href="' . admin_url( "revision.php?page=fork-diff&right={$post->ID}" ) . '">' . __( 'Compare', Mintboard_Editor::text_domain ) . '</a>';
				// TODO: use formats, move to templates
				$info_set = Mintboard_Editor_Summary::regular_summary;
				if (Mintboard_Editor::is_media_fork($post)) {
					$info_set = Mintboard_Editor_Summary::media_summary;
				}
				foreach ($info_set as $k => $v) {
					$summary_info[] = __($v[1], Mintboard_Editor::text_domain) . ' (' . Mintboard_Editor_Summary::yes_no($summary->$k) . ')';
				}
			}
			array_unshift($actions, '<div style="display:block; color:#999;" class="inline-fork-summary">' .
				__('Changes', Mintboard_Editor::text_domain) . ': ' .
			                        (join(', ', $summary_info)) . '</div>');
		}

		return $actions;

	}

	function admin_footer() {
		$cs = get_current_screen();
		if (!$cs) {
			return;
		}
		if ($cs->post_type != Mintboard_Editor::post_type ) {
			return;
		}
		?>
<script type='text/javascript'>
	jQuery( document ).ready( function( $ ){
		$( '.inline-fork-summary' ).each( function(){
			var pSpan = $(this).parent();
			var pTarget = pSpan.parent();
			$(this).detach();
			pSpan.remove();
			pTarget.before($(this));
		});
	});
</script>
		<?php
	}

	/**
	 * Callback to handle ajax forks
	 * Note: Will output 0 on failure,
	 */
	function ajax() {

		foreach ( array( 'post', 'author', 'action' ) as $var )
			$$var = ( isset( $_GET[$var] ) ) ? $_GET[$var] : null;

		check_ajax_referer( Mintboard_Editor::post_type.'-' . $action . '_' . $post );

		if ( $action == 'merge_action' )
			$result = $this->parent->merge( $post, $author );
		else
			$result = $this->parent->fork( $post, $author );

		if ( $result == false )
			$result = -1;

		die( $result );

	}


	/**
	 * Add admin body class for the forks list table view
	 */
	function add_fork_css_class( $classes ) {
		if ( 'edit-fork' == get_current_screen()->id ) {
			return $classes .= ' fork-list';
		}
		return $classes;
	}

	/*
	 * Returns post type corresponding to a filter, false if no filter set
	 * @return bool|string
	 */
	function get_filter_type() {
		if (!isset($_GET[self::filter_tag])) {
			return false;
		}
		switch ($_GET[self::filter_tag]) {
			case self::filter_posts: return 'post';
			case self::filter_pages: return 'page';
			case self::filter_media: return 'attachment';
		}
		return false;
	}

	function filter_menu($submenu_file, $parent_file) {
		if(!$this->get_filter_type()){
			return $submenu_file;
		}
		return self::filter_prefix.$_GET[self::filter_tag];
	}

	function filter_title($admin_title, $title)	{
		if(!$this->get_filter_type()){
			return $admin_title;
		}
		global $post_type_object;
		switch ($_GET[self::filter_tag]) {
			case self::filter_posts: $admin_title = __( 'Forked Posts', Mintboard_Editor::text_domain ); break;
			case self::filter_pages: $admin_title = __( 'Forked Pages', Mintboard_Editor::text_domain ); break;
			case self::filter_media: $admin_title = __( 'Forked Media', Mintboard_Editor::text_domain ); break;
		}
		// HACK: wp does not have filter for a h1 title, hacking var that is used in the edit.php
		$post_type_object->labels->name = $admin_title;
		return $admin_title;
	}

	function filter_fork_types($where, $query) {
		$filter = $this->get_filter_type();
		if (!$filter) {
			return $where;
		}
		global $wpdb;
		$where .= " AND (($wpdb->posts.post_parent IN (SELECT $wpdb->posts.ID FROM $wpdb->posts WHERE $wpdb->posts.post_type='$filter')) OR ($wpdb->posts.ID IN (SELECT $wpdb->postmeta.post_id FROM $wpdb->postmeta WHERE $wpdb->postmeta.meta_key='".Mintboard_Editor::tag_fork_type."' AND $wpdb->postmeta.meta_value='$filter')))";
		return $where;
	}

	function add_menu_items() {
        
        if(in_array('page', Mintboard_Editor::supported_post_types)) {
    		add_submenu_page( 'edit.php?post_type='.Mintboard_Editor::post_type, '', __( 'Forked Posts', Mintboard_Editor::text_domain ), 'read_forks', self::filter_prefix . self::filter_posts, function(){});
        }
        
        if(in_array('page', Mintboard_Editor::supported_post_types)) {
    		add_submenu_page( 'edit.php?post_type='.Mintboard_Editor::post_type, '', __( 'Forked Pages', Mintboard_Editor::text_domain ), 'read_forks', self::filter_prefix . self::filter_pages, function(){});
        }

        if(in_array('media', Mintboard_Editor::supported_post_types)) {
    		add_submenu_page( 'edit.php?post_type='.Mintboard_Editor::post_type, '', __( 'Forked Media', Mintboard_Editor::text_domain ), 'read_forks', self::filter_prefix . self::filter_media, function(){});
        }
	}

	function redirect_fork_types() {
		if(!isset($_GET['post_type']) || !isset($_GET['page']) || ($_GET['post_type'] != Mintboard_Editor::post_type)) {
			return;
		}
		$len_prefix = strlen(self::filter_prefix);
		if(strncmp($_GET['page'], self::filter_prefix, $len_prefix) == 0){
			wp_safe_redirect(admin_url('edit.php?post_type='.Mintboard_Editor::post_type.'&'.self::filter_tag.'='.substr($_GET['page'], $len_prefix)));
			die();
		}
	}

	function filter_js() {
		$cs = get_current_screen();
		if (!$cs) {
			return;
		}
		if ($cs->post_type != Mintboard_Editor::post_type ) {
			return;
		}
		$page_url = admin_url('post-new.php?post_type=' . Mintboard_Editor::post_type . '&' . self::filter_tag . '=page');
		switch($this->get_filter_type()) {
			case false:
				?>
<script type="text/javascript">
	jQuery(document).ready(function ($) {
		var b1 = $("a.page-title-action");
		var b2 = $(b1.clone());
		b1.text("<?php _e('Add new post fork', Mintboard_Editor::text_domain); ?>");
		b2.text("<?php _e('Add new page fork', Mintboard_Editor::text_domain); ?>");
		b2.attr("href", "<?php echo $page_url; ?>");
		b2.insertAfter(b1);
	});
</script>
				<?php
				break;
			case 'attachment':
			?>
<script type="text/javascript">
	jQuery(document).ready(function ($) {
		$("a.page-title-action").remove();
	});
</script>
			<?php
				break;
			case 'page':
			?>
<script type="text/javascript">
	jQuery(document).ready(function ($) {
		$("a.page-title-action").attr("href", "<?php echo $page_url; ?>");
	});
</script>
			<?php
				break;
		}
	}
}
