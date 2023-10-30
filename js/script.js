jQuery(document).ready(function ($) {
    // Do stuff
    // console.log('Hello from WPO-MOD!');

    var $wpo_mod_run_cache_preload_btn = $('#wpo_mod_preload_cache'),
        $cache_preload_status_el = $('#wpo_mod_preload_cache_status'),
        check_status_interval = null;

    $wpo_mod_run_cache_preload_btn.on('click', handlePreloadCacheButtonClick);

    function handlePreloadCacheButtonClick(e) {
        var $btn = $(this),
            is_running = $btn.data('running'),
            status = $cache_preload_status_el.text(),
            ajaxBody = {
                action: wpo_mod_ajax_object.run_action,
                nonce: wpo_mod_ajax_object.nonce,
                post_id: wpo_mod_ajax_object.post_id
            };

        $btn.prop('disabled', true);

        if (is_running) {
            $btn.data('running', false);
            clearInterval(check_status_interval);
            check_status_interval = null;

            ajaxBody.action = wpo_mod_ajax_object.cancel_action;

            $.ajax({
                url: wpo_mod_ajax_object.ajax_url,
                type: 'POST',
                data: ajaxBody,
                success: function (data) {
                    // console.log('WPO-MOD Preload Cache Success!');
                    // console.log(data);
                    if (data && data.hasOwnProperty('message')) {
                        $cache_preload_status_el.text(data.message);
                    }
                },
                error: function (error) {
                    // console.log('WPO-MOD Preload Cache Error!');
                    // console.log(error);
                }
            }).always(function () {
                $btn.val(wpo_mod_ajax_object.run_now);
                $btn.prop('disabled', false);
            });
        } else {
            $cache_preload_status_el.text(wpo_mod_ajax_object.starting_preload);

            $btn.data('running', true);

            ajaxBody.action = wpo_mod_ajax_object.run_action;

            $.ajax({
                url: wpo_mod_ajax_object.ajax_url,
                type: 'POST',
                data: ajaxBody,
                success: function (data) {
                    // console.log('WPO-MOD Preload Cache Success!');
                    // console.log(data);
                    run_update_cache_preload_status();
                },
                error: function (error) {
                    // console.log('WPO-MOD Preload Cache Error!');
                    // console.log(error);
                    $btn.prop('disabled', false);
                    $btn.data('running', false);
                }
            }).always(function (data) {
                if (data && !data.success) {

                    var error_text = wpo_mod_ajax_object.error_text;

                    if (data.data.message) {
                        error_text = data.data.message;
                    }

                    alert(error_text);

                    $cache_preload_status_el.text(status);
                    $btn.prop('disabled', false);
                    $btn.data('running', false);

                    return;
                }

                // Preload Cache is running
                $cache_preload_status_el.text(wpo_mod_ajax_object.loading_urls);
                $btn.val(wpo_mod_ajax_object.cancel);
                $btn.prop('disabled', false);
            });
        }
    }

    /**
     * If already running then update status
     */
    if ($wpo_mod_run_cache_preload_btn.data('running')) {
        run_update_cache_preload_status();
    }

    /**
     * Create interval action for update preloader status.
     *
     * @return void
     */
    function run_update_cache_preload_status() {
        if (check_status_interval) return;

        check_status_interval = setInterval(function () {
            update_cache_preload_status();
        }, 5000);
    }

    /**
     * Update cache preload status ajax action.
     *
     * @return void
     */
    function update_cache_preload_status() {
        // Ajax request body
        let ajaxBody = {
            action: wpo_mod_ajax_object.update_status_action,
            nonce: wpo_mod_ajax_object.nonce,
            post_id: wpo_mod_ajax_object.post_id
        };

        // Make ajax request
        $.ajax({
            url: wpo_mod_ajax_object.ajax_url,
            type: 'POST',
            data: ajaxBody,
            success: function (data) {
                // console.log('WPO-MOD Update Preload Cache Status Success!');
                // console.log(data);
                if (data.done) {
                    $wpo_mod_run_cache_preload_btn.val(wpo_mod_ajax_object.run_now);
                    $wpo_mod_run_cache_preload_btn.data('running', false);
                    clearInterval(check_status_interval);
                    check_status_interval = null;
                } else {
                    $wpo_mod_run_cache_preload_btn.val(wpo_mod_ajax_object.cancel);
                    $wpo_mod_run_cache_preload_btn.data('running', true);
                }
                $cache_preload_status_el.text(data.message);
            },
            error: function (error) {
                // console.log('WPO-MOD Update Preload Cache Status Error!');
                // console.log(error);
            }
        });
    }

    // Call update status function on initial load
    update_cache_preload_status();
});