<?php
$thumb_orig = get_post_meta( $this->diff->left->ID, '_thumbnail_id', true );
$thumb_fork = get_post_meta( $this->diff->right->ID, '_thumbnail_id', true );
function _getAttachmentName($thumb_id) {
	if ( $thumb_id  ) {
		$post = get_post( $thumb_id );
		if ($post) {
			$file = get_attached_file( $post->ID );
			$filename = esc_html( wp_basename( $file ) );
			/*
			$title = esc_attr( $post->post_title );
			if (empty($title)){
				$title = $filename;
			}
			*/
			$url = wp_get_attachment_url( $thumb_id );
			return $filename.' ('.$url.')';
		}

	}
	return "No image";
}
?>
<script type="text/javascript">

( function( $ ) {

$( document ).ready( function() {
	$('#compare').mergely( {
		cmsettings: {
			readOnly: true,
			lineNumbers: false,
			lineWrapping: true
		},
		lhs: function( setValue ) {
			setValue( <?php echo json_encode( wp_kses_post( $this->diff->left->post_content ) ); ?> ); 
		},
		rhs: function( setValue ) {
			setValue( <?php echo json_encode( wp_kses_post( $this->diff->right->post_content ) ); ?> ); 
		},
		ignorews: true,
		sidebar: false,
		// TODO: fix width
		// HACK: we can't use 100% because of sidebars
		editor_width: "45%",
	} );
} );

} )( jQuery );
</script>

<div class="wrap">
	<h2><?php _e( 'Compare Fork:', Mintboard_Editor::text_domain ); ?> <?php echo esc_html( $this->diff->right->post_title ); ?></h2>
	<p><?php printf( __( 'Forked from <a href="%1$s">%2$s</a>', Mintboard_Editor::text_domain ), admin_url( "post.php?post={$this->diff->right->post_parent}&action=edit" ), $this->get_parent_name( $this->diff->right ) ); ?></p>

	<div class="postbox">
			<table style="width:100%;">
				<tr><td><h3 class="inside">Post title</h3></td><td><h3 class="inside">Fork title</h3></td></tr>
				<tr><td class="inside"><input style="width:100%;" type="text" id="post-title-orig" readonly="readonly" value="<?php echo esc_html( wp_kses_post( $this->diff->left->post_title ) ); ?>" /></td>
				<td class="inside"><input style="width:100%;" type="text" readonly="readonly" id="post-title-fork" value="<?php
					function _show_fork_string($post_str, $fork_str)
					{
						$s = $fork_str;
						if (empty($fork_str)) {
							$s = $post_str;
						}
						echo esc_html( wp_kses_post( $s ) );
					}
					_show_fork_string($this->diff->left->post_title, $this->diff->right->post_title);
					?>" /></td></tr>

				<tr><td><h3 class="inside">Post slug</h3></td><td><h3 class="inside">Fork slug</h3></td></tr>
				<tr><td class="inside"><input style="width:100%;" type="text" readonly="readonly" id="post-slug-orig" value="<?php echo esc_html( wp_kses_post( $this->diff->left->post_name ) ); ?>" /></td>
				<td class="inside"><input style="width:100%;" type="text" readonly="readonly" id="post-slug-fork" value="<?php
					_show_fork_string($this->diff->left->post_name, $this->diff->right->post_name);
					?>" /></td></tr>

				<tr><td><h3 class="inside">Post excerpt</h3></td><td><h3 class="inside">Fork excerpt</h3></td></tr>
				<tr>
					<td class="inside">
						<!-- TODO: use styles -->
						<textarea rows="5" readonly="readonly" style="width:100%;"><?php echo esc_html( wp_kses_post( $this->diff->left->post_excerpt ) ); ?></textarea>
					</td>
					<td class="inside">
						<textarea rows="5" readonly="readonly" style="width:100%;"><?php echo esc_html( wp_kses_post( $this->diff->right->post_excerpt ) ); ?></textarea>
					</td>
				</tr>
			</table>
	</div>
	<div id="mergely-resizer">
		<div id="compare">
		</div>
	</div>
	<div class="clear"></div>
	<div class="postbox">
		<table style="width:100%;">
			<tr>
			<td>
				<div style="float: left;" class="inside">
					<div id="image-orig">
<?php
		function _showImage($thumb_id)
		{
			if ( $thumb_id && get_post( $thumb_id ) ) {
				$size = isset( $_wp_additional_image_sizes['post-thumbnail'] ) ? 'post-thumbnail' : array( 266, 266 );
				echo wp_get_attachment_image( $thumb_id, $size );
			}
			else{
				$no_image = plugins_url( "/assets/images/no-image.png", dirname( __FILE__ ) );
				echo '<img src="'.$no_image.'" alt="No image" />';
			}
		}
		_showImage($thumb_orig);
?>
					</div>
				</div>
			</td>
			<td>
				<div style="float: left;" class="inside">
<?php
		_showImage($thumb_fork);
?>
				</div>
			</td>
			</tr>
			<tr>
				<td class="inside"><input type="text"  readonly="readonly" style="width:100%;"
				                          value="<?php echo esc_html( wp_kses_post( _getAttachmentName($thumb_orig)) ); ?>" />
				</td>
				<td class="inside"><input type="text" readonly="readonly" style="width:100%;"
				                          value="<?php echo esc_html( wp_kses_post( _getAttachmentName($thumb_fork)) ); ?>" />
				</td>
			</tr>
			</table>
	</div>
</div>