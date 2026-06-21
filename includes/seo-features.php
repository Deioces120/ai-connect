<?php
if (!defined('ABSPATH')) exit;

class AIC_SEO_Features {

    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'init_404_monitor']);
    }

    // ======================== 404 MONITOR ========================

    public function init_404_monitor() {
        if (!is_admin() && !wp_doing_ajax()) {
            add_action('template_redirect', function () {
                if (is_404()) {
                    $this->log_404();
                }
            });
        }
    }

    private function log_404() {
        global $wpdb;
        $table = $wpdb->prefix . 'aic_404_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `url` text NOT NULL,
                `referrer` text,
                `ip` varchar(45) NOT NULL,
                `user_agent` text,
                `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `count` int(11) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `time` (`time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        $url = $_SERVER['REQUEST_URI'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $ip = $this->get_client_ip();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, count FROM $table WHERE url = %s ORDER BY id DESC LIMIT 1", $url));
        if ($existing) {
            $wpdb->update($table, ['count' => $existing->count + 1, 'time' => current_time('mysql')], ['id' => $existing->id]);
        } else {
            $wpdb->insert($table, [
                'url' => $url, 'referrer' => $referrer, 'ip' => $ip,
                'user_agent' => $ua, 'time' => current_time('mysql'), 'count' => 1,
            ]);
        }
    }

    public function get_404_logs($limit = 100, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'aic_404_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['data' => [], 'total' => 0];
        }
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY time DESC LIMIT %d OFFSET %d", $limit, $offset), ARRAY_A);
        return ['data' => $logs ?: [], 'total' => $total];
    }

    public function clear_404_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'aic_404_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }
        return true;
    }

    public function delete_404_log($id) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'aic_404_logs', ['id' => (int) $id]);
        return true;
    }

    // ======================== DEEP SEO ANALYSIS (PER PAGE) ========================

    public function deep_analysis($post_id) {
        $post = get_post($post_id);
        if (!$post) return null;

        $content = apply_filters('the_content', $post->post_content);
        $plain = wp_strip_all_tags($post->post_content);
        $url = get_permalink($post_id);

        // Title analysis
        $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true)
                   ?: get_post_meta($post_id, '_rank_math_title', true)
                   ?: $post->post_title;
        $title_len = mb_strlen($meta_title);
        $primary_keyword = sanitize_text_field($_GET['focus_keyword'] ?? '');
        $title_has_keyword = $primary_keyword ? (mb_stripos($meta_title, $primary_keyword) !== false) : null;
        $title_has_brand = (mb_stripos($meta_title, get_bloginfo('name')) !== false);

        // Meta description
        $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true)
                   ?: get_post_meta($post_id, '_rank_math_description', true)
                   ?: '';
        $desc_len = mb_strlen($meta_desc);
        $desc_has_keyword = $primary_keyword ? (mb_stripos($meta_desc, $primary_keyword) !== false) : null;

        // Headings
        $headings = $this->extract_headings($content);
        $h1_count = count(array_filter($headings, fn($h) => $h['level'] === 1));
        $h2_count = count(array_filter($headings, fn($h) => $h['level'] === 2));
        $h3_count = count(array_filter($headings, fn($h) => $h['level'] === 3));

        // Content
        $word_count = $this->count_words($plain);
        $sentence_count = max(1, preg_match_all('/[.!?؟]+/', $plain));
        $paragraph_count = max(1, substr_count($plain, "\n\n") + 1);
        $readability = $this->flesch_score($plain);
        $gunning_fog = $this->gunning_fog_score($plain);
        $coleman_liau = $this->coleman_liau_score($plain);

        // Keyword analysis
        $keyword_data = $this->analyze_keyword_in_content($plain, $primary_keyword, $meta_title, $meta_desc, $content);

        // Images
        $images = $this->extract_images($content);
        $imgs_with_alt = count(array_filter($images, fn($i) => $i['has_alt']));
        $imgs_with_title = count(array_filter($images, fn($i) => !empty($i['title'])));
        $large_images = array_filter($images, fn($i) => isset($i['width']) && $i['width'] > 1920);

        // Links
        $links = $this->extract_links($content);
        $internal_count = count($links['internal']);
        $external_count = count($links['external']);
        $nofollow_count = count(array_filter($links['external'], fn($l) => strpos($l['rel'] ?? '', 'nofollow') !== false));

        // URL analysis
        $slug = $post->post_name;
        $slug_len = mb_strlen($slug);
        $slug_has_keyword = $primary_keyword ? (mb_stripos($slug, sanitize_title($primary_keyword)) !== false) : null;
        $slug_too_long = $slug_len > 60;
        $slug_has_numbers = preg_match('/\d/', $slug);

        // Schema / Structured Data
        $has_schema = (bool) preg_match('/itemtype|application\/ld\+json/i', $content);
        $schema_types = [];
        if (preg_match_all('/itemtype=["\']([^"\']+)["\']/i', $content, $m)) {
            $schema_types = $m[1];
        }

        // Open Graph
        $og_title = get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true) ?: '';
        $og_desc = get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true) ?: '';
        $og_image = get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true) ?: '';
        $twitter_card = get_post_meta($post_id, '_yoast_wpseo_twitter-card-type', true) ?: '';

        // Featured image
        $has_featured = has_post_thumbnail($post_id);
        $featured_size = '';
        if ($has_featured) {
            $thumb_id = get_post_thumbnail_id($post_id);
            $img_meta = wp_get_attachment_metadata($thumb_id);
            $featured_size = ($img_meta['width'] ?? 0) . 'x' . ($img_meta['height'] ?? 0);
        }

        // Page resources (for speed indicators)
        global $wpdb;
        $comment_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = '1'", $post_id
        ));
        $revision_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision'", $post_id
        ));

        // Content uniqueness (simple check)
        $content_hash = md5($plain);
        $duplicates = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_aic_content_hash'
             WHERE pm.meta_value = %s AND p.ID != %d",
            $content_hash, $post_id
        ));
        update_post_meta($post_id, '_aic_content_hash', $content_hash);

        // Score calculation
        $score = 0;
        $issues = [];
        $passed = [];

        // Title scoring
        if ($title_len === 0) {
            $issues[] = ['severity' => 'critical', 'type' => 'title', 'message' => 'عنوان متا وجود ندارد', 'fix' => 'یک عنوان 30-60 کاراکتری بنویسید'];
        } elseif ($title_len < 30) {
            $issues[] = ['severity' => 'warning', 'type' => 'title', 'message' => "عنوان کوتاه است ($title_len کاراکتر)", 'fix' => 'عنوان را به حداقل 30 کاراکتر برسانید'];
            $score += 5;
        } elseif ($title_len > 60) {
            $issues[] = ['severity' => 'warning', 'type' => 'title', 'message' => "عنوان بلند است ($title_len کاراکتر)", 'fix' => 'عنوان را به حداکثر 60 کاراکتر کاهش دهید'];
            $score += 10;
        } else {
            $score += 15;
            $passed[] = 'title_length';
        }

        if ($primary_keyword && $title_has_keyword === false) {
            $issues[] = ['severity' => 'warning', 'type' => 'title', 'message' => 'کلمه کلیدی در عنوان نیست', 'fix' => 'کلمه کلیدی را در عنوان قرار دهید'];
        } elseif ($title_has_keyword) {
            $score += 5;
            $passed[] = 'title_keyword';
        }

        if ($title_has_brand) {
            $score += 3;
            $passed[] = 'title_brand';
        }

        // Meta description scoring
        if ($desc_len === 0) {
            $issues[] = ['severity' => 'critical', 'type' => 'meta', 'message' => 'توضیحات متا وجود ندارد', 'fix' => 'توضیحات متا 120-160 کاراکتری بنویسید'];
        } elseif ($desc_len < 120) {
            $issues[] = ['severity' => 'warning', 'type' => 'meta', 'message' => "توضیحات کوتاه است ($desc_len کاراکتر)", 'fix' => 'توضیحات را به حداقل 120 کاراکتر برسانید'];
            $score += 5;
        } elseif ($desc_len > 160) {
            $issues[] = ['severity' => 'warning', 'type' => 'meta', 'message' => "توضیحات بلند است ($desc_len کاراکتر)", 'fix' => 'توضیحات را به حداکثر 160 کاراکتر کاهش دهید'];
            $score += 10;
        } else {
            $score += 15;
            $passed[] = 'meta_desc_length';
        }

        if ($primary_keyword && $desc_has_keyword === false) {
            $issues[] = ['severity' => 'info', 'type' => 'meta', 'message' => 'کلمه کلیدی در توضیحات متا نیست', 'fix' => 'کلمه کلیدی را در توضیحات متا قرار دهید'];
        } elseif ($desc_has_keyword) {
            $score += 3;
            $passed[] = 'meta_desc_keyword';
        }

        // Heading scoring
        if ($h1_count === 0) {
            $issues[] = ['severity' => 'critical', 'type' => 'heading', 'message' => 'تگ H1 وجود ندارد', 'fix' => 'یک تگ H1 اضافه کنید'];
        } elseif ($h1_count > 1) {
            $issues[] = ['severity' => 'warning', 'type' => 'heading', 'message' => "$h1_count تگ H1 وجود دارد", 'fix' => 'فقط یک H1 داشته باشید'];
            $score += 5;
        } else {
            $score += 10;
            $passed[] = 'h1_count';
        }

        if ($h2_count >= 2) {
            $score += 5;
            $passed[] = 'h2_structure';
        } elseif ($word_count > 300 && $h2_count === 0) {
            $issues[] = ['severity' => 'warning', 'type' => 'heading', 'message' => 'تگ H2 ندارد', 'fix' => 'محتوا را با H2 ساختاربندی کنید'];
        }

        if (count($headings) >= 5) {
            $score += 5;
            $passed[] = 'heading_structure';
        }

        // Content scoring
        if ($word_count < 100) {
            $issues[] = ['severity' => 'critical', 'type' => 'content', 'message' => "محتوا بسیار کم است ($word_count کلمه)", 'fix' => 'حداقل 300 کلمه بنویسید'];
        } elseif ($word_count < 300) {
            $issues[] = ['severity' => 'warning', 'type' => 'content', 'message' => "محتوا کوتاه است ($word_count کلمه)", 'fix' => 'محتوا را به حداقل 300 کلمه افزایش دهید'];
            $score += 5;
        } elseif ($word_count >= 1000) {
            $score += 15;
            $passed[] = 'content_depth';
        } else {
            $score += 10;
            $passed[] = 'content_length';
        }

        if ($readability >= 60) {
            $score += 5;
            $passed[] = 'readability';
        } elseif ($readability < 30) {
            $issues[] = ['severity' => 'warning', 'type' => 'readability', 'message' => "خوانایی پایین ($readability)", 'fix' => 'جملات را کوتاه‌تر بنویسید'];
        }

        // Image scoring
        if (count($images) === 0) {
            $issues[] = ['severity' => 'warning', 'type' => 'images', 'message' => 'تصویری وجود ندارد', 'fix' => 'حداقل یک تصویر اضافه کنید'];
        } else {
            $score += 5;
            if ($imgs_with_alt === count($images)) {
                $score += 5;
                $passed[] = 'image_alt';
            } else {
                $missing_alt = count($images) - $imgs_with_alt;
                $issues[] = ['severity' => 'warning', 'type' => 'images', 'message' => "$missing_alt تصویر بدون alt", 'fix' => 'به همه تصاویر alt اضافه کنید'];
            }
        }

        // Link scoring
        if ($internal_count >= 3) {
            $score += 5;
            $passed[] = 'internal_links';
        } elseif ($word_count > 300 && $internal_count < 2) {
            $issues[] = ['severity' => 'warning', 'type' => 'links', 'message' => 'لینک داخلی کم است', 'fix' => 'حداقل 2 لینک داخلی اضافه کنید'];
        }

        // Slug scoring
        if ($slug_len > 0 && $slug_len <= 50) {
            $score += 3;
            $passed[] = 'slug_length';
        } elseif ($slug_len > 60) {
            $issues[] = ['severity' => 'info', 'type' => 'url', 'message' => "Slug خیلی بلند است ($slug_len کاراکتر)", 'fix' => 'Slug را کوتاه‌تر کنید'];
        }

        if ($primary_keyword && $slug_has_keyword) {
            $score += 3;
            $passed[] = 'slug_keyword';
        }

        // Featured image
        if ($has_featured) {
            $score += 5;
            $passed[] = 'featured_image';
        } else {
            $issues[] = ['severity' => 'warning', 'type' => 'media', 'message' => 'تصویر شاخص وجود ندارد', 'fix' => 'یک تصویر شاخص اضافه کنید'];
        }

        // Duplicate content
        if ($duplicates > 0) {
            $issues[] = ['severity' => 'critical', 'type' => 'content', 'message' => "محتوای تکراری در $duplicates صفحه", 'fix' => 'محتوا را یکتا کنید'];
        }

        // Open Graph
        if ($og_title && $og_desc && $og_image) {
            $score += 3;
            $passed[] = 'open_graph';
        }

        $score = min(100, $score);

        return [
            'post_id' => $post_id,
            'title' => $post->post_title,
            'url' => $url,
            'type' => $post->post_type,
            'score' => $score,
            'grade' => $score >= 80 ? 'A' : ($score >= 60 ? 'B' : ($score >= 40 ? 'C' : 'D')),
            'date' => $post->post_date,
            'modified' => $post->post_modified,

            'title_analysis' => [
                'meta_title' => $meta_title,
                'length' => $title_len,
                'has_keyword' => $title_has_keyword,
                'has_brand' => $title_has_brand,
                'optimal' => $title_len >= 30 && $title_len <= 60,
            ],
            'meta_analysis' => [
                'description' => $meta_desc,
                'length' => $desc_len,
                'has_keyword' => $desc_has_keyword,
                'optimal' => $desc_len >= 120 && $desc_len <= 160,
            ],
            'heading_analysis' => [
                'h1_count' => $h1_count,
                'h2_count' => $h2_count,
                'h3_count' => $h3_count,
                'total' => count($headings),
                'structure' => $headings,
            ],
            'content_analysis' => [
                'word_count' => $word_count,
                'sentence_count' => $sentence_count,
                'paragraph_count' => $paragraph_count,
                'readability_flesch' => $readability,
                'readability_fog' => $gunning_fog,
                'readability_coleman' => $coleman_liau,
                'content_hash' => $content_hash,
                'duplicate_count' => $duplicates,
                'avg_words_per_sentence' => round($word_count / max(1, $sentence_count), 1),
            ],
            'keyword_analysis' => $keyword_data,
            'image_analysis' => [
                'total' => count($images),
                'with_alt' => $imgs_with_alt,
                'without_alt' => count($images) - $imgs_with_alt,
                'with_title' => $imgs_with_title,
                'large_images' => count($large_images),
                'images' => array_slice($images, 0, 20),
            ],
            'link_analysis' => [
                'internal' => $internal_count,
                'external' => $external_count,
                'nofollow' => $nofollow_count,
                'total' => $internal_count + $external_count,
                'internal_links' => array_slice($links['internal'], 0, 20),
                'external_links' => array_slice($links['external'], 0, 20),
            ],
            'url_analysis' => [
                'slug' => $slug,
                'slug_length' => $slug_len,
                'has_keyword' => $slug_has_keyword,
                'has_numbers' => $slug_has_numbers,
                'too_long' => $slug_too_long,
            ],
            'schema_analysis' => [
                'has_schema' => $has_schema,
                'types' => $schema_types,
            ],
            'social_analysis' => [
                'og_title' => $og_title,
                'og_desc' => $og_desc,
                'og_image' => $og_image,
                'twitter_card' => $twitter_card,
                'complete' => !empty($og_title) && !empty($og_desc) && !empty($og_image),
            ],
            'media_analysis' => [
                'has_featured_image' => $has_featured,
                'featured_size' => $featured_size,
            ],
            'page_meta' => [
                'comment_count' => $comment_count,
                'revision_count' => $revision_count,
            ],
            'issues' => $issues,
            'passed' => $passed,
            'errors' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
            'warnings' => count(array_filter($issues, fn($i) => $i['severity'] === 'warning')),
            'info' => count(array_filter($issues, fn($i) => $i['severity'] === 'info')),
        ];
    }

    // ======================== SITE-WIDE HEALTH ========================

    public function site_health() {
        global $wpdb;

        // Basic counts
        $total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        $total_pages = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish'");
        $total_products = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
        $total_comments = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='1'");
        $total_users = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");

        // Pages missing meta description
        $no_meta = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_yoast_wpseo_metadesc'
            WHERE p.post_status='publish' AND p.post_type IN ('post','page','product')
            AND (pm.meta_value IS NULL OR pm.meta_value='')");

        // Pages missing title
        $no_title = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_yoast_wpseo_title'
            WHERE p.post_status='publish' AND p.post_type IN ('post','page','product')
            AND (pm.meta_value IS NULL OR pm.meta_value='')");

        // Pages missing featured image
        $no_thumb = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_thumbnail_id'
            WHERE p.post_status='publish' AND p.post_type IN ('post','page')
            AND (pm.meta_value IS NULL OR pm.meta_value='')");

        // Thin content (< 300 words)
        $thin_content = 0;
        $all_posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product')");
        foreach ($all_posts as $pid) {
            $content = wp_strip_all_tags(get_post_field('post_content', $pid));
            if ($this->count_words($content) < 300) $thin_content++;
        }

        // Duplicate titles
        $dup_titles = (int) $wpdb->get_var("SELECT COUNT(*) FROM (
            SELECT post_title, COUNT(*) as cnt FROM {$wpdb->posts}
            WHERE post_status='publish' AND post_type IN ('post','page','product')
            GROUP BY post_title HAVING cnt > 1
        ) as t");

        // Images without alt
        $total_images = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image%'");
        $no_alt = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_wp_attachment_image_alt'
            WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image%'
            AND (pm.meta_value IS NULL OR pm.meta_value='')");

        // Schema detection
        $posts_with_schema = 0;
        foreach (array_slice($all_posts, 0, 50) as $pid) {
            $c = get_post_field('post_content', $pid);
            if (preg_match('/itemtype|application\/ld\+json/i', $c)) $posts_with_schema++;
        }

        // 404 count
        $table_404 = $wpdb->prefix . 'aic_404_logs';
        $total_404 = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_404'") === $table_404) {
            $total_404 = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_404");
        }

        // SSL
        $is_ssl = is_ssl();

        // Permalinks
        global $wp_rewrite;
        $has_permalinks = (bool) $wp_rewrite->permalink_structure;

        // Theme & Plugins
        $theme = wp_get_theme();
        $active_plugins = count(get_option('active_plugins', []));

        // Server info
        $php_version = PHP_VERSION;
        $wp_version = get_bloginfo('version');
        $mysql_version = $wpdb->get_var("SELECT VERSION()");
        $memory = ini_get('memory_limit');

        // Robots.txt
        $robots_exists = file_exists(ABSPATH . 'robots.txt');

        // Sitemap
        $sitemap_exists = @file_exists(ABSPATH . 'sitemap.xml') || @file_exists(ABSPATH . 'news-sitemap.xml');

        $checks = [
            'posts' => $total_posts,
            'pages' => $total_pages,
            'products' => $total_products,
            'comments' => $total_comments,
            'users' => $total_users,
            'missing_meta' => $no_meta,
            'missing_title' => $no_title,
            'missing_featured_image' => $no_thumb,
            'thin_content' => $thin_content,
            'duplicate_titles' => $dup_titles,
            'total_images' => $total_images,
            'images_without_alt' => $no_alt,
            'posts_with_schema' => $posts_with_schema,
            'total_404s' => $total_404,
            'ssl' => $is_ssl,
            'permalinks' => $has_permalinks,
            'theme' => $theme->get('Name'),
            'theme_version' => $theme->get('Version'),
            'active_plugins' => $active_plugins,
            'php_version' => $php_version,
            'wp_version' => $wp_version,
            'mysql_version' => $mysql_version,
            'memory_limit' => $memory,
            'robots_txt' => $robots_exists,
            'sitemap' => $sitemap_exists,
        ];

        // Health score
        $health = 100;
        if ($no_meta > 0) $health -= min(20, $no_meta * 2);
        if ($no_title > 0) $health -= min(15, $no_title * 2);
        if ($no_thumb > 0) $health -= min(10, $no_thumb * 1);
        if ($thin_content > 0) $health -= min(15, $thin_content * 2);
        if ($dup_titles > 0) $health -= min(10, $dup_titles * 3);
        if ($no_alt > 0) $health -= min(10, $no_alt * 1);
        if ($total_404 > 10) $health -= min(10, $total_404 / 5);
        if (!$is_ssl) $health -= 15;
        if (!$has_permalinks) $health -= 10;
        if (!$robots_exists) $health -= 5;
        if ($posts_with_schema === 0 && $total_posts > 0) $health -= 10;

        return [
            'health_score' => max(0, $health),
            'health_grade' => $health >= 80 ? 'A' : ($health >= 60 ? 'B' : ($health >= 40 ? 'C' : 'D')),
            'checks' => $checks,
            'issues' => $this->compile_site_issues($checks),
        ];
    }

    private function compile_site_issues($c) {
        $issues = [];
        if ($c['missing_meta'] > 0) $issues[] = ['severity' => 'critical', 'message' => "{$c['missing_meta']} صفحه بدون توضیحات متا", 'fix' => 'توضیحات متا برای همه صفحات بنویسید'];
        if ($c['missing_title'] > 0) $issues[] = ['severity' => 'critical', 'message' => "{$c['missing_title']} صفحه بدون عنوان متا", 'fix' => 'عنوان متا برای همه صفحات بنویسید'];
        if ($c['thin_content'] > 0) $issues[] = ['severity' => 'warning', 'message' => "{$c['thin_content']} صفحه با محتوای کم (زیر 300 کلمه)", 'fix' => 'محتوا را غنی‌تر کنید'];
        if ($c['duplicate_titles'] > 0) $issues[] = ['severity' => 'warning', 'message' => "{$c['duplicate_titles']} عنوان تکراری", 'fix' => 'عنوان هر صفحه را یکتا کنید'];
        if ($c['images_without_alt'] > 0) $issues[] = ['severity' => 'warning', 'message' => "{$c['images_without_alt']} تصویر بدون alt", 'fix' => 'به همه تصاویر alt اضافه کنید'];
        if ($c['missing_featured_image'] > 0) $issues[] = ['severity' => 'info', 'message' => "{$c['missing_featured_image']} صفحه بدون تصویر شاخص", 'fix' => 'تصویر شاخص اضافه کنید'];
        if (!$c['ssl']) $issues[] = ['severity' => 'critical', 'message' => 'SSL فعال نیست', 'fix' => 'HTTPS را فعال کنید'];
        if (!$c['permalinks']) $issues[] = ['severity' => 'warning', 'message' => 'پیوند یکتا تنظیم نشده', 'fix' => 'پیوند یکتا را تنظیم کنید'];
        if (!$c['robots_txt']) $issues[] = ['severity' => 'info', 'message' => 'فایل robots.txt وجود ندارد', 'fix' => 'فایل robots.txt بسازید'];
        if ($c['posts_with_schema'] === 0 && $c['posts'] > 0) $issues[] = ['severity' => 'info', 'message' => 'هیچ صفحه‌ای Schema ندارد', 'fix' => 'داده‌های ساختاریافته اضافه کنید'];
        if ($c['total_404s'] > 10) $issues[] = ['severity' => 'warning', 'message' => "{$c['total_404s']} خطای 404 ثبت شده", 'fix' => 'لینک‌های شکسته را اصلاح کنید'];
        return $issues;
    }

    // ======================== CONTENT MATRIX ========================

    public function content_matrix($type = 'any', $limit = 50) {
        $args = [
            'post_type' => $type === 'any' ? ['post', 'page'] : $type,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
        ];
        $query = new WP_Query($args);
        $matrix = [];

        foreach ($query->posts as $post) {
            $plain = wp_strip_all_tags($post->post_content);
            $word_count = $this->count_words($plain);
            $meta_title = get_post_meta($post->ID, '_yoast_wpseo_title', true) ?: $post->post_title;
            $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) ?: '';

            $matrix[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'type' => $post->post_type,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'word_count' => $word_count,
                'meta_title_length' => mb_strlen($meta_title),
                'meta_desc_length' => mb_strlen($meta_desc),
                'has_meta_desc' => mb_strlen($meta_desc) > 0,
                'has_featured_image' => has_post_thumbnail($post->ID),
                'heading_count' => count($this->extract_headings($post->post_content)),
                'image_count' => count($this->extract_images($post->post_content)),
                'link_count' => count($this->extract_links($post->post_content)['internal']) + count($this->extract_links($post->post_content)['external']),
                'readability' => $this->flesch_score($plain),
                'content_length' => mb_strlen($plain),
            ];
        }

        return [
            'total' => count($matrix),
            'avg_word_count' => count($matrix) > 0 ? round(array_sum(array_column($matrix, 'word_count')) / count($matrix)) : 0,
            'avg_readability' => count($matrix) > 0 ? round(array_sum(array_column($matrix, 'readability')) / count($matrix)) : 0,
            'posts' => $matrix,
        ];
    }

    // ======================== 404 DEEP ANALYSIS ========================

    public function analyze_404_deep() {
        global $wpdb;
        $table = $wpdb->prefix . 'aic_404_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['data' => [], 'summary' => []];
        }

        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY count DESC LIMIT 200", ARRAY_A);

        // Group by referrer
        $by_referrer = [];
        foreach ($logs as $log) {
            $ref = $log['referrer'] ?: 'direct';
            $by_referrer[$ref] = ($by_referrer[$ref] ?? 0) + $log['count'];
        }
        arsort($by_referrer);

        // Group by time (hourly)
        $by_hour = array_fill(0, 24, 0);
        foreach ($logs as $log) {
            $hour = (int) date('H', strtotime($log['time']));
            $by_hour[$hour] += $log['count'];
        }

        // Most hit 404s
        $top_urls = array_slice($logs, 0, 20);

        // Total
        $total_hits = array_sum(array_column($logs, 'count'));
        $unique_urls = count($logs);

        return [
            'total_hits' => $total_hits,
            'unique_urls' => $unique_urls,
            'top_urls' => $top_urls,
            'by_referrer' => array_slice($by_referrer, 0, 10, true),
            'by_hour' => $by_hour,
        ];
    }

    // ======================== BROKEN LINKS DEEP ========================

    public function check_broken_links_deep($limit = 100) {
        global $wpdb;
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') LIMIT %d", $limit
        ));

        $all_links = [];
        foreach ($posts as $post) {
            $internal = [];
            $external = [];
            preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $post->post_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $i => $url) {
                    $item = ['url' => $url, 'text' => wp_strip_all_tags($matches[2][$i] ?? '')];
                    if (strpos($url, home_url()) === 0 || (strpos($url, '/') === 0 && strpos($url, '//') !== 0)) {
                        $internal[] = $item;
                    } else {
                        $external[] = $item;
                    }
                }
            }
            $all_links[] = [
                'post_id' => $post->ID,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'internal' => $internal,
                'external' => $external,
            ];
        }

        // Check external links
        $unique_external = [];
        foreach ($all_links as $pl) {
            foreach ($pl['external'] as $link) {
                if (!isset($unique_external[$link['url']])) {
                    $unique_external[$link['url']] = $link;
                    $unique_external[$link['url']]['found_on'] = [];
                }
                $unique_external[$link['url']]['found_on'][] = ['id' => $pl['post_id'], 'title' => $pl['post_title']];
            }
        }

        $results = [];
        $checked = 0;
        foreach (array_slice(array_keys($unique_external), 0, 50) as $url) {
            $code = $this->check_url($url);
            $results[] = [
                'url' => $url,
                'status' => $code,
                'is_broken' => $code >= 400 || $code === 0,
                'is_redirect' => $code >= 300 && $code < 400,
                'found_on' => $unique_external[$url]['found_on'],
            ];
            $checked++;
        }

        $broken = array_filter($results, fn($r) => $r['is_broken']);
        $redirects = array_filter($results, fn($r) => $r['is_redirect']);
        $working = array_filter($results, fn($r) => !$r['is_broken'] && !$r['is_redirect']);

        // Internal link check
        $internal_broken = [];
        foreach ($all_links as $pl) {
            foreach ($pl['internal'] as $link) {
                $path = wp_parse_url($link['url'], PHP_URL_PATH);
                if ($path && !url_to_postid($link['url'])) {
                    $internal_broken[] = [
                        'url' => $link['url'],
                        'post_id' => $pl['post_id'],
                        'post_title' => $pl['post_title'],
                    ];
                }
            }
        }

        return [
            'total_checked' => $checked,
            'broken_count' => count($broken),
            'redirect_count' => count($redirects),
            'working_count' => count($working),
            'internal_broken_count' => count($internal_broken),
            'broken' => array_values($broken),
            'redirects' => array_values($redirects),
            'internal_broken' => array_slice($internal_broken, 0, 20),
            'summary' => [
                'total_links_found' => array_sum(array_map(fn($pl) => count($pl['internal']) + count($pl['external']), $all_links)),
                'internal_total' => array_sum(array_map(fn($pl) => count($pl['internal']), $all_links)),
                'external_total' => array_sum(array_map(fn($pl) => count($pl['external']), $all_links)),
            ],
        ];
    }

    private function check_url($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AIC Link Checker/1.0');
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);
        curl_close($ch);
        if ($error || $code === 0) return 0;
        return (int) $code;
    }

    // ======================== SEO REPORT (SIMPLE) ========================

    public function seo_report($post_id = 0) {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => $post_id ? 1 : 50,
        ];
        if ($post_id) $args['p'] = $post_id;

        $query = new WP_Query($args);
        $reports = [];
        foreach ($query->posts as $post) {
            $r = $this->deep_analysis($post->ID);
            $reports[] = $r;
        }

        $summary = [
            'total' => count($reports),
            'avg_score' => count($reports) > 0 ? round(array_sum(array_column($reports, 'score')) / count($reports)) : 0,
            'grades' => ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0],
            'total_errors' => array_sum(array_column($reports, 'errors')),
            'total_warnings' => array_sum(array_column($reports, 'warnings')),
        ];
        foreach ($reports as $r) $summary['grades'][$r['grade']]++;

        return ['reports' => $reports, 'summary' => $summary];
    }

    // ======================== CONTENT ANALYSIS ========================

    public function content_analysis($post_id) {
        $post = get_post($post_id);
        if (!$post) return null;

        $content = apply_filters('the_content', $post->post_content);
        $plain = wp_strip_all_tags($post->post_content);
        $word_count = $this->count_words($plain);
        $sentences = max(1, preg_match_all('/[.!?؟]+/', $plain));
        $paragraphs = max(1, substr_count($plain, "\n\n") + 1);

        $readability = $this->flesch_score($plain);
        $headings = $this->extract_headings($content);
        $links = $this->extract_links($content);
        $images = $this->extract_images($content);
        $keywords = $this->analyze_keywords($plain);
        $suggestions = $this->generate_content_suggestions($word_count, $sentences, $readability, $headings, $links, $images, $keywords);

        return [
            'post_id' => $post_id,
            'title' => $post->post_title,
            'word_count' => $word_count,
            'sentence_count' => $sentences,
            'paragraph_count' => $paragraphs,
            'avg_words_per_sentence' => round($word_count / $sentences, 1),
            'readability_score' => $readability,
            'readability_label' => $this->get_readability_label($readability),
            'headings' => $headings,
            'heading_count' => count($headings),
            'links' => ['internal' => count($links['internal']), 'external' => count($links['external'])],
            'images' => ['total' => count($images), 'with_alt' => count(array_filter($images, fn($i) => $i['has_alt']))],
            'keywords' => $keywords,
            'suggestions' => $suggestions,
        ];
    }

    private function analyze_keyword_in_content($plain, $keyword, $title, $desc, $html) {
        if (!$keyword) return ['keyword' => '', 'total_words' => $this->count_words($plain)];

        $lower_plain = mb_strtolower($plain);
        $lower_kw = mb_strtolower($keyword);
        $total_words = max(1, $this->count_words($plain));

        $total_count = mb_substr_count($lower_plain, $lower_kw);
        $density = round(($total_count / $total_words) * 100, 2);

        // Placement checks
        $in_title = mb_stripos($title, $keyword) !== false;
        $in_desc = mb_stripos($desc, $keyword) !== false;

        $first_100 = mb_substr($plain, 0, 500);
        $in_first_paragraph = mb_stripos($first_100, $keyword) !== false;

        // Count in headings
        $headings_text = '';
        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $html, $hm);
        foreach ($hm[1] as $h) $headings_text .= ' ' . wp_strip_all_tags($h);
        $in_headings = mb_stripos(mb_strtolower($headings_text), $lower_kw) !== false;

        $optimal = $density >= 1 && $density <= 3;

        return [
            'keyword' => $keyword,
            'total_count' => $total_count,
            'density' => $density,
            'optimal' => $optimal,
            'status' => $optimal ? 'good' : ($density > 3 ? 'over' : 'under'),
            'placement' => [
                'in_title' => $in_title,
                'in_meta_desc' => $in_desc,
                'in_headings' => $in_headings,
                'in_first_paragraph' => $in_first_paragraph,
            ],
            'total_words' => $total_words,
        ];
    }

    // ======================== AI CONTENT GENERATION ========================

    public function generate_content($topic, $keywords = [], $length = 'medium', $lang = 'fa') {
        $keyword_str = !empty($keywords) ? implode(', ', $keywords) : $topic;
        $length_map = ['short' => 300, 'medium' => 600, 'long' => 1000];
        $target_words = $length_map[$length] ?? 600;
        $lang_instruction = $lang === 'fa' ? 'به زبان فارسی' : 'in English';

        return [
            'prompt' => "You are a professional SEO content writer. Write an article about: {$topic}\n\nRequirements:\n- Write {$lang_instruction}\n- Target {$target_words} words\n- Keywords: {$keyword_str}\n- Include H2/H3 headings\n- Include FAQ section\n- Optimize for search engines",
            'topic' => $topic,
            'keywords' => $keywords,
            'length' => $length,
            'target_words' => $target_words,
        ];
    }

    public function optimize_meta($post_id) {
        $post = get_post($post_id);
        if (!$post) return null;

        $content = wp_strip_all_tags($post->post_content);
        $title = $post->post_title;
        $seo_title = mb_strlen($title) > 55 ? mb_substr($title, 0, 55) . '...' : $title;
        $seo_title .= ' | ' . get_bloginfo('name');
        if (mb_strlen($seo_title) > 60) $seo_title = mb_substr($seo_title, 0, 57) . '...';

        $excerpt = wp_trim_words($content, 25);
        $seo_desc = mb_strlen($excerpt) > 160 ? mb_substr($excerpt, 0, 157) . '...' : $excerpt;

        update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_desc);
        update_post_meta($post_id, '_rank_math_title', $seo_title);
        update_post_meta($post_id, '_rank_math_description', $seo_desc);

        return ['title' => $seo_title, 'title_len' => mb_strlen($seo_title), 'desc' => $seo_desc, 'desc_len' => mb_strlen($seo_desc)];
    }

    // ======================== ROBOTS.TXT ========================

    public function get_robots_txt() {
        $file = ABSPATH . 'robots.txt';
        if (file_exists($file)) {
            return ['content' => file_get_contents($file), 'path' => $file, 'exists' => true];
        }
        return ['content' => $this->default_robots_txt(), 'path' => $file, 'exists' => false];
    }

    public function save_robots_txt($content) {
        $file = ABSPATH . 'robots.txt';
        $backup_dir = ABSPATH . '/.aic_backups';
        if (!is_dir($backup_dir)) @wp_mkdir_p($backup_dir);
        if (file_exists($file)) @copy($file, $backup_dir . '/robots.txt.' . date('Y-m-d_H-i-s') . '.bak');
        return @file_put_contents($file, $content) !== false;
    }

    public function default_robots_txt() {
        return "User-agent: *\nAllow: /\nDisallow: /wp-admin/\nSitemap: " . home_url('/sitemap.xml') . "\n";
    }

    // ======================== HELPERS ========================

    private function extract_headings($html) {
        $headings = [];
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $headings[] = ['level' => (int) $matches[1][$i], 'text' => wp_strip_all_tags($matches[2][$i])];
        }
        return $headings;
    }

    private function extract_links($html) {
        $links = ['internal' => [], 'external' => []];
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*?(?:rel=["\']([^"\']*)["\'])?[^>]*>(.*?)<\/a>/is', $html, $matches);
        $site_url = home_url();
        for ($i = 0; $i < count($matches[0]); $i++) {
            $url = $matches[1][$i];
            $rel = $matches[2][$i] ?? '';
            $text = wp_strip_all_tags($matches[3][$i]);
            if (strpos($url, $site_url) === 0 || (strpos($url, '/') === 0 && strpos($url, '//') !== 0)) {
                $links['internal'][] = ['url' => $url, 'text' => $text];
            } else {
                $links['external'][] = ['url' => $url, 'text' => $text, 'rel' => $rel];
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
            preg_match('/title=["\']([^"\']*)["\']/i', $img, $title);
            preg_match('/width=["\']?(\d+)["\']?/i', $img, $w);
            preg_match('/height=["\']?(\d+)["\']?/i', $img, $h);
            $images[] = [
                'src' => $src[1] ?? '', 'alt' => $alt[1] ?? '', 'title' => $title[1] ?? '',
                'has_alt' => !empty($alt[1]),
                'width' => isset($w[1]) ? (int) $w[1] : null,
                'height' => isset($h[1]) ? (int) $h[1] : null,
            ];
        }
        return $images;
    }

    private function count_words($text) {
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        return str_word_count(trim($text));
    }

    private function flesch_score($text) {
        $sentences = max(1, preg_match_all('/[.!?؟]+/', $text));
        $words = max(1, $this->count_words($text));
        $syllables = max(1, $this->count_syllables($text));
        return max(0, min(100, round(206.835 - 1.015 * ($words / $sentences) - 84.6 * ($syllables / $words))));
    }

    private function gunning_fog_score($text) {
        $sentences = max(1, preg_match_all('/[.!?؟]+/', $text));
        $words = max(1, $this->count_words($text));
        $complex = max(1, preg_match_all('/\b\w{7,}\b/', $text));
        return round(0.4 * ($words / $sentences + 100 * $complex / $words));
    }

    private function coleman_liau_score($text) {
        $sentences = max(1, preg_match_all('/[.!?؟]+/', $text));
        $words = max(1, $this->count_words($text));
        $letters = max(1, preg_match_all('/[\p{L}]/u', $text));
        $l = ($letters / $words) * 100;
        $s = ($sentences / $words) * 100;
        return max(0, min(100, round(0.0588 * $l - 0.296 * $s - 15.8)));
    }

    private function count_syllables($text) {
        $text = strtolower(preg_replace('/[^a-z\x{0600}-\x{06FF}]/u', '', $text));
        preg_match_all('/[aeiouy]/i', $text, $vowels);
        return max(1, count($vowels[0]));
    }

    private function analyze_keywords($text) {
        $stopwords = ['the','a','an','and','or','but','in','on','at','to','for','of','with','is','it','that','this','from','by','as','be','are','was','were','will','would','could','should','may','might','can','shall','has','have','had','do','does','did','not','no','so','if','then','than','too','very','just','این','از','با','برای','در','که','را','به','و','یا','اما','اگر','تا','هم','نه','بله','هر','همه','بیشتر','کمتر','خیلی','شود','شد','می','باید','کنند','کند','است','بود'];
        $words = preg_split('/\s+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $total = max(1, count($words));
        $freq = [];
        foreach ($words as $w) {
            $w = preg_replace('/[^\p{L}\p{N}]/u', '', $w);
            if (mb_strlen($w) < 3 || in_array($w, $stopwords)) continue;
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }
        arsort($freq);
        $keywords = [];
        foreach (array_slice($freq, 0, 20, true) as $word => $count) {
            $density = round(($count / $total) * 100, 2);
            $keywords[] = ['word' => $word, 'count' => $count, 'density' => $density, 'optimal' => $density >= 1 && $density <= 3, 'status' => $density > 3 ? 'over' : ($density >= 1 ? 'good' : 'under')];
        }
        return ['total_words' => $total, 'unique_words' => count($freq), 'keywords' => $keywords];
    }

    private function generate_content_suggestions($word_count, $sentences, $readability, $headings, $links, $images, $keywords) {
        $s = [];
        if ($word_count < 300) $s[] = ['priority' => 'high', 'type' => 'content', 'message' => "تعداد کلمات کم است ($word_count)", 'detail' => 'حداقل 300 کلمه توصیه می‌شود'];
        if ($sentences < 5) $s[] = ['priority' => 'medium', 'type' => 'content', 'message' => 'تعداد جملات کم است', 'detail' => 'حداقل 5 جمله نیاز است'];
        if ($readability < 40) $s[] = ['priority' => 'medium', 'type' => 'readability', 'message' => 'خوانایی پایین است', 'detail' => 'جملات را کوتاه‌تر بنویسید'];
        if (count($headings) < 3) $s[] = ['priority' => 'medium', 'type' => 'structure', 'message' => 'تگ‌های عنوان کم هستند', 'detail' => 'از H2/H3 استفاده کنید'];
        if (count($links['internal']) < 2) $s[] = ['priority' => 'medium', 'type' => 'links', 'message' => 'لینک داخلی کم است', 'detail' => 'حداقل 2 لینک داخلی اضافه کنید'];
        if (count($images) === 0) $s[] = ['priority' => 'medium', 'type' => 'media', 'message' => 'تصویری وجود ندارد', 'detail' => 'حداقل یک تصویر اضافه کنید'];
        return $s;
    }

    private function get_readability_label($score) {
        if ($score >= 80) return 'خیلی آسان';
        if ($score >= 60) return 'آسان';
        if ($score >= 40) return 'متوسط';
        if ($score >= 20) return 'سخت';
        return 'خیلی سخت';
    }

    private function get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return trim($_SERVER['HTTP_CLIENT_IP']);
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
