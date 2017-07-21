<?php
$thumb_url = false;
if ( $attachment_id = intval( $post->ID ) ) {
    $thumb_url = wp_get_attachment_image_src($attachment_id, array(900, 450), true);
}
$img_url = Mintboard_Editor_WP::get_attachment_url( $post->ID );

$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
?>
    <div class="wp_attachment_image wp-clearfix">
        <p><img class="thumbnail" id="fork_image" src="<?php echo set_url_scheme( $thumb_url[0] ); ?>" style="max-width:100%" alt="" /></p>
        <div id="plupload-upload-ui" class="hide-if-no-js">
            <input type="hidden" name="<?php echo Mintboard_Editor_Fork_Media::replace_image_name; ?>" id="replace_file" value="0" />
            <input id="plupload-browse-button" type="button" value="<?php _e('Replace File'); ?>" class="button" />
        </div>
        <?php

        $plupload_init = array(
            'runtimes'            => 'html5,silverlight,flash,html4',
            'browse_button'       => 'plupload-browse-button',
            'container'           => 'plupload-upload-ui',
            'file_data_name'      => 'async-upload',
            'multiple_queues'     => true,
            'max_file_size'       => wp_max_upload_size().'b',
            'url'                 => admin_url('admin-ajax.php'),
            'flash_swf_url'       => includes_url('js/plupload/plupload.flash.swf'),
            'silverlight_xap_url' => includes_url('js/plupload/plupload.silverlight.xap'),
            'filters'             => array(array('title' => __('Allowed Files'), 'extensions' => '*')),
            'multipart'           => true,
            'urlstream_upload'    => true,

            'multipart_params'    => array(
                '_ajax_nonce' => wp_create_nonce(Mintboard_Editor_Fork_Media::ajax_form),
                'action'      => Mintboard_Editor_Fork_Media::ajax_action,
                'post_id' => $post->ID,
            ),
        );

        $plupload_init = apply_filters('plupload_init', $plupload_init); ?>

        <script type="text/javascript">

            jQuery(document).ready(function($){

                // create the uploader and pass the config from above
                var uploader = new plupload.Uploader(<?php echo json_encode($plupload_init); ?>);

                // checks if browser supports drag and drop upload, makes some css adjustments if necessary
                uploader.bind('Init', function(up){
                    var uploaddiv = $('#plupload-upload-ui');

                    if(up.features.dragdrop){
                        uploaddiv.addClass('drag-drop');
                        $('#drag-drop-area')
                            .bind('dragover.wp-uploader', function(){ uploaddiv.addClass('drag-over'); })
                            .bind('dragleave.wp-uploader, drop.wp-uploader', function(){ uploaddiv.removeClass('drag-over'); });

                    }else{
                        uploaddiv.removeClass('drag-drop');
                        $('#drag-drop-area').unbind('.wp-uploader');
                    }
                });

                uploader.init();

                uploader.bind('FilesAdded', function(up, files){
                    var hundredmb = 100 * 1024 * 1024, max = parseInt(up.settings.max_file_size, 10);
                    if (files.length > 1){
                        alert("<?php _e('You can upload only one file', Mintboard_Editor::text_domain); ?>");
                        return false;
                    }
                    var file = files[0]; //process only the first one
                    if (max > hundredmb && file.size > hundredmb && up.runtime != 'html5'){
                        alert("<?php _e('Maximum allowed size exceeded', Mintboard_Editor::text_domain); ?>");
                        return false;
                    }else{
                        $("#plupload-browse-button").prop("disabled", true);
                    }

                    up.refresh();
                    up.start();
                });

                uploader.bind('FileUploaded', function(up, file, response) {
                    var respData;
                    try {
                        respData = eval(response.response);
                    } catch(err) {
                        respData = eval('(' + response.response + ')');
                    }
                    $("#fork_image").attr("src", respData.url);
                    $("#replace_file").val("<?php echo Mintboard_Editor_Fork_Media::replace_image_tag; ?>");
                });

                uploader.bind('UploadComplete', function(up, file, response) {
                    $("#plupload-browse-button").prop("disabled", false);
                });
                // TODO: show upload error message
            });

        </script>
    </div>
    <div class="wp_attachment_details edit-form-section">
        <p>
            <label for="attachment_caption"><strong><?php _e( 'Caption' ); ?></strong></label><br />
            <textarea class="widefat" name="excerpt" id="attachment_caption"><?php echo $post->post_excerpt; ?></textarea>
        </p>


        <?php if ( 'image' === substr( $post->post_mime_type, 0, 5 ) ) : ?>
            <p>
                <label for="attachment_alt"><strong><?php _e( 'Alternative Text' ); ?></strong></label><br />
                <input type="text" class="widefat" name="_wp_attachment_image_alt" id="attachment_alt" value="<?php echo esc_attr( $alt_text ); ?>" />
            </p>
        <?php endif; ?>

        <?php
        $quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' );
        $editor_args = array(
            'textarea_name' => 'content',
            'textarea_rows' => 5,
            'media_buttons' => false,
            'tinymce' => false,
            'quicktags' => $quicktags_settings,
        );
        ?>

        <label for="attachment_content"><strong><?php _e( 'Description' ); ?></strong><?php
            if ( preg_match( '#^(audio|video)/#', $post->post_mime_type ) ) {
                echo ': ' . __( 'Displayed on attachment pages.' );
            } ?></label>
        <?php wp_editor( $post->post_content, 'attachment_content', $editor_args ); ?>

    </div>
<?php
$extras = get_compat_media_markup( $post->ID );
echo $extras['item'];
echo '<input type="hidden" id="image-edit-context" value="edit-attachment" />' . "\n";
?>