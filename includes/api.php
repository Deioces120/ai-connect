<?php
if (!defined('ABSPATH')) exit;

class AIC_API {

    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function auth($request) {
        return AIC_Auth::check($request);
    }

    private function ok($data) {
        return rest_ensure_response($data);
    }

    private function err($message, $code = 400) {
        return new WP_Error('aic_error', $message, ['status' => $code]);
    }

    private function post_array($post, $full = false) {
        $data = [
            'id'       => (int) $post->ID,
            'title'    => $post->post_title,
            'status'   => $post->post_status,
            'type'     => $post->post_type,
            'author'   => (int) $post->post_author,
            'date'     => $post->post_date,
            'modified' => $post->post_modified,
            'slug'     => $post->post_name,
        ];
        if ($full) {
            $data['content']   = $post->post_content;
            $data['excerpt']   = $post->post_excerpt;
            $data['permalink'] = get_permalink($post->ID);
            $data['meta']      = get_post_meta($post->ID);
        }
        return $data;
    }

    private function is_text_file($filename) {
        $text_exts = ['php', 'css', 'js', 'html', 'htm', 'txt', 'json', 'xml', 'svg', 'md', 'log', 'htaccess', 'ini', 'yml', 'yaml', 'env', 'sql', 'csv', 'less', 'sass', 'scss'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $text_exts, true) || empty($ext);
    }

