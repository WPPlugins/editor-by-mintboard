<div class="inside"><div class="button-container"><?php if ( $fork ) { ?>
	<?php _e( 'View Fork', Mintboard_Editor::text_domain ); ?>: <a href="<?php echo admin_url('post.php?post=' . ($fork->ID) . '&action=edit' ); ?>"><?php echo esc_html( wp_kses_post( $this->get_fork_name( $fork->ID )) ); ?></a>
<?php } else { ?>
	<a href="<?php echo wp_nonce_url( admin_url( "?fork={$post->ID}" ), Mintboard_Editor::post_type.'-fork_' . $post->ID ); ?>" class="button button-primary"><?php _e( 'Fork', Mintboard_Editor::text_domain ); ?></a>
<?php }
?></div>
<div class="clear"></div>
</div>