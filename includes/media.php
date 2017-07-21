<?php
/*
 * Class with hooks to handle media forks
 */

class Mintboard_Editor_Fork_Media
{
    const remove_pt_support = array('editor', 'revisions', 'thumbnail', 'excerpt');
    const ajax_action = 'media_fork_upload';
    const ajax_form = 'media-upload';
    const pending_image_tag = 'pending_image';
    const replace_image_tag = '1';
    const replace_image_name = 'replace_image';

    const action_fork_media = Mintboard_Editor::post_type.'-fork_media';

    function __construct(&$parent) {
        $this->parent = &$parent;
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('pre_get_posts', array($this, 'adjust_post'));
        add_action('edit_form_after_title', array($this, 'implant_editor'));
        add_filter('get_sample_permalink_html', array($this, 'adjust_permalink'));

        add_action('save_post', array($this, 'update_media_fork'));
        add_action('wp_ajax_'.self::ajax_action, array($this, 'handle_media_upload'));

        add_action( 'admin_footer', array( $this, 'add_direct_media_link' ) );
    }

    function add_meta_boxes() {
        global $post;

        if ($post->post_status == 'auto-draft')
            return;

        $is_media = Mintboard_Editor::is_media_fork($post);
        if ($is_media) {
            remove_meta_box('slugdiv', Mintboard_Editor::post_type, 'normal');
            remove_meta_box('postimagediv', Mintboard_Editor::post_type, 'side');
        }
    }

    function adjust_post( $query ) {
        $cs = get_current_screen();
        if (!$cs) {
            return;
        }
        if (($cs->post_type != Mintboard_Editor::post_type) || ($cs->id != Mintboard_Editor::post_type) ) {
            return;
        }
        if(!isset($_GET['post']) || !Mintboard_Editor::is_media_fork((int)$_GET['post'])) {
            return;
        }
        foreach(self::remove_pt_support as $entry) {
            remove_post_type_support (Mintboard_Editor::post_type, $entry);
        }
    }


    function implant_editor($post) {
        if(($post->post_type != Mintboard_Editor::post_type) || !Mintboard_Editor::is_media_fork($post->ID)) {
            return;
        }
        add_filter('image_downsize', array('Mintboard_Editor_WP', 'image_downsize'), 10, 3);
        $this->parent->template('media-editor', compact('post'));
    }

    function handle_media_upload() {
        check_ajax_referer(self::ajax_form);
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if(!$post) {
            wp_die("Post not found");
        }
        if(!Mintboard_Editor::is_media_fork($post)) {
            wp_die("Invalid fork");
        }
        self::remove_pending_image($post->ID);
        $file = $_FILES['async-upload'];
        $status = wp_handle_upload($file, array('test_form'=>true, 'action' => 'media_fork_upload'));
        update_post_meta($post->ID, self::pending_image_tag, esc_sql($status['file']));
        die('{"jsonrpc" : "2.0", "url" : "'.($status['url']).'" }');
    }

    function adjust_permalink($ret) {
        global $post;
        if ($post->post_type != Mintboard_Editor::post_type ) {
            return $ret;
        }

        if(Mintboard_Editor::is_media_fork($post)) {
            $ret = '';
            return $ret;
        }
        return $ret;
    }

    function update_media_fork($post_id)
    {
        $post = get_post($post_id);
        if (!Mintboard_Editor::is_media_fork($post)) {
            return;
        }
        if (isset($_POST['_wp_attachment_image_alt'])) {
            update_post_meta($post->ID,
                '_wp_attachment_image_alt',
                wp_strip_all_tags(wp_slash($_POST['_wp_attachment_image_alt']), true));
        } else {
            delete_post_meta($post->ID, '_wp_attachment_image_alt');
        }
        if (isset($_POST[self::replace_image_name]) && ($_POST[self::replace_image_name] == self::replace_image_tag)) {
            $image_file = get_post_meta($post->ID, self::pending_image_tag, true);
            if(!empty($image_file)) {
                delete_post_meta($post->ID, self::pending_image_tag);
                update_attached_file($post->ID, $image_file);
                wp_update_post(array('ID' => $post->ID,
                    'post_mime_type' => Mintboard_Editor_WP::get_mime_type(strtolower( pathinfo( $image_file, PATHINFO_EXTENSION ) ))));
                $metadata = wp_generate_attachment_metadata($post->ID, $image_file);
                wp_update_attachment_metadata($post->ID, $metadata);
            }
        }
        else {
            self::remove_pending_image($post_id);
        }
    }

    static function remove_pending_image($post_id) {
        $image_file = get_post_meta($post_id, self::pending_image_tag, true);
        if(!empty($image_file)) {
            delete_post_meta($post_id, self::pending_image_tag);
            @unlink($image_file);
        }
    }

    function add_direct_media_link() {
        $cs = get_current_screen();
        if (!$cs || ($cs->id != 'upload')) {
            return;
        }
        ?>
        <script type="text/javascript">
            const forksList = <?php
                $args = array(
                    'post_type' => Mintboard_Editor::post_type,
                    'post_status' => array( 'draft', 'pending' ),
                );

                $posts = get_posts( $args );
                $forks = array();
                foreach($posts as $post) {
                    if($post->post_parent) {
                        $forks[$post->post_parent] = $post->ID;
                    }
                }

                echo json_encode($forks);
        ?>;
            jQuery(document).ready(function( $ ) {
                var e = $('#tmpl-attachment-details-two-column');
                var s = e.html();
                if(s != undefined) {
                    s = s.replace('<div class="actions">', '<div class="actions"><span class="mintboard_editor_post_id" style="display:none">{{ data.id }}</span><span class="mintboard_editor_do_fork hidden"><a href="<?php echo admin_url( '?fork_action={{ data.id }}').'&_wpnonce='.wp_create_nonce(self::action_fork_media); ?>"><?php _e('Fork', Mintboard_Editor::text_domain); ?></a> | </span><span class="mintboard_editor_view_fork hidden"><a href="<?php echo admin_url( 'post.php?post=FORK_ID&action=edit'); ?>"><?php _e('View fork', Mintboard_Editor::text_domain); ?></a> | </span>');
                    e.html(s);
                }
                setInterval( function() {
                    $(".mintboard_editor_post_id").each(function() {
                        if($(this).text() != '') {
                            var id = parseInt($(this).text());
                            if(isNaN(id)) {
                                return;
                            }
                            $(this).text("");
                            if(id in forksList) {
                                var e = $(".mintboard_editor_view_fork");
                                var a = $(".mintboard_editor_view_fork a");
                                var s = a.attr("href");
                                s = s.replace("FORK_ID", forksList[id]);
                                a.attr("href", s);
                                e.removeClass("hidden");
                            }
                            else {
                                $(".mintboard_editor_do_fork").removeClass("hidden");
                            }
                        }
                    });
                }, 500);
            });
        </script>
        <?php
    }

}
?>