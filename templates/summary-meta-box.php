<div id="fork-summary">
<?php
$summary = Mintboard_Editor::get_summary($post->ID);
if(!$summary->has_parent){
    echo '<p>'.__('This is a completely new fork', Mintboard_Editor::text_domain) . '</p>';
}
else {
    $info_set = Mintboard_Editor_Summary::regular_summary;
    if (Mintboard_Editor::is_media_fork($post)) {
        $info_set = Mintboard_Editor_Summary::media_summary;
    }
    foreach ($info_set as $k => $v) {
    ?>
    <p>
        <?php echo(__($v[0], Mintboard_Editor::text_domain) . ': ' . Mintboard_Editor_Summary::yes_no($summary->$k)); ?>
    </p>
    <?php
    }
}
?>
    <div class="clear"></div>
</div>
