<div id="fork-info">
<?php
if($post->post_parent) {
	printf(__('<p>Forked from <a href="%1$s">%2$s</a></p>', Mintboard_Editor::text_domain), admin_url("post.php?post={$post->post_parent}&action=edit"), $this->get_parent_name($post));
}
if($post->post_status != 'auto-draft') {
	?>
	<div class="button-container">
		<a href="<?php echo esc_url(get_preview_post_link($post)); ?>" target="_blank"
		   class="button button-large"><?php _e('Preview', Mintboard_Editor::text_domain); ?></a>
	</div>
	<?php
}
else {
?>
	<div class="button-container">
		<label for="<?php echo Mintboard_Editor::tag_fork_type; ?>"><?php _e('Fork type', Mintboard_Editor::text_domain); ?></label>
		<select name="<?php echo Mintboard_Editor::tag_fork_type; ?>" id="<?php echo Mintboard_Editor::tag_fork_type; ?>">
			<option value="post"><?php _e('post', Mintboard_Editor::text_domain); ?></option>
			<option value="page"<?php
			if(isset($_REQUEST[Mintboard_Editor_Admin::filter_tag]) && ($_REQUEST[Mintboard_Editor_Admin::filter_tag] == 'page')) { echo ' selected'; } ?>>
				<?php _e('page', Mintboard_Editor::text_domain); ?></option>
		</select>
	</div>
<?php
}
if($post->post_parent) {
?>
	<div class="button-container">
		<a href="<?php echo admin_url( "revision.php?page=fork-diff&right={$post->ID}" ); ?>" target="_blank"
		   class="button button-large"><?php _e( 'Compare', Mintboard_Editor::text_domain ); ?></a>
	</div>
<?php
}
if($post->post_status != 'auto-draft') {
?>
<div class="button-container">
	<a href="<?php echo get_delete_post_link($post->ID); ?>"
	   class="button button-large"><?php _e('Move to Trash', Mintboard_Editor::text_domain); ?></a>
</div>
<?php
}
?>
</div>
<div id="major-publishing-actions">
<div id="delete-action">
<?php
$can_merge = current_user_can( 'publish_fork', $post->ID ) && ($post->post_status != 'auto-draft');
if($can_merge) {
	submit_button(__('Save Fork', Mintboard_Editor::text_domain), 'button button-large', 'save', false);
}
else {
	echo '&nbsp;';
}
?>
</div>

<div id="publishing-action">
<?php
if($can_merge) {
	?><img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="ajax-loading" id="ajax-loading" alt=""
		   style="visibility: hidden; ">
	<input name="original_publish" type="hidden" id="original_publish" value="Publish">
	<?php
	$publish_text = 'Merge';
	if(!$post->post_parent) {
		$publish_text = 'Publish';
	}
	submit_button(__($publish_text, Mintboard_Editor::text_domain), 'primary', 'publish', false);
}
else{
	submit_button(__('Save Fork', Mintboard_Editor::text_domain), 'primary', 'save', false);
}?>
</div>
<div class="clear"></div>
</div>
