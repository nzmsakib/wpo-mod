<?php
/*
Plugin Name: WPO Mod
Plugin URI: 
Description: WordPress Plugin for WP-Optimize. This will add some extra features to WP-Optimize. The feature is to cache signle post/page from the editing screen.
Version: 1.0.0
Text Domain: wpo-mod
Author: Md Nazmus Sakib
Author URI: https://github.com/nzmsakib
*/

if (!defined('ABSPATH')) die('No direct access allowed');

// Check to make sure if WP_Optimize is already call and returns.
if (!class_exists('WPO_Mod')) :
    define('WPOM_VERSION', '1.0.0');
    define('WPOM_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('WPOM_PLUGIN_MAIN_PATH', plugin_dir_path(__FILE__));
    define('WPOM_PLUGIN_SLUG', plugin_basename(__FILE__));

    class WPO_Mod
    {
        protected static $_instance = null;

        // allowed post types
        protected $allowed_post_types = array('post', 'page');

        // flag to check if the single post/page cache preload button is clicked
        protected $is_preload_cache_clicked = false;

        // the post to be cached
        protected $post = null;

        // WP Optimize Page Cache Preloader Instance
        protected $cache_preloader = null;

        /**
         * Class constructor
         */
        public function __construct()
        {
            if (!$this->is_required_plugins_active()) {
                // Show admin notice if required plugins are not active
                add_action('admin_notices', array($this, 'admin_notices'));
                return;
            }

            // Add metabox to post/page edit screen
            add_action('add_meta_boxes', array($this, 'add_meta_box'));

            add_action('wpo_mod_after_render_meta_box', array($this, 'do_after_render_meta_box'));

            // ajax action for running preload cache
            add_action('wp_ajax_wpo_mod_run_preload_cache', array($this, 'wpo_mod_run_preload_cache'), 8, 1);
            add_action('wp_ajax_wpo_mod_cancel_preload_cache', array($this, 'wpo_mod_cancel_preload_cache'), 7, 1);
            add_action('wp_ajax_wpo_mod_update_cache_preload_status', array($this, 'wpo_mod_update_cache_preload_status'), 10, 1);

            // filter to get site urls
            add_filter('wpo_preload_get_site_urls', array($this, 'wpo_mod_preload_get_site_urls'), 10, 1);

            // Initialize WP Optimize Page Cache Preloader
            $this->cache_preloader = WP_Optimize_Page_Cache_Preloader::instance();
        }

        /**
         * Cancel preload cache
         */
        public function wpo_mod_cancel_preload_cache()
        {
            // $this->logToFile('wpo_mod_cancel_preload_cache() called');

            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id) {
                wp_send_json_error(array('message' => __('Invalid post id.', 'wpo-mod')));
                return;
            }
            $this->cache_preloader->cancel_preload();
            $info = $this->cache_preloader->get_status_info();

            // $this->logToFile('Message: ' . $info['message']);
            // $this->logToFile('Clicked: ' . $this->is_preload_cache_clicked == true ? 'true' : 'false');

            // $info['message'] = get_post_meta($post_id, 'wpo_mod_preload_cache_status', true);

            wp_send_json($info);
        }

        /**
         * Get status of cache preload.
         *
         * @return array
         */
        public function wpo_mod_update_cache_preload_status()
        {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id) {
                wp_send_json_error(array('message' => __('Invalid post id.', 'wpo-mod')));
                return;
            }
            $info = $this->cache_preloader->get_status_info();

            // $this->logToFile('wpo_mod_update_cache_preload_status() Clicked: ' . ($this->is_preload_cache_clicked == true ? 'true' : 'false'));
            // if ($this->is_preload_cache_clicked == true) {
            //     $this->logToFile('Message: ' . $info['message']);
            //     $this->logToFile('Clicked: true');
            //     update_post_meta($post_id, 'wpo_mod_preload_cache_status', $info['message']);
            // }
            // $info['message'] = get_post_meta($post_id, 'wpo_mod_preload_cache_status', true);


            wp_send_json($info);
        }

        /**
         * Preload cache
         */
        public function wpo_mod_run_preload_cache()
        {
            $this->is_preload_cache_clicked = true;
            // $this->logToFile('wpo_mod_run_preload_cache() called: ' . ($this->is_preload_cache_clicked == true ? 'true' : 'false'));

            if (!$this->cache_preloader->is_option_active()) {
                wp_send_json_error(array('message' => __('Cache preload is not enabled.', 'wpo-mod')));
                return;
            }

            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id) {
                wp_send_json_error(array('message' => __('Invalid post id.', 'wpo-mod')));
                return;
            }

            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(array('message' => __('Post not found.', 'wpo-mod')));
                return;
            }

            $this->post = $post;
            $this->cache_preloader->run('manual');
            update_post_meta($post_id, 'wpo_mod_preload_cache_status', __('Starting preload...', 'wpo-mod'));
            wp_send_json(array('message' => __('Cache preload started.', 'wpo-mod')));
        }

        /**
         * Get site urls
         */
        public function wpo_mod_preload_get_site_urls($urls)
        {
            // $this->logToFile('wpo_mod_preload_get_site_urls() called');

            if (!$this->is_preload_cache_clicked || !$this->post) {
                // $this->logToFile('wpo_mod_preload_get_site_urls() is_preload_cache_clicked is false. Total urls: ' . count($urls));
                return $urls;
            }

            $url = get_permalink($this->post);
            // $this->logToFile('wpo_mod_preload_get_site_urls() is_preload_cache_clicked is true. URL: ' . $url . ' Total urls: 1');
            return [
                $url
            ];
        }

        /**
         * After render metabox
         */
        function do_after_render_meta_box($post)
        {
            $enqueue_version = $this->get_enqueue_version();

            $ajax_object = array(
                'nonce' => wp_create_nonce('wpom-ajax-nonce'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'post_id' => $post->ID,
                'run_action' => 'wpo_mod_run_preload_cache',
                'cancel_action' => 'wpo_mod_cancel_preload_cache',
                'update_status_action' => 'wpo_mod_update_cache_preload_status',
                'run_now' => __('Run now', 'wpo-mod'),
                'cancel' => __('Cancel', 'wpo-mod'),
                'starting_preload' => __('Starting preload...', 'wpo-mod'),
                'error_text' => __('Error', 'wpo-mod'),
                'loading_urls' => __('Loading URLs...', 'wpo-mod'),
            );

            // Register or enqueue scripts only after metabox loaded
            wp_enqueue_script('wpo-mod-script', WPOM_PLUGIN_URL . 'js/script.js', array('jquery'), $enqueue_version);
            wp_localize_script('wpo-mod-script', 'wpo_mod_ajax_object', $ajax_object);
        }

        /**
         * Add metabox to post/page edit screen
         */
        public function add_meta_box()
        {
            foreach ($this->allowed_post_types as $post_type) {
                add_meta_box(
                    'wpo-mod-metabox',
                    __('Cache Preload', 'wpo-mod'),
                    array($this, 'render_meta_box'),
                    $post_type,
                    'side',
                    'high'
                );
            }
        }

        /**
         * Render metabox
         */
        public function render_meta_box($post)
        {
            $is_running = $this->cache_preloader->is_running();
            $status_message = get_post_meta($post->ID, 'wpo_mod_preload_cache_status', true);

            include_once(WPOM_PLUGIN_MAIN_PATH . 'templates/cache-preload-metabox.php');
            do_action('wpo_mod_after_render_meta_box', $post);
        }

        /**
         * Show admin notice if required plugins are not active
         */
        public function admin_notices()
        {
            $required_plugins = $this->get_required_plugins();
            $active_plugins = get_option('active_plugins');
            $missing_plugins = array_diff($required_plugins, $active_plugins);

            if (count($missing_plugins) > 0) {
                $message = sprintf(
                    __('%s requires %s to be installed and activated.', 'wpo-mod'),
                    '<strong>' . __('WPO Mod', 'wpo-mod') . '</strong>',
                    '<strong>' . implode(', ', array_keys($missing_plugins)) . '</strong>'
                );
                echo '<div class="error"><p>' . $message . '</p></div>';
            }
        }

        /**
         * Returns desired enqueue version string
         *
         * @return string Enqueue version as string
         */
        public function get_enqueue_version()
        {
            return (defined('WP_DEBUG') && WP_DEBUG) ? WPOM_VERSION . '.' . time() : WPOM_VERSION;
        }

        /**
         * Auto-loads classes.
         *
         * @param string $class_name The name of the class.
         */
        private function loader($class_name)
        {
            $dirs = $this->get_class_directories();

            foreach ($dirs as $dir) {
                $class_file = WPOM_PLUGIN_MAIN_PATH . trailingslashit($dir) . 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
                if (file_exists($class_file)) {
                    require_once($class_file);
                    return;
                }
            }
        }

        /**
         * Returns an array of class directories
         *
         * @return array
         */
        private function get_class_directories()
        {
            return array(
            );
        }

        /**
         * Returns true if required plugins are installed and active
         *
         * @return bool
         */
        private function is_required_plugins_active()
        {
            $required_plugins = $this->get_required_plugins();
            $active_plugins = get_option('active_plugins');

            foreach ($required_plugins as $plugin) {
                if (!in_array($plugin, $active_plugins)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Returns an array of required plugin slugs
         *
         * @return array
         */
        private function get_required_plugins()
        {
            return array(
                'wp-optimize' => 'wp-optimize/wp-optimize.php'
            );
        }

        public static function instance()
        {
            if (empty(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        private function logToFile($msg)
        {
            $logFile = WPOM_PLUGIN_MAIN_PATH . 'log.txt';
            $log = fopen($logFile, 'a');
            fwrite($log, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n"); // "\n" is the newline character in UNIX
            fclose($log);
        }
    }

    function WPO_Mod()
    {
        return WPO_Mod::instance();
    }
endif;

$GLOBALS['wpo_mod'] = WPO_Mod();
