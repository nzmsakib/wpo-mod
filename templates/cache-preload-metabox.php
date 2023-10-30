<div class="wpo-mod-cache-preload-metabox">
    <p>
        Preload cache for this <?php echo $post->post_type; ?>.
    </p>
    <p>
		<input id="wpo_mod_preload_cache" class="button button-primary" type="submit" name="wpo_mod_preload_cache" value="<?php echo $is_running ? esc_attr__('Cancel', 'wp-optimize') : esc_attr__('Run now', 'wp-optimize'); ?>" <?php echo $is_running ? 'data-running="1"' : ''; ?>>
		<div id="wpo_mod_preload_cache_status"><?php
			echo esc_html($status_message);
		?></div>
	</p>
</div>