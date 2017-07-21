<?php
class Mintboard_Editor_Bulk_Actions {

    function __construct( &$parent ) {

        $this->parent = &$parent;
        add_action('admin_footer', array($this, 'bulk_actions_js'));
        add_action('admin_action_merge', array($this, 'bulk_actions_merge'));
        add_action('admin_notices', array($this, 'bulk_actions_notice'));
    }

    /**
     * Injects javascript for custom bulk actions
     */
    function bulk_actions_js() {
        $cs = get_current_screen();
        if (!$cs) {
            return;
        }
        if (!current_user_can( 'publish_forks' )) {
            return;
        }
        if ($cs->post_type != Mintboard_Editor::post_type ) {
            return;
        }
        // HACK: buggy Wordpress fires action hook only for the first select, so select the second (lower) by ourself
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function( $ ) {
                var elem = $('<option>').val('merge').text('<?php _e('Merge', Mintboard_Editor::text_domain); ?>')
                elem.insertAfter("select[name='action'] option[value='edit'], select[name='action2'] option[value='edit']");
                $(document).on('change', 'select[name="action2"]', function(e) {
                    $('select[name="action"]').val( $('select[name="action2"]').val() );
                });
            });
        </script>
        <?php
    }

    /**
     * Process merge bulk action
     */
    function bulk_actions_merge() {
        if(!isset($_REQUEST['post'])) {
            return;
        }
        check_admin_referer('bulk-posts');
        $cs = get_current_screen();
        if (!$cs) {
            return;
        }
        $post_type = $cs->post_type;
        if ($post_type != Mintboard_Editor::post_type ) {
            return;
        }

        $ids = array_map('intval', $_REQUEST['post'] );
        if(!count($ids)) {
            return;
        }
        foreach($ids as $id) {
            if (!current_user_can( 'publish_fork', $id )) {
                wp_die(__('Not allowed', Mintboard_Editor::text_domain));
            }
            $this->parent->merge( $id );
        }
        $sendback = remove_query_arg( array('merged', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
        if ( ! $sendback ) {
            $sendback = admin_url("edit.php?post_type=$post_type");
        }
        $wp_list_table = _get_list_table('WP_Posts_List_Table');
        $pagenum = $wp_list_table->get_pagenum();
        $sendback = add_query_arg( 'paged', $pagenum, $sendback );
        $sendback = add_query_arg( 'merged', count($ids), $sendback );
        $sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status',
            'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
        wp_redirect($sendback);
        exit();
    }

    /**
     * Show admin notice
     */
    function bulk_actions_notice() {
        $cs = get_current_screen();
        if (!$cs) {
            return;
        }
        $post_type = $cs->post_type;

        if($post_type == Mintboard_Editor::post_type && isset($_REQUEST['merged']) && (int) $_REQUEST['merged']) {
            // TODO: use string templates
            $msg = __('Forks merged', Mintboard_Editor::text_domain ) . ' (' . ((int) $_REQUEST['merged']) . ')';
            echo '<div class="updated"><p>'.$msg.'</p></div>';
        }
    }

}
?>