    public function register_routes() {

        // Ping (no auth)
        register_rest_route('ai-connect/v1', '/ping', [[
            'methods'             => 'GET',
            'callback'            => function () {
                return $this->ok([
                    'status'  => 'ok',
                    'version' => AIC_VERSION,
                    'time'    => current_time('mysql'),
                    'site'    => get_site_url(),
                    'php'     => PHP_VERSION,
                    'wp'      => get_bloginfo('version'),
                ]);
            },
            'permission_callback' => '__return_true',
        ]]);

        // Posts
        register_rest_route('ai-connect/v1', '/posts', [
            ['methods' => 'GET', 'callback' => [$this, 'get_posts'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_post'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/posts/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'get_post'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'PUT', 'callback' => [$this, 'update_post'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_post'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Pages
        register_rest_route('ai-connect/v1', '/pages', [
            ['methods' => 'GET', 'callback' => [$this, 'get_pages'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_page'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/pages/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'get_page'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'PUT', 'callback' => [$this, 'update_page'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_page'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Media
        register_rest_route('ai-connect/v1', '/media', [
            ['methods' => 'GET', 'callback' => [$this, 'get_media'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'upload_media'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/media/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_media'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Users
        register_rest_route('ai-connect/v1', '/users', [
            ['methods' => 'GET', 'callback' => [$this, 'get_users'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/users/me', [
            ['methods' => 'GET', 'callback' => [$this, 'get_me'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Comments
        register_rest_route('ai-connect/v1', '/comments', [
            ['methods' => 'GET', 'callback' => [$this, 'get_comments'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_comment'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/comments/(?P<id>\d+)', [
            ['methods' => 'PUT', 'callback' => [$this, 'update_comment'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_comment'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Categories & Tags
        register_rest_route('ai-connect/v1', '/categories', [
            ['methods' => 'GET', 'callback' => [$this, 'get_categories'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_category'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/tags', [
            ['methods' => 'GET', 'callback' => [$this, 'get_tags'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_tag'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Theme files
        register_rest_route('ai-connect/v1', '/theme/files', [
            ['methods' => 'GET', 'callback' => [$this, 'theme_files'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/theme/read', [
            ['methods' => 'GET', 'callback' => [$this, 'theme_read'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/theme/write', [
            ['methods' => 'POST', 'callback' => [$this, 'theme_write'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Plugin files
        register_rest_route('ai-connect/v1', '/plugin/files', [
            ['methods' => 'GET', 'callback' => [$this, 'plugin_files'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/plugin/read', [
            ['methods' => 'GET', 'callback' => [$this, 'plugin_read'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/plugin/write', [
            ['methods' => 'POST', 'callback' => [$this, 'plugin_write'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Plugins management
        register_rest_route('ai-connect/v1', '/plugins', [
            ['methods' => 'GET', 'callback' => [$this, 'list_plugins'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/plugins/activate', [
            ['methods' => 'POST', 'callback' => [$this, 'activate_plugin'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/plugins/deactivate', [
            ['methods' => 'POST', 'callback' => [$this, 'deactivate_plugin'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Themes management
        register_rest_route('ai-connect/v1', '/themes', [
            ['methods' => 'GET', 'callback' => [$this, 'list_themes'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/themes/activate', [
            ['methods' => 'POST', 'callback' => [$this, 'activate_theme'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Widgets
        register_rest_route('ai-connect/v1', '/widgets', [
            ['methods' => 'GET', 'callback' => [$this, 'get_widgets'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'save_widget'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/widget-areas', [
            ['methods' => 'GET', 'callback' => [$this, 'widget_areas'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Menus
        register_rest_route('ai-connect/v1', '/menus', [
            ['methods' => 'GET', 'callback' => [$this, 'get_menus'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_menu'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/menus/(?P<id>\d+)/items', [
            ['methods' => 'GET', 'callback' => [$this, 'menu_items'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'add_menu_item'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/menu-items/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_menu_item'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Options
        register_rest_route('ai-connect/v1', '/options', [
            ['methods' => 'GET', 'callback' => [$this, 'all_options'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/options/(?P<key>.+)', [
            ['methods' => 'GET', 'callback' => [$this, 'get_option_val'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'set_option_val'], 'permission_callback' => [$this, 'auth']],
        ]);

        // File system
        register_rest_route('ai-connect/v1', '/files', [
            ['methods' => 'GET', 'callback' => [$this, 'list_files'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/files/read', [
            ['methods' => 'GET', 'callback' => [$this, 'read_file'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/files/write', [
            ['methods' => 'POST', 'callback' => [$this, 'write_file'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/files/delete', [
            ['methods' => 'POST', 'callback' => [$this, 'delete_file'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/files/mkdir', [
            ['methods' => 'POST', 'callback' => [$this, 'make_dir'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Database
        register_rest_route('ai-connect/v1', '/db/query', [
            ['methods' => 'POST', 'callback' => [$this, 'db_query'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/db/tables', [
            ['methods' => 'GET', 'callback' => [$this, 'db_tables'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/db/table/(?P<name>[a-zA-Z0-9_]+)', [
            ['methods' => 'GET', 'callback' => [$this, 'db_describe'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Custom CSS
        register_rest_route('ai-connect/v1', '/custom-css', [
            ['methods' => 'GET', 'callback' => [$this, 'get_css'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'set_css'], 'permission_callback' => [$this, 'auth']],
        ]);

        // System
        register_rest_route('ai-connect/v1', '/system/info', [
            ['methods' => 'GET', 'callback' => [$this, 'sys_info'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/system/exec', [
            ['methods' => 'POST', 'callback' => [$this, 'sys_exec'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/system/php', [
            ['methods' => 'POST', 'callback' => [$this, 'sys_php'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Search
        register_rest_route('ai-connect/v1', '/search', [
            ['methods' => 'GET', 'callback' => [$this, 'search'], 'permission_callback' => [$this, 'auth']],
        ]);

        // ===== NEW FEATURES =====

        // Custom Post Types
        register_rest_route('ai-connect/v1', '/post-types', [
            ['methods' => 'GET', 'callback' => [$this, 'get_post_types'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_post_type'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Redirects
        register_rest_route('ai-connect/v1', '/redirects', [
            ['methods' => 'GET', 'callback' => [$this, 'get_redirects'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'add_redirect'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/redirects/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_redirect'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Cache
        register_rest_route('ai-connect/v1', '/cache/flush', [
            ['methods' => 'POST', 'callback' => [$this, 'flush_cache'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Request Log
        register_rest_route('ai-connect/v1', '/logs', [
            ['methods' => 'GET', 'callback' => [$this, 'get_logs'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Rate Limit Settings
        register_rest_route('ai-connect/v1', '/rate-limit', [
            ['methods' => 'GET', 'callback' => [$this, 'get_rate_limit'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'set_rate_limit'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Setup Wizard
        register_rest_route('ai-connect/v1', '/setup', [
            ['methods' => 'POST', 'callback' => [$this, 'run_setup'], 'permission_callback' => [$this, 'auth']],
        ]);

        // WooCommerce Products
        register_rest_route('ai-connect/v1', '/products', [
            ['methods' => 'GET', 'callback' => [$this, 'get_products'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_product'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/products/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'get_product'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'PUT', 'callback' => [$this, 'update_product'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_product'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Orders (WooCommerce)
        register_rest_route('ai-connect/v1', '/orders', [
            ['methods' => 'GET', 'callback' => [$this, 'get_orders'], 'permission_callback' => [$this, 'auth']],
        ]);

        // SEO Settings
        register_rest_route('ai-connect/v1', '/seo', [
            ['methods' => 'GET', 'callback' => [$this, 'get_seo'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'set_seo'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Health Check
        register_rest_route('ai-connect/v1', '/health', [
            ['methods' => 'GET', 'callback' => [$this, 'health_check'], 'permission_callback' => [$this, 'auth']],
        ]);

        // ===== SEO ANALYSIS =====

        // Analyze page/post SEO
        register_rest_route('ai-connect/v1', '/seo/analyze/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_analyze'], 'permission_callback' => [$this, 'auth']],
        ]);

        // SEO Score for all pages
        register_rest_route('ai-connect/v1', '/seo/scores', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_scores'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Keyword density analysis
        register_rest_route('ai-connect/v1', '/seo/keywords/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_keywords'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Content quality analysis
        register_rest_route('ai-connect/v1', '/seo/quality/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_quality'], 'permission_callback' => [$this, 'auth']],
        ]);

        // SEO suggestions
        register_rest_route('ai-connect/v1', '/seo/suggestions/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_suggestions'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Auto-generate meta tags
        register_rest_route('ai-connect/v1', '/seo/auto-meta/(?P<id>\d+)', [
            ['methods' => 'POST', 'callback' => [$this, 'seo_auto_meta'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Site-wide SEO overview
        register_rest_route('ai-connect/v1', '/seo/overview', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_overview'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Analytics
        register_rest_route('ai-connect/v1', '/analytics/pages', [
            ['methods' => 'GET', 'callback' => [$this, 'analytics_pages'], 'permission_callback' => [$this, 'auth']],
        ]);

        register_rest_route('ai-connect/v1', '/analytics/summary', [
            ['methods' => 'GET', 'callback' => [$this, 'analytics_summary'], 'permission_callback' => [$this, 'auth']],
        ]);

        // ===== AGENT & DEVOPS =====

        register_rest_route('ai-connect/v1', '/agent/history', [
            ['methods' => 'GET', 'callback' => [$this, 'agent_history'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'agent_log'], 'permission_callback' => [$this, 'auth']],
        ]);

        register_rest_route('ai-connect/v1', '/devops/health', [
            ['methods' => 'GET', 'callback' => [$this, 'devops_health'], 'permission_callback' => [$this, 'auth']],
        ]);

        register_rest_route('ai-connect/v1', '/devops/scan', [
            ['methods' => 'POST', 'callback' => [$this, 'devops_scan'], 'permission_callback' => [$this, 'auth']],
        ]);

        register_rest_route('ai-connect/v1', '/devops/firewall', [
            ['methods' => 'GET', 'callback' => [$this, 'devops_firewall'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'devops_block_ip'], 'permission_callback' => [$this, 'auth']],
        ]);

        register_rest_route('ai-connect/v1', '/devops/warnings', [
            ['methods' => 'GET', 'callback' => [$this, 'devops_warnings'], 'permission_callback' => [$this, 'auth']],
        ]);

        // ===== COMPREHENSIVE SEO FEATURES =====

        // Deep Analysis per page
        register_rest_route('ai-connect/v1', '/seo/deep/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_deep_analysis'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Site-wide health
        register_rest_route('ai-connect/v1', '/seo/site-health', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_site_health'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Content matrix
        register_rest_route('ai-connect/v1', '/seo/content-matrix', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_content_matrix'], 'permission_callback' => [$this, 'auth']],
        ]);

        // SEO Report (all pages)
        register_rest_route('ai-connect/v1', '/seo/report', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_report'], 'permission_callback' => [$this, 'auth']],
        ]);

        // 404 Deep Analysis
        register_rest_route('ai-connect/v1', '/seo/404-deep', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_404_deep'], 'permission_callback' => [$this, 'auth']],
        ]);

        // 404 Logs
        register_rest_route('ai-connect/v1', '/seo/404-logs', [
            ['methods' => 'GET', 'callback' => [$this, 'get_404_logs'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route('ai-connect/v1', '/seo/404-logs/clear', [
            ['methods' => 'POST', 'callback' => [$this, 'clear_404_logs'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Broken Links Deep
        register_rest_route('ai-connect/v1', '/seo/broken-deep', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_broken_deep'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Content Analysis
        register_rest_route('ai-connect/v1', '/seo/analyze-content/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'analyze_content'], 'permission_callback' => [$this, 'auth']],
        ]);

        // AI Agent: Raw page data for analysis
        register_rest_route('ai-connect/v1', '/seo/raw-data/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_raw_data'], 'permission_callback' => [$this, 'auth']],
        ]);

        // AI Agent: Save analysis results
        register_rest_route('ai-connect/v1', '/seo/save-analysis', [
            ['methods' => 'POST', 'callback' => [$this, 'seo_save_analysis'], 'permission_callback' => [$this, 'auth']],
        ]);

        // AI Agent: Get saved analysis
        register_rest_route('ai-connect/v1', '/seo/get-analysis/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_get_analysis'], 'permission_callback' => [$this, 'auth']],
        ]);

        // AI Agent: Bulk analysis prompt
        register_rest_route('ai-connect/v1', '/seo/bulk-prompt', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_bulk_prompt'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Content Generation
        register_rest_route('ai-connect/v1', '/seo/generate-content', [
            ['methods' => 'POST', 'callback' => [$this, 'generate_content'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Image Search
        register_rest_route('ai-connect/v1', '/seo/search-images', [
            ['methods' => 'GET', 'callback' => [$this, 'search_images'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Save Content to Post
        register_rest_route('ai-connect/v1', '/seo/save-content', [
            ['methods' => 'POST', 'callback' => [$this, 'save_content'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Content Queue: list pending tasks for agents
        register_rest_route('ai-connect/v1', '/seo/content-queue', [
            ['methods' => 'GET', 'callback' => [$this, 'content_queue'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Content Queue: agent submits completed content
        register_rest_route('ai-connect/v1', '/seo/content-complete', [
            ['methods' => 'POST', 'callback' => [$this, 'content_complete'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Meta Optimization
        register_rest_route('ai-connect/v1', '/seo/optimize-meta/(?P<id>\d+)', [
            ['methods' => 'POST', 'callback' => [$this, 'optimize_meta'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Robots.txt
        register_rest_route('ai-connect/v1', '/seo/robots', [
            ['methods' => 'GET', 'callback' => [$this, 'get_robots'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'save_robots'], 'permission_callback' => [$this, 'auth']],
        ]);

        // Full SEO Data for AI Agent
        register_rest_route('ai-connect/v1', '/seo/full-data', [
            ['methods' => 'GET', 'callback' => [$this, 'seo_full_data'], 'permission_callback' => [$this, 'auth']],
        ]);
    }

    // ======================== POSTS ========================

    public function get_posts($request) {
        $args = [
            'post_type'      => 'post',
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 20),
            'paged'          => (int) ($request->get_param('page') ?: 1),
            'post_status'    => $request->get_param('status') ?: 'any',
        ];
        if ($s = $request->get_param('search')) {
            $args['s'] = sanitize_text_field($s);
        }
        $query = new WP_Query($args);
        $data = [];
        foreach ($query->posts as $post) {
            $data[] = $this->post_array($post);
        }
        return $this->ok(['data' => $data, 'total' => $query->found_posts, 'pages' => $query->max_num_pages]);
    }

    public function get_post($request) {
        $post = get_post((int) $request['id']);
        if (!$post) {
            return $this->err('Post not found', 404);
        }
        return $this->ok($this->post_array($post, true));
    }

    public function create_post($request) {
        $id = wp_insert_post([
            'post_title'   => sanitize_text_field($request->get_param('title')),
            'post_content' => $request->get_param('content') ?? '',
            'post_excerpt' => $request->get_param('excerpt') ?? '',
            'post_status'  => $request->get_param('status') ?: 'draft',
            'post_type'    => $request->get_param('type') ?: 'post',
            'post_author'  => (int) ($request->get_param('author') ?: get_current_user_id()),
            'meta_input'   => $request->get_param('meta') ?: [],
        ], true);
        if (is_wp_error($id)) {
            return $id;
        }
        return $this->ok($this->post_array(get_post($id), true));
    }

    public function update_post($request) {
        $data = ['ID' => (int) $request['id']];
        if ($t = $request->get_param('title')) {
            $data['post_title'] = sanitize_text_field($t);
        }
        if ($request->get_param('content') !== null) {
            $data['post_content'] = $request->get_param('content');
        }
        if ($request->get_param('excerpt') !== null) {
            $data['post_excerpt'] = $request->get_param('excerpt');
        }
        if ($s = $request->get_param('status')) {
            $data['post_status'] = sanitize_text_field($s);
        }
        if ($type = $request->get_param('type')) {
            $data['post_type'] = sanitize_text_field($type);
        }
        $result = wp_update_post($data, true);
        if (is_wp_error($result)) {
            return $result;
        }
        if ($meta = $request->get_param('meta')) {
            foreach ((array) $meta as $key => $val) {
                update_post_meta($data['ID'], sanitize_key($key), $val);
            }
        }
        return $this->ok($this->post_array(get_post($data['ID']), true));
    }

    public function delete_post($request) {
        $result = wp_delete_post((int) $request['id'], true);
        if (!$result) {
            return $this->err('Delete failed', 500);
        }
        return $this->ok(['deleted' => true, 'id' => (int) $request['id']]);
    }

    // ======================== PAGES ========================

    public function get_pages($request) {
        $args = [
            'post_type'      => 'page',
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 20),
            'paged'          => (int) ($request->get_param('page') ?: 1),
            'post_status'    => $request->get_param('status') ?: 'any',
        ];
        if ($s = $request->get_param('search')) {
            $args['s'] = sanitize_text_field($s);
        }
        $query = new WP_Query($args);
        $data = [];
        foreach ($query->posts as $post) {
            $data[] = $this->post_array($post);
        }
        return $this->ok(['data' => $data, 'total' => $query->found_posts, 'pages' => $query->max_num_pages]);
    }

    public function get_page($request) {
        $post = get_post((int) $request['id']);
        if (!$post) {
            return $this->err('Page not found', 404);
        }
        return $this->ok($this->post_array($post, true));
    }

    public function create_page($request) {
        $id = wp_insert_post([
            'post_title'    => sanitize_text_field($request->get_param('title')),
            'post_content'  => $request->get_param('content') ?? '',
            'post_status'   => $request->get_param('status') ?: 'draft',
            'post_type'     => 'page',
            'post_author'   => (int) ($request->get_param('author') ?: get_current_user_id()),
            'page_template' => sanitize_text_field($request->get_param('template') ?? ''),
            'meta_input'    => $request->get_param('meta') ?: [],
        ], true);
        if (is_wp_error($id)) {
            return $id;
        }
        return $this->ok($this->post_array(get_post($id), true));
    }

    public function update_page($request) {
        return $this->update_post($request);
    }

    public function delete_page($request) {
        return $this->delete_post($request);
    }

    // ======================== MEDIA ========================

    public function get_media($request) {
        $args = [
            'post_type'      => 'attachment',
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 20),
            'post_status'    => 'inherit',
        ];
        $query = new WP_Query($args);
        $data = [];
        foreach ($query->posts as $att) {
            $meta = wp_get_attachment_metadata($att->ID);
            $data[] = [
                'id'      => (int) $att->ID,
                'title'   => $att->post_title,
                'url'     => wp_get_attachment_url($att->ID),
                'type'    => $att->post_mime_type,
                'size'    => isset($meta['size']) ? (int) $meta['size'] : 0,
                'date'    => $att->post_date,
            ];
        }
        return $this->ok(['data' => $data, 'total' => $query->found_posts]);
    }

    public function upload_media($request) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file_data = base64_decode($request->get_param('file') ?? '');
        if (empty($file_data)) {
            return $this->err('No file data');
        }
        $filename = sanitize_file_name($request->get_param('filename') ?: 'upload.bin');
        $tmp = wp_tempnam($filename);
        file_put_contents($tmp, $file_data);
        $upload = wp_handle_sideload(['tmp_name' => $tmp, 'name' => $filename], ['test_form' => false]);
        if (isset($upload['error'])) {
            return $this->err($upload['error']);
        }
        $att_id = wp_insert_attachment([
            'post_title'     => $filename,
            'post_mime_type' => $upload['type'],
            'post_status'    => 'inherit',
            'guid'           => $upload['url'],
        ], $upload['file']);
        return $this->ok(['id' => (int) $att_id, 'url' => $upload['url']]);
    }

    public function delete_media($request) {
        wp_delete_attachment((int) $request['id'], true);
        return $this->ok(['deleted' => true]);
    }

    // ======================== USERS ========================

    public function get_users($request) {
        $users = get_users(['number' => (int) ($request->get_param('per_page') ?: 50)]);
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id'         => (int) $user->ID,
                'name'       => $user->display_name,
                'email'      => $user->user_email,
                'role'       => implode(', ', $user->roles),
                'registered' => $user->user_registered,
            ];
        }
        return $this->ok(['data' => $data]);
    }

    public function get_me($request) {
        $user = wp_get_current_user();
        return $this->ok([
            'id'    => (int) $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'role'  => $user->roles,
        ]);
    }

    // ======================== COMMENTS ========================

    public function get_comments($request) {
        $args = ['number' => (int) ($request->get_param('per_page') ?: 20)];
        if ($pid = $request->get_param('post_id')) {
            $args['post_id'] = (int) $pid;
        }
        $comments = get_comments($args);
        $data = [];
        foreach ($comments as $c) {
            $data[] = [
                'id'      => (int) $c->comment_ID,
                'post_id' => (int) $c->comment_post_ID,
                'author'  => $c->comment_author,
                'email'   => $c->comment_author_email,
                'content' => $c->comment_content,
                'status'  => $c->comment_approved,
                'date'    => $c->comment_date,
            ];
        }
        return $this->ok(['data' => $data]);
    }

    public function create_comment($request) {
        $id = wp_insert_comment([
            'comment_post_ID'  => (int) $request->get_param('post_id'),
            'comment_content'  => $request->get_param('content') ?? '',
            'comment_author'   => sanitize_text_field($request->get_param('author') ?: 'AI Agent'),
            'comment_approved' => $request->get_param('approved') ?: '1',
        ]);
        return $this->ok(['id' => (int) $id, 'created' => true]);
    }

    public function update_comment($request) {
        $data = ['comment_ID' => (int) $request['id']];
        if ($content = $request->get_param('content')) {
            $data['comment_content'] = $content;
        }
        if ($status = $request->get_param('status')) {
            $data['comment_approved'] = sanitize_text_field($status);
        }
        wp_update_comment($data);
        return $this->ok(['updated' => true]);
    }

    public function delete_comment($request) {
        wp_delete_comment((int) $request['id'], true);
        return $this->ok(['deleted' => true]);
    }

    // ======================== CATEGORIES & TAGS ========================

    public function get_categories($request) {
        $cats = get_categories(['hide_empty' => false]);
        $data = [];
        foreach ($cats as $c) {
            $data[] = ['id' => (int) $c->term_id, 'name' => $c->name, 'slug' => $c->slug, 'count' => (int) $c->count];
        }
        return $this->ok(['data' => $data]);
    }

    public function create_category($request) {
        $name = sanitize_text_field($request->get_param('name'));
        if (!$name) {
            return $this->err('Name required');
        }
        $id = wp_insert_category(['cat_name' => $name, 'category_nicename' => sanitize_title($request->get_param('slug') ?: $name)]);
        return is_wp_error($id) ? $id : $this->ok(['id' => (int) $id]);
    }

    public function get_tags($request) {
        $tags = get_tags(['hide_empty' => false]);
        $data = [];
        foreach ($tags as $t) {
            $data[] = ['id' => (int) $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => (int) $t->count];
        }
        return $this->ok(['data' => $data]);
    }

    public function create_tag($request) {
        $name = sanitize_text_field($request->get_param('name'));
        if (!$name) {
            return $this->err('Name required');
        }
        $result = wp_insert_term($name, 'post_tag', ['slug' => sanitize_title($request->get_param('slug') ?: $name)]);
        return is_wp_error($result) ? $result : $this->ok(['id' => (int) $result['term_id']]);
    }

    // ======================== THEME FILES ========================

    public function theme_files($request) {
        $theme = wp_get_theme();
        $dir = $theme->theme_root . '/' . $theme->get_template();
        if (!is_dir($dir)) {
            return $this->err('Theme directory not found', 404);
        }
        $skip = ['.git', '.DS_Store', 'Thumbs.db', '.aic_backups', 'node_modules'];
        $files = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (in_array($name, $skip, true) || strpos($name, '.') === 0) {
                continue;
            }
            $rel = str_replace('\\', '/', str_replace($dir . '/', '', $file->getPathname()));
            if (strpos($rel, '.aic_backups') === 0) {
                continue;
            }
            $files[] = [
                'name'     => $rel,
                'type'     => $this->is_text_file($name) ? 'text' : 'binary',
                'size'     => (int) $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }
        usort($files, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        return $this->ok(['theme' => $theme->get_template(), 'path' => $dir, 'files' => $files]);
    }

    public function theme_read($request) {
        $path = $request->get_param('path');
        if (!$path) {
            return $this->err('Path required');
        }
        $theme = wp_get_theme();
        $dir = $theme->theme_root . '/' . $theme->get_template();
        $full = $dir . '/' . ltrim(str_replace('\\', '/', $path), '/');
        if (!file_exists($full) || !is_file($full)) {
            return $this->err('File not found', 404);
        }
        if (!$this->is_text_file(basename($full))) {
            return $this->err('Binary file - cannot read as text');
        }
        $content = file_get_contents($full);
        if ($content === false) {
            return $this->err('Read failed', 500);
        }
        return $this->ok([
            'path'     => $path,
            'content'  => $content,
            'size'     => (int) filesize($full),
            'modified' => date('Y-m-d H:i:s', filemtime($full)),
        ]);
    }

    public function theme_write($request) {
        $path = $request->get_param('path');
        $content = $request->get_param('content');
        if (!$path || $content === null) {
            return $this->err('Path and content required');
        }
        $theme = wp_get_theme();
        $dir = $theme->theme_root . '/' . $theme->get_template();
        $full = $dir . '/' . ltrim(str_replace('\\', '/', $path), '/');
        $backup_dir = dirname($full) . '/.aic_backups';
        if (!is_dir($backup_dir)) {
            @wp_mkdir_p($backup_dir);
        }
        if (file_exists($full)) {
            @copy($full, $backup_dir . '/' . basename($full) . '.' . date('Y-m-d_H-i-s') . '.bak');
        }
        $result = @file_put_contents($full, $content);
        if ($result === false) {
            return $this->err('Write failed', 500);
        }
        return $this->ok(['saved' => true, 'path' => $path, 'bytes' => (int) $result]);
    }

    // ======================== PLUGIN FILES ========================

    public function plugin_files($request) {
        $dir = WP_PLUGIN_DIR;
        $plugin = $request->get_param('plugin') ?? '';
        $skip = ['.git', '.DS_Store', 'Thumbs.db', '.aic_backups', 'node_modules'];
        $files = [];
        if ($plugin) {
            $pdir = $dir . '/' . ltrim($plugin, '/');
            if (!is_dir($pdir)) {
                return $this->err('Plugin not found', 404);
            }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pdir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                $name = $file->getFilename();
                if (in_array($name, $skip, true) || strpos($name, '.') === 0) continue;
                $rel = str_replace('\\', '/', str_replace($pdir . '/', '', $file->getPathname()));
                if (strpos($rel, '.aic_backups') === 0) continue;
                $files[] = [
                    'name'     => $rel,
                    'type'     => $this->is_text_file($name) ? 'text' : 'binary',
                    'size'     => (int) $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
            usort($files, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        } else {
            foreach (scandir($dir) as $d) {
                if ($d[0] === '.' || !is_dir($dir . '/' . $d)) continue;
                $files[] = ['name' => $d, 'type' => 'dir'];
            }
        }
        return $this->ok(['plugin' => $plugin, 'path' => $dir, 'files' => $files]);
    }

    public function plugin_read($request) {
        $path = $request->get_param('path');
        if (!$path) return $this->err('Path required');
        $full = WP_PLUGIN_DIR . '/' . ltrim(str_replace('\\', '/', $path), '/');
        if (!file_exists($full) || !is_file($full)) return $this->err('Not found', 404);
        if (!$this->is_text_file(basename($full))) return $this->err('Binary file');
        $content = file_get_contents($full);
        if ($content === false) return $this->err('Read failed', 500);
        return $this->ok(['path' => $path, 'content' => $content, 'size' => (int) filesize($full), 'modified' => date('Y-m-d H:i:s', filemtime($full))]);
    }

    public function plugin_write($request) {
        $path = $request->get_param('path');
        $content = $request->get_param('content');
        if (!$path || $content === null) return $this->err('Path and content required');
        $full = WP_PLUGIN_DIR . '/' . ltrim(str_replace('\\', '/', $path), '/');
        $backup = dirname($full) . '/.aic_backups';
        if (!is_dir($backup)) @wp_mkdir_p($backup);
        if (file_exists($full)) @copy($full, $backup . '/' . basename($full) . '.' . date('Y-m-d_H-i-s') . '.bak');
        $result = @file_put_contents($full, $content);
        return $result !== false ? $this->ok(['saved' => true, 'bytes' => (int) $result]) : $this->err('Write failed', 500);
    }

    // ======================== PLUGINS MANAGEMENT ========================

    public function list_plugins($request) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        $data = [];
        foreach ($plugins as $file => $info) {
            $data[] = [
                'file'    => $file,
                'name'    => $info['Name'] ?? '',
                'version' => $info['Version'] ?? '',
                'active'  => is_plugin_active($file),
            ];
        }
        return $this->ok(['data' => $data]);
    }

    public function activate_plugin($request) {
        $file = $request->get_param('plugin');
        if (!$file) return $this->err('Plugin name required');
        activate_plugin($file);
        return $this->ok(['activated' => $file]);
    }

    public function deactivate_plugin($request) {
        $file = $request->get_param('plugin');
        if (!$file) return $this->err('Plugin name required');
        deactivate_plugins($file);
        return $this->ok(['deactivated' => $file]);
    }

    // ======================== THEMES MANAGEMENT ========================

    public function list_themes($request) {
        $themes = wp_get_themes();
        $active = wp_get_theme()->get_template();
        $data = [];
        foreach ($themes as $theme) {
            $data[] = [
                'name'    => $theme->get('Name'),
                'slug'    => $theme->get_template(),
                'version' => $theme->get('Version'),
                'active'  => $theme->get_template() === $active,
            ];
        }
        return $this->ok(['data' => $data]);
    }

    public function activate_theme($request) {
        $slug = sanitize_text_field($request->get_param('theme'));
        if (!$slug) return $this->err('Theme slug required');
        switch_theme($slug);
        return $this->ok(['activated' => $slug]);
    }

    // ======================== WIDGETS ========================

    public function get_widgets($request) {
        global $wp_registered_sidebars, $wp_registered_widgets;
        $sidebars_widgets = get_option('sidebars_widgets', []);
        $data = [];
        foreach ($sidebars_widgets as $sb_id => $widget_ids) {
            if (!isset($wp_registered_sidebars[$sb_id])) continue;
            $items = [];
            foreach ($widget_ids as $wid) {
                if (!isset($wp_registered_widgets[$wid])) continue;
                $w = $wp_registered_widgets[$wid];
                $items[] = [
                    'id'   => $wid,
                    'name' => $w['name'] ?? '',
                ];
            }
            $data[] = [
                'id'      => $sb_id,
                'name'    => $wp_registered_sidebars[$sb_id]['name'] ?? '',
                'widgets' => $items,
            ];
        }
        return $this->ok(['data' => $data]);
    }

    public function widget_areas($request) {
        global $wp_registered_sidebars;
        $data = [];
        foreach ($wp_registered_sidebars as $id => $sb) {
            $data[] = ['id' => $id, 'name' => $sb['name'] ?? '', 'description' => $sb['description'] ?? ''];
        }
        return $this->ok(['data' => $data]);
    }

    public function save_widget($request) {
        $sidebar = $request->get_param('sidebar');
        $widget = $request->get_param('widget');
        $instance = $request->get_param('instance');
        if (!$sidebar || !$widget) return $this->err('sidebar and widget required');
        $sidebars = get_option('sidebars_widgets', []);
        if (!isset($sidebars[$sidebar])) $sidebars[$sidebar] = [];
        $wdata = get_option('widget_' . $widget, []);
        $nidx = !empty($wdata) ? max(array_keys($wdata)) + 1 : 1;
        $wdata[$nidx] = (array) $instance;
        update_option('widget_' . $widget, $wdata);
        $sidebars[$sidebar][] = $widget . '-' . $nidx;
        update_option('sidebars_widgets', $sidebars);
        return $this->ok(['saved' => true, 'widget_id' => $widget . '-' . $nidx]);
    }

    // ======================== MENUS ========================

    public function get_menus($request) {
        $menus = get_terms(['taxonomy' => 'nav_menu', 'hide_empty' => false]);
        if (is_wp_error($menus)) return $menus;
        $data = [];
        foreach ($menus as $m) {
            $data[] = ['id' => (int) $m->term_id, 'name' => $m->name, 'slug' => $m->slug, 'count' => (int) $m->count];
        }
        return $this->ok(['data' => $data]);
    }

    public function create_menu($request) {
        $name = sanitize_text_field($request->get_param('name'));
        if (!$name) return $this->err('Menu name required');
        $id = wp_create_nav_menu($name);
        return is_wp_error($id) ? $id : $this->ok(['id' => (int) $id]);
    }

    public function menu_items($request) {
        $items = wp_get_nav_menu_items((int) $request['id']);
        $data = [];
        if ($items) {
            foreach ($items as $item) {
                $data[] = [
                    'id'     => (int) $item->ID,
                    'title'  => $item->title ?? '',
                    'url'    => $item->url ?? '',
                    'target' => $item->target ?? '',
                    'parent' => (int) $item->menu_item_parent,
                    'order'  => (int) $item->menu_order,
                ];
            }
        }
        return $this->ok(['data' => $data]);
    }

    public function add_menu_item($request) {
        $menu = (int) $request['id'];
        $url = esc_url_raw($request->get_param('url'));
        $title = sanitize_text_field($request->get_param('title'));
        if (!$url || !$title) return $this->err('url and title required');
        $id = wp_update_nav_menu_item($menu, 0, [
            'menu-item-title'   => $title,
            'menu-item-url'     => $url,
            'menu-item-status'  => 'publish',
            'menu-item-target'  => sanitize_text_field($request->get_param('target') ?: '_self'),
            'menu-item-parent'  => (int) ($request->get_param('parent') ?: 0),
            'menu-item-type'    => sanitize_text_field($request->get_param('type') ?: 'custom'),
        ]);
        return $this->ok(['id' => (int) $id]);
    }

    public function delete_menu_item($request) {
        wp_delete_post((int) $request['id'], true);
        return $this->ok(['deleted' => true]);
    }

    // ======================== OPTIONS ========================

    public function all_options($request) {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} ORDER BY option_name",
            ARRAY_A
        );
        $data = [];
        foreach ($results as $row) {
            if (strpos($row['option_name'], '_transient_') === 0) continue;
            if (strpos($row['option_name'], '_site_transient_') === 0) continue;
            $data[$row['option_name']] = maybe_unserialize($row['option_value']);
        }
        return $this->ok(['data' => $data, 'count' => count($data)]);
    }

    public function get_option_val($request) {
        $key = sanitize_text_field($request['key']);
        $value = get_option($key);
        if ($value === false) return $this->err('Not found', 404);
        return $this->ok(['key' => $key, 'value' => $value]);
    }

    public function set_option_val($request) {
        $key = sanitize_text_field($request['key']);
        $value = $request->get_param('value');
        update_option($key, $value);
        return $this->ok(['saved' => true, 'key' => $key]);
    }

    // ======================== FILE SYSTEM ========================

    public function list_files($request) {
        $path = $request->get_param('path') ?: ABSPATH;
        if (!is_dir($path)) return $this->err('Directory not found', 404);
        $items = [];
        foreach (scandir($path) as $f) {
            if ($f[0] === '.') continue;
            $fp = $path . '/' . $f;
            $items[] = [
                'name'     => $f,
                'type'     => is_dir($fp) ? 'dir' : 'file',
                'size'     => is_file($fp) ? (int) filesize($fp) : 0,
                'modified' => date('Y-m-d H:i:s', @filemtime($fp)),
            ];
        }
        return $this->ok(['path' => $path, 'items' => $items]);
    }

    public function read_file($request) {
        $path = $request->get_param('path');
        if (!$path || !file_exists($path)) return $this->err('Not found', 404);
        if (!is_file($path)) return $this->err('Not a file');
        if (!$this->is_text_file(basename($path))) return $this->err('Binary file');
        $content = file_get_contents($path);
        if ($content === false) return $this->err('Read failed', 500);
        return $this->ok([
            'path'     => $path,
            'content'  => $content,
            'size'     => (int) filesize($path),
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
        ]);
    }

    public function write_file($request) {
        $path = $request->get_param('path');
        $content = $request->get_param('content');
        if (!$path || $content === null) return $this->err('Path and content required');
        $dir = dirname($path);
        if (!is_dir($dir)) @wp_mkdir_p($dir);
        $bak = $dir . '/.aic_backups';
        if (!is_dir($bak)) @wp_mkdir_p($bak);
        if (file_exists($path)) @copy($path, $bak . '/' . basename($path) . '.' . date('Y-m-d_H-i-s') . '.bak');
        $result = @file_put_contents($path, $content);
        return $result !== false ? $this->ok(['saved' => true, 'bytes' => (int) $result]) : $this->err('Write failed', 500);
    }

    public function delete_file($request) {
        $path = $request->get_param('path');
        if (!$path || !file_exists($path)) return $this->err('Not found', 404);
        $bak = dirname($path) . '/.aic_backups';
        if (!is_dir($bak)) @wp_mkdir_p($bak);
        @copy($path, $bak . '/' . basename($path) . '.' . date('Y-m-d_H-i-s') . '.bak');
        @unlink($path);
        return $this->ok(['deleted' => true]);
    }

    public function make_dir($request) {
        $path = $request->get_param('path');
        if (!$path) return $this->err('Path required');
        $result = @wp_mkdir_p($path);
        return $this->ok(['created' => (bool) $result, 'path' => $path]);
    }

    // ======================== DATABASE ========================

    public function db_query($request) {
        global $wpdb;
        $sql = trim($request->get_param('sql') ?? '');
        $args = $request->get_param('args') ?: [];
        if (empty($sql)) return $this->err('SQL required');

        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, $args);
        }

        $trimmed = ltrim($sql);
        $is_read = stripos($trimmed, 'SELECT') === 0
            || stripos($trimmed, 'SHOW') === 0
            || stripos($trimmed, 'DESCRIBE') === 0
            || stripos($trimmed, 'EXPLAIN') === 0;

        if ($is_read) {
            $result = $wpdb->get_results($sql, ARRAY_A);
            if ($result === null) {
                return $this->err($wpdb->last_error);
            }
            return $this->ok(['rows' => count($result), 'data' => $result]);
        }

        $result = $wpdb->query($sql);
        if ($result === false) {
            return $this->err($wpdb->last_error);
        }
        return $this->ok(['affected' => (int) $result]);
    }

    public function db_tables($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $prefix . '%'), 0);
        return $this->ok(['tables' => $tables, 'prefix' => $prefix]);
    }

    public function db_describe($request) {
        global $wpdb;
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $request['name']);
        $prefix = $wpdb->prefix;
        if (strpos($name, $prefix) !== 0) {
            return $this->err('Only site tables allowed');
        }
        $columns = $wpdb->get_results("DESCRIBE `$name`", ARRAY_A);
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$name`");
        return $this->ok(['table' => $name, 'columns' => $columns, 'rows' => $count]);
    }

    // ======================== CUSTOM CSS ========================

    public function get_css($request) {
        return $this->ok(['css' => get_option('aic_custom_css', '')]);
    }

    public function set_css($request) {
        $css = $request->get_param('css') ?? '';
        update_option('aic_custom_css', $css);
        return $this->ok(['saved' => true]);
    }

    // ======================== SYSTEM ========================

    public function sys_info($request) {
        $upload_dir = wp_upload_dir();
        return $this->ok([
            'php'           => PHP_VERSION,
            'wp'            => get_bloginfo('version'),
            'site'          => get_site_url(),
            'theme'         => wp_get_theme()->get('Name'),
            'theme_version' => wp_get_theme()->get('Version'),
            'memory'        => ini_get('memory_limit'),
            'max_time'      => ini_get('max_execution_time'),
            'uploads'       => $upload_dir['basedir'],
            'server'        => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'plugins'       => count(get_option('active_plugins', [])),
        ]);
    }

    public function sys_exec($request) {
        $cmd = $request->get_param('command');
        if (!$cmd) return $this->err('Command required');
        $blocked = ['rm -rf /', 'mkfs', 'dd if=', ':(){:', 'wget | sh', 'curl | sh'];
        foreach ($blocked as $b) {
            if (stripos($cmd, $b) !== false) {
                return $this->err('Blocked command', 403);
            }
        }
        $output = [];
        $return_code = 0;
        exec($cmd . ' 2>&1', $output, $return_code);
        return $this->ok([
            'command' => $cmd,
            'output'  => implode("\n", $output),
            'exit'    => (int) $return_code,
        ]);
    }

    public function sys_php($request) {
        $code = $request->get_param('code');
        if (!$code) return $this->err('Code required');
        ob_start();
        $result = @eval($code);
        $output = ob_get_clean();
        return $this->ok(['result' => $result, 'output' => $output ?: '']);
    }

    // ======================== SEARCH ========================

    public function search($request) {
        $q = $request->get_param('q');
        if (!$q) return $this->err('Query required');
        $type = $request->get_param('type') ?: 'any';
        $query = new WP_Query([
            's'              => sanitize_text_field($q),
            'post_type'      => sanitize_text_field($type),
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 20),
        ]);
        $data = [];
        foreach ($query->posts as $post) {
            $data[] = $this->post_array($post);
        }
        return $this->ok(['data' => $data, 'total' => $query->found_posts]);
    }

    // ======================== CUSTOM POST TYPES ========================

    public function get_post_types($request) {
        $types = get_post_types(['public' => true], 'objects');
        $data = [];
        foreach ($types as $type) {
            $data[] = [
                'name'        => $type->name,
                'label'       => $type->label,
                'labels'      => $type->labels,
                'supports'    => $type->supports,
                'has_archive' => $type->has_archive,
                'menu_icon'   => $type->menu_icon,
            ];
        }
        return $this->ok(['data' => $data]);
    }

    public function create_post_type($request) {
        $name = sanitize_text_field($request->get_param('name'));
        $label = sanitize_text_field($request->get_param('label') ?: $name);
        if (!$name) return $this->err('Name required');

        $labels = [
            'name'          => $label,
            'singular_name' => $label,
            'add_new'       => 'افزودن ' . $label,
            'add_new_item'  => 'افزودن ' . $label . ' جدید',
            'edit_item'     => 'ویرایش ' . $label,
            'new_item'      => $label . ' جدید',
            'view_item'     => 'مشاهده ' . $label,
            'search_items'  => 'جستجوی ' . $label,
            'not_found'     => $label . ' یافت نشد',
            'all_items'     => 'همه ' . $label . '‌ها',
            'menu_name'     => $label,
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-' . ($request->get_param('icon') ?: 'admin-post'),
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields'],
            'capability_type'    => 'post',
        ];

        register_post_type($name, $args);
        flush_rewrite_rules();
        return $this->ok(['created' => true, 'name' => $name]);
    }

    // ======================== REDIRECTS ========================

    public function get_redirects($request) {
        $redirects = get_option('aic_redirects', []);
        $data = [];
        foreach ($redirects as $id => $r) {
            $data[] = ['id' => $id, 'from' => $r['from'], 'to' => $r['to'], 'code' => $r['code'] ?? 301];
        }
        return $this->ok(['data' => $data]);
    }

    public function add_redirect($request) {
        $from = sanitize_text_field($request->get_param('from'));
        $to = esc_url_raw($request->get_param('to'));
        $code = (int) ($request->get_param('code') ?: 301);
        if (!$from || !$to) return $this->err('from and to required');

        $redirects = get_option('aic_redirects', []);
        $id = md5($from . $to);
        $redirects[$id] = ['from' => $from, 'to' => $to, 'code' => $code];
        update_option('aic_redirects', $redirects);

        // Add rewrite rule
        add_rewrite_rule('^' . ltrim($from, '/') . '$', $to, 'top');
        flush_rewrite_rules();
        return $this->ok(['created' => true, 'id' => $id]);
    }

    public function delete_redirect($request) {
        $redirects = get_option('aic_redirects', []);
        $id = $request['id'];
        if (isset($redirects[$id])) {
            unset($redirects[$id]);
            update_option('aic_redirects', $redirects);
            flush_rewrite_rules();
        }
        return $this->ok(['deleted' => true]);
    }

    // ======================== CACHE ========================

    public function flush_cache($request) {
        global $wp_object_cache;
        if (method_exists($wp_object_cache, 'flush')) {
            $wp_object_cache->flush();
        }
        wp_cache_flush();
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('global', 'sites');
        }
        flush_rewrite_rules();
        return $this->ok(['flushed' => true]);
    }

    // ======================== REQUEST LOG ========================

    public function get_logs($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'aic_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return $this->ok(['data' => [], 'message' => 'Log table not created yet']);
        }
        $limit = (int) ($request->get_param('per_page') ?: 100);
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT $limit", ARRAY_A);
        return $this->ok(['data' => $logs ?: []]);
    }

    // ======================== RATE LIMIT ========================

    public function get_rate_limit($request) {
        return $this->ok([
            'limit'     => (int) get_option('aic_rate_limit', 60),
            'window'    => 60,
            'remaining' => $this->get_remaining_requests(),
        ]);
    }

    public function set_rate_limit($request) {
        $limit = (int) ($request->get_param('limit') ?: 60);
        update_option('aic_rate_limit', $limit);
        return $this->ok(['saved' => true, 'limit' => $limit]);
    }

    private function get_remaining_requests() {
        $limit = (int) get_option('aic_rate_limit', 60);
        $key = 'aic_rate_' . md5($_SERVER['REMOTE_ADDR'] ?? '0');
        $current = (int) get_transient($key);
        return max(0, $limit - $current);
    }

    // ======================== SETUP WIZARD ========================

    public function run_setup($request) {
        $results = [];

        // 1. Set permalinks
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/%postname%/');
        $wp_rewrite->flush_rules();
        $results['permalinks'] = 'set';

        // 2. Set language
        update_option('WPLANG', 'fa_IR');
        $results['language'] = 'fa_IR';

        // 3. Set timezone
        update_option('gmt_offset', 3.5);
        update_option('timezone_string', 'Asia/Tehran');
        $results['timezone'] = 'Asia/Tehran';

        // 4. Set date format
        update_option('date_format', 'Y/m/d');
        update_option('time_format', 'H:i');
        $results['date_format'] = 'set';

        // 5. Create essential pages
        $pages = [
            ['title' => 'خانه', 'slug' => 'home', 'status' => 'publish'],
            ['title' => 'درباره ما', 'slug' => 'about', 'status' => 'publish'],
            ['title' => 'تماس با ما', 'slug' => 'contact', 'status' => 'publish'],
        ];
        foreach ($pages as $p) {
            $existing = get_page_by_path($p['slug']);
            if (!$existing) {
                $id = wp_insert_post(['post_title' => $p['title'], 'post_name' => $p['slug'], 'post_status' => $p['status'], 'post_type' => 'page']);
                $results['pages'][] = ['title' => $p['title'], 'id' => (int) $id];
            }
        }

        // 6. Set front page
        $home = get_page_by_path('home');
        if ($home) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $home->ID);
            $results['front_page'] = $home->ID;
        }

        // 7. Set admin email (keep existing)
        // 8. Delete sample content
        $sample = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 5, 'author' => 1]);
        foreach ($sample as $s) {
            if ($s->post_title === 'سلام دنیا!' || $s->post_title === 'Hello world!') {
                wp_delete_post($s->ID, true);
                $results['deleted_sample'] = true;
            }
        }

        return $this->ok(['setup' => 'complete', 'results' => $results]);
    }

    // ======================== WOOCOMMERCE PRODUCTS ========================

    public function get_products($request) {
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => (int) ($request->get_param('per_page') ?: 20),
            'paged'          => (int) ($request->get_param('page') ?: 1),
            'post_status'    => 'publish',
        ];
        if ($s = $request->get_param('search')) {
            $args['s'] = sanitize_text_field($s);
        }
        $query = new WP_Query($args);
        $data = [];
        foreach ($query->posts as $post) {
            $price = get_post_meta($post->ID, '_price', true);
            $regular_price = get_post_meta($post->ID, '_regular_price', true);
            $sale_price = get_post_meta($post->ID, '_sale_price', true);
            $sku = get_post_meta($post->ID, '_sku', true);
            $stock = get_post_meta($post->ID, '_stock_status', true);
            $data[] = [
                'id'            => (int) $post->ID,
                'title'         => $post->post_title,
                'content'       => $post->post_content,
                'excerpt'       => $post->post_excerpt,
                'status'        => $post->post_status,
                'price'         => $price,
                'regular_price' => $regular_price,
                'sale_price'    => $sale_price,
                'sku'           => $sku,
                'stock'         => $stock,
                'permalink'     => get_permalink($post->ID),
                'date'          => $post->post_date,
            ];
        }
        return $this->ok(['data' => $data, 'total' => $query->found_posts, 'pages' => $query->max_num_pages]);
    }

    public function get_product($request) {
        $post = get_post((int) $request['id']);
        if (!$post || $post->post_type !== 'product') return $this->err('Product not found', 404);
        return $this->ok([
            'id'            => (int) $post->ID,
            'title'         => $post->post_title,
            'content'       => $post->post_content,
            'excerpt'       => $post->post_excerpt,
            'price'         => get_post_meta($post->ID, '_price', true),
            'regular_price' => get_post_meta($post->ID, '_regular_price', true),
            'sale_price'    => get_post_meta($post->ID, '_sale_price', true),
            'sku'           => get_post_meta($post->ID, '_sku', true),
            'stock'         => get_post_meta($post->ID, '_stock_status', true),
            'permalink'     => get_permalink($post->ID),
        ]);
    }

    public function create_product($request) {
        $title = sanitize_text_field($request->get_param('title'));
        $price = $request->get_param('price');
        if (!$title || !$price) return $this->err('title and price required');

        $id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $request->get_param('content') ?? '',
            'post_excerpt' => $request->get_param('excerpt') ?? '',
            'post_status'  => 'publish',
            'post_type'    => 'product',
        ]);

        if (!$id || is_wp_error($id)) return $id;

        update_post_meta($id, '_price', $price);
        update_post_meta($id, '_regular_price', $price);
        update_post_meta($id, '_sku', $request->get_param('sku') ?: '');
        update_post_meta($id, '_stock_status', 'instock');
        update_post_meta($id, '_manage_stock', 'no');
        wp_set_object_terms($id, 'simple', 'product_type');

        return $this->ok(['id' => (int) $id, 'title' => $title, 'price' => $price]);
    }

    public function update_product($request) {
        $data = ['ID' => (int) $request['id']];
        if ($t = $request->get_param('title')) $data['post_title'] = sanitize_text_field($t);
        if ($request->get_param('content') !== null) $data['post_content'] = $request->get_param('content');
        $result = wp_update_post($data, true);
        if (is_wp_error($result)) return $result;

        $pid = (int) $request['id'];
        if ($p = $request->get_param('price')) update_post_meta($pid, '_price', $p);
        if ($rp = $request->get_param('regular_price')) update_post_meta($pid, '_regular_price', $rp);
        if ($sp = $request->get_param('sale_price')) update_post_meta($pid, '_sale_price', $sp);
        if ($sku = $request->get_param('sku')) update_post_meta($pid, '_sku', $sku);
        if ($st = $request->get_param('stock')) update_post_meta($pid, '_stock_status', $st);

        return $this->ok(['updated' => true]);
    }

    public function delete_product($request) {
        wp_delete_post((int) $request['id'], true);
        return $this->ok(['deleted' => true]);
    }

    // ======================== ORDERS ========================

    public function get_orders($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_order_posts';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return $this->err('WooCommerce not installed');
        }
        $limit = (int) ($request->get_param('per_page') ?: 20);
        $orders = $wpdb->get_results("SELECT * FROM $table ORDER BY ID DESC LIMIT $limit", ARRAY_A);
        $data = [];
        foreach ($orders as $o) {
            $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id IN (SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d)", $o['ID']), ARRAY_A);
            $m = [];
            foreach ($meta as $mv) $m[$mv['meta_key']] = $mv['meta_value'];
            $data[] = [
                'id'     => (int) $o['ID'],
                'status' => $o['post_status'],
                'total'  => $m['_order_total'] ?? '0',
                'date'   => $o['post_date'],
            ];
        }
        return $this->ok(['data' => $data]);
    }

    // ======================== SEO ========================

    public function get_seo($request) {
        return $this->ok([
            'title'       => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'og_title'    => get_option('aic_og_title', ''),
            'og_desc'     => get_option('aic_og_desc', ''),
            'og_image'    => get_option('aic_og_image', ''),
            'canonical'   => get_option('aic_canonical', ''),
        ]);
    }

    public function set_seo($request) {
        if ($t = $request->get_param('title')) update_option('blogname', sanitize_text_field($t));
        if ($d = $request->get_param('description')) update_option('blogdescription', sanitize_text_field($d));
        if ($request->get_param('og_title') !== null) update_option('aic_og_title', sanitize_text_field($request->get_param('og_title')));
        if ($request->get_param('og_desc') !== null) update_option('aic_og_desc', sanitize_text_field($request->get_param('og_desc')));
        if ($request->get_param('og_image') !== null) update_option('aic_og_image', esc_url_raw($request->get_param('og_image')));
        return $this->ok(['saved' => true]);
    }

    // ======================== HEALTH CHECK ========================

    public function health_check($request) {
        $checks = [];

        // PHP version
        $checks['php'] = ['status' => version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'warning', 'value' => PHP_VERSION];

        // WordPress version
        $checks['wp'] = ['status' => 'ok', 'value' => get_bloginfo('version')];

        // Database
        global $wpdb;
        $checks['database'] = ['status' => $wpdb->check_connection() ? 'ok' : 'error'];

        // Memory
        $mem = ini_get('memory_limit');
        $checks['memory'] = ['status' => 'ok', 'value' => $mem];

        // Upload directory
        $upload_dir = wp_upload_dir();
        $checks['uploads'] = ['status' => $upload_dir['error'] ? 'error' : 'ok', 'path' => $upload_dir['basedir']];

        // Active plugins
        $checks['plugins'] = ['status' => 'ok', 'count' => count(get_option('active_plugins', []))];

        // Theme
        $theme = wp_get_theme();
        $checks['theme'] = ['status' => 'ok', 'name' => $theme->get('Name'), 'version' => $theme->get('Version')];

        // WooCommerce
        $checks['woocommerce'] = ['status' => class_exists('WooCommerce') ? 'ok' : 'not_installed'];

        // Permalinks
        global $wp_rewrite;
        $checks['permalinks'] = ['status' => $wp_rewrite->permalink_structure ? 'ok' : 'not_set'];

        // SSL
        $checks['ssl'] = ['status' => is_ssl() ? 'ok' : 'not_ssl'];

        $all_ok = true;
        foreach ($checks as $c) {
            if ($c['status'] !== 'ok') { $all_ok = false; break; }
        }

        return $this->ok(['status' => $all_ok ? 'healthy' : 'issues_found', 'checks' => $checks]);
    }

    // ======================== SEO ANALYSIS ========================

    private function get_post_html($post_id) {
        $post = get_post($post_id);
        if (!$post) return null;
        $content = apply_filters('the_content', $post->post_content);
        $content = str_replace(']]>', ']]&gt;', $content);
        return [
            'title'    => $post->post_title,
            'content'  => $content,
            'excerpt'  => $post->post_excerpt,
            'plain'    => wp_strip_all_tags($content),
            'url'      => get_permalink($post_id),
            'type'     => $post->post_type,
            'date'     => $post->post_date,
            'modified' => $post->post_modified,
        ];
    }

    private function extract_headings($html) {
        $headings = [];
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $headings[] = [
                'level' => (int) $matches[1][$i],
                'text'  => wp_strip_all_tags($matches[2][$i]),
            ];
        }
        return $headings;
    }

    private function extract_links($html) {
        $links = ['internal' => [], 'external' => []];
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches);
        $site_url = home_url();
        for ($i = 0; $i < count($matches[0]); $i++) {
            $url = $matches[1][$i];
            $text = wp_strip_all_tags($matches[2][$i]);
            if (strpos($url, $site_url) === 0 || strpos($url, '/') === 0) {
                $links['internal'][] = ['url' => $url, 'text' => $text];
            } else {
                $links['external'][] = ['url' => $url, 'text' => $text];
            }
        }
        return $links;
    }

    private function extract_images($html) {
        $images = [];
        preg_match_all('/<img[^>]+>/is', $html, $matches);
        foreach ($matches[0] as $img) {
            preg_match('/alt=["\']([^"\']*)["\']/i', $img, $alt);
            preg_match('/src=["\']([^"\']*)["\']/i', $img, $src);
            $images[] = [
                'src' => $src[1] ?? '',
                'alt' => $alt[1] ?? '',
                'has_alt' => !empty($alt[1]),
            ];
        }
        return $images;
    }

    private function count_words($text) {
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));
        return str_word_count($text);
    }

    private function flesch_score($text) {
        $sentences = max(1, preg_match_all('/[.!?؟]+/', $text));
        $words = max(1, $this->count_words($text));
        $syllables = max(1, $this->count_syllables($text));
        $score = 206.835 - 1.015 * ($words / $sentences) - 84.6 * ($syllables / $words);
        return max(0, min(100, round($score)));
    }

    private function count_syllables($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z\x{0600}-\x{06FF}]/u', '', $text);
        preg_match_all('/[aeiouy]/i', $text, $vowels);
        return max(1, count($vowels[0]));
    }

    private function keyword_density($text, $keyword) {
        $text = strtolower($text);
        $keyword = strtolower($keyword);
        $word_count = max(1, $this->count_words($text));
        $keyword_count = substr_count($text, $keyword);
        return round(($keyword_count / $word_count) * 100, 2);
    }

    public function seo_analyze($request) {
        $post_id = (int) $request['id'];
        $html_data = $this->get_post_html($post_id);
        if (!$html_data) return $this->err('Post not found', 404);

        $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true)
                    ?: get_post_meta($post_id, '_rank_math_title', true)
                    ?: $html_data['title'];
        $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true)
                   ?: get_post_meta($post_id, '_rank_math_description', true)
                   ?: $html_data['excerpt'];

        $headings = $this->extract_headings($html_data['content']);
        $links = $this->extract_links($html_data['content']);
        $images = $this->extract_images($html_data['content']);
        $word_count = $this->count_words($html_data['plain']);
        $readability = $this->flesch_score($html_data['plain']);

        // Score calculation
        $score = 0;
        $issues = [];

        // Title check (30 chars optimal)
        $title_len = strlen($meta_title);
        if ($title_len >= 30 && $title_len <= 60) $score += 15;
        elseif ($title_len > 0) { $score += 5; $issues[] = "عنوان متا $title_len کاراکتر است (بهینه: 30-60)"; }
        else $issues[] = "عنوان متا وجود ندارد";

        // Meta description check
        $desc_len = strlen($meta_desc);
        if ($desc_len >= 120 && $desc_len <= 160) $score += 15;
        elseif ($desc_len > 0) { $score += 5; $issues[] = "توضیحات متا $desc_len کاراکتر است (بهینه: 120-160)"; }
        else $issues[] = "توضیحات متا وجود ندارد";

        // H1 check
        $h1_count = count(array_filter($headings, fn($h) => $h['level'] === 1));
        if ($h1_count === 1) $score += 10;
        elseif ($h1_count === 0) $issues[] = "تگ H1 وجود ندارد";
        else { $score += 5; $issues[] = "$h1_count تگ H1 وجود دارد (بهینه: 1)"; }

        // Heading structure
        if (count($headings) >= 3) $score += 10;
        else $issues[] = "تگ‌های عنوان کم هستند (" . count($headings) . ")";

        // Word count
        if ($word_count >= 300) $score += 10;
        elseif ($word_count >= 100) $score += 5;
        else $issues[] = "تعداد کلمات کم است ($word_count)";

        // Images
        $imgs_with_alt = count(array_filter($images, fn($i) => $i['has_alt']));
        if (count($images) > 0 && $imgs_with_alt === count($images)) $score += 10;
        elseif (count($images) > 0) { $score += 3; $issues[] = (count($images) - $imgs_with_alt) . " تصویر بدون alt"; }
        else $issues[] = "تصویری وجود ندارد";

        // Links
        $total_links = count($links['internal']) + count($links['external']);
        if ($total_links >= 2) $score += 10;
        else $issues[] = "لینک کم دارد ($total_links)";

        // Readability
        if ($readability >= 60) $score += 10;
        elseif ($readability >= 40) $score += 5;
        else $issues[] = "خوانایی پایین ($readability)";

        // URL slug
        $slug = get_post_field('post_name', $post_id);
        if (strlen($slug) > 0 && strlen($slug) <= 60) $score += 10;
        elseif (strlen($slug) > 60) $issues[] = "Slug خیلی بلند است";

        return $this->ok([
            'post_id'     => $post_id,
            'title'       => $html_data['title'],
            'url'         => $html_data['url'],
            'score'       => min(100, $score),
            'meta'        => ['title' => $meta_title, 'title_length' => $title_len, 'description' => $meta_desc, 'desc_length' => $desc_len],
            'headings'    => $headings,
            'heading_count' => count($headings),
            'links'       => ['internal' => count($links['internal']), 'external' => count($links['external'])],
            'images'      => ['total' => count($images), 'with_alt' => $imgs_with_alt, 'without_alt' => count($images) - $imgs_with_alt],
            'word_count'  => $word_count,
            'readability' => $readability,
            'issues'      => $issues,
            'grade'       => $score >= 80 ? 'A' : ($score >= 60 ? 'B' : ($score >= 40 ? 'C' : 'D')),
        ]);
    }

    public function seo_scores($request) {
        $type = $request->get_param('type') ?: 'any';
        $posts = get_posts(['post_type' => $type, 'posts_per_page' => (int) ($request->get_param('per_page') ?: 50), 'post_status' => 'publish']);
        $scores = [];
        foreach ($posts as $post) {
            $meta_title = get_post_meta($post->ID, '_yoast_wpseo_title', true) ?: $post->post_title;
            $title_len = strlen($meta_title);
            $score = 0;
            if ($title_len >= 30 && $title_len <= 60) $score += 30;
            elseif ($title_len > 0) $score += 10;
            $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) ?: $post->post_excerpt;
            if (strlen($meta_desc) >= 120 && strlen($meta_desc) <= 160) $score += 30;
            elseif (strlen($meta_desc) > 0) $score += 10;
            $word_count = $this->count_words(wp_strip_all_tags($post->post_content));
            if ($word_count >= 300) $score += 20;
            elseif ($word_count >= 100) $score += 10;
            $headings = $this->extract_headings(apply_filters('the_content', $post->post_content));
            if (count($headings) >= 3) $score += 20;
            $scores[] = [
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'type'       => $post->post_type,
                'score'      => min(100, $score),
                'grade'      => $score >= 80 ? 'A' : ($score >= 60 ? 'B' : ($score >= 40 ? 'C' : 'D')),
                'word_count' => $word_count,
                'url'        => get_permalink($post->ID),
            ];
        }
        usort($scores, fn($a, $b) => $a['score'] - $b['score']);
        return $this->ok(['data' => $scores, 'total' => count($scores)]);
    }

    public function seo_keywords($request) {
        $post_id = (int) $request['id'];
        $html_data = $this->get_post_html($post_id);
        if (!$html_data) return $this->err('Not found', 404);

        $text = strtolower($html_data['plain']);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stopwords = ['the','a','an','and','or','but','in','on','at','to','for','of','with','is','it','that','this','from','by','as','be','are','was','were','will','would','could','should','may','might','can','shall','has','have','had','do','does','did','not','no','so','if','then','than','too','very','just','about','above','after','again','all','also','any','because','before','between','both','each','few','more','most','other','some','such','into','through','during','out','off','over','under','until','while','این','از','با','برای','در','که','را','به','و','یا','اما','اگر','تا','هم','از','تا','نه','بله','هر','همه','بیشتر','کمتر','خیلی'];

        $freq = [];
        foreach ($words as $w) {
            $w = preg_replace('/[^\p{L}\p{N}]/u', '', $w);
            if (mb_strlen($w) < 3 || in_array($w, $stopwords)) continue;
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }
        arsort($freq);
        $top = array_slice($freq, 0, 20, true);

        $density = [];
        foreach ($top as $word => $count) {
            $density[$word] = [
                'count'   => $count,
                'density' => $this->keyword_density($html_data['plain'], $word),
            ];
        }

        return $this->ok([
            'post_id'     => $post_id,
            'total_words' => $this->count_words($html_data['plain']),
            'keywords'    => $density,
        ]);
    }

    public function seo_quality($request) {
        $post_id = (int) $request['id'];
        $html_data = $this->get_post_html($post_id);
        if (!$html_data) return $this->err('Not found', 404);

        $text = $html_data['plain'];
        $word_count = $this->count_words($text);
        $sentences = max(1, preg_match_all('/[.!?؟]+/', $text));
        $paragraphs = max(1, substr_count($text, "\n\n") + 1);
        $avg_words_per_sentence = round($word_count / $sentences, 1);
        $avg_sentences_per_para = round($sentences / $paragraphs, 1);

        $readability = $this->flesch_score($text);
        $readability_label = $readability >= 80 ? 'خیلی آسان' : ($readability >= 60 ? 'آسان' : ($readability >= 40 ? 'متوسط' : ($readability >= 20 ? 'سخت' : 'خیلی سخت')));

        $content_score = 0;
        if ($word_count >= 300) $content_score += 25;
        elseif ($word_count >= 150) $content_score += 15;
        if ($sentences >= 5) $content_score += 25;
        if ($readability >= 50) $content_score += 25;
        if ($paragraphs >= 3) $content_score += 25;

        return $this->ok([
            'post_id'                => $post_id,
            'word_count'             => $word_count,
            'sentence_count'         => $sentences,
            'paragraph_count'        => $paragraphs,
            'avg_words_per_sentence' => $avg_words_per_sentence,
            'avg_sentences_per_para' => $avg_sentences_per_para,
            'readability_score'      => $readability,
            'readability_label'      => $readability_label,
            'content_score'          => min(100, $content_score),
            'content_grade'          => $content_score >= 80 ? 'A' : ($content_score >= 60 ? 'B' : ($content_score >= 40 ? 'C' : 'D')),
        ]);
    }

    public function seo_suggestions($request) {
        $post_id = (int) $request['id'];
        $analysis = $this->seo_analyze($request);
        if (is_wp_error($analysis)) return $analysis;
        $data = $analysis->data;
        $suggestions = [];

        if ($data['meta']['title_length'] < 30) $suggestions[] = ['type' => 'title', 'priority' => 'high', 'message' => 'عنوان متا کوتاه است. حداقل 30 کاراکتر بنویسید.', 'fix' => 'عنوان جذاب و توصیفی با 30-60 کاراکتر بنویسید'];
        if ($data['meta']['title_length'] > 60) $suggestions[] = ['type' => 'title', 'priority' => 'medium', 'message' => 'عنوان متا بلند است. حداکثر 60 کاراکتر باشد.', 'fix' => 'عنوان را خلاصه‌تر کنید'];
        if ($data['meta']['desc_length'] < 120) $suggestions[] = ['type' => 'meta', 'priority' => 'high', 'message' => 'توضیحات متا کوتاه است. حداقل 120 کاراکتر بنویسید.', 'fix' => 'توصیف جذاب و کامل با 120-160 کاراکتر بنویسید'];
        if ($data['meta']['desc_length'] > 160) $suggestions[] = ['type' => 'meta', 'priority' => 'medium', 'message' => 'توضیحات متا بلند است.', 'fix' => 'توضیحات را خلاصه‌تر کنید'];
        if ($data['heading_count'] < 3) $suggestions[] = ['type' => 'heading', 'priority' => 'medium', 'message' => 'تگ‌های عنوان کم هستند.', 'fix' => 'از H2 و H3 برای ساختاربندی محتوا استفاده کنید'];
        if ($data['images']['without_alt'] > 0) $suggestions[] = ['type' => 'image', 'priority' => 'high', 'message' => $data['images']['without_alt'] . ' تصویر بدون alt دارد.', 'fix' => 'به همه تصاویر متن جایگزین (alt) اضافه کنید'];
        if ($data['word_count'] < 300) $suggestions[] = ['type' => 'content', 'priority' => 'medium', 'message' => 'تعداد کلمات کم است (' . $data['word_count'] . ').', 'fix' => 'محتوا را به حداقل 300 کلمه افزایش دهید'];
        if ($data['links']['internal'] < 2) $suggestions[] = ['type' => 'links', 'priority' => 'medium', 'message' => 'لینک داخلی کم دارد.', 'fix' => 'به صفحات مرتبط دیگر لینک بدهید'];
        if ($data['readability'] < 50) $suggestions[] = ['type' => 'readability', 'priority' => 'medium', 'message' => 'خوانایی محتوا پایین است.', 'fix' => 'جملات را کوتاه‌تر و ساده‌تر بنویسید'];

        return $this->ok([
            'post_id'     => $post_id,
            'score'       => $data['score'],
            'grade'       => $data['grade'],
            'suggestions' => $suggestions,
            'total_issues' => count($suggestions),
        ]);
    }

    public function seo_auto_meta($request) {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post) return $this->err('Not found', 404);

        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = wp_trim_words($content, 25);

        // Generate SEO title
        $seo_title = $title . ' | ' . get_bloginfo('name');
        if (strlen($seo_title) > 60) {
            $seo_title = substr($title, 0, 55) . '... | ' . get_bloginfo('name');
        }

        // Generate meta description
        $seo_desc = $excerpt;
        if (strlen($seo_desc) > 160) {
            $seo_desc = substr($excerpt, 0, 157) . '...';
        }

        // Apply filters for Yoast/RankMath
        update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_desc);
        update_post_meta($post_id, '_rank_math_title', $seo_title);
        update_post_meta($post_id, '_rank_math_description', $seo_desc);

        return $this->ok([
            'post_id'    => $post_id,
            'title'      => $seo_title,
            'title_len'  => strlen($seo_title),
            'desc'       => $seo_desc,
            'desc_len'   => strlen($seo_desc),
            'applied_to' => ['yoast', 'rankmath'],
        ]);
    }

    public function seo_overview($request) {
        global $wpdb;

        $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'");
        $total_pages = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'");
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");

        // Posts without meta description
        $no_meta = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_yoast_wpseo_metadesc' WHERE p.post_type = 'post' AND p.post_status = 'publish' AND pm.meta_value IS NULL");

        // Posts without featured image
        $no_thumb = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id' WHERE p.post_type IN ('post','page') AND p.post_status = 'publish' AND pm.meta_value IS NULL");

        return $this->ok([
            'total_posts'     => (int) $total_posts,
            'total_pages'     => (int) $total_pages,
            'total_products'  => (int) $total_products,
            'without_meta'    => (int) $no_meta,
            'without_thumb'   => (int) $no_thumb,
            'site_title'      => get_bloginfo('name'),
            'site_desc'       => get_bloginfo('description'),
            'permalink'       => get_option('permalink_structure'),
        ]);
    }

    // ======================== ANALYTICS ========================

    public function analytics_pages($request) {
        global $wpdb;
        $limit = (int) ($request->get_param('per_page') ?: 20);
        $results = $wpdb->get_results(
            "SELECT post_id, meta_value as views FROM {$wpdb->postmeta}
             WHERE meta_key = 'post_views_count'
             ORDER BY CAST(meta_value AS UNSIGNED) DESC
             LIMIT $limit",
            ARRAY_A
        );
        $data = [];
        foreach ($results as $r) {
            $post = get_post($r['post_id']);
            if ($post) {
                $data[] = [
                    'id'     => (int) $r['post_id'],
                    'title'  => $post->post_title,
                    'views'  => (int) $r['views'],
                    'url'    => get_permalink($post->ID),
                    'type'   => $post->post_type,
                ];
            }
        }
        return $this->ok(['data' => $data]);
    }

    public function analytics_summary($request) {
        global $wpdb;
        $total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'");
        $total_pages = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'");
        $total_comments = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '1'");
        $total_users = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $total_views = (int) $wpdb->get_var("SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = 'post_views_count'");

        return $this->ok([
            'posts'    => $total_posts,
            'pages'    => $total_pages,
            'comments' => $total_comments,
            'users'    => $total_users,
            'views'    => $total_views ?: 0,
        ]);
    }

    // ======================== AGENT ========================

    public function agent_history($request) {
        $history = get_option('aic_agent_history', []);
        if (!is_array($history)) $history = [];
        $limit = (int) ($request->get_param('per_page') ?: 50);
        $history = array_reverse($history);
        $history = array_slice($history, 0, $limit);
        return $this->ok(['data' => $history, 'total' => count(get_option('aic_agent_history', []))]);
    }

    public function agent_log($request) {
        $action = sanitize_text_field($request->get_param('action'));
        $detail = sanitize_textarea_field($request->get_param('detail'));
        if (!$action) return $this->err('action required');

        $history = get_option('aic_agent_history', []);
        if (!is_array($history)) $history = [];

        $user = wp_get_current_user();
        $history[] = [
            'time'   => current_time('mysql'),
            'action' => $action,
            'detail' => $detail,
            'user'   => $user ? $user->display_name : 'API',
        ];

        if (count($history) > 200) $history = array_slice($history, -200);
        update_option('aic_agent_history', $history);

        return $this->ok(['logged' => true, 'action' => $action]);
    }

    // ======================== DEVOPS ========================

    public function devops_health($request) {
        global $wpdb;

        $php_version = PHP_VERSION;
        $mysql_version = $wpdb->get_var("SELECT VERSION()");
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->aic_convert_to_bytes($memory_limit);
        $memory_used = @function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
        $memory_pct = $memory_bytes > 0 ? round(($memory_used / $memory_bytes) * 100, 1) : 0;
        $disk_free = @function_exists('disk_free_space') ? round(disk_free_space(ABSPATH) / 1073741824, 2) : 0;
        $disk_total = @function_exists('disk_total_space') ? round(disk_total_space(ABSPATH) / 1073741824, 2) : 0;
        $disk_pct = $disk_total > 0 ? round(($disk_total - $disk_free) / $disk_total * 100, 1) : 0;

        return $this->ok([
            'php'         => $php_version,
            'php_ok'      => version_compare($php_version, '7.4', '>='),
            'mysql'       => $mysql_version,
            'memory_limit'=> $memory_limit,
            'memory_used' => $memory_used,
            'memory_pct'  => $memory_pct,
            'disk_free'   => $disk_free,
            'disk_total'  => $disk_total,
            'disk_pct'    => $disk_pct,
            'upload_max'  => ini_get('upload_max_filesize'),
            'post_max'    => ini_get('post_max_size'),
            'max_exec'    => ini_get('max_execution_time'),
        ]);
    }

    public function devops_scan($request) {
        global $wpdb;

        $wp_config_perm = @substr(sprintf('%o', fileperms(ABSPATH . 'wp-config.php')), -4);
        $htaccess_perm = @file_exists(ABSPATH . '.htaccess') ? substr(sprintf('%o', fileperms(ABSPATH . '.htaccess')), -4) : null;
        $upload_perm = @file_exists(WP_CONTENT_DIR . '/uploads') ? substr(sprintf('%o', fileperms(WP_CONTENT_DIR . '/uploads')), -4) : null;

        $malware_found = [];
        $scan_dirs = [WP_CONTENT_DIR . '/themes', WP_CONTENT_DIR . '/plugins'];
        $suspicious_patterns = ['/eval\s*\(/i', '/base64_decode\s*\(/i', '/shell_exec\s*\(/i', '/system\s*\(/i', '/passthru\s*\(/i'];
        foreach ($scan_dirs as $dir) {
            if (!is_dir($dir)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            $count = 0;
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $count++;
                if ($count > 500) break;
                $content = @file_get_contents($file->getPathname());
                if ($content === false) continue;
                foreach ($suspicious_patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $malware_found[] = ['file' => str_replace(WP_CONTENT_DIR, '', $file->getPathname()), 'pattern' => $pattern];
                        break;
                    }
                }
            }
        }

        $admin_users = $wpdb->get_results("SELECT user_login, user_email FROM {$wpdb->users} u JOIN {$wpdb->usermeta} um ON u.ID=um.user_id WHERE um.meta_key='{$wpdb->prefix}capabilities' AND um.meta_value LIKE '%administrator%'", ARRAY_A);

        $wpconfig_secure = true;
        if (file_exists(ABSPATH . 'wp-config.php')) {
            $fc = file_get_contents(ABSPATH . 'wp-config.php');
            if (preg_match('/WP_DEBUG.*true/i', $fc)) $wpconfig_secure = false;
        }

        $results = [
            'time'              => current_time('mysql'),
            'wp_config_perm'    => $wp_config_perm,
            'htaccess_perm'     => $htaccess_perm,
            'upload_perm'       => $upload_perm,
            'malware_found'     => $malware_found,
            'malware_count'     => count($malware_found),
            'admin_users'       => count($admin_users),
            'wpconfig_secure'   => $wpconfig_secure,
        ];

        update_option('aic_last_scan', $results);
        return $this->ok($results);
    }

    public function devops_firewall($request) {
        $blocked_ips = get_option('aic_blocked_ips', []);
        if (!is_array($blocked_ips)) $blocked_ips = [];

        $htaccess_rules = [];
        if (file_exists(ABSPATH . '.htaccess')) {
            $content = file_get_contents(ABSPATH . '.htaccess');
            if (preg_match_all('/Order\s+(Deny|Allow)\s*,\s*(Deny|Allow)/i', $content)) {
                $htaccess_rules[] = 'Access rules found';
            }
            if (preg_match('/Deny from all/i', $content)) {
                $htaccess_rules[] = 'Deny from all rule found';
            }
        }

        return $this->ok([
            'blocked_ips'   => $blocked_ips,
            'htaccess_rules'=> $htaccess_rules,
        ]);
    }

    public function devops_block_ip($request) {
        $ip = sanitize_text_field($request->get_param('ip'));
        if (!$ip) return $this->err('IP required');

        $blocked_ips = get_option('aic_blocked_ips', []);
        if (!is_array($blocked_ips)) $blocked_ips = [];

        $blocked_ips[$ip] = ['date' => current_time('mysql'), 'reason' => sanitize_text_field($request->get_param('reason') ?: 'manual')];
        update_option('aic_blocked_ips', $blocked_ips);

        return $this->ok(['blocked' => true, 'ip' => $ip]);
    }

    public function devops_warnings($request) {
        global $wpdb;

        $failed_logins = 0;
        $brute_force_ips = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aic_logs'") === $wpdb->prefix . 'aic_logs') {
            $failed_logins = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aic_logs WHERE method='LOGIN_FAIL'");
            $brute_ips = $wpdb->get_results("SELECT ip, COUNT(*) as cnt FROM {$wpdb->prefix}aic_logs WHERE method='LOGIN_FAIL' GROUP BY ip HAVING cnt > 5 ORDER BY cnt DESC LIMIT 10", ARRAY_A);
            if ($brute_ips) $brute_force_ips = $brute_ips;
        }

        $blocked_ips = get_option('aic_blocked_ips', []);
        if (!is_array($blocked_ips)) $blocked_ips = [];

        return $this->ok([
            'failed_logins'  => $failed_logins,
            'brute_force_ips'=> $brute_force_ips,
            'blocked_count'  => count($blocked_ips),
            'threat_level'   => $failed_logins > 50 ? 'high' : ($failed_logins > 10 ? 'medium' : 'low'),
        ]);
    }

    private function aic_convert_to_bytes($val) {
        $val = trim($val);
        $num = (int) $val;
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            case 'g': $num *= 1073741824; break;
            case 'm': $num *= 1048576; break;
            case 'k': $num *= 1024; break;
        }
        return $num;
    }

    // ======================== NEW SEO FEATURES ========================

    public function get_404_logs($request) {
        $seo = AIC_SEO_Features::get_instance();
        $limit = (int) ($request->get_param('per_page') ?: 100);
        $offset = (int) ($request->get_param('offset') ?: 0);
        return $this->ok($seo->get_404_logs($limit, $offset));
    }

    public function clear_404_logs($request) {
        $seo = AIC_SEO_Features::get_instance();
        $seo->clear_404_logs();
        return $this->ok(['cleared' => true]);
    }

    public function seo_deep_analysis($request) {
        $seo = AIC_SEO_Features::get_instance();
        $result = $seo->deep_analysis((int) $request['id']);
        if (!$result) return $this->err('Post not found', 404);
        return $this->ok($result);
    }

    public function seo_site_health($request) {
        $seo = AIC_SEO_Features::get_instance();
        return $this->ok($seo->site_health());
    }

    public function seo_content_matrix($request) {
        $seo = AIC_SEO_Features::get_instance();
        $type = sanitize_text_field($request->get_param('type') ?: 'any');
        $limit = (int) ($request->get_param('limit') ?: 50);
        return $this->ok($seo->content_matrix($type, $limit));
    }

    public function seo_report($request) {
        $seo = AIC_SEO_Features::get_instance();
        $post_id = (int) ($request->get_param('post_id') ?: 0);
        return $this->ok($seo->seo_report($post_id));
    }

    public function seo_404_deep($request) {
        $seo = AIC_SEO_Features::get_instance();
        return $this->ok($seo->analyze_404_deep());
    }

    public function seo_broken_deep($request) {
        $seo = AIC_SEO_Features::get_instance();
        $limit = (int) ($request->get_param('limit') ?: 100);
        return $this->ok($seo->check_broken_links_deep($limit));
    }

    public function analyze_content($request) {
        $seo = AIC_SEO_Features::get_instance();
        $result = $seo->content_analysis((int) $request['id']);
        if (!$result) return $this->err('Post not found', 404);
        return $this->ok($result);
    }

    public function generate_content($request) {
        $topic = sanitize_textarea_field($request->get_param('topic') ?? '');
        $instructions = sanitize_textarea_field($request->get_param('instructions') ?? '');
        $related_post_id = (int) ($request->get_param('related_post_id') ?? 0);
        $word_limit = (int) ($request->get_param('word_limit') ?? 500);
        $content_type = sanitize_text_field($request->get_param('content_type') ?? 'article');
        $include_images = (bool) ($request->get_param('include_images') ?? false);

        if (empty($topic) && empty($instructions)) {
            return $this->err('topic or instructions required');
        }

        $context = '';
        if ($related_post_id) {
            $post = get_post($related_post_id);
            if ($post) {
                $plain = wp_strip_all_tags($post->post_content);
                $context = "صفحه مرتبط: {$post->post_title}\nمحتوای فعلی: " . mb_substr($plain, 0, 1000) . "\n\n";
            }
        }

        $all_posts = get_posts(['post_type' => ['post', 'page'], 'posts_per_page' => 10, 'post_status' => 'publish', 'orderby' => 'modified', 'order' => 'DESC']);
        $site_context = "نام سایت: " . get_bloginfo('name') . "\nتوضیحات: " . get_bloginfo('description') . "\n\nآخرین صفحات:\n";
        foreach ($all_posts as $p) {
            $site_context .= "- {$p->post_title} ({$p->post_type})\n";
        }

        $type_labels = ['article' => 'مقاله', 'product_desc' => 'توضیحات محصول', 'landing' => 'لندینگ پیج', 'faq' => 'سوالات متداول', 'service' => 'توضیحات خدمات'];

        $prompt = "شما متخصص تولید محتوا و سئو هستید.\n\n";
        $prompt .= "## اطلاعات سایت\n{$site_context}\n";
        if ($context) $prompt .= "## صفحه مرتبط\n{$context}\n";
        $type_label = $type_labels[$content_type] ?? 'مقاله';
        $prompt .= "## درخواست\n- موضوع: {$topic}\n- نوع: {$type_label}\n- کلمات: حدود {$word_limit}\n";
        if ($instructions) $prompt .= "- دستورالعمل: {$instructions}\n";
        $prompt .= "\n## قوانین\n1. محتوای اصیل و یکتا\n2. ساختار HTML (H2, H3, ul, li, strong, p)\n3. عنوان جذاب\n4. پاراگراف اول خلاصه\n5. جملات کوتاه و خوانا\n6. خلاصه در انتها\n";
        if ($include_images) {
            $prompt .= "7. برای هر بخش یک پیشنهاد تصویر: [IMAGE: توضیح | alt text]\n";
        }
        $prompt .= "\nخروجی: HTML کامل بنویس.";

        $ai_url = get_option('aic_ai_api_url', '');
        $ai_key = get_option('aic_ai_api_key', '');
        $ai_model = get_option('aic_ai_model', 'gpt-4o-mini');

        if (empty($ai_url) || empty($ai_key)) {
            return $this->err('AI API not configured');
        }

        $response = wp_remote_post($ai_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $ai_key,
            ],
            'body' => json_encode([
                'model' => $ai_model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.7,
                'max_tokens' => min(4000, max(1000, $word_limit * 2)),
            ]),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            return $this->err('AI API Error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!$body || !isset($body['choices'][0]['message']['content'])) {
            return $this->err('Invalid AI response');
        }

        $generated = trim($body['choices'][0]['message']['content']);
        $images = [];
        if ($include_images) {
            preg_match_all('/\[IMAGE:\s*(.+?)\s*\|\s*(.+?)\s*\]/i', $generated, $img_matches);
            for ($i = 0; $i < count($img_matches[0]); $i++) {
                $images[] = ['description' => $img_matches[1][$i], 'alt' => $img_matches[2][$i]];
            }
            $generated = preg_replace('/\[IMAGE:[^\]]*\]/i', '', $generated);
        }

        return $this->ok([
            'content' => $generated,
            'word_count' => str_word_count(strip_tags($generated)),
            'target_words' => $word_limit,
            'images' => $images,
            'topic' => $topic,
            'type' => $content_type,
        ]);
    }

    public function optimize_meta($request) {
        $seo = AIC_SEO_Features::get_instance();
        $result = $seo->optimize_meta((int) $request['id']);
        if (!$result) return $this->err('Post not found', 404);
        return $this->ok($result);
    }

    public function get_robots($request) {
        $seo = AIC_SEO_Features::get_instance();
        return $this->ok($seo->get_robots_txt());
    }

    public function save_robots($request) {
        $seo = AIC_SEO_Features::get_instance();
        $content = $request->get_param('content');
        if ($content === null) return $this->err('Content required');
        $result = $seo->save_robots_txt($content);
        return $this->ok(['saved' => $result]);
    }

    public function seo_full_data($request) {
        $seo = AIC_SEO_Features::get_instance();
        $health = $seo->site_health();
        $matrix = $seo->content_matrix('any', 50);
        $report = $seo->seo_report(0);
        $broken = $seo->check_broken_links_deep(50);
        $data_404 = $seo->analyze_404_deep();
        $robots = $seo->get_robots_txt();

        return $this->ok([
            'site_health' => $health,
            'content_matrix' => $matrix,
            'seo_report' => $report,
            'broken_links' => $broken,
            'errors_404' => $data_404,
            'robots_txt' => $robots,
            'generated_at' => current_time('mysql'),
        ]);
    }

    public function search_images($request) {
        $query = sanitize_text_field($request->get_param('q') ?? '');
        if (empty($query)) return $this->err('Query required');

        $results = [];

        $pexels_key = get_option('aic_pexels_key', '');
        if ($pexels_key) {
            $response = wp_remote_get("https://api.pexels.com/v1/search?query=" . urlencode($query) . "&per_page=6", [
                'headers' => ['Authorization' => $pexels_key],
                'timeout' => 15,
            ]);
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                foreach (($data['photos'] ?? []) as $photo) {
                    $results[] = [
                        'url' => $photo['src']['medium'],
                        'full' => $photo['src']['large2x'],
                        'alt' => $photo['alt'] ?: $query,
                        'source' => 'Pexels',
                        'photographer' => $photo['photographer'] ?? '',
                    ];
                }
            }
        }

        if (empty($results)) {
            $response = wp_remote_get("https://commons.wikimedia.org/w/api.php?action=query&list=search&srsearch=" . urlencode($query) . "&srnamespace=6&srlimit=6&format=json", [
                'timeout' => 15,
            ]);
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                foreach (($data['query']['search'] ?? []) as $item) {
                    $title = str_replace('File:', '', $item['title']);
                    $results[] = [
                        'url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/' . urlencode($title),
                        'full' => 'https://commons.wikimedia.org/wiki/Special:FilePath/' . urlencode($title),
                        'alt' => $item['title'],
                        'source' => 'Wikimedia',
                    ];
                }
            }
        }

        return $this->ok(['images' => $results, 'query' => $query]);
    }

    public function save_content($request) {
        $post_id = (int) ($request->get_param('post_id') ?? 0);
        $content = $request->get_param('content');
        $title = sanitize_text_field($request->get_param('title') ?? '');

        if (empty($content)) return $this->err('Content required');

        if ($post_id) {
            $update = ['ID' => $post_id, 'post_content' => $content];
            if ($title) $update['post_title'] = $title;
            $result = wp_update_post($update, true);
        } else {
            $result = wp_insert_post([
                'post_title' => $title ?: 'محتوای تولید شده - ' . date('Y-m-d H:i'),
                'post_content' => $content,
                'post_status' => 'draft',
                'post_type' => 'post',
            ]);
        }

        if (is_wp_error($result)) {
            return $this->err($result->get_error_message());
        }

        return $this->ok(['post_id' => $result, 'saved' => true]);
    }

    public function content_queue($request) {
        $queue = get_option('aic_content_queue', []);
        $status = sanitize_text_field($request->get_param('status') ?? 'pending');
        $filtered = array_filter($queue, fn($t) => $t['status'] === $status);
        return $this->ok(['tasks' => array_values($filtered), 'total' => count($filtered)]);
    }

    public function content_complete($request) {
        $request_id = sanitize_text_field($request->get_param('request_id') ?? '');
        $content = $request->get_param('content');
        $agent = sanitize_text_field($request->get_param('agent') ?? 'unknown');

        if (empty($request_id) || empty($content)) {
            return $this->err('request_id and content required');
        }

        $queue = get_option('aic_content_queue', []);
        if (empty($queue[$request_id])) {
            return $this->err('Request not found', 404);
        }

        $actual_words = str_word_count(strip_tags($content));

        $queue[$request_id]['status'] = 'completed';
        $queue[$request_id]['content'] = $content;
        $queue[$request_id]['agent'] = $agent;
        $queue[$request_id]['completed_at'] = current_time('mysql');
        $queue[$request_id]['word_count'] = $actual_words;
        update_option('aic_content_queue', $queue);

        $history = get_option('aic_content_history', []);
        $history[] = [
            'time' => current_time('mysql'),
            'topic' => $queue[$request_id]['topic'],
            'type' => $queue[$request_id]['content_type_key'],
            'words' => $actual_words,
            'target' => $queue[$request_id]['word_limit'],
            'related_post' => $queue[$request_id]['related_post_id'],
            'agent' => $agent,
        ];
        if (count($history) > 50) $history = array_slice($history, -50);
        update_option('aic_content_history', $history);

        return $this->ok([
            'saved' => true,
            'request_id' => $request_id,
            'word_count' => $actual_words,
            'completed_at' => $queue[$request_id]['completed_at'],
        ]);
    }

    // ======================== AI AGENT ENDPOINTS ========================

    public function seo_raw_data($request) {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post) return $this->err('Post not found', 404);

        $content = apply_filters('the_content', $post->post_content);
        $plain = wp_strip_all_tags($post->post_content);
        $url = get_permalink($post_id);

        $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true) ?: $post->post_title;
        $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true) ?: '';

        $headings = [];
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $content, $hm);
        for ($i = 0; $i < count($hm[0]); $i++) {
            $headings[] = ['level' => (int) $hm[1][$i], 'text' => wp_strip_all_tags($hm[2][$i])];
        }

        $images = [];
        preg_match_all('/<img[^>]+>/is', $content, $im);
        foreach ($im[0] as $img) {
            preg_match('/src=["\']([^"\']*)["\']/i', $img, $src);
            preg_match('/alt=["\']([^"\']*)["\']/i', $img, $alt);
            $images[] = ['src' => $src[1] ?? '', 'alt' => $alt[1] ?? '', 'has_alt' => !empty($alt[1])];
        }

        $internal_links = [];
        $external_links = [];
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $lm);
        $site_url = home_url();
        for ($i = 0; $i < count($lm[0]); $i++) {
            $item = ['url' => $lm[1][$i], 'text' => wp_strip_all_tags($lm[2][$i])];
            if (strpos($lm[1][$i], $site_url) === 0 || (strpos($lm[1][$i], '/') === 0)) {
                $internal_links[] = $item;
            } else {
                $external_links[] = $item;
            }
        }

        $featured = has_post_thumbnail($post_id);
        $featured_url = $featured ? get_the_post_thumbnail_url($post_id, 'full') : '';

        return $this->ok([
            'post_id' => $post_id,
            'title' => $post->post_title,
            'url' => $url,
            'type' => $post->post_type,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'meta_title' => $meta_title,
            'meta_title_length' => mb_strlen($meta_title),
            'meta_description' => $meta_desc,
            'meta_desc_length' => mb_strlen($meta_desc),
            'content_html' => $content,
            'content_plain' => $plain,
            'content_length' => mb_strlen($plain),
            'word_count' => str_word_count($plain),
            'slug' => $post->post_name,
            'headings' => $headings,
            'images' => $images,
            'internal_links' => $internal_links,
            'external_links' => $external_links,
            'has_featured_image' => $featured,
            'featured_image_url' => $featured_url,
            'comment_count' => (int) $post->comment_count,
            'og_title' => get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true),
            'og_desc' => get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true),
            'og_image' => get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true),
            'schema_present' => (bool) preg_match('/itemtype|application\/ld\+json/i', $content),
            'raw_for_ai' => [
                'instruction' => 'این داده‌های خام یک صفحه وبسایت هست. لطفاً محتوا رو بخون و از نظر سئو و کیفیت محتوا تحلیل کن.',
                'page_title' => $post->post_title,
                'meta_title' => $meta_title,
                'meta_description' => $meta_desc,
                'full_content' => $plain,
                'headings_list' => array_map(fn($h) => 'H' . $h['level'] . ': ' . $h['text'], $headings),
                'images_count' => count($images),
                'images_without_alt' => count(array_filter($images, fn($i) => !$i['has_alt'])),
                'internal_links_count' => count($internal_links),
                'external_links_count' => count($external_links),
                'word_count' => str_word_count($plain),
                'site_name' => get_bloginfo('name'),
                'site_url' => home_url(),
            ],
        ]);
    }

    public function seo_save_analysis($request) {
        $post_id = (int) ($request->get_param('post_id') ?: 0);
        $analysis = $request->get_param('analysis');
        $score = (int) ($request->get_param('score') ?: 0);
        $grade = sanitize_text_field($request->get_param('grade') ?: 'D');
        $agent = sanitize_text_field($request->get_param('agent') ?: 'ai-agent');

        if (!$post_id || !$analysis) return $this->err('post_id and analysis required');

        $data = [
            'score' => $score,
            'grade' => $grade,
            'analysis' => $analysis,
            'agent' => $agent,
            'time' => current_time('mysql'),
        ];

        update_post_meta($post_id, '_aic_ai_analysis', $data);
        update_post_meta($post_id, '_aic_ai_score', $score);
        update_post_meta($post_id, '_aic_ai_grade', $grade);

        return $this->ok(['saved' => true, 'post_id' => $post_id, 'score' => $score, 'grade' => $grade]);
    }

    public function seo_get_analysis($request) {
        $post_id = (int) $request['id'];
        $data = get_post_meta($post_id, '_aic_ai_analysis', true);
        if (!$data) return $this->ok(['has_analysis' => false]);
        return $this->ok(['has_analysis' => true, 'data' => $data]);
    }

    public function seo_bulk_prompt($request) {
        $seo = AIC_SEO_Features::get_instance();
        $health = $seo->site_health();
        $matrix = $seo->content_matrix('any', 50);

        $pages = [];
        foreach ($matrix['posts'] as $p) {
            $raw = $this->seo_raw_data_raw($p['id']);
            if ($raw) $pages[] = $raw;
        }

        return $this->ok([
            'prompt' => 'شما یک متخصص سئو هستید. لطفاً داده‌های زیر رو تحلیل کنید و برای هر صفحه نمره و پیشنهاد بدهید.',
            'site_health' => $health,
            'pages' => $pages,
        ]);
    }

    private function seo_raw_data_raw($post_id) {
        $post = get_post($post_id);
        if (!$post) return null;

        $content = apply_filters('the_content', $post->post_content);
        $plain = wp_strip_all_tags($post->post_content);
        $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true) ?: $post->post_title;
        $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true) ?: '';

        return [
            'post_id' => $post_id,
            'title' => $post->post_title,
            'url' => get_permalink($post_id),
            'meta_title' => $meta_title,
            'meta_desc' => $meta_desc,
            'content_plain' => $plain,
            'word_count' => str_word_count($plain),
            'slug' => $post->post_name,
        ];
    }
}
