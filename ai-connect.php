<?php
/**
 * Plugin Name: AI Connect Pro
 * Description: اتصال کامل هوش مصنوعی به وردپرس - مدیریت همه چیز | Complete AI Integration for WordPress
 * Version: 8.4.0
 * Author: Deioces120
 * Text Domain: ai-connect
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('AIC_VERSION', '8.4.0');
define('AIC_PLUGIN_DIR', plugin_dir_path(__FILE__));

register_activation_hook(__FILE__, function () {
    if (!get_option('aic_api_key')) update_option('aic_api_key', wp_generate_password(64, false));
    update_option('aic_enabled', '1');
    update_option('aic_allowed_ips', '');
    update_option('aic_rate_limit', 300);

    $src = AIC_PLUGIN_DIR . 'theme';
    $dst = get_theme_root() . '/ai-connect-theme';
    if (!is_dir($dst) && is_dir($src)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) {
            $t = $dst . '/' . substr($f->getPathname(), strlen($src) + 1);
            if ($f->isDir()) { if (!is_dir($t)) @mkdir($t, 0755, true); } else @copy($f->getPathname(), $t);
        }
    }
    if (is_dir($dst)) switch_theme('ai-connect-theme');
    if (class_exists('WooCommerce')) {
        $sid = get_option('woocommerce_shop_page_id');
        if (!$sid || !get_post($sid)) {
            $sid = wp_insert_post(['post_title' => 'فروشگاه', 'post_name' => 'shop', 'post_status' => 'publish', 'post_type' => 'page']);
            update_option('woocommerce_shop_page_id', $sid);
        }
    }
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () { flush_rewrite_rules(); });

require_once AIC_PLUGIN_DIR . 'includes/auth.php';
require_once AIC_PLUGIN_DIR . 'includes/api.php';
require_once AIC_PLUGIN_DIR . 'includes/seo-features.php';
require_once AIC_PLUGIN_DIR . 'languages/i18n.php';
AIC_API::get_instance();
AIC_SEO_Features::get_instance();
AIC_i18n::get_instance();

add_action('admin_menu', function () {
    add_menu_page('AI Connect', 'AI Connect', 'manage_options', 'ai-connect', 'aic_page_dashboard', 'dashicons-networking', 80);
    add_submenu_page('ai-connect', aic_t('menu_dashboard'), aic_t('menu_dashboard'), 'manage_options', 'ai-connect', 'aic_page_dashboard');
    add_submenu_page('ai-connect', aic_t('menu_api'), aic_t('menu_api'), 'manage_options', 'aic-api', 'aic_page_api');
    add_submenu_page('ai-connect', aic_t('menu_seo'), aic_t('menu_seo'), 'manage_options', 'aic-seo', 'aic_page_seo');
    add_submenu_page('ai-connect', aic_t('menu_analytics'), aic_t('menu_analytics'), 'manage_options', 'aic-analytics', 'aic_page_analytics');
    add_submenu_page('ai-connect', aic_t('menu_content'), aic_t('menu_content'), 'manage_options', 'aic-content', 'aic_page_content');
    add_submenu_page('ai-connect', aic_t('menu_security'), aic_t('menu_security'), 'manage_options', 'aic-security', 'aic_page_security');
    add_submenu_page('ai-connect', aic_t('menu_woocommerce'), aic_t('menu_woocommerce'), 'manage_options', 'aic-woocommerce', 'aic_page_woocommerce');
    add_submenu_page('ai-connect', aic_t('menu_agent'), aic_t('menu_agent'), 'manage_options', 'aic-agent', 'aic_page_agent');
    add_submenu_page('ai-connect', aic_t('language'), aic_t('language'), 'manage_options', 'aic-language', 'aic_page_language');
});

add_action('admin_init', function () {
    if (isset($_POST['aic_save']) && check_admin_referer('aic_settings')) {
        $tab = sanitize_text_field($_POST['aic_tab'] ?? '');
        if ($tab === 'api') {
            update_option('aic_enabled', sanitize_text_field($_POST['aic_enabled']));
            update_option('aic_allowed_ips', sanitize_text_field($_POST['aic_allowed_ips']));
            update_option('aic_rate_limit', (int) $_POST['aic_rate_limit']);
            if (!empty($_POST['aic_regenerate_key'])) update_option('aic_api_key', wp_generate_password(64, false));
        } elseif ($tab === 'seo') {
            update_option('aic_seo_auto_meta', isset($_POST['aic_seo_auto_meta']) ? '1' : '0');
            update_option('aic_seo_analyze_products', isset($_POST['aic_seo_analyze_products']) ? '1' : '0');
        } elseif ($tab === 'ai_agent') {
            update_option('aic_ai_api_url', sanitize_url($_POST['aic_ai_api_url'] ?? ''));
            update_option('aic_ai_api_key', sanitize_text_field($_POST['aic_ai_api_key'] ?? ''));
            update_option('aic_ai_model', sanitize_text_field($_POST['aic_ai_model'] ?? 'gpt-4o-mini'));
        } elseif ($tab === 'security') {
            update_option('aic_log_requests', isset($_POST['aic_log_requests']) ? '1' : '0');
            update_option('aic_block_bad_bots', isset($_POST['aic_block_bad_bots']) ? '1' : '0');
        } elseif ($tab === 'agent') {
            $history = get_option('aic_agent_history', []);
            $action = sanitize_text_field($_POST['aic_agent_action'] ?? '');
            $detail = sanitize_textarea_field($_POST['aic_agent_detail'] ?? '');
            if ($action) {
                $history[] = ['time' => current_time('mysql'), 'action' => $action, 'detail' => $detail, 'user' => wp_get_current_user()->display_name];
                if (count($history) > 200) $history = array_slice($history, -200);
                update_option('aic_agent_history', $history);
            }
        } elseif ($tab === 'language') {
            $lang = sanitize_text_field($_POST['aic_language'] ?? 'fa');
            if (in_array($lang, ['fa', 'en'])) {
                AIC_i18n::get_instance()->set_lang($lang);
            }
        }
        add_action('admin_notices', function () { echo '<div class="notice notice-success is-dismissible"><p>' . aic_t('settings_saved') . '</p></div>'; });
    }
});

// ======================== AI ANALYSIS AJAX ========================
add_action('wp_ajax_aic_ai_analyze', function () {
    check_ajax_referer('aic_ajax', 'nonce');

    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error('No post ID');

    $ai_url = get_option('aic_ai_api_url', '');
    $ai_key = get_option('aic_ai_api_key', '');
    $ai_model = get_option('aic_ai_model', 'gpt-4o-mini');

    if (empty($ai_url) || empty($ai_key)) {
        wp_send_json_error('AI API not configured. Go to Settings and set your AI API URL and Key.');
    }

    // Fetch raw data
    $raw = rest_do_request(new WP_REST_Request('GET', '/ai-connect/v1/seo/raw-data/' . $post_id));
    $data = $raw->get_data();
    if (!$data) wp_send_json_error('Failed to fetch page data');

    $page_data = $data['raw_for_ai'] ?? $data;

    $prompt = "شما یک متخصص سئو و تولید محتوا هستید. این داده‌های یک صفحه وبسایت رو تحلیل کنید:

عنوان صفحه: {$page_data['page_title']}
عنوان متا: {$page_data['meta_title']}
توضیحات متا: {$page_data['meta_description']}
محتوای متنی: {$page_data['full_content']}
تعداد کلمات: {$page_data['word_count']}
هدینگ‌ها: " . implode(', ', $page_data['headings_list']) . "
تصاویر: {$page_data['images_count']} (بدون alt: {$page_data['images_without_alt']})
لینک داخلی: {$page_data['internal_links_count']}
لینک خارجی: {$page_data['external_links_count']}
نام سایت: {$page_data['site_name']}

لطفاً:
1. محتوا رو واقعاً بخونید و کیفیتش رو قضاوت کنید
2. عنوان و متا رو بررسی کنید
3. ساختار محتوا رو ارزیابی کنید
4. نمره 0-100 بدید
5. گرید A/B/C/D بدید
6. 5 پیشنهاد عملیاتی و دقیق بدهید

خروجی رو به صورت JSON برگردونید:
{\"score\": number, \"grade\": \"A/B/C/D\", \"analysis\": \"توضیح کامل تحلیل شما به فارسی\", \"suggestions\": [\"پیشنهاد 1\", \"پیشنهاد 2\", ...]}";

    // Call AI API
    $response = wp_remote_post($ai_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $ai_key,
        ],
        'body' => json_encode([
            'model' => $ai_model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ]),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('AI API Error: ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!$body || !isset($body['choices'][0]['message']['content'])) {
        wp_send_json_error('Invalid AI response');
    }

    $ai_response = $body['choices'][0]['message']['content'];

    // Try to parse JSON from response
    $json_match = null;
    if (preg_match('/\{[\s\S]*\}/', $ai_response, $json_match)) {
        $analysis = json_decode($json_match[0], true);
    }

    if (!$analysis) {
        $analysis = [
            'score' => 50,
            'grade' => 'C',
            'analysis' => $ai_response,
            'suggestions' => [],
        ];
    }

    // Save analysis
    $save_data = [
        'score' => (int) ($analysis['score'] ?? 50),
        'grade' => $analysis['grade'] ?? 'C',
        'analysis' => $analysis['analysis'] ?? $ai_response,
        'suggestions' => $analysis['suggestions'] ?? [],
        'agent' => 'ai-api',
        'time' => current_time('mysql'),
    ];
    update_post_meta($post_id, '_aic_ai_analysis', $save_data);
    update_post_meta($post_id, '_aic_ai_score', $save_data['score']);
    update_post_meta($post_id, '_aic_ai_grade', $save_data['grade']);

    wp_send_json_success($save_data);
});

// ======================== SEO REFRESH AJAX ========================
add_action('wp_ajax_aic_refresh_seo', function () {
    check_ajax_referer('aic_ajax', 'nonce');

    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error('No post ID');

    $seo = AIC_SEO_Features::get_instance();
    $result = $seo->deep_analysis($post_id);
    if (!$result) wp_send_json_error('Could not analyze post');

    $post = get_post($post_id);
    $plain = wp_strip_all_tags($post->post_content);
    $title = $result['title_analysis']['meta_title'];
    $desc = $result['meta_analysis']['description'];
    $wc = $result['content_analysis']['word_count'];
    $flesch = $result['content_analysis']['readability_flesch'];
    $fog = $result['content_analysis']['readability_fog'];
    $h1 = $result['heading_analysis']['h1_count'];
    $h2 = $result['heading_analysis']['h2_count'];
    $img_total = $result['image_analysis']['total'];
    $img_no_alt = $result['image_analysis']['without_alt'];
    $int_links = $result['link_analysis']['internal'];
    $ext_links = $result['link_analysis']['external'];
    $slug = $result['url_analysis']['slug'];
    $slug_len = $result['url_analysis']['slug_length'];
    $has_featured = !empty($result['featured_analysis']['has_featured'] ?? false);
    $has_schema = !empty($result['schema_analysis']['has_schema'] ?? false);
    $has_og = !empty($result['og_analysis']['has_og'] ?? false);
    $issues = $result['issues'];
    $passed = $result['passed'];
    $critical_count = count(array_filter($issues, fn($i) => $i['severity'] === 'critical'));
    $warning_count = count(array_filter($issues, fn($i) => $i['severity'] === 'warning'));
    $score = $result['score'];

    $analysis_text = "";

    // --- Section 1: Expert Opinion (نگاه کلی کارشناس) ---
    $analysis_text .= "### 🧠 نگاه کلی کارشناس\n";
    $analysis_text .= "**عنوان صفحه:** {$title}\n";
    $analysis_text .= "**تعداد کلمات محتوا:** {$wc}\n\n";

    $analysis_text .= "**نقطه نظر کارشناسی:**\n";

    if ($wc < 50) {
        $analysis_text .= "- این صفحه عملاً محتوای قابل‌ارزیابی ندارد. با {$wc} کلمه، گوگل این صفحه را به‌عنوان thin content شناسایی می‌کند و رتبه‌ای به آن نمی‌دهد. باید حداقل ۳۰۰ کلمه محتوای مفید و مرتبط با موضوع صفحه بنویسید.\n";
    } elseif ($wc < 150) {
        $analysis_text .= "- محتوا بسیار کم است ({$wc} کلمه). این صفحه برای رقابت در نتایج جستجو کافی نیست. رقبای شما مقالات ۱۰۰۰+ کلمه‌ای دارند. محتوا را至少 به ۳۰۰ کلمه افزایش دهید.\n";
    } elseif ($wc < 300) {
        $analysis_text .= "- محتوا کوتاه است ({$wc} کلمه). برای صفحات فرود یا مقالات سئو، حداقل ۳۰۰ کلمه نیاز است. اگر این صفحه محصول است، توضیحات بیشتری اضافه کنید.\n";
    } elseif ($wc < 800) {
        $analysis_text .= "- محتوا در سطح قابل‌قبولی است ({$wc} کلمه). برای بهبود رتبه، اضافه کردن ۲۰۰-۵۰۰ کلمه محتوای تکمیلی توصیه می‌شود.\n";
    } else {
        $analysis_text .= "- محتوای خوبی دارید ({$wc} کلمه). این تعداد برای سئو مناسب است.\n";
    }

    if ($title) {
        $title_len = mb_strlen($title);
        if ($title_len < 20) {
            $analysis_text .= "- عنوان «{$title}» خیلی کوتاه و مبهم است. کاربر وقتی این عنوان را در گوگل ببیند، نمی‌فهمد صفحه درباره چیست. عنوان باید دقیق، جذاب و ۳۰-۶۰ کاراکتری باشد.\n";
        } elseif ($title_len > 65) {
            $analysis_text .= "- عنوان «{$title}» خیلی بلند است ({$title_len} کاراکتر). گوگل آن را بریده نشان می‌دهد. عنوان را به زیر ۶۰ کاراکتر برسانید.\n";
        } else {
            $analysis_text .= "- عنوان «{$title}» از نظر طول مناسب است ({$title_len} کاراکتر).\n";
        }
    } else {
        $analysis_text .= "- ⚠️ این صفحه عنوان متا ندارد. عنوان متا مهم‌ترین عامل سئوی On-Page است. فوراً یک عنوان ۳۰-۶۰ کاراکتری تنظیم کنید.\n";
    }

    if (empty($desc)) {
        $analysis_text .= "- توضیحات متا (Meta Description) وجود ندارد. گوگل به‌صورت خودکار بخشی از محتوا را نشان می‌دهد که ممکن است نامرتبط باشد. یک توضیح ۱۲۰-۱۶۰ کاراکتری بنویسید که کاربر را ترغیب به کلیک کند.\n";
    } elseif (mb_strlen($desc) < 80) {
        $analysis_text .= "- توضیحات متا خیلی کوتاه است (".mb_strlen($desc)." کاراکتر). از فضای موجود استفاده کنید و توضیح کامل‌تری بنویسید.\n";
    }

    $analysis_text .= "\n";

    // --- Section 2: Content Quality (کیفیت محتوا) ---
    $analysis_text .= "### 📄 بررسی کیفیت محتوا\n";

    if ($flesch > 70) {
        $analysis_text .= "- **خوانایی عالی** (Flesch: {$flesch}): محتوا ساده و قابل‌فهم است. مخاطب عام می‌تواند آن را بخواند.\n";
    } elseif ($flesch > 50) {
        $analysis_text .= "- **خوانایی متوسط** (Flesch: {$flesch}): محتوا نسبتاً خوانا است. جملات را می‌توان کمی ساده‌تر کرد.\n";
    } elseif ($flesch > 30) {
        $analysis_text .= "- **خوانایی ضعیف** (Flesch: {$flesch}): محتوا پیچیده است. جملات طولانی و کلمات تخصصی زیادی دارد. سعی کنید جملات را کوتاه‌تر و ساده‌تر بنویسید.\n";
    } else {
        $analysis_text .= "- **خوانایی بسیار ضعیف** (Flesch: {$flesch}): محتوا تقریباً غیرقابل‌فهم است. جملات بسیار طولانی هستند. حداکثر ۲۰ کلمه در هر جمله بنویسید.\n";
    }

    if ($fog > 20) {
        $analysis_text .= "- **Gunning Fog: {$fog}** — محتوا بسیار پیچیده است و فقط مخاطبان متخصص می‌توانند آن را بفهمند.\n";
    } elseif ($fog > 12) {
        $analysis_text .= "- **Gunning Fog: {$fog}** — سطح پیچیدگی متوسط. برای محتوای عمومی بهتر است زیر ۱۲ باشد.\n";
    } else {
        $analysis_text .= "- **Gunning Fog: {$fog}** — سطح پیچیدگی مناسب.\n";
    }

    $first_200 = mb_substr($plain, 0, 500);
    if (mb_strlen(trim($first_200)) < 100) {
        $analysis_text .= "- ⚠️ **پاراگراف اول بسیار کوتاه است.** پاراگراف اول صفحه مهم‌ترین بخش محتواست. گوگل و کاربر از همینجا متوجه موضوع صفحه می‌شوند. حداقل ۱۰۰ کلمه در پاراگراف اول بنویسید.\n";
    }

    $analysis_text .= "\n";

    // --- Section 3: Structure (ساختار صفحه) ---
    $analysis_text .= "### 🏗️ بررسی ساختار صفحه\n";

    if ($h1 === 0) {
        $analysis_text .= "- 🔴 **تگ H1 وجود ندارد.** H1 مهم‌ترین تگ ساختاری صفحه است و گوگل از آن برای فهمیدن موضوع اصلی صفحه استفاده می‌کند. حتماً یک H1 منحصربه‌فرد اضافه کنید.\n";
    } elseif ($h1 > 1) {
        $analysis_text .= "- ⚠️ **{$h1} تگ H1 وجود دارد.** هر صفحه باید فقط یک H1 داشته باشد. H1 اضافی را به H2 تبدیل کنید.\n";
    } else {
        $analysis_text .= "- ✅ تگ H1 به‌درستی وجود دارد.\n";
    }

    if ($h2 === 0 && $wc > 300) {
        $analysis_text .= "- ⚠️ **بدون تگ H2.** محتوای {$wc} کلمه‌ای بدون ساختار هدینگ، هم برای کاربر و هم برای گوگل نامفهوم است. محتوا را به بخش‌های ۲۰۰-۳۰۰ کلمه‌ای با H2 تقسیم کنید.\n";
    } elseif ($h2 >= 3) {
        $analysis_text .= "- ✅ ساختار هدینگ خوب است ({$h2} تگ H2).\n";
    }

    $headings = $result['heading_analysis']['structure'] ?? [];
    if (!empty($headings)) {
        $bad_headings = array_filter($headings, function($h) use ($plain) {
            $text = mb_strtolower(trim($h['text']));
            return mb_strlen($h['text']) < 3 || in_array($text, ['', 'test', 'test1', 'test2', 'عنوان', 'here', 'click']);
        });
        if (!empty($bad_headings)) {
            $analysis_text .= "- ⚠️ **هدینگ‌های نامناسب:** " . count($bad_headings) . " هدینگ متن معناداری ندارند. هدینگ‌ها باید توصیفی و مرتبط با محتوای زیرشان باشند.\n";
        }
    }

    $analysis_text .= "\n";

    // --- Section 4: Images & Links (تصاویر و لینک‌ها) ---
    $analysis_text .= "### 🖼️ بررسی تصاویر و لینک‌ها\n";

    if ($img_total === 0) {
        $analysis_text .= "- ⚠️ **هیچ تصویری در محتوا وجود ندارد.** تصاویر هم تجربه کاربری را بهبود می‌دهند و هم فرصت سئو (از طریق alt text) فراهم می‌کنند.\n";
    } else {
        if ($img_no_alt > 0) {
            $analysis_text .= "- ⚠️ **{$img_no_alt} تصویر بدون alt text.** Alt text به گوگل کمک می‌کند محتوای تصویر را بفهمد. به همه تصاویر alt توصیفی اضافه کنید.\n";
        } else {
            $analysis_text .= "- ✅ همه تصاویر alt text دارند.\n";
        }
    }

    if ($int_links === 0 && $ext_links === 0) {
        $analysis_text .= "- ⚠️ **هیچ لینکی در محتوا وجود ندارد.** لینک‌های داخلی به گوگل کمک می‌کنند ساختار سایت را بفهمد و صفحات مهم‌تر را شناسایی کند. حداقل ۲-۳ لینک داخلی به صفحات مرتبط اضافه کنید.\n";
    } else {
        if ($int_links === 0) {
            $analysis_text .= "- ⚠️ لینک داخلی وجود ندارد. صفحات داخلی سایت را به هم لینک کنید.\n";
        }
        if ($ext_links > 5) {
            $analysis_text .= "- ⚠️ لینک‌های خارجی زیاد هستند ({$ext_links}). هر لینک خارجی اعتبار صفحه را کاهش می‌دهد. لینک‌های غیرضروری را حذف کنید یا nofollow بگذارید.\n";
        }
    }

    if (!$has_featured) {
        $analysis_text .= "- ⚠️ **تصویر شاخص ندارد.** تصویر شاخص در شبکه‌های اجتماعی و نتایج جستجو نمایش داده می‌شود.\n";
    }

    if (!$has_schema) {
        $analysis_text .= "- ⚠️ **داده ساختاریافته (Schema) ندارد.** اضافه کردن Schema به گوگل کمک می‌کند اطلاعات صفحه را بهتر نمایش دهد (Rich Snippets).\n";
    }

    if (!$has_og) {
        $analysis_text .= "- ⚠️ **OG Tags ندارد.** وقتی لینک این صفحه در شبکه‌های اجتماعی به اشتراک گذاشته شود، عنوان و تصویر نادرستی نمایش داده می‌شود.\n";
    }

    $analysis_text .= "\n";

    // --- Section 5: URL & Technical ---
    $analysis_text .= "### ⚙️ بررسی فنی\n";

    if ($slug_len > 60) {
        $analysis_text .= "- ⚠️ Slug خیلی بلند است ({$slug_len} کاراکتر). URL کوتاه‌تر خوانایی بهتری دارد.\n";
    }

    if (preg_match('/\d{4,}/', $slug)) {
        $analysis_text .= "- ⚠️ Slug شامل اعداد طولانی است. URL‌های خوانا بهتر رتبه می‌گیرند.\n";
    }

    if ($result['content_analysis']['duplicate_count'] > 0) {
        $analysis_text .= "- 🔴 **محتوای تکراری!** {$result['content_analysis']['duplicate_count']} صفحه دیگر محتوای مشابه دارند. محتوای تکراری به‌شدت به سئو آسیب می‌زند.\n";
    }

    $analysis_text .= "\n";

    // --- Section 6: Priority Actions (اولویت‌بندی اقدامات) ---
    $analysis_text .= "### 🎯 اولویت‌بندی اقدامات\n";

    $actions = [];
    if ($critical_count > 0) {
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'critical') {
                $actions[] = "🔴 **فوری:** {$issue['message']} — {$issue['fix']}";
            }
        }
    }
    if ($wc < 300) {
        $actions[] = "📝 **مهم:** محتوا را به حداقل ۳۰۰ کلمه افزایش دهید";
    }
    foreach ($issues as $issue) {
        if ($issue['severity'] === 'warning' && count($actions) < 8) {
            $actions[] = "⚠️ {$issue['message']} — {$issue['fix']}";
        }
    }
    if (empty($actions)) {
        $actions[] = "✅ صفحه وضعیت خوبی دارد. فقط بهینه‌سازی‌های جزئی نیاز است.";
    }
    foreach (array_slice($actions, 0, 10) as $idx => $action) {
        $analysis_text .= ($idx + 1) . ". {$action}\n";
    }

    $grade_label = $score >= 80 ? 'عالی' : ($score >= 60 ? 'خوب' : ($score >= 40 ? 'نیاز به بهبود' : 'ضعیف'));
    $save_data = [
        'score' => $score,
        'grade' => $result['grade'],
        'analysis' => $analysis_text,
        'agent' => 'aic-agent',
        'time' => current_time('mysql'),
    ];
    update_post_meta($post_id, '_aic_ai_analysis', $save_data);
    update_post_meta($post_id, '_aic_ai_score', $score);
    update_post_meta($post_id, '_aic_ai_grade', $result['grade']);

    wp_send_json_success([
        'score' => $score,
        'grade' => $result['grade'],
        'grade_label' => $grade_label,
        'analysis' => $analysis_text,
        'time' => $save_data['time'],
        'issues' => $result['issues'],
        'passed' => $result['passed'],
        'title_analysis' => $result['title_analysis'],
        'meta_analysis' => $result['meta_analysis'],
        'heading_analysis' => $result['heading_analysis'],
        'content_analysis' => $result['content_analysis'],
        'image_analysis' => $result['image_analysis'],
        'link_analysis' => $result['link_analysis'],
        'url_analysis' => $result['url_analysis'],
        'keyword_analysis' => $result['keyword_analysis'],
        'date' => $result['date'],
        'modified' => $result['modified'],
    ]);
});

// ======================== CONTENT GENERATION AJAX ========================
add_action('wp_ajax_aic_generate_content', function () {
    check_ajax_referer('aic_ajax', 'nonce');

    $topic = sanitize_textarea_field($_POST['topic'] ?? '');
    $related_post_id = (int) ($_POST['related_post_id'] ?? 0);
    $instructions = sanitize_textarea_field($_POST['instructions'] ?? '');
    $word_limit = (int) ($_POST['word_limit'] ?? 500);
    $content_type = sanitize_text_field($_POST['content_type'] ?? 'article');
    $include_images = !empty($_POST['include_images']);

    if (empty($topic) && empty($instructions)) {
        wp_send_json_error('موضوع یا دستورالعمل وارد کنید');
    }

    $context_data = '';
    if ($related_post_id) {
        $post = get_post($related_post_id);
        if ($post) {
            $plain = wp_strip_all_tags($post->post_content);
            $context_data = "صفحه مرتبط: {$post->post_title}\nمحتوای فعلی: " . mb_substr($plain, 0, 1000) . "\n\n";
        }
    }

    $all_posts = get_posts(['post_type' => ['post', 'page'], 'posts_per_page' => 10, 'post_status' => 'publish', 'orderby' => 'modified', 'order' => 'DESC']);
    $site_context = "نام سایت: " . get_bloginfo('name') . "\nتوضیحات سایت: " . get_bloginfo('description') . "\n\nآخرین صفحات سایت:\n";
    foreach ($all_posts as $p) {
        $site_context .= "- {$p->post_title} ({$p->post_type})\n";
    }

    $type_labels = ['article' => 'مقاله', 'product_desc' => 'توضیحات محصول', 'landing' => 'لندینگ پیج', 'faq' => 'سوالات متداول', 'service' => 'توضیحات خدمات'];
    $type_label = $type_labels[$content_type] ?? 'مقاله';

    $prompt = "شما یک متخصص حرفه‌ای تولید محتوا و سئو هستید.\n\n";
    $prompt .= "## اطلاعات سایت\n{$site_context}\n";
    if ($context_data) $prompt .= "## اطلاعات صفحه مرتبط\n{$context_data}\n";
    $prompt .= "## درخواست کاربر\n";
    $prompt .= "- موضوع: {$topic}\n";
    $prompt .= "- نوع محتوا: {$type_label}\n";
    $prompt .= "- تعداد کلمات مورد نظر: حدود {$word_limit} کلمه\n";
    if ($instructions) $prompt .= "- دستورالعمل‌های خاص کاربر: {$instructions}\n";
    $prompt .= "\n## قوانین تولید محتوا\n";
    $prompt .= "1. محتوا باید کاملاً اصیل و یکتا باشد\n";
    $prompt .= "2. ساختار HTML داشته باشد (H2, H3, ul, li, strong, p)\n";
    $prompt .= "3. عنوان جذاب و سئو فرندلی داشته باشد\n";
    $prompt .= "4. پاراگراف اول خلاصه کل مقاله باشد\n";
    $prompt .= "5. جملات کوتاه و خوانا باشند\n";
    $prompt .= "6. خلاصه یا نتیجه‌گیری در انتها داشته باشد\n";
    if ($include_images) {
        $prompt .= "7. برای هر بخش اصلی یک پیشنهاد تصویر: [IMAGE: توضیح | alt text]\n";
    }
    $prompt .= "\nخروجی: فقط HTML محتوا را بنویس.";

    $generated = '';
    $used_agent = false;

    // Method 1: Try external AI API
    $ai_url = get_option('aic_ai_api_url', '');
    $ai_key = get_option('aic_ai_api_key', '');
    $ai_model = get_option('aic_ai_model', 'gpt-4o-mini');

    if (!empty($ai_url) && !empty($ai_key)) {
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

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body && isset($body['choices'][0]['message']['content'])) {
                $generated = trim($body['choices'][0]['message']['content']);
                $used_agent = true;
            }
        }
    }

    // Method 2: Try plugin's own queue (agent connected via API)
    if (empty($generated)) {
        $queue = get_option('aic_content_queue', []);
        $request_id = 'req_' . time() . '_' . wp_rand(1000, 9999);
        $task = [
            'id' => $request_id,
            'status' => 'pending',
            'topic' => $topic,
            'instructions' => $instructions,
            'content_type' => $type_label,
            'content_type_key' => $content_type,
            'word_limit' => $word_limit,
            'related_post_id' => $related_post_id,
            'include_images' => $include_images,
            'prompt' => $prompt,
            'created_at' => current_time('mysql'),
            'agent' => '',
            'completed_at' => '',
        ];
        $queue[$request_id] = $task;
        update_option('aic_content_queue', $queue);

        // Try to find and process with any connected agent
        $agent_url = get_option('aic_agent_url', '');
        if (!empty($agent_url)) {
            $resp = wp_remote_post(rtrim($agent_url, '/') . '/seo/content-generate', [
                'headers' => ['Content-Type' => 'application/json', 'X-API-Key' => get_option('aic_api_key', '')],
                'body' => json_encode(['prompt' => $prompt, 'word_limit' => $word_limit, 'request_id' => $request_id]),
                'timeout' => 120,
            ]);
            if (!is_wp_error($resp)) {
                $rdata = json_decode(wp_remote_retrieve_body($resp), true);
                if (!empty($rdata['content'])) {
                    $generated = $rdata['content'];
                    $used_agent = true;
                }
            }
        }
    }

    // Method 3: Generate structured content as fallback
    if (empty($generated)) {
        $generated = aic_generate_structured_content($topic, $instructions, $content_type, $word_limit, $include_images, $related_post_id);
    }

    $images = [];
    if ($include_images) {
        preg_match_all('/\[IMAGE:\s*(.+?)\s*\|\s*(.+?)\s*\]/i', $generated, $img_matches);
        for ($i = 0; $i < count($img_matches[0]); $i++) {
            $images[] = ['description' => $img_matches[1][$i], 'alt' => $img_matches[2][$i]];
        }
        $generated = preg_replace('/\[IMAGE:[^\]]*\]/i', '', $generated);
    }

    $actual_words = str_word_count(strip_tags($generated));

    $history = get_option('aic_content_history', []);
    $history[] = [
        'time' => current_time('mysql'),
        'topic' => $topic,
        'type' => $content_type,
        'words' => $actual_words,
        'target' => $word_limit,
        'related_post' => $related_post_id,
        'images' => count($images),
        'method' => $used_agent ? 'ai-agent' : 'structured',
    ];
    if (count($history) > 50) $history = array_slice($history, -50);
    update_option('aic_content_history', $history);

    wp_send_json_success([
        'content' => $generated,
        'word_count' => $actual_words,
        'target_words' => $word_limit,
        'images' => $images,
        'topic' => $topic,
        'type' => $content_type,
        'time' => current_time('mysql'),
        'method' => $used_agent ? 'ai-agent' : 'structured',
    ]);
});

function aic_generate_structured_content($topic, $instructions, $content_type, $word_limit, $include_images, $related_post_id = 0) {
    $site_name = get_bloginfo('name');
    $intro_lines = [];
    $body_sections = [];
    $contact_info = [];

    // Extract site name from instructions
    $name_patterns = ['/سایت\s+(?:رسمی\s+)?([\w\s]+)\s+هست/i', '/سایت\s+([\w\s]+)\s+است/i', '/نام\s+([\w\s]+)\s+هست/i'];
    foreach ($name_patterns as $np) {
        if (preg_match($np, $instructions, $nm)) {
            $found_name = trim($nm[1]);
            if (mb_strlen($found_name) > 2 && mb_strlen($found_name) < 40) {
                $site_name = $found_name;
                break;
            }
        }
    }

    $inst_lower = mb_strtolower($instructions);
    $topic_lower = mb_strtolower($topic);

    // Extract contact info from instructions
    if (preg_match_all('/تلگرام\s*[:：]?\s*@[:：]?\s*(\w+)/i', $instructions, $tm)) {
        $contact_info['telegram'] = $tm[1];
    }
    if (preg_match_all('/اینستاگرام\s*[:：]?\s*@?[:：]?\s*(\w+)/i', $instructions, $igm)) {
        $contact_info['instagram'] = $igm[1];
    }
    if (preg_match_all('/(0\d{10}|\+98\d{10})/', $instructions, $phm)) {
        $contact_info['phone'] = $phm[0];
    }
    if (preg_match_all('/[\w.+-]+@[\w-]+\.[\w.]+/', $instructions, $em)) {
        $contact_info['email'] = $em[0];
    }
    if (preg_match_all('/(https?:\/\/[^\s]+)/', $instructions, $wm)) {
        $contact_info['website'] = $wm[0];
    }

    // Detect topic nature from instructions
    $is_movie_site = (mb_strpos($inst_lower, 'فیلم') !== false || mb_strpos($inst_lower, 'movie') !== false || mb_strpos($inst_lower, 'هندی') !== false);
    $is_contact_page = (mb_strpos($topic_lower, 'ارتباط') !== false || mb_strpos($topic_lower, 'تماس') !== false || mb_strpos($topic_lower, 'contact') !== false);
    $is_about_page = (mb_strpos($topic_lower, 'درباره') !== false || mb_strpos($topic_lower, 'about') !== false);
    $is_services = (mb_strpos($topic_lower, 'خدمات') !== false || mb_strpos($topic_lower, 'service') !== false);

    // Build intro based on actual instructions
    $first_sentence = "در {$site_name}، ما تلاش می‌کنیم بهترین تجربه را برای کاربرانمان فراهم کنیم.";
    if (!empty($instructions)) {
        $inst_clean = trim(preg_replace('/\n+/', ' ', $instructions));
        $first_sentence = "{$inst_clean}";
    }
    $intro_lines[] = "<p>{$first_sentence}</p>";

    if ($is_movie_site) {
        $intro_lines[] = "<p>این سایت مرجع تخصصی فیلم‌های هندی با زیرنویس فارسی است. ما مجموعه‌ای بی‌نظیر از بهترین فیلم‌های بالیوود و سینمای هند را با کیفیت بالا و لینک دانلود مستقیم در اختیار شما قرار می‌دهیم.</p>";
    } elseif ($is_contact_page) {
        $intro_lines[] = "<p>ما همیشه آماده شنیدن صدای شما هستیم. از طریق راه‌های ارتباطی زیر می‌توانید با ما در تماس باشید.</p>";
    } elseif ($is_about_page) {
        $intro_lines[] = "<p>{$site_name} با هدف ارائه بهترین خدمات به کاربران خود راه‌اندازی شده و همواره تلاش می‌کند تا نیازهای مخاطبانش را به بهترین شکل ممکن برآورده کند.</p>";
    } else {
        $intro_lines[] = "<p>در ادامه با جزئیات بیشتری درباره {$topic} آشنا خواهید شد.</p>";
    }

    // Build body sections based on content type and topic
    if ($content_type === 'faq') {
        $body_sections[] = '<h2>常见 سوالات متداول</h2>';
        $faq_items = [
            ["چطور می‌توانم از {$site_name} استفاده کنم؟", "کافی است وارد سایت شوید و از بخش‌های مختلف دیدن کنید. همه چیز ساده و در دسترس است."],
            ["آیا استفاده از {$site_name} رایگان است؟", "بله، تمامی امکانات سایت برای کاربران رایگان است."],
            ["چگونه می‌توانم مشکلم را گزارش دهم؟", "از طریق راه‌های ارتباطی ذکر شده در انتهای همین صفحه می‌توانید مشکلات خود را اعلام کنید."],
        ];
        foreach ($faq_items as $faq) {
            $body_sections[] = "<h3>{$faq[0]}</h3><p>{$faq[1]}</p>";
        }
    } elseif ($content_type === 'product_desc') {
        $body_sections[] = "<h2>ویژگی‌های محصول</h2>";
        $body_sections[] = "<p>{$topic} با دقت و کیفیت بالا طراحی و تولید شده است. این محصول دارای ویژگی‌های منحصربه‌فردی است که آن را از سایر محصولات مشابه متمایز می‌کند.</p>";
        $body_sections[] = "<ul><li>کیفیت بالا و تضمین شده</li><li>قیمت مناسب و رقابتی</li><li>ارسال سریع و مطمئن</li><li>پشتیبانی 24 ساعته</li></ul>";
        $body_sections[] = "<h2>نحوه سفارش</h2>";
        $body_sections[] = "<p>برای سفارش {$topic} کافی است با ما از طریق راه‌های ارتباطی زیر تماس بگیرید. تیم پشتیبانی ما در اسرع وقت پاسخگوی شما خواهد بود.</p>";
    } else {
        // Article / Landing / Service
        $sections_data = [];

        if ($is_movie_site) {
            $sections_data = [
                ['معرفی فیلم‌های هندی', "سینمای هند یکی از غنی‌ترین و متنوع‌ترین سینماهای جهان است. از درام‌های عاشقانه گرفته تا اکشن‌های هیجان‌انگیز، فیلم‌های هندی برای هر سلیقه‌ای چیزی برای ارائه دارند. ما در {$site_name} بهترین فیلم‌ها را با زیرنویس فارسی باکیفیت برای شما آماده کرده‌ایم."],
                ['دانلود با لینک مستقیم', "یکی از مزیت‌های اصلی {$site_name} ارائه لینک‌های دانلود مستقیم است. نیازی به ثبت‌نام طولانی یا پرداخت هزینه نیست. کافی است فیلم مورد نظرتان را انتخاب کرده و با یک کلیک دانلود کنید. تمامی لینک‌ها تست شده و سالم هستند."],
                ['زیرنویس فارسی', "تمام فیلم‌های موجود در {$site_name} دارای زیرنویس فارسی هماهنگ هستند. تیم ترجمه ما با دقت کامل زیرنویس‌ها را آماده کرده تا از تماشای فیلم لذت ببرید."],
                ['آرشیو کامل فیلم‌ها', "ما تلاش می‌کنیم آرشیو فیلم‌های هندی خود را به‌روز نگه داریم. هر هفته فیلم‌های جدیدی به {$site_name} اضافه می‌شوند تا همیشه گزینه‌های تازه‌ای برای تماشا داشته باشید."],
            ];
        } elseif ($is_contact_page) {
            $sections_data = [
                ['پشتیبانی تلگرام', "تیم پشتیبانی ما در تلگرام آماده پاسخگویی به سوالات شماست. هر سوال یا مشکلی دارید، کافی است پیام دهید تا در کوتاه‌ترین زمان ممکن پاسخ دریافت کنید."],
                ['صفحه اینستاگرام', "ما را در اینستاگرام دنبال کنید تا از آخرین اخبار، به‌روزرسانی‌ها و محتوای جدید سایت مطلع شوید. لینک صفحه رسمی ما در بخش زیر قابل دسترسی است."],
                ['ارسال پیام مستقیم', "اگر سوال فوری دارید، می‌توانید از طریق فرم تماس زیر پیام خود را ارسال کنید. کارشناسان ما در اسرع وقت با شما تماس خواهند گرفت."],
            ];
        } else {
            $sections_data = [
                ["معرفی {$topic}", "{$topic} یکی از موضوعات مهمی است که تأثیر مستقیمی بر تجربه کاربران دارد. در {$site_name} ما با تیکه بر دانش و تجربه، بهترین خدمات را در این زمینه ارائه می‌دهیم."],
                ["چرا {$topic} مهم است؟", "در دنیای امروز، {$topic} نقش کلیدی در موفقیت هر کسب‌وکاری ایفا می‌کند. بدون توجه به این موضوع، نمی‌توان انتظار رشد و پیشرفت داشت."],
                ["مزایای ما", "تیم {$site_name} با سال‌ها تجربه در زمینه {$topic}، می‌تواند بهترین راهکارها را متناسب با نیاز شما ارائه دهد. ما به کیفیت کارمان ایمان داریم."],
                ["نحوه استفاده", "شروع کار با {$site_name} بسیار ساده است. کافی است از بخش‌های مختلف سایت دیدن کنید و از خدمات ما بهره‌مند شوید."],
            ];
        }

        foreach ($sections_data as $idx => $sec) {
            $body_sections[] = "<h2>{$sec[0]}</h2>";
            $body_sections[] = "<p>{$sec[1]}</p>";

            if ($idx === 1 && $is_movie_site) {
                $body_sections[] = "<ul><li>دانلود با سرعت بالا</li><li>بدون نیاز به ثبت‌نام</li><li>لینک‌های تست شده و سالم</li><li>پشتیبانی از فرمت‌های مختلف</li></ul>";
            }

            if ($include_images && $idx % 2 === 0) {
                $body_sections[] = "<p>[IMAGE: تصویر مرتبط با {$sec[0]} | {$sec[0]} {$topic}]</p>";
            }
        }
    }

    // Build contact section if contact info exists
    if (!empty($contact_info)) {
        $body_sections[] = "<h2>راه‌های ارتباطی</h2>";
        $body_sections[] = "<p>برای ارتباط با ما می‌توانید از راه‌های زیر استفاده کنید:</p>";
        $body_sections[] = "<ul>";
        if (!empty($contact_info['telegram'])) {
            foreach ($contact_info['telegram'] as $t) {
                $t = ltrim($t, ':');
                $body_sections[] = "<li><strong>تلگرام پشتیبانی:</strong> @{$t}</li>";
            }
        }
        if (!empty($contact_info['instagram'])) {
            foreach ($contact_info['instagram'] as $ig) {
                $ig = ltrim($ig, ':');
                $body_sections[] = "<li><strong>اینستاگرام:</strong> @{$ig}</li>";
            }
        }
        if (!empty($contact_info['phone'])) {
            foreach ($contact_info['phone'] as $ph) {
                $body_sections[] = "<li><strong>تلفن:</strong> {$ph}</li>";
            }
        }
        if (!empty($contact_info['email'])) {
            foreach ($contact_info['email'] as $em) {
                $body_sections[] = "<li><strong>ایمیل:</strong> {$em}</li>";
            }
        }
        if (!empty($contact_info['website'])) {
            foreach ($contact_info['website'] as $w) {
                $body_sections[] = "<li><strong>وبسایت:</strong> {$w}</li>";
            }
        }
        $body_sections[] = "</ul>";
    }

    // Conclusion
    $body_sections[] = "<h2>نتیجه‌گیری</h2>";
    if ($is_movie_site) {
        $body_sections[] = "<p>اگر علاقه‌مند به فیلم‌های هندی هستید، <strong>{$site_name}</strong> بهترین انتخاب برای شماست. با آرشیو کامل فیلم‌ها، زیرنویس فارسی باکیفیت و لینک‌های دانلود مستقیم، تجربه‌ای بی‌نظیر از تماشای فیلم خواهید داشت. ما را در شبکه‌های اجتماعی دنبال کنید تا از جدیدترین فیلم‌ها مطلع شوید.</p>";
    } else {
        $body_sections[] = "<p> {$topic} یکی از موضوعاتی است که با برنامه‌ریزی درست و اجرای حرفه‌ای می‌توان به نتایج فوق‌العاده‌ای رسید. {$site_name} آماده همکاری با شما در این زمینه است. برای کسب اطلاعات بیشتر از راه‌های ارتباطی بالا استفاده کنید.</p>";
    }

    $html = "<h1>" . esc_html($topic) . "</h1>\n";
    $html .= implode("\n", $intro_lines) . "\n";
    $html .= implode("\n", $body_sections) . "\n";

    return $html;
}

add_action('wp_ajax_aic_check_content_task', function () {
    check_ajax_referer('aic_ajax', 'nonce');
    $request_id = sanitize_text_field($_POST['request_id'] ?? '');
    $queue = get_option('aic_content_queue', []);

    if (empty($queue[$request_id])) {
        wp_send_json_error('درخواست یافت نشد');
    }

    $task = $queue[$request_id];
    wp_send_json_success($task);
});

// ======================== IMAGE SEARCH AJAX ========================
add_action('wp_ajax_aic_search_images', function () {
    check_ajax_referer('aic_ajax', 'nonce');

    $query = sanitize_text_field($_POST['query'] ?? '');
    if (empty($query)) wp_send_json_error('عبارت جستجو وارد کنید');

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

    wp_send_json_success(['images' => $results, 'query' => $query]);
});

// ======================== CSS ========================
add_action('admin_head', function () {
    $lang = AIC_i18n::get_instance()->get_lang();
    $direction = $lang === 'fa' ? 'rtl' : 'ltr';
    ?>
    <style>
    .aic-wrap{max-width:1100px;margin:20px auto;font-family:Tahoma,sans-serif;direction:<?php echo $direction; ?>}
    .aic-header{display:flex;align-items:center;gap:15px;margin-bottom:25px;padding:15px 20px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:10px}
    .aic-header h1{margin:0;font-size:18px;flex:1}
    .aic-header small{opacity:.7}
    .aic-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
    .aic-card{background:#fff;border-radius:10px;padding:16px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:transform .2s}
    .aic-card:hover{transform:translateY(-2px)}
    .aic-card-num{font-size:24px;font-weight:700;color:#667eea}
    .aic-card-label{font-size:11px;color:#888;margin-top:4px}
    .aic-card-icon{font-size:20px;margin-bottom:4px}
    .aic-section{background:#fff;border-radius:10px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:18px}
    .aic-section h2{font-size:14px;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:6px}
    .aic-table{width:100%;border-collapse:collapse}
    .aic-table th,.aic-table td{padding:8px 10px;text-align:<?php echo $direction === 'rtl' ? 'right' : 'left'; ?>;border-bottom:1px solid #f5f5f5;font-size:12px}
    .aic-table th{background:#fafafa;font-weight:600;color:#555}
    .aic-grade{display:inline-block;width:24px;height:24px;line-height:24px;text-align:center;border-radius:50%;color:#fff;font-weight:700;font-size:10px}
    .aic-gA{background:#27ae60}.aic-gB{background:#2ecc71}.aic-gC{background:#f39c12}.aic-gD{background:#e74c3c}
    .aic-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
    .aic-bOk{background:#d4edda;color:#155724}.aic-bWarn{background:#fff3cd;color:#856404}.aic-bErr{background:#f8d7da;color:#721c24}
    .aic-progress{height:5px;background:#eee;border-radius:3px;overflow:hidden}
    .aic-progress-fill{height:100%;border-radius:3px}
    .aic-btn{display:inline-block;padding:6px 12px;border-radius:6px;border:none;cursor:pointer;font-size:11px;text-decoration:none;transition:all .2s;font-family:Tahoma}
    .aic-btnPri{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}.aic-btnPri:hover{opacity:.9;transform:translateY(-1px)}
    .aic-btnSec{background:#f0f0f0;color:#333}.aic-btnSec:hover{background:#e0e0e0}
    .aic-btnGreen{background:#27ae60;color:#fff}.aic-btnRed{background:#e74c3c;color:#fff}
    .aic-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:15px}
    .aic-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .aic-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
    @media(max-width:768px){.aic-grid-2,.aic-grid-3,.aic-grid-4{grid-template-columns:1fr}}
    .aic-form-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
    .aic-form-row label{min-width:110px;font-size:12px;font-weight:600}
    .aic-form-row input,.aic-form-row select{flex:1;max-width:350px}
    code.aic-key{background:#f6f6f6;padding:5px 8px;border-radius:5px;font-size:11px;user-select:all;word-break:break-all;display:inline-block}
    .aic-bar{display:flex;align-items:end;gap:3px;height:60px;margin-top:10px}
    .aic-bar-item{flex:1;background:linear-gradient(to top,#667eea,#764ba2);border-radius:3px 3px 0 0;min-height:4px;position:relative;transition:height .3s}
    .aic-bar-item span{position:absolute;bottom:-16px;left:50%;transform:translateX(-50%);font-size:9px;color:#888;white-space:nowrap}
    .aic-error-item{background:#f8f9fa;border-radius:6px;padding:10px 12px;margin-bottom:8px;border-right:3px solid #667eea}
    .aic-error-title{font-weight:600;font-size:13px;color:#333}
    .aic-error-solution{font-size:11px;color:#666;margin-top:4px;line-height:1.6}
    </style>
    <?php
});

// ======================== HELPER ========================
function aic_count($type) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish'", $type));
}

// ======================== 1. DASHBOARD ========================
function aic_page_dashboard() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $tp = aic_count('post'); $tg = aic_count('page'); $tpd = aic_count('product');
    $tc = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='1'");
    $tu = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
    $key = get_option('aic_api_key', '');

    // SEO scores
    $seo_posts = get_posts(['post_type' => ['post', 'page'], 'posts_per_page' => 5, 'post_status' => 'publish']);
    ?>
    <div class="aic-wrap">
        <div class="aic-header">
            <span style="font-size:28px">&#x1F916;</span>
            <h1>AI Connect Pro <small>v<?php echo AIC_VERSION; ?></small></h1>
            <small>Deioces120</small>
        </div>

        <!-- Stats -->
        <div class="aic-cards">
            <div class="aic-card"><div class="aic-card-icon">&#x1F4DD;</div><div class="aic-card-num"><?php echo $tp; ?></div><div class="aic-card-label"><?php echo aic_t('posts'); ?></div></div>
            <div class="aic-card"><div class="aic-card-icon">&#x1F4C4;</div><div class="aic-card-num"><?php echo $tg; ?></div><div class="aic-card-label"><?php echo aic_t('pages'); ?></div></div>
            <div class="aic-card"><div class="aic-card-icon">&#x1F6D2;</div><div class="aic-card-num"><?php echo $tpd; ?></div><div class="aic-card-label"><?php echo aic_t('products'); ?></div></div>
            <div class="aic-card"><div class="aic-card-icon">&#x1F4AC;</div><div class="aic-card-num"><?php echo $tc; ?></div><div class="aic-card-label"><?php echo aic_t('comments'); ?></div></div>
            <div class="aic-card"><div class="aic-card-icon">&#x1F465;</div><div class="aic-card-num"><?php echo $tu; ?></div><div class="aic-card-label"><?php echo aic_t('users'); ?></div></div>
        </div>

        <div class="aic-grid-2">
            <!-- API -->
            <div class="aic-section">
                <h2>&#x1F517; API</h2>
                <div style="margin-bottom:8px"><small><?php echo aic_t('api_key'); ?>:</small><br><code class="aic-key"><?php echo esc_html($key); ?></code></div>
                <div style="margin-bottom:8px"><small><?php echo aic_t('api_address'); ?>:</small><br><code class="aic-key" style="direction:ltr"><?php echo esc_url(rest_url('ai-connect/v1/')); ?></code></div>
                <button class="aic-btn aic-btnPri" onclick="fetch('<?php echo esc_url(rest_url('ai-connect/v1/ping')); ?>').then(r=>r.json()).then(d=>{document.getElementById('tst').innerHTML=d.status==='ok'?'&#x2705; <?php echo aic_t('connected'); ?>':'&#x274C; <?php echo aic_t('disconnected'); ?>'}).catch(e=>document.getElementById('tst').innerHTML='&#x274C; '+e.message)"><?php echo aic_t('test_connection'); ?></button>
                <span id="tst" style="margin-right:6px;font-size:12px"></span>
            </div>

            <!-- SEO Quick -->
            <div class="aic-section">
                <h2>&#x1F3AF; SEO</h2>
                <?php if ($seo_posts) : ?>
                    <table class="aic-table">
                        <tr><th><?php echo aic_t('page'); ?></th><th><?php echo aic_t('score'); ?></th><th><?php echo aic_t('grade'); ?></th></tr>
                        <?php foreach ($seo_posts as $p) :
                            $mt = get_post_meta($p->ID, '_yoast_wpseo_title', true) ?: $p->post_title;
                            $s = 0;
                            if (strlen($mt) >= 30 && strlen($mt) <= 60) $s += 30; elseif (strlen($mt) > 0) $s += 10;
                            $md = get_post_meta($p->ID, '_yoast_wpseo_metadesc', true) ?: $p->post_excerpt;
                            if (strlen($md) >= 120 && strlen($md) <= 160) $s += 30; elseif (strlen($md) > 0) $s += 10;
                            if (str_word_count(wp_strip_all_tags($p->post_content)) >= 300) $s += 20; elseif (str_word_count(wp_strip_all_tags($p->post_content)) >= 100) $s += 10;
                            $g = $s >= 80 ? 'A' : ($s >= 60 ? 'B' : ($s >= 40 ? 'C' : 'D'));
                        ?>
                            <tr><td><a href="<?php echo get_edit_post_link($p->ID); ?>"><?php echo esc_html(mb_substr($p->post_title, 0, 22)); ?></a></td>
                            <td><div class="aic-progress" style="width:60px"><div class="aic-progress-fill" style="width:<?php echo $s; ?>%;background:<?php echo $s>=60?'#27ae60':($s>=40?'#f39c12':'#e74c3c'); ?>"></div></div></td>
                            <td><span class="aic-grade aic-g<?php echo $g; ?>"><?php echo $g; ?></span></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p style="color:#888;font-size:12px"><?php echo aic_t('no_content'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- WooCommerce Quick -->
        <?php if (class_exists('WooCommerce')) :
            $products = wc_get_products(['limit' => 5, 'orderby' => 'total_sales', 'order' => 'DESC', 'return' => 'ids']);
            $orders_count = wc_orders_count('completed');
            $revenue = 0;
            if ($orders_count > 0) {
                $recent = wc_get_orders(['limit' => 50, 'status' => 'completed', 'return' => 'ids']);
                foreach ($recent as $oid) { $o = wc_get_order($oid); if ($o) $revenue += $o->get_total(); }
            }
        ?>
        <div class="aic-section">
            <h2>&#x1F6D2; <?php echo aic_t('store'); ?></h2>
            <div class="aic-grid-4">
                <div style="text-align:center"><div style="font-size:20px;font-weight:700;color:#27ae60"><?php echo (wp_count_posts('product'))->publish ?? 0; ?></div><div style="font-size:10px;color:#888"><?php echo aic_t('products'); ?></div></div>
                <div style="text-align:center"><div style="font-size:20px;font-weight:700;color:#667eea"><?php echo $orders_count; ?></div><div style="font-size:10px;color:#888"><?php echo aic_t('orders'); ?></div></div>
                <div style="text-align:center"><div style="font-size:20px;font-weight:700;color:#e74c3c"><?php echo wc_price($revenue); ?></div><div style="font-size:10px;color:#888"><?php echo aic_t('revenue'); ?></div></div>
                <div style="text-align:center"><div style="font-size:20px;font-weight:700;color:#f39c12"><?php echo wc_price($orders_count > 0 ? $revenue / $orders_count : 0); ?></div><div style="font-size:10px;color:#888"><?php echo aic_t('average'); ?></div></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Links -->
        <div class="aic-section">
            <h2>&#x26A1; <?php echo aic_t('quick_access'); ?></h2>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <a href="<?php echo admin_url('admin.php?page=aic-api'); ?>" class="aic-btn aic-btnPri">API</a>
                <a href="<?php echo admin_url('admin.php?page=aic-seo'); ?>" class="aic-btn aic-btnPri"><?php echo aic_t('menu_seo'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=aic-analytics'); ?>" class="aic-btn aic-btnPri"><?php echo aic_t('menu_analytics'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=aic-content'); ?>" class="aic-btn aic-btnPri"><?php echo aic_t('menu_content'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=aic-security'); ?>" class="aic-btn aic-btnPri"><?php echo aic_t('menu_security'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=aic-woocommerce'); ?>" class="aic-btn aic-btnPri"><?php echo aic_t('menu_woocommerce'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=aic-agent'); ?>" class="aic-btn aic-btnPri"><?php echo aic_t('menu_agent'); ?></a>
                <a href="<?php echo admin_url('edit.php'); ?>" class="aic-btn aic-btnSec"><?php echo aic_t('all_posts'); ?></a>
                <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="aic-btn aic-btnSec"><?php echo aic_t('all_products'); ?></a>
                <a href="<?php echo home_url('/'); ?>" class="aic-btn aic-btnSec" target="_blank">&#x1F3E0; <?php echo aic_t('view_site'); ?></a>
            </div>
        </div>

        <!-- Guide -->
        <div class="aic-section">
            <h2>&#x1F4D6; <?php echo aic_t('quick_guide'); ?></h2>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:8px;direction:ltr;text-align:left;overflow-x:auto;font-size:11px;margin:0"># تست اتصال
curl -s "<?php echo esc_url(rest_url('ai-connect/v1/ping')); ?>"

# تحلیل SEO
curl -s -H "X-API-Key: KEY" "<?php echo esc_url(rest_url('ai-connect/v1/seo/analyze/1')); ?>"

# ووکامرس
curl -s -H "X-API-Key: KEY" "<?php echo esc_url(rest_url('ai-connect/v1/products')); ?>"

# اسکن امنیتی
curl -s -X POST -H "X-API-Key: KEY" "<?php echo esc_url(rest_url('ai-connect/v1/devops/scan')); ?>"</pre>
        </div>
    </div>
    <?php
}

// ======================== 2. API ========================
function aic_page_api() {
    if (!current_user_can('manage_options')) return;
    $key = get_option('aic_api_key', '');
    $en = get_option('aic_enabled', '1');
    $ips = get_option('aic_allowed_ips', '');
    $rl = get_option('aic_rate_limit', 300);
    ?>
    <div class="aic-wrap">
        <div class="aic-header"><h1>&#x1F517; API</h1></div>
        <form method="post"><?php wp_nonce_field('aic_settings'); ?>
        <input type="hidden" name="aic_tab" value="api">
        <div class="aic-section">
            <h2>&#x1F511; <?php echo aic_t('api_settings'); ?></h2>
            <div class="aic-form-row"><label><?php echo aic_t('status'); ?></label><select name="aic_enabled"><option value="1" <?php selected($en,'1'); ?>><?php echo aic_t('enabled'); ?></option><option value="0" <?php selected($en,'0'); ?>><?php echo aic_t('disabled'); ?></option></select></div>
            <div class="aic-form-row"><label><?php echo aic_t('api_key_label'); ?></label><div><code class="aic-key"><?php echo esc_html($key); ?></code><br><label style="font-size:11px;margin-top:4px"><input type="checkbox" name="aic_regenerate_key" value="1"> <?php echo aic_t('new_key'); ?></label></div></div>
            <div class="aic-form-row"><label><?php echo aic_t('allowed_ip'); ?></label><input type="text" name="aic_allowed_ips" value="<?php echo esc_attr($ips); ?>" placeholder="<?php echo aic_t('all_allowed'); ?>"></div>
            <div class="aic-form-row"><label><?php echo aic_t('rate_limit'); ?></label><input type="number" name="aic_rate_limit" value="<?php echo $rl; ?>" min="0" style="max-width:80px"><small><?php echo aic_t('requests_per_min'); ?></small></div>
            <p class="submit"><button type="submit" name="aic_save" class="button button-primary"><?php echo aic_t('save'); ?></button></p>
        </div>
        </form>
        <div class="aic-section">
            <h2>&#x1F4CA; Endpoints</h2>
            <?php $eps = [
                ['GET','/ping', aic_t('test_connection')],
                ['GET','/posts', aic_t('posts')],['POST','/posts', aic_t('add') . ' ' . aic_t('posts')],['GET|PUT|DEL','/posts/{id}', aic_t('edit') . ' ' . aic_t('posts')],
                ['GET','/pages', aic_t('pages')],['GET|POST','/media', 'Media'],['GET|POST','/comments', aic_t('comments')],['GET','/users', aic_t('users')],
                ['GET|POST','/products', aic_t('products')],['GET','/orders', aic_t('orders')],['GET','/plugins', 'Plugins'],['GET','/themes', 'Themes'],
                ['GET|POST','/menus', 'Menus'],['GET|POST','/options', aic_t('settings')],['GET|POST','/files', 'Files'],['POST','/db/query','SQL'],
                ['GET|POST','/custom-css','CSS'],['GET','/seo/analyze/{id}', aic_t('seo_report')],['GET','/seo/scores', aic_t('score')],
                ['GET','/seo/keywords/{id}', 'Keywords'],['GET','/seo/quality/{id}', aic_t('content_quality')],['GET','/seo/suggestions/{id}', 'Suggestions'],
                ['GET','/analytics/summary', aic_t('analytics_summary')],['GET','/agent/history', aic_t('agent_history')],['GET','/devops/health', aic_t('server_health')],
                ['POST','/devops/scan', aic_t('security_scan')],['GET|POST','/devops/firewall', aic_t('firewall')],['GET','/devops/warnings', 'Warnings'],
                ['GET','/health', 'Health'],['POST','/setup', 'Setup'],['POST','/system/exec', 'Execute'],
            ]; ?>
            <table class="aic-table"><tr><th><?php echo aic_t('method'); ?></th><th><?php echo aic_t('path'); ?></th><th><?php echo aic_t('description'); ?></th></tr>
            <?php foreach ($eps as $e) : ?>
                <tr><td><code style="font-size:10px"><?php echo $e[0]; ?></code></td><td><code style="font-size:10px;direction:ltr"><?php echo $e[1]; ?></code></td><td style="font-size:11px"><?php echo $e[2]; ?></td></tr>
            <?php endforeach; ?></table>
        </div>
    </div>
    <?php
}

// ======================== 3. SEO + THEME ========================
function aic_page_seo() {
    if (!current_user_can('manage_options')) return;
    $seo = AIC_SEO_Features::get_instance();
    $tab = sanitize_text_field($_GET['aic_seo_tab'] ?? 'settings');
    $theme = wp_get_theme();

    // Handle form submissions
    if (isset($_POST['aic_save']) && check_admin_referer('aic_settings')) {
        $tab_save = sanitize_text_field($_POST['aic_tab'] ?? '');
        if ($tab_save === 'seo') {
            update_option('aic_seo_auto_meta', isset($_POST['aic_seo_auto_meta']) ? '1' : '0');
            update_option('aic_seo_analyze_products', isset($_POST['aic_seo_analyze_products']) ? '1' : '0');
        }
    }

    $tabs = [
        'settings'  => ['icon' => '&#x2699;&#xFE0F;', 'label' => aic_t('seo_settings')],
        'report'    => ['icon' => '&#x1F3AF;', 'label' => aic_t('seo_report')],
        '404'       => ['icon' => '&#x1F41B;', 'label' => aic_t('monitor_404')],
        'broken'    => ['icon' => '&#x1F517;', 'label' => aic_t('broken_links')],
        'analysis'  => ['icon' => '&#x1F50D;', 'label' => aic_t('content_analysis')],
        'generator' => ['icon' => '&#x1F916;', 'label' => aic_t('content_generator')],
        'robots'    => ['icon' => '&#x1F4C3;', 'label' => 'Robots.txt'],
    ];

    $base_url = admin_url('admin.php?page=aic-seo');
    ?>
    <div class="aic-wrap">
        <div class="aic-header"><h1>&#x1F3AF; <?php echo aic_t('menu_seo'); ?></h1></div>

        <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:20px;background:#fff;padding:10px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.05)">
            <?php foreach ($tabs as $key => $t) :
                $active = $tab === $key;
                $url = add_query_arg('aic_seo_tab', $key, $base_url);
            ?>
                <a href="<?php echo $url; ?>" class="aic-btn <?php echo $active ? 'aic-btnPri' : 'aic-btnSec'; ?>" style="font-size:11px"><?php echo $t['icon'] . ' ' . $t['label']; ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($tab === 'settings') : ?>
            <?php $auto = get_option('aic_seo_auto_meta', '0'); $ap = get_option('aic_seo_analyze_products', '1'); ?>
            <form method="post"><?php wp_nonce_field('aic_settings'); ?>
            <input type="hidden" name="aic_tab" value="seo">
            <div class="aic-section">
                <h2>&#x2699;&#xFE0F; <?php echo aic_t('seo_settings'); ?></h2>
                <div class="aic-form-row"><label><?php echo aic_t('auto_meta'); ?></label><label><input type="checkbox" name="aic_seo_auto_meta" value="1" <?php checked($auto); ?>> <?php echo aic_t('auto_meta_desc'); ?></label></div>
                <div class="aic-form-row"><label><?php echo aic_t('analyze_products'); ?></label><label><input type="checkbox" name="aic_seo_analyze_products" value="1" <?php checked($ap); ?>> <?php echo aic_t('analyze_products_desc'); ?></label></div>
                <p class="submit"><button type="submit" name="aic_save" class="button button-primary"><?php echo aic_t('save'); ?></button></p>
            </div>
            </form>

            <?php $ai_url = get_option('aic_ai_api_url', ''); $ai_key = get_option('aic_ai_api_key', ''); $ai_model = get_option('aic_ai_model', 'gpt-4o-mini'); ?>
            <form method="post"><?php wp_nonce_field('aic_settings'); ?>
            <input type="hidden" name="aic_tab" value="ai_agent">
            <div class="aic-section" style="border:2px solid #667eea">
                <h2>&#x1F916; <?php echo aic_t('ai_agent_settings'); ?></h2>
                <p style="font-size:11px;color:#666;margin-bottom:10px"><?php echo aic_t('ai_agent_desc'); ?></p>
                <div class="aic-form-row"><label><?php echo aic_t('api_url'); ?></label><input type="url" name="aic_ai_api_url" value="<?php echo esc_attr($ai_url); ?>" placeholder="https://api.openai.com/v1/chat/completions" style="max-width:400px"></div>
                <div class="aic-form-row"><label><?php echo aic_t('api_key_ai'); ?></label><input type="password" name="aic_ai_api_key" value="<?php echo esc_attr($ai_key); ?>" placeholder="sk-..." style="max-width:400px"></div>
                <div class="aic-form-row"><label><?php echo aic_t('model'); ?></label>
                    <select name="aic_ai_model">
                        <option value="gpt-4o-mini" <?php selected($ai_model, 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                        <option value="gpt-4o" <?php selected($ai_model, 'gpt-4o'); ?>>GPT-4o</option>
                        <option value="gpt-4-turbo" <?php selected($ai_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                        <option value="gpt-3.5-turbo" <?php selected($ai_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                    </select>
                </div>
                <p class="submit"><button type="submit" name="aic_save" class="button button-primary"><?php echo aic_t('save_ai_settings'); ?></button></p>
            </div>
            </form>

            <div class="aic-section">
                <h2>&#x1F4E6; <?php echo aic_t('active_theme'); ?></h2>
                <div class="aic-grid-3">
                    <div><strong><?php echo aic_t('theme_name'); ?>:</strong> <?php echo $theme->get('Name'); ?></div>
                    <div><strong><?php echo aic_t('theme_version'); ?>:</strong> <?php echo $theme->get('Version'); ?></div>
                    <div><strong><?php echo aic_t('theme_author'); ?>:</strong> <?php echo $theme->get('Author'); ?></div>
                </div>
            </div>

        <?php elseif ($tab === 'report') : ?>
            <?php $post_id = (int)($_GET['post_id'] ?? 0); $result = $seo->seo_report($post_id); $reports = $result['reports']; $summary = $result['summary']; ?>
            <div class="aic-cards" style="grid-template-columns:repeat(5,1fr)">
                <div class="aic-card"><div class="aic-card-num"><?php echo $summary['total']; ?></div><div class="aic-card-label">کل صفحات</div></div>
                <div class="aic-card"><div class="aic-card-num" style="color:#667eea"><?php echo $summary['avg_score']; ?></div><div class="aic-card-label">میانگین</div></div>
                <div class="aic-card"><div class="aic-card-num" style="color:#27ae60"><?php echo $summary['grades']['A']; ?></div><div class="aic-card-label">A</div></div>
                <div class="aic-card"><div class="aic-card-num" style="color:#f39c12"><?php echo $summary['total_warnings']; ?></div><div class="aic-card-label">هشدار</div></div>
                <div class="aic-card"><div class="aic-card-num" style="color:#e74c3c"><?php echo $summary['total_errors']; ?></div><div class="aic-card-label">خطا</div></div>
            </div>

            <!-- AI Analysis Section - Unified -->
            <?php
            $ai_url = get_option('aic_ai_api_url', '');
            $ai_key = get_option('aic_ai_api_key', '');
            $ai_configured = !empty($ai_url) && !empty($ai_key);
            $ai_analysis = $post_id ? get_post_meta($post_id, '_aic_ai_analysis', true) : null;
            $plugin_api_key = get_option('aic_api_key', '');
            ?>
            <div style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:12px;padding:20px;margin-bottom:20px;color:#fff">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:15px">
                    <div>
                        <h2 style="margin:0;font-size:18px;color:#fff">&#x1F916; تحلیل هوش مصنوعی</h2>
                        <p style="margin:4px 0 0;font-size:11px;opacity:.8">تحلیل واقعی محتوا توسط ایجنت هوش مصنوعی</p>
                    </div>
                    <?php if ($post_id && $ai_configured) : ?>
                        <button id="aic-ai-btn" onclick="aicAnalyze(<?php echo $post_id; ?>)" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);padding:8px 16px;border-radius:6px;cursor:pointer;font-size:12px;font-family:Tahoma;transition:all .2s" onmouseover="this.style.background='rgba(255,255,255,.3)'" onmouseout="this.style.background='rgba(255,255,255,.2)'">
                            &#x26A1; تحلیل خودکار
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Connection Status Bar -->
                <div style="display:flex;gap:10px;margin-bottom:15px">
                    <div style="flex:1;background:rgba(255,255,255,.15);border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:8px">
                        <span style="font-size:18px">&#x1F504;</span>
                        <div>
                            <div style="font-size:11px;font-weight:700">AI خارجی</div>
                            <div style="font-size:10px;opacity:.8"><?php echo $ai_configured ? '✅ متصل' : '⚠️ تنظیم نشده'; ?></div>
                        </div>
                    </div>
                    <div style="flex:1;background:rgba(255,255,255,.15);border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:8px;border:1px solid rgba(255,255,255,.4)">
                        <span style="font-size:18px">&#x1F310;</span>
                        <div>
                            <div style="font-size:11px;font-weight:700">API افزونه</div>
                            <div style="font-size:10px;opacity:.8">✅ فعال • توصیه شده</div>
                        </div>
                    </div>
                </div>

                <span id="aic-ai-status" style="font-size:11px;display:block;text-align:center;min-height:16px"></span>
            </div>

            <!-- Agent Connection Panel (when viewing a page) -->
            <?php if ($post_id) : ?>
            <div class="aic-section" style="border:1px solid #e5e7eb">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                    <span style="font-size:20px">&#x1F310;</span>
                    <div>
                        <h3 style="margin:0;font-size:14px">اتصال ایجنت از طریق API افزونه</h3>
                        <p style="margin:2px 0 0;font-size:11px;color:#888">داده‌های خام رو بگیر، تحلیل کن، نتیجه رو ذخیره کن</p>
                    </div>
                </div>

                <!-- API Endpoints -->
                <div style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:8px;font-family:monospace;font-size:11px;direction:ltr;text-align:left;margin-bottom:12px;overflow-x:auto">
                    <div style="color:#608b4e;margin-bottom:5px"># دریافت داده خام صفحه</div>
                    <div>GET <?php echo esc_url(rest_url('ai-connect/v1/seo/raw-data/' . $post_id)); ?></div>
                    <div style="color:#569cd6">Header: X-API-Key: <?php echo substr($plugin_api_key, 0, 16); ?>...</div>
                    <br>
                    <div style="color:#608b4e;margin-bottom:5px"># ذخیره نتیجه تحلیل</div>
                    <div>POST <?php echo esc_url(rest_url('ai-connect/v1/seo/save-analysis')); ?></div>
                    <div style="color:#569cd6">{"post_id":<?php echo $post_id; ?>,"score":85,"grade":"B","analysis":"..."}</div>
                </div>

                <!-- Action Buttons -->
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a href="<?php echo esc_url(rest_url('ai-connect/v1/seo/raw-data/' . $post_id)); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:5px;background:#667eea;color:#fff;padding:8px 14px;border-radius:6px;font-size:11px;text-decoration:none;font-family:Tahoma;transition:all .2s" onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
                        &#x1F4E5; دریافت داده خام
                    </a>
                    <button onclick="navigator.clipboard.writeText(this.dataset.url);this.innerHTML='&#x2705; کپی شد'" data-url="<?php echo esc_url(rest_url('ai-connect/v1/seo/raw-data/' . $post_id)); ?>" style="background:#f3f4f6;color:#374151;padding:8px 14px;border-radius:6px;font-size:11px;border:1px solid #d1d5db;cursor:pointer;font-family:Tahoma">
                        &#x1F4CB; کپی آدرس
                    </button>
                    <button onclick="aicRefreshAndAnalyze(<?php echo $post_id; ?>)" style="background:#8b5cf6;color:#fff;padding:8px 14px;border-radius:6px;font-size:11px;border:none;cursor:pointer;font-family:Tahoma">
                        &#x1F504; بروزرسانی و تحلیل مجدد
                    </button>
                    <?php if ($ai_configured) : ?>
                        <button onclick="aicAnalyze(<?php echo $post_id; ?>)" style="background:#10b981;color:#fff;padding:8px 14px;border-radius:6px;font-size:11px;border:none;cursor:pointer;font-family:Tahoma">
                            &#x26A1; تحلیل خودکار با AI
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dynamic Real-time Results Container -->
            <?php if ($post_id) : ?>
            <div id="aic-dynamic-results" class="aic-section" style="display:none;border:2px solid #10b981;background:#f0fdf4"></div>
            <?php endif; ?>

            <!-- AI Analysis Results -->
            <?php if ($ai_analysis) : ?>
            <div id="aic-old-analysis" class="aic-section" style="border:1px solid #e5e7eb">
                <div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;padding-bottom:15px;border-bottom:1px solid #f0f0f0">
                    <!-- Score Circle -->
                    <div style="position:relative;width:70px;height:70px;flex-shrink:0">
                        <svg viewBox="0 0 70 70" style="transform:rotate(-90deg)">
                            <circle cx="35" cy="35" r="28" fill="none" stroke="#eee" stroke-width="6"/>
                            <circle cx="35" cy="35" r="28" fill="none" stroke="<?php echo $ai_analysis['score']>=80?'#27ae60':($ai_analysis['score']>=60?'#2ecc71':($ai_analysis['score']>=40?'#f39c12':'#e74c3c')); ?>" stroke-width="6" stroke-dasharray="<?php echo $ai_analysis['score'] * 1.76; ?> 176" stroke-linecap="round"/>
                        </svg>
                        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center">
                            <div style="font-size:20px;font-weight:700;color:<?php echo $ai_analysis['score']>=60?'#27ae60':'#e74c3c'; ?>"><?php echo $ai_analysis['score']; ?></div>
                        </div>
                    </div>
                    <div style="flex:1">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                            <span style="font-size:16px;font-weight:700">نمره <?php echo $ai_analysis['grade']; ?></span>
                            <span class="aic-badge" style="background:<?php echo $ai_analysis['score']>=60?'#d4edda':'#f8d7da'; ?>;color:<?php echo $ai_analysis['score']>=60?'#155724':'#721c24'; ?>"><?php echo $ai_analysis['score']>=80?'عالی':($ai_analysis['score']>=60?'خوب':($ai_analysis['score']>=40?'نیاز به بهبود':'ضعیف')); ?></span>
                        </div>
                        <div style="font-size:11px;color:#888">تحلیل شده توسط: <strong><?php echo esc_html($ai_analysis['agent'] ?? 'unknown'); ?></strong> • <?php echo $ai_analysis['time']; ?></div>
                    </div>
                </div>

                <!-- Structured Analysis -->
                <?php
                $lines = explode("\n", $ai_analysis['analysis']);
                $section_icons = ['نگاه' => '&#x1F9E0;', 'کارشناس' => '&#x1F9E0;', 'کیفیت' => '&#x1F4C4;', 'محتوا' => '&#x1F4C4;', 'ساختار' => '&#x1F3D7;&#xFE0F;', 'هدینگ' => '&#x1F3F7;&#xFE0F;', 'تصاویر' => '&#x1F5BC;&#xFE0F;', 'لینک' => '&#x1F517;', 'شبکه' => '&#x1F4E2;', 'نمره' => '&#x1F3AF;', 'پیشنهاد' => '&#x1F4A1;', 'اقدام' => '&#x1F3AF;', 'فنی' => '&#x2699;&#xFE0F;', 'عنوان' => '&#x1F4DD;', 'meta' => '&#x1F4DD;', 'توضیحات' => '&#x1F4DD;'];
                $current_section = '';
                $current_items = [];
                $sections = [];

                foreach ($lines as $line) {
                    $trim = trim($line);
                    if (preg_match('/^###\s+(.+)/', $trim, $m)) {
                        if ($current_section) $sections[] = ['title' => $current_section, 'items' => $current_items, 'icon' => $current_icon ?? '&#x1F4CB;'];
                        $current_section = preg_replace('/[*_]/', '', $m[1]);
                        $current_items = [];
                        $current_icon = '&#x1F4CB;';
                        foreach ($section_icons as $k => $v) { if (mb_stripos($current_section, $k) !== false) { $current_icon = $v; break; } }
                    } elseif (preg_match('/^\*\*(.+?)\*\*:?\s*$/', $trim, $m)) {
                        $current_items[] = ['type' => 'label', 'text' => preg_replace('/[*_]/', '', $m[1])];
                    } elseif (preg_match('/^[-*]\s+(.+)/', $trim, $m)) {
                        $current_items[] = ['type' => 'item', 'text' => preg_replace('/[*_]/', '', $m[1])];
                    } elseif (preg_match('/^[0-9]+\.\s+(.+)/', $trim, $m)) {
                        $current_items[] = ['type' => 'numbered', 'text' => preg_replace('/[*_]/', '', $m[1])];
                    } elseif ($trim && !preg_match('/^#+/', $trim) && !preg_match('/^---/', $trim)) {
                        $text = preg_replace('/[*_]/', '', $trim);
                        if ($text) $current_items[] = ['type' => 'text', 'text' => $text];
                    }
                }
                if ($current_section) $sections[] = ['title' => $current_section, 'items' => $current_items, 'icon' => $current_icon ?? '&#x1F4CB;'];
                ?>

                <?php if (!empty($sections)) : ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:15px">
                        <?php foreach ($sections as $sec) : ?>
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px">
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e5e7eb">
                                    <span style="font-size:16px"><?php echo $sec['icon']; ?></span>
                                    <strong style="font-size:12px;color:#374151"><?php echo esc_html($sec['title']); ?></strong>
                                </div>
                                <?php foreach ($sec['items'] as $item) : ?>
                                    <?php if ($item['type'] === 'label') : ?>
                                        <div style="font-size:11px;font-weight:700;color:#1f2937;margin:6px 0 2px"><?php echo esc_html($item['text']); ?></div>
                                    <?php elseif ($item['type'] === 'item') : ?>
                                        <div style="font-size:11px;color:#6b7280;padding:2px 0;padding-right:10px;line-height:1.6">
                                            <span style="color:#9ca3af">•</span> <?php echo esc_html($item['text']); ?>
                                        </div>
                                    <?php elseif ($item['type'] === 'numbered') : ?>
                                        <div style="font-size:11px;color:#6b7280;padding:2px 0;padding-right:10px;line-height:1.6">
                                            <span style="color:#667eea;font-weight:700">◆</span> <?php echo esc_html($item['text']); ?>
                                        </div>
                                    <?php else : ?>
                                        <div style="font-size:11px;color:#374151;padding:2px 0;line-height:1.6"><?php echo esc_html($item['text']); ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php elseif ($post_id) : ?>
            <div id="aic-old-analysis" class="aic-section" style="text-align:center;padding:30px;border:2px dashed #d1d5db">
                <div style="font-size:32px;margin-bottom:8px">&#x1F916;</div>
                <div style="font-size:13px;color:#666;margin-bottom:5px">هنوز تحلیل AI انجام نشده</div>
                <div style="font-size:11px;color:#999">از دکمه "تحلیل خودکار" یا "دریافت داده خام" استفاده کنید</div>
            </div>
            <?php endif; ?>
            <script>
            function aicAnalyze(postId) {
                var btn = document.getElementById('aic-ai-btn');
                var status = document.getElementById('aic-ai-status');
                if (btn) { btn.disabled = true; btn.innerHTML = '&#x23F3; در حال تحلیل...'; }
                status.innerHTML = '&#x1F504; ایجنت در حال دریافت داده و تحلیل محتوا...';
                status.style.color = '#fff';

                var data = new FormData();
                data.append('action', 'aic_ai_analyze');
                data.append('nonce', '<?php echo wp_create_nonce('aic_ajax'); ?>');
                data.append('post_id', postId);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) {
                            status.innerHTML = '&#x2705; تحلیل تمام شد!';
                            var oldSection = document.getElementById('aic-old-analysis');
                            if (oldSection) { oldSection.style.display = 'none'; }
                            location.reload();
                        } else {
                            status.innerHTML = '&#x274C; ' + d.data;
                            status.style.color = '#fecaca';
                            if (btn) { btn.disabled = false; btn.innerHTML = '&#x26A1; تحلیل خودکار'; }
                        }
                    })
                    .catch(function(e) {
                        status.innerHTML = '&#x274C; خطا: ' + e.message;
                        status.style.color = '#fecaca';
                        if (btn) { btn.disabled = false; btn.innerHTML = '&#x26A1; تحلیل خودکار'; }
                    });
            }
            function aicRefreshAndAnalyze(postId) {
                var status = document.getElementById('aic-ai-status');
                var btn = event.target;
                btn.disabled = true;
                btn.innerHTML = '&#x23F3; در حال تحلیل...';
                status.innerHTML = '&#x1F504; در حال دریافت و تحلیل زنده داده‌ها...';
                status.style.color = '#fff';

                var data = new FormData();
                data.append('action', 'aic_refresh_seo');
                data.append('nonce', '<?php echo wp_create_nonce('aic_ajax'); ?>');
                data.append('post_id', postId);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) {
                            var r = d.data;
                            status.innerHTML = '&#x2705; بروزرسانی و تحلیل تمام شد! • ' + r.time;
                            btn.innerHTML = '&#x2705; بروز شد!';
                            btn.style.background = '#10b981';

                            var oldSection = document.getElementById('aic-old-analysis');
                            if (oldSection) oldSection.style.display = 'none';

                            var container = document.getElementById('aic-dynamic-results');
                            if (container) {
                                container.innerHTML = '';
                                container.style.display = 'block';

                                var html = '';
                                html += '<div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;padding-bottom:15px;border-bottom:2px solid #10b981">';
                                html += '<div style="position:relative;width:70px;height:70px;flex-shrink:0">';
                                html += '<svg viewBox="0 0 70 70" style="transform:rotate(-90deg)">';
                                html += '<circle cx="35" cy="35" r="28" fill="none" stroke="#eee" stroke-width="6"/>';
                                var color = r.score >= 80 ? '#27ae60' : (r.score >= 60 ? '#2ecc71' : (r.score >= 40 ? '#f39c12' : '#e74c3c'));
                                html += '<circle cx="35" cy="35" r="28" fill="none" stroke="' + color + '" stroke-width="6" stroke-dasharray="' + (r.score * 1.76) + ' 176" stroke-linecap="round"/>';
                                html += '</svg>';
                                html += '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center"><div style="font-size:20px;font-weight:700;color:' + color + '">' + r.score + '</div></div>';
                                html += '</div>';
                                html += '<div style="flex:1"><div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">';
                                html += '<span style="font-size:16px;font-weight:700">نمره ' + r.grade + ' — ' + r.grade_label + '</span>';
                                html += '<span class="aic-badge" style="background:' + (r.score>=60?'#d4edda':'#f8d7da') + ';color:' + (r.score>=60?'#155724':'#721c24') + '">' + r.grade_label + '</span>';
                                html += '</div>';
                                html += '<div style="font-size:11px;color:#666">تحلیل شده توسط: <strong>AiConnect Agent</strong> • ' + r.time + '</div>';
                                html += '<div style="font-size:10px;color:#999">تاریخ انتشار: ' + r.date + ' • آخرین تغییر: ' + r.modified + '</div></div></div>';

                                html += '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:15px">';
                                html += '<div style="background:#fff;border-radius:6px;padding:8px;text-align:center;border:1px solid #e5e7eb"><div style="font-size:18px;font-weight:700;color:#667eea">' + r.content_analysis.word_count + '</div><div style="font-size:10px;color:#888">کلمه</div></div>';
                                html += '<div style="background:#fff;border-radius:6px;padding:8px;text-align:center;border:1px solid #e5e7eb"><div style="font-size:18px;font-weight:700;color:#667eea">' + r.heading_analysis.total + '</div><div style="font-size:10px;color:#888">هدینگ</div></div>';
                                html += '<div style="background:#fff;border-radius:6px;padding:8px;text-align:center;border:1px solid #e5e7eb"><div style="font-size:18px;font-weight:700;color:#667eea">' + r.image_analysis.total + '</div><div style="font-size:10px;color:#888">تصویر</div></div>';
                                html += '<div style="background:#fff;border-radius:6px;padding:8px;text-align:center;border:1px solid #e5e7eb"><div style="font-size:18px;font-weight:700;color:#667eea">' + r.link_analysis.total + '</div><div style="font-size:10px;color:#888">لینک</div></div>';
                                html += '</div>';

                                if (r.analysis) {
                                    var lines = r.analysis.split('\n');
                                    var sectionIcons = {'نگاه':'🧠','کارشناس':'🧠','کیفیت':'📄','محتوا':'📄','ساختار':'🏗️','هدینگ':'🏷️','تصاویر':'🖼️','لینک':'🔗','فنی':'⚙️','اقدام':'🎯','نمره':'🎯','پیشنهاد':'💡','عنوان':'📝','meta':'📝','توضیحات':'📝'};
                                    var inSection = false;
                                    html += '<div style="margin-top:5px">';
                                    for (var i = 0; i < lines.length; i++) {
                                        var line = lines[i].trim();
                                        if (!line) continue;
                                        var h3m = line.match(/^###\s+(.+)/);
                                        if (h3m) {
                                            if (inSection) html += '</div>';
                                            var stitle = h3m[1].replace(/[*_]/g, '');
                                            var sicon = '📋';
                                            for (var k in sectionIcons) { if (stitle.indexOf(k) !== -1) { sicon = sectionIcons[k]; break; } }
                                            html += '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:10px">';
                                            html += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #f0f0f0">';
                                            html += '<span style="font-size:16px">' + sicon + '</span>';
                                            html += '<strong style="font-size:13px;color:#374151">' + stitle + '</strong></div>';
                                            inSection = true;
                                            continue;
                                        }
                                        var boldm = line.match(/^\*\*(.+?)\*\*:?\s*$/);
                                        if (boldm) {
                                            html += '<div style="font-size:11px;font-weight:700;color:#1f2937;margin:6px 0 2px">' + boldm[1].replace(/\*\*/g, '') + '</div>';
                                            continue;
                                        }
                                        var itemm = line.match(/^[-*]\s+(.+)/);
                                        if (itemm) {
                                            var txt = itemm[1].replace(/\*\*/g, '<strong>').replace(/<strong>([^<]+)$/g, '<strong>$1</strong>');
                                            txt = txt.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                                            html += '<div style="font-size:11px;color:#555;padding:2px 0 2px 10px;line-height:1.7"><span style="color:#9ca3af">•</span> ' + txt + '</div>';
                                            continue;
                                        }
                                        var numm = line.match(/^(\d+)\.\s+(.+)/);
                                        if (numm) {
                                            var ntxt = numm[2].replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                                            html += '<div style="font-size:11px;color:#555;padding:2px 0 2px 10px;line-height:1.7"><span style="color:#667eea;font-weight:700">' + numm[1] + '.</span> ' + ntxt + '</div>';
                                            continue;
                                        }
                                        var txt2 = line.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                                        html += '<div style="font-size:11px;color:#374151;padding:2px 0;line-height:1.7">' + txt2 + '</div>';
                                    }
                                    if (inSection) html += '</div>';
                                    html += '</div>';
                                }

                                container.innerHTML = html;
                            }

                            setTimeout(function() {
                                btn.disabled = false;
                                btn.innerHTML = '&#x1F504; بروزرسانی و تحلیل مجدد';
                                btn.style.background = '#8b5cf6';
                            }, 3000);
                        } else {
                            status.innerHTML = '&#x274C; ' + d.data;
                            status.style.color = '#fecaca';
                            btn.disabled = false;
                            btn.innerHTML = '&#x1F504; بروزرسانی و تحلیل مجدد';
                        }
                    })
                    .catch(function(e) {
                        status.innerHTML = '&#x274C; خطا: ' + e.message;
                        status.style.color = '#fecaca';
                        btn.disabled = false;
                        btn.innerHTML = '&#x1F504; بروزرسانی و تحلیل مجدد';
                    });
            }
            </script>
            <div class="aic-section">
                <h2>&#x1F4CB; لیست صفحات</h2>
                <table class="aic-table">
                    <tr><th>صفحه</th><th>نوع</th><th>کلمات</th><th>آخرین تغییر</th><th>مشکلات</th><th>امتیاز</th><th>نمره</th></tr>
                    <?php foreach ($reports as $r) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url(add_query_arg(['aic_seo_tab' => 'report', 'post_id' => $r['post_id']], $base_url)); ?>"><?php echo esc_html(mb_substr($r['title'], 0, 25)); ?></a></td>
                            <td style="font-size:10px"><?php echo esc_html($r['type']); ?></td>
                            <td><?php echo $r['content_analysis']['word_count']; ?></td>
                            <td style="font-size:10px;color:#888"><?php echo esc_html($r['modified'] ?? $r['date']); ?></td>
                            <td>
                                <?php if ($r['errors'] > 0) : ?><span class="aic-badge aic-bErr"><?php echo $r['errors']; ?> خطا</span> <?php endif; ?>
                                <?php if ($r['warnings'] > 0) : ?><span class="aic-badge aic-bWarn"><?php echo $r['warnings']; ?> هشدار</span><?php endif; ?>
                                <?php if (!empty($r['issues'])) : ?>
                                    <div style="font-size:10px;color:#666;margin-top:4px;line-height:1.6">
                                    <?php foreach (array_slice($r['issues'], 0, 3) as $issue) : ?>
                                        <div>&#x1F6A9; <?php echo esc_html($issue['message']); ?></div>
                                    <?php endforeach; ?>
                                    <?php if (count($r['issues']) > 3) : ?>
                                        <div style="color:#999">+<?php echo count($r['issues']) - 3; ?> مشکل دیگر...</div>
                                    <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><div class="aic-progress" style="width:60px"><div class="aic-progress-fill" style="width:<?php echo $r['score']; ?>%;background:<?php echo $r['score']>=60?'#27ae60':($r['score']>=40?'#f39c12':'#e74c3c'); ?>"></div></div></td>
                            <td><span class="aic-grade aic-g<?php echo $r['grade']; ?>"><?php echo $r['grade']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <?php if ($post_id && !empty($reports)) :
                $r = $reports[0]; ?>
                <div class="aic-section">
                    <h2>&#x1F50D; آنالیز جزئی: <?php echo esc_html($r['title']); ?></h2>

                    <!-- Score Gauge -->
                    <div style="display:flex;align-items:center;gap:30px;margin-bottom:20px;padding:20px;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.05)">
                        <div style="position:relative;width:120px;height:120px;flex-shrink:0">
                            <svg viewBox="0 0 120 120" style="transform:rotate(-90deg)">
                                <circle cx="60" cy="60" r="50" fill="none" stroke="#eee" stroke-width="10"/>
                                <circle cx="60" cy="60" r="50" fill="none" stroke="<?php echo $r['score']>=80?'#27ae60':($r['score']>=60?'#2ecc71':($r['score']>=40?'#f39c12':'#e74c3c')); ?>" stroke-width="10" stroke-dasharray="<?php echo $r['score'] * 3.14; ?> 314" stroke-linecap="round"/>
                            </svg>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center">
                                <div style="font-size:28px;font-weight:700;color:<?php echo $r['score']>=60?'#27ae60':'#e74c3c'; ?>"><?php echo $r['score']; ?></div>
                                <div style="font-size:10px;color:#888">از 100</div>
                            </div>
                        </div>
                        <div style="flex:1">
                            <div style="font-size:18px;font-weight:700;margin-bottom:5px">نمره <?php echo $r['grade']; ?> — <?php echo $r['score']>=80?'عالی':($r['score']>=60?'خوب':($r['score']>=40?'نیاز به بهبود':'ضعیف')); ?></div>
                            <div style="font-size:12px;color:#666;margin-bottom:10px">
                                <?php echo $r['errors']; ?> خطا • <?php echo $r['warnings']; ?> هشدار • <?php echo count($r['passed']); ?> مورد موفق
                            </div>
                            <div style="height:8px;background:#eee;border-radius:4px;overflow:hidden">
                                <div style="height:100%;width:<?php echo $r['score']; ?>%;background:linear-gradient(90deg,<?php echo $r['score']>=80?'#27ae60,#2ecc71':($r['score']>=60?'#2ecc71,#27ae60':($r['score']>=40?'#f39c12,#e67e22':'#e74c3c,#c0392b')); ?>);border-radius:4px;transition:width .5s"></div>
                            </div>
                        </div>
                    </div>

                    <!-- SEO Checklist Progress -->
                    <div class="aic-section">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                            <h3 style="margin:0;font-size:13px">&#x1F4CB; چک‌لیست سئو</h3>
                            <div style="font-size:10px;color:#888">تاریخ انتشار: <?php echo esc_html($r['date']); ?> • آخرین تغییر: <?php echo esc_html($r['modified']); ?></div>
                        </div>
                        <h3>&#x1F4CB; چک‌لیست سئو</h3>
                        <?php
                        $checklist = [
                            ['title' => 'عنوان متا', 'ok' => $r['title_analysis']['optimal'], 'detail' => $r['title_analysis']['length'] . ' کاراکتر (بهینه: 30-60)'],
                            ['title' => 'توضیحات متا', 'ok' => $r['meta_analysis']['optimal'], 'detail' => $r['meta_analysis']['length'] . ' کاراکتر (بهینه: 120-160)'],
                            ['title' => 'تگ H1', 'ok' => $r['heading_analysis']['h1_count'] === 1, 'detail' => $r['heading_analysis']['h1_count'] . ' عدد (بهینه: 1)'],
                            ['title' => 'ساختار H2+', 'ok' => $r['heading_analysis']['h2_count'] >= 2, 'detail' => $r['heading_analysis']['h2_count'] . ' عدد H2, ' . $r['heading_analysis']['h3_count'] . ' عدد H3'],
                            ['title' => 'طول محتوا', 'ok' => $r['content_analysis']['word_count'] >= 300, 'detail' => $r['content_analysis']['word_count'] . ' کلمه (حداقل: 300)'],
                            ['title' => 'خوانایی', 'ok' => $r['content_analysis']['readability_flesch'] >= 60, 'detail' => 'Flesch: ' . $r['content_analysis']['readability_flesch'] . ' (حداقل: 60)'],
                            ['title' => 'تصاویر', 'ok' => $r['image_analysis']['total'] > 0, 'detail' => $r['image_analysis']['total'] . ' تصویر, ' . $r['image_analysis']['without_alt'] . ' بدون alt'],
                            ['title' => 'لینک داخلی', 'ok' => $r['link_analysis']['internal'] >= 2, 'detail' => $r['link_analysis']['internal'] . ' داخلی, ' . $r['link_analysis']['external'] . ' خارجی'],
                            ['title' => 'تصویر شاخص', 'ok' => $r['media_analysis']['has_featured_image'], 'detail' => $r['media_analysis']['has_featured_image'] ? 'موجود' : 'ندارد'],
                            ['title' => 'ساختار URL', 'ok' => $r['url_analysis']['slug_length'] <= 60, 'detail' => 'Slug: ' . $r['url_analysis']['slug_length'] . ' کاراکتر'],
                        ];
                        $ok_count = count(array_filter($checklist, fn($c) => $c['ok']));
                        $total_check = count($checklist);
                        $check_pct = round(($ok_count / $total_check) * 100);
                        ?>
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:15px">
                            <div style="flex:1;height:10px;background:#eee;border-radius:5px;overflow:hidden">
                                <div style="height:100%;width:<?php echo $check_pct; ?>%;background:linear-gradient(90deg,#27ae60,#2ecc71);border-radius:5px"></div>
                            </div>
                            <span style="font-size:13px;font-weight:700;color:<?php echo $check_pct>=70?'#27ae60':'#e74c3c'; ?>"><?php echo $ok_count . '/' . $total_check; ?> (<?php echo $check_pct; ?>%)</span>
                        </div>
                        <table class="aic-table">
                            <tr><th>آیتم</th><th>وضعیت</th><th>جزئیات</th></tr>
                            <?php foreach ($checklist as $c) : ?>
                                <tr>
                                    <td><strong><?php echo $c['title']; ?></strong></td>
                                    <td><?php if ($c['ok']) : ?><span class="aic-badge aic-bOk">&#x2705; ردیف</span><?php else : ?><span class="aic-badge aic-bErr">&#x274C; ناموفق</span><?php endif; ?></td>
                                    <td style="font-size:11px;color:#666"><?php echo $c['detail']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <div class="aic-grid-2">
                        <!-- Title & Meta -->
                        <div class="aic-section">
                            <h3>&#x1F4DD; عنوان و متا</h3>
                            <div style="background:#f8f9fa;padding:12px;border-radius:8px;margin-bottom:10px">
                                <div style="font-size:11px;color:#888">عنوان متا (پیش‌نمایش گوگل):</div>
                                <div style="color:#1a0dab;font-size:15px;font-weight:500;line-height:1.3"><?php echo esc_html($r['title_analysis']['meta_title']); ?></div>
                                <div style="color:#006621;font-size:13px"><?php echo esc_url($r['url']); ?></div>
                                <div style="color:#545454;font-size:12px;line-height:1.4"><?php echo esc_html(mb_substr($r['meta_analysis']['description'], 0, 160) ?: 'توضیحات متا وجود ندارد'); ?></div>
                            </div>
                            <table class="aic-table">
                                <tr><td>طول عنوان</td><td><strong><?php echo $r['title_analysis']['length']; ?></strong> / 60</td>
                                    <td><div style="height:6px;background:#eee;border-radius:3px;overflow:hidden"><div style="height:100%;width:<?php echo min(100, ($r['title_analysis']['length']/60)*100); ?>%;background:<?php echo $r['title_analysis']['optimal']?'#27ae60':'#e74c3c'; ?>;border-radius:3px"></div></div></td></tr>
                                <tr><td>طول توضیحات</td><td><strong><?php echo $r['meta_analysis']['length']; ?></strong> / 160</td>
                                    <td><div style="height:6px;background:#eee;border-radius:3px;overflow:hidden"><div style="height:100%;width:<?php echo min(100, ($r['meta_analysis']['length']/160)*100); ?>%;background:<?php echo $r['meta_analysis']['optimal']?'#27ae60':'#e74c3c'; ?>;border-radius:3px"></div></div></td></tr>
                                <tr><td>کلمه کلیدی در عنوان</td><td><?php echo $r['title_analysis']['has_keyword'] ? '&#x2705; بله' : '&#x274C; خیر'; ?></td><td></td></tr>
                                <tr><td>نام برند در عنوان</td><td><?php echo $r['title_analysis']['has_brand'] ? '&#x2705; بله' : '&#x274C; خیر'; ?></td><td></td></tr>
                            </table>
                        </div>

                        <!-- Content & Readability -->
                        <div class="aic-section">
                            <h3>&#x1F4CA; محتوا و خوانایی</h3>
                            <div style="display:flex;gap:15px;margin-bottom:15px">
                                <div style="flex:1;text-align:center;padding:12px;background:#f8f9fa;border-radius:8px">
                                    <div style="font-size:24px;font-weight:700;color:<?php echo $r['content_analysis']['readability_flesch']>=60?'#27ae60':'#e74c3c'; ?>"><?php echo $r['content_analysis']['readability_flesch']; ?></div>
                                    <div style="font-size:10px;color:#888">Flesch</div>
                                </div>
                                <div style="flex:1;text-align:center;padding:12px;background:#f8f9fa;border-radius:8px">
                                    <div style="font-size:24px;font-weight:700;color:#667eea"><?php echo $r['content_analysis']['readability_fog']; ?></div>
                                    <div style="font-size:10px;color:#888">Gunning Fog</div>
                                </div>
                                <div style="flex:1;text-align:center;padding:12px;background:#f8f9fa;border-radius:8px">
                                    <div style="font-size:24px;font-weight:700;color:#764ba2"><?php echo $r['content_analysis']['readability_coleman']; ?></div>
                                    <div style="font-size:10px;color:#888">Coleman-Liau</div>
                                </div>
                            </div>
                            <div style="background:#f8f9fa;padding:10px;border-radius:8px;margin-bottom:10px;font-size:12px">
                                &#x1F4A1; <strong>تفسیر:</strong>
                                <?php $fl = $r['content_analysis']['readability_flesch'];
                                if ($fl >= 80) echo 'محتوا خیلی آسان و قابل فهم برای همه.';
                                elseif ($fl >= 60) echo 'محتوا آسان و مناسب مخاطب عمومی.';
                                elseif ($fl >= 40) echo 'محتوا نسبتاً پیچیده. برای مخاطب تخصصی مناسب‌تره.';
                                else echo 'محتوا خیلی پیچیده. جملات رو کوتاه‌تر و ساده‌تر کنید.';
                                ?>
                            </div>
                            <table class="aic-table">
                                <tr><td>کلمات</td><td><strong><?php echo $r['content_analysis']['word_count']; ?></strong></td><td><div style="height:6px;background:#eee;border-radius:3px;overflow:hidden"><div style="height:100%;width:<?php echo min(100, ($r['content_analysis']['word_count']/1000)*100); ?>%;background:#667eea;border-radius:3px"></div></div></td></tr>
                                <tr><td>جملات</td><td><?php echo $r['content_analysis']['sentence_count']; ?></td><td></td></tr>
                                <tr><td>پاراگراف</td><td><?php echo $r['content_analysis']['paragraph_count']; ?></td><td></td></tr>
                                <tr><td>کلمات هر جمله</td><td><?php echo $r['content_analysis']['avg_words_per_sentence']; ?></td><td><span class="aic-badge <?php echo $r['content_analysis']['avg_words_per_sentence']<=20?'aic-bOk':'aic-bWarn'; ?>"><?php echo $r['content_analysis']['avg_words_per_sentence']<=20?'مناسب':'بلند'; ?></span></td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- Heading Structure -->
                    <div class="aic-section">
                        <h3>&#x1F3F7;&#xFE0F; ساختار عنوان‌ها (Heading Hierarchy)</h3>
                        <?php if (!empty($r['heading_analysis']['structure'])) : ?>
                            <div style="background:#f8f9fa;padding:12px;border-radius:8px;margin-bottom:10px">
                                <?php foreach ($r['heading_analysis']['structure'] as $h) : ?>
                                    <div style="padding:4px 0;border-bottom:1px solid #eee;font-size:12px;margin-left:<?php echo ($h['level']-1)*20; ?>px">
                                        <span class="aic-badge" style="background:<?php echo ['#e74c3c','#e67e22','#f1c40f','#2ecc71','#3498db','#9b59b6'][$h['level']-1] ?? '#888'; ?>;color:#fff;font-size:9px;margin-left:5px">H<?php echo $h['level']; ?></span>
                                        <?php echo esc_html($h['text']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div style="color:#888;font-size:12px;padding:10px">تگ عنوانی یافت نشد.</div>
                        <?php endif; ?>
                    </div>

                    <div class="aic-grid-2">
                        <!-- Images -->
                        <div class="aic-section">
                            <h3>&#x1F5BC;&#xFE0F; تحلیل تصاویر</h3>
                            <?php if ($r['image_analysis']['total'] > 0) : ?>
                                <div style="display:flex;gap:15px;margin-bottom:10px">
                                    <div style="flex:1;text-align:center;padding:8px;background:#d4edda;border-radius:6px">
                                        <div style="font-size:18px;font-weight:700;color:#155724"><?php echo $r['image_analysis']['with_alt']; ?></div>
                                        <div style="font-size:10px;color:#155724">با alt</div>
                                    </div>
                                    <div style="flex:1;text-align:center;padding:8px;background:<?php echo $r['image_analysis']['without_alt']>0?'#f8d7da':'#d4edda'; ?>;border-radius:6px">
                                        <div style="font-size:18px;font-weight:700;color:<?php echo $r['image_analysis']['without_alt']>0?'#721c24':'#155724'; ?>"><?php echo $r['image_analysis']['without_alt']; ?></div>
                                        <div style="font-size:10px;color:<?php echo $r['image_analysis']['without_alt']>0?'#721c24':'#155724'; ?>">بدون alt</div>
                                    </div>
                                </div>
                                <?php if (!empty($r['image_analysis']['images'])) : ?>
                                    <table class="aic-table" style="font-size:10px">
                                        <tr><th>تصویر</th><th>alt</th><th>اندازه</th></tr>
                                        <?php foreach (array_slice($r['image_analysis']['images'], 0, 5) as $img) : ?>
                                            <tr>
                                                <td style="direction:ltr;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html($img['src']); ?></td>
                                                <td><?php echo $img['has_alt'] ? '&#x2705;' : '&#x274C;'; ?></td>
                                                <td><?php echo ($img['width'] ?? '?') . 'x' . ($img['height'] ?? '?'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                <?php endif; ?>
                            <?php else : ?>
                                <div style="color:#888;font-size:12px;padding:10px;text-align:center">&#x26A0;&#xFE0F; تصویری در محتوا وجود ندارد</div>
                            <?php endif; ?>
                        </div>

                        <!-- Links -->
                        <div class="aic-section">
                            <h3>&#x1F517; تحلیل لینک‌ها</h3>
                            <?php $total_links = $r['link_analysis']['total']; ?>
                            <?php if ($total_links > 0) : ?>
                                <div style="display:flex;gap:10px;margin-bottom:10px">
                                    <div style="flex:1;height:24px;background:#3498db;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700">
                                        داخلی: <?php echo $r['link_analysis']['internal']; ?>
                                    </div>
                                    <div style="flex:1;height:24px;background:#e74c3c;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700">
                                        خارجی: <?php echo $r['link_analysis']['external']; ?>
                                    </div>
                                </div>
                                <div style="height:8px;background:#eee;border-radius:4px;overflow:hidden;display:flex">
                                    <div style="width:<?php echo ($r['link_analysis']['internal']/$total_links)*100; ?>%;background:#3498db"></div>
                                    <div style="width:<?php echo ($r['link_analysis']['external']/$total_links)*100; ?>%;background:#e74c3c"></div>
                                </div>
                                <div style="margin-top:10px;font-size:11px;color:#666">
                                    نسبت داخلی/خارجی: <strong><?php echo $r['link_analysis']['internal']; ?>:<?php echo $r['link_analysis']['external']; ?></strong>
                                    <?php if ($r['link_analysis']['nofollow'] > 0) : ?>
                                        | nofollow: <?php echo $r['link_analysis']['nofollow']; ?>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <div style="color:#888;font-size:12px;padding:10px;text-align:center">&#x26A0;&#xFE0F; لینکی در محتوا وجود ندارد</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Social Preview -->
                    <div class="aic-section">
                        <h3>&#x1F4E2; پیش‌نمایش شبکه اجتماعی</h3>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                            <div style="border:1px solid #ddd;border-radius:8px;overflow:hidden">
                                <div style="background:#3b5998;color:#fff;padding:8px 12px;font-size:11px;font-weight:700">Facebook</div>
                                <div style="padding:12px">
                                    <div style="font-size:13px;font-weight:700;color:#1c1e21;margin-bottom:4px"><?php echo esc_html($r['social_analysis']['og_title'] ?: $r['title_analysis']['meta_title']); ?></div>
                                    <div style="font-size:12px;color:#606770;margin-bottom:4px"><?php echo esc_html(mb_substr($r['social_analysis']['og_desc'] ?: $r['meta_analysis']['description'], 0, 100)); ?></div>
                                    <div style="font-size:11px;color:#90949c"><?php echo esc_url($r['url']); ?></div>
                                </div>
                            </div>
                            <div style="border:1px solid #ddd;border-radius:8px;overflow:hidden">
                                <div style="background:#1da1f2;color:#fff;padding:8px 12px;font-size:11px;font-weight:700">Twitter</div>
                                <div style="padding:12px">
                                    <div style="font-size:13px;font-weight:700;color:#14171a;margin-bottom:4px"><?php echo esc_html($r['social_analysis']['og_title'] ?: $r['title_analysis']['meta_title']); ?></div>
                                    <div style="font-size:12px;color:#657786;margin-bottom:4px"><?php echo esc_html(mb_substr($r['social_analysis']['og_desc'] ?: $r['meta_analysis']['description'], 0, 100)); ?></div>
                                    <div style="font-size:11px;color:#aab8c2"><?php echo esc_url($r['url']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:10px;font-size:11px">
                            OG Title: <?php echo $r['social_analysis']['og_title'] ? '<span class="aic-badge aic-bOk">موجود</span>' : '<span class="aic-badge aic-bErr">ندارد</span>'; ?>
                            OG Desc: <?php echo $r['social_analysis']['og_desc'] ? '<span class="aic-badge aic-bOk">موجود</span>' : '<span class="aic-badge aic-bErr">ندارد</span>'; ?>
                            OG Image: <?php echo $r['social_analysis']['og_image'] ? '<span class="aic-badge aic-bOk">موجود</span>' : '<span class="aic-badge aic-bErr">ندارد</span>'; ?>
                        </div>
                    </div>

                    <!-- Keyword Analysis -->
                    <?php if (!empty($r['keyword_analysis']['keyword'])) : ?>
                    <div class="aic-section">
                        <h3>&#x1F511; کلمه کلیدی: <strong><?php echo esc_html($r['keyword_analysis']['keyword']); ?></strong></h3>
                        <div style="display:flex;gap:15px;margin-bottom:15px">
                            <div style="text-align:center;padding:12px;background:<?php echo $r['keyword_analysis']['optimal']?'#d4edda':'#f8d7da'; ?>;border-radius:8px;flex:1">
                                <div style="font-size:24px;font-weight:700;color:<?php echo $r['keyword_analysis']['optimal']?'#155724':'#721c24'; ?>"><?php echo $r['keyword_analysis']['density']; ?>%</div>
                                <div style="font-size:10px;color:<?php echo $r['keyword_analysis']['optimal']?'#155724':'#721c24'; ?>">چگالی (بهینه: 1-3%)</div>
                            </div>
                            <div style="text-align:center;padding:12px;background:#f8f9fa;border-radius:8px;flex:1">
                                <div style="font-size:24px;font-weight:700;color:#667eea"><?php echo $r['keyword_analysis']['total_count']; ?></div>
                                <div style="font-size:10px;color:#888">تعداد تکرار</div>
                            </div>
                        </div>
                        <h4 style="font-size:12px;margin:10px 0 5px">موقعیت کلمه کلیدی:</h4>
                        <table class="aic-table">
                            <tr><td>در عنوان متا</td><td><?php echo $r['keyword_analysis']['placement']['in_title'] ? '&#x2705; موجود' : '&#x274C; موجود نیست'; ?></td></tr>
                            <tr><td>در توضیحات متا</td><td><?php echo $r['keyword_analysis']['placement']['in_meta_desc'] ? '&#x2705; موجود' : '&#x274C; موجود نیست'; ?></td></tr>
                            <tr><td>در هدینگ‌ها</td><td><?php echo $r['keyword_analysis']['placement']['in_headings'] ? '&#x2705; موجود' : '&#x274C; موجود نیست'; ?></td></tr>
                            <tr><td>در پاراگراف اول</td><td><?php echo $r['keyword_analysis']['placement']['in_first_paragraph'] ? '&#x2705; موجود' : '&#x274C; موجود نیست'; ?></td></tr>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Issues -->
                    <?php if (!empty($r['issues'])) : ?>
                    <div class="aic-section">
                        <h3>&#x26A0;&#xFE0F; مشکلات و راه‌حل‌ها (<?php echo count($r['issues']); ?> مورد)</h3>
                        <?php
                        $issue_type_labels = ['title' => 'عنوان', 'meta' => 'متا', 'heading' => 'هدینگ', 'content' => 'محتوا', 'images' => 'تصاویر', 'links' => 'لینک‌ها', 'media' => 'رسانه', 'url' => 'آدرس', 'readability' => 'خوانایی'];
                        foreach ($r['issues'] as $issue) : ?>
                            <div class="aic-error-item" style="border-right-color:<?php echo $issue['severity'] === 'critical' ? '#e74c3c' : ($issue['severity'] === 'warning' ? '#f39c12' : '#3498db'); ?>;margin-bottom:10px">
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <div class="aic-error-title" style="flex:1">
                                        <?php echo $issue['severity'] === 'critical' ? '&#x274C;' : ($issue['severity'] === 'warning' ? '&#x26A0;&#xFE0F;' : '&#x2139;&#xFE0F;'); ?>
                                        <?php echo esc_html($issue['message']); ?>
                                    </div>
                                    <span class="aic-badge" style="background:<?php echo $issue['severity'] === 'critical' ? '#f8d7da' : ($issue['severity'] === 'warning' ? '#fff3cd' : '#d1ecf1'); ?>;color:<?php echo $issue['severity'] === 'critical' ? '#721c24' : ($issue['severity'] === 'warning' ? '#856404' : '#0c5460'); ?>;white-space:nowrap"><?php echo $issue_type_labels[$issue['type']] ?? $issue['type']; ?></span>
                                </div>
                                <div class="aic-error-solution" style="margin-top:5px;padding:8px;background:#f0faf0;border-radius:4px;border-right:3px solid #27ae60">
                                    &#x2705; <strong>راه‌حل:</strong> <?php echo esc_html($issue['fix']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Passed -->
                    <?php if (!empty($r['passed'])) : ?>
                    <div class="aic-section">
                        <h3>&#x2705; موارد موفق (<?php echo count($r['passed']); ?> مورد)</h3>
                        <div style="display:flex;flex-wrap:wrap;gap:6px">
                            <?php
                            $passed_labels = [
                                'title_length' => 'طول عنوان مناسب', 'title_keyword' => 'کلمه کلیدی در عنوان',
                                'title_brand' => 'نام برند در عنوان', 'meta_desc_length' => 'طول توضیحات متا مناسب',
                                'meta_desc_keyword' => 'کلمه کلیدی در متا', 'h1_count' => 'تگ H1 صحیح',
                                'h2_structure' => 'ساختار H2 مناسب', 'heading_structure' => 'ساختار عنوان‌ها خوب',
                                'content_length' => 'طول محتوا مناسب', 'content_depth' => 'محتوای عمیق',
                                'readability' => 'خوانایی خوب', 'image_alt' => 'alt تصاویر کامل',
                                'internal_links' => 'لینک داخلی کافی', 'slug_length' => 'طول URL مناسب',
                                'slug_keyword' => 'کلمه کلیدی در URL', 'featured_image' => 'تصویر شاخص موجود',
                                'open_graph' => 'تگ‌های OG کامل',
                            ];
                            foreach ($r['passed'] as $p) :
                                $label = $passed_labels[$p] ?? $p;
                            ?>
                                <span class="aic-badge aic-bOk" style="font-size:11px">&#x2705; <?php echo esc_html($label); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'broken') : ?>
            <?php $result = null;
            if (isset($_POST['aic_check_links']) && check_admin_referer('aic_broken_links')) {
                $result = $seo->check_broken_links_deep((int)($_POST['limit'] ?? 100));
            } ?>
            <div class="aic-section">
                <h2>&#x2699;&#xFE0F; تنظیمات</h2>
                <form method="post" style="display:flex;align-items:center;gap:10px">
                    <?php wp_nonce_field('aic_broken_links'); ?>
                    <input type="hidden" name="aic_seo_tab" value="broken">
                    <label style="font-size:12px">حداکثر صفحات:</label>
                    <input type="number" name="limit" value="100" min="10" max="500" style="width:80px">
                    <button type="submit" name="aic_check_links" class="aic-btn aic-btnPri">شروع بررسی</button>
                </form>
            </div>
            <?php if ($result) : ?>
            <div class="aic-cards" style="grid-template-columns:repeat(3,1fr)">
                <div class="aic-card"><div class="aic-card-num"><?php echo $result['total_checked']; ?></div><div class="aic-card-label">کل بررسی شده</div></div>
                <div class="aic-card"><div class="aic-card-num" style="color:#27ae60"><?php echo $result['working_count']; ?></div><div class="aic-card-label">سالم</div></div>
                <div class="aic-card"><div class="aic-card-num" style="color:#e74c3c"><?php echo $result['broken_count']; ?></div><div class="aic-card-label">شکسته</div></div>
            </div>
            <?php if (!empty($result['broken'])) : ?>
            <div class="aic-section">
                <h2>&#x274C; لینک‌های شکسته</h2>
                <table class="aic-table">
                    <tr><th>آدرس</th><th>کد</th><th>صفحه</th></tr>
                    <?php foreach ($result['broken'] as $link) : ?>
                        <tr>
                            <td><code style="font-size:10px;direction:ltr"><?php echo esc_html(mb_substr($link['url'], 0, 60)); ?></code></td>
                            <td><span class="aic-badge aic-bErr"><?php echo $link['status']; ?></span></td>
                            <td style="font-size:11px"><?php echo esc_html($link['found_on'][0]['title'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($tab === 'analysis') : ?>
            <?php $post_id = (int)($_GET['post_id'] ?? 0); $analysis = $post_id ? $seo->content_analysis($post_id) : null;
            $posts = get_posts(['post_type' => ['post', 'page'], 'posts_per_page' => 50, 'post_status' => 'publish']); ?>
            <div class="aic-section">
                <h2>&#x1F4DD; انتخاب صفحه</h2>
                <form method="get" style="display:flex;align-items:center;gap:10px">
                    <input type="hidden" name="page" value="aic-seo">
                    <input type="hidden" name="aic_seo_tab" value="analysis">
                    <select name="post_id" style="max-width:400px">
                        <option value="0">-- انتخاب کنید --</option>
                        <?php foreach ($posts as $p) : ?>
                            <option value="<?php echo $p->ID; ?>" <?php selected($post_id, $p->ID); ?>><?php echo esc_html($p->post_title . ' (' . $p->post_type . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="aic-btn aic-btnPri">آنالیز</button>
                </form>
            </div>
            <?php if ($analysis) : ?>
            <div class="aic-cards" style="grid-template-columns:repeat(4,1fr)">
                <div class="aic-card"><div class="aic-card-num"><?php echo $analysis['word_count']; ?></div><div class="aic-card-label">کلمات</div></div>
                <div class="aic-card"><div class="aic-card-num"><?php echo $analysis['sentence_count']; ?></div><div class="aic-card-label">جملات</div></div>
                <div class="aic-card"><div class="aic-card-num" style="color:<?php echo $analysis['readability_score'] >= 60 ? '#27ae60' : '#e74c3c'; ?>"><?php echo $analysis['readability_score']; ?></div><div class="aic-card-label">خوانایی</div></div>
                <div class="aic-card"><div class="aic-card-num" style="color:#667eea"><?php echo $analysis['heading_count']; ?></div><div class="aic-card-label">تگ عنوان</div></div>
            </div>
            <div class="aic-grid-2">
                <div class="aic-section">
                    <h2>&#x1F4CA; کلمات کلیدی</h2>
                    <table class="aic-table">
                        <tr><th>کلمه</th><th>تعداد</th><th>چگالی</th><th>وضعیت</th></tr>
                        <?php foreach ($analysis['keywords']['keywords'] as $kw) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($kw['word']); ?></strong></td>
                                <td><?php echo $kw['count']; ?></td>
                                <td><?php echo $kw['density']; ?>%</td>
                                <td><?php if ($kw['status'] === 'good') : ?><span class="aic-badge aic-bOk">بهینه</span><?php elseif ($kw['status'] === 'over') : ?><span class="aic-badge aic-bErr">زیاد</span><?php else : ?><span class="aic-badge aic-bWarn">کم</span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="aic-section">
                    <h2>&#x1F4A1; پیشنهادات</h2>
                    <?php if (!empty($analysis['suggestions'])) : ?>
                        <?php foreach ($analysis['suggestions'] as $s) : ?>
                            <div class="aic-error-item" style="border-right-color:<?php echo $s['priority'] === 'high' ? '#e74c3c' : '#f39c12'; ?>">
                                <div class="aic-error-title"><?php echo esc_html($s['message']); ?></div>
                                <div class="aic-error-solution"><?php echo esc_html($s['detail']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p style="color:#27ae60;font-size:12px">&#x2705; محتوا بهینه است!</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'generator') : ?>
            <?php
            if (isset($_POST['aic_optimize_meta']) && check_admin_referer('aic_optimize_meta')) {
                $seo->optimize_meta((int)($_POST['optimize_post_id'] ?? 0));
            }
            $content_history = get_option('aic_content_history', []);
            ?>
            <style>
            .aic-gen-form{display:grid;gap:12px}
            .aic-gen-row{display:grid;grid-template-columns:140px 1fr;align-items:start;gap:10px}
            .aic-gen-row label{font-size:12px;font-weight:600;color:#374151;padding-top:8px}
            .aic-gen-input,.aic-gen-select,.aic-gen-textarea{width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;font-family:Tahoma;transition:border-color .2s}
            .aic-gen-input:focus,.aic-gen-select:focus,.aic-gen-textarea:focus{border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,.15)}
            .aic-gen-textarea{min-height:100px;resize:vertical;line-height:1.7}
            .aic-gen-btn{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-family:Tahoma;cursor:pointer;transition:all .2s;font-weight:600}
            .aic-gen-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(102,126,234,.3)}
            .aic-gen-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
            .aic-gen-result{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-top:15px}
            .aic-gen-toolbar{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center}
            .aic-gen-toolbar button{padding:6px 12px;border-radius:6px;border:1px solid #d1d5db;background:#fff;font-size:11px;cursor:pointer;font-family:Tahoma;transition:all .2s}
            .aic-gen-toolbar button:hover{background:#f3f4f6;border-color:#667eea}
            .aic-gen-preview{border:1px solid #e5e7eb;border-radius:8px;padding:15px;min-height:200px;line-height:1.8;font-size:13px;background:#fafafa;overflow-y:auto;max-height:600px}
            .aic-gen-preview h1{font-size:20px;margin:0 0 15px;color:#1f2937;border-bottom:2px solid #667eea;padding-bottom:8px}
            .aic-gen-preview h2{font-size:16px;margin:20px 0 10px;color:#374151}
            .aic-gen-preview h3{font-size:14px;margin:15px 0 8px;color:#4b5563}
            .aic-gen-preview p{margin:8px 0;color:#374151}
            .aic-gen-preview ul,.aic-gen-preview ol{margin:8px 0 8px 20px;color:#374151}
            .aic-gen-preview strong{color:#1f2937}
            .aic-gen-images-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
            .aic-gen-img-card{border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;cursor:pointer;transition:all .2s}
            .aic-gen-img-card:hover{border-color:#667eea;transform:scale(1.02)}
            .aic-gen-img-card.selected{border:2px solid #667eea}
            .aic-gen-img-card img{width:100%;height:120px;object-fit:cover}
            .aic-gen-img-card .alt-text{font-size:10px;padding:4px 6px;color:#666;background:#f9fafb}
            .aic-gen-progress{display:none;margin:15px 0;padding:15px;background:#f0f4ff;border-radius:8px;text-align:center}
            .aic-gen-progress.active{display:block}
            .aic-gen-progress .spinner{margin:0 auto 10px}
            .aic-word-slider{display:flex;align-items:center;gap:10px}
            .aic-word-slider input[type=range]{flex:1;accent-color:#667eea}
            .aic-word-slider .val{background:#667eea;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:700;min-width:50px;text-align:center}
            .aic-history-item{display:flex;align-items:center;gap:10px;padding:8px;border-bottom:1px solid #f0f0f0;font-size:11px}
            .aic-history-item:last-child{border-bottom:none}
            </style>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px">
                <div>
                    <!-- Main Generator Form -->
                    <div class="aic-section">
                        <h2>&#x1F916; تولید محتوا با AI Agent</h2>
                        <p style="font-size:11px;color:#666;margin-bottom:15px">موضوع و دستورالعمل رو مشخص کن، ایجنت محتوای حرفه‌ای تولید می‌کنه.</p>

                        <div class="aic-gen-form">
                            <div class="aic-gen-row">
                                <label>&#x1F4CB; موضوع اصلی</label>
                                <input type="text" id="aic-gen-topic" class="aic-gen-input" placeholder="مثلاً: آموزش طراحی سایت فروشگاهی" value="">
                            </div>

                            <div class="aic-gen-row">
                                <label>&#x1F517; صفحه مرتبط</label>
                                <select id="aic-gen-related" class="auc-gen-select" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;font-family:Tahoma">
                                    <option value="0">— موضوع جدید (مرتبط با هیچ صفحه‌ای نیست) —</option>
                                    <?php foreach (get_posts(['post_type' => ['post', 'page'], 'posts_per_page' => 30, 'post_status' => 'publish', 'orderby' => 'modified', 'order' => 'DESC']) as $p) : ?>
                                        <option value="<?php echo $p->ID; ?>"><?php echo esc_html($p->post_title . ' (' . $p->post_type . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="aic-gen-row">
                                <label>&#x1F4DD; نوع محتوا</label>
                                <select id="aic-gen-type" class="aic-gen-select" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;font-family:Tahoma">
                                    <option value="article">مقاله وبلاگی</option>
                                    <option value="product_desc">توضیحات محصول</option>
                                    <option value="landing">لندینگ پیج</option>
                                    <option value="faq">سوالات متداول (FAQ)</option>
                                    <option value="service">توضیحات خدمات</option>
                                </select>
                            </div>

                            <div class="aic-gen-row">
                                <label>&#x1F4CF; تعداد کلمات</label>
                                <div class="aic-word-slider">
                                    <input type="range" id="aic-gen-words" min="100" max="3000" step="100" value="500" oninput="document.getElementById('aic-gen-words-val').textContent=this.value+' کلمه'">
                                    <span class="val" id="aic-gen-words-val">500 کلمه</span>
                                </div>
                            </div>

                            <div class="aic-gen-row">
                                <label>&#x1F4AC; دستورالعمل</label>
                                <textarea id="aic-gen-instructions" class="aic-gen-textarea" placeholder="دقیقاً بنویس چه محتوایی می‌خوای...&#10;&#10;مثال: مقاله‌ای درباره مزایای طراحی سایت فروشگاهی بنویس. مخاطب کارآفرینان هستند. لحن صمیمی باشه. حتماً بخش مقایسه با رقبا داشته باشه."></textarea>
                            </div>

                            <div class="aic-gen-row">
                                <label>&#x1F5BC;&#xFE0F; تصاویر</label>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding-top:5px">
                                    <input type="checkbox" id="aic-gen-images" style="accent-color:#667eea;width:16px;height:16px">
                                    <span style="font-size:12px;color:#374151">پیشنهاد تصاویر مرتبط</span>
                                </label>
                            </div>

                            <div class="aic-gen-row">
                                <label></label>
                                <div style="display:flex;gap:10px">
                                    <button class="aic-gen-btn" id="aic-gen-btn" onclick="aicGenerateContent()">&#x26A1; تولید محتوا</button>
                                    <button class="aic-gen-btn" style="background:linear-gradient(135deg,#10b981,#059669)" onclick="aicGenAndSave()">&#x1F4BE; تولید و ذخیره در صفحه</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress -->
                    <div id="aic-gen-progress" class="aic-gen-progress">
                        <div class="spinner is-active" style="width:30px;height:30px"></div>
                        <div style="font-size:12px;color:#667eea;font-weight:600" id="aic-gen-progress-text">ایجنت در حال تولید محتوا...</div>
                        <div style="font-size:10px;color:#888;margin-top:4px">این عملیات ممکن است ۱۵-۳۰ ثانیه طول بکشد</div>
                    </div>

                    <!-- Result -->
                    <div id="aic-gen-result" style="display:none">
                        <div class="aic-gen-result">
                            <div class="aic-gen-toolbar">
                                <strong style="font-size:12px;color:#374151;flex:1">&#x1F4DD; محتوای تولید شده</strong>
                                <span id="aic-gen-wordcount" style="font-size:10px;color:#888;background:#f3f4f6;padding:3px 8px;border-radius:10px"></span>
                                <button onclick="aicGenCopyHTML()">&#x1F4CB; کپی HTML</button>
                                <button onclick="aicGenCopyText()">&#x1F4C4; کپی متن</button>
                                <button onclick="aicGenPreview()">&#x1F441;&#xFE0F; پیش‌نمایش</button>
                            </div>
                            <div id="aic-gen-html-source" style="display:none"></div>
                            <div id="aic-gen-preview" class="aic-gen-preview"></div>
                        </div>

                        <!-- Image Suggestions -->
                        <div id="aic-gen-images-section" style="display:none">
                            <div class="aic-section" style="margin-top:12px">
                                <h2>&#x1F5BC;&#xFE0F; تصاویر پیشنهادی</h2>
                                <div id="aic-gen-images-grid" class="aic-gen-images-grid"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- Search & Add Images -->
                    <div class="aic-section">
                        <h2>&#x1F50D; جستجوی تصویر</h2>
                        <div style="display:flex;gap:8px;margin-bottom:10px">
                            <input type="text" id="aic-img-search" class="aic-gen-input" placeholder="عبارت جستجو..." style="flex:1">
                            <button onclick="aicSearchImages()" style="background:#667eea;color:#fff;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:11px;font-family:Tahoma">جستجو</button>
                        </div>
                        <div id="aic-img-results" style="max-height:300px;overflow-y:auto"></div>
                    </div>

                    <!-- History -->
                    <div class="aic-section">
                        <h2>&#x1F4C5; تاریخچه تولید</h2>
                        <?php if (!empty($content_history)) : ?>
                            <?php foreach (array_reverse(array_slice($content_history, -10)) as $h) : ?>
                                <div class="aic-history-item">
                                    <span style="font-size:10px;color:#888"><?php echo esc_html(mb_substr($h['topic'], 0, 20)); ?></span>
                                    <span style="font-size:10px;color:#667eea;font-weight:600"><?php echo $h['words']; ?> کلمه</span>
                                    <span style="font-size:9px;color:#aaa"><?php echo esc_html($h['time']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p style="font-size:11px;color:#999;text-align:center">هنوز محتوایی تولید نشده</p>
                        <?php endif; ?>
                    </div>

                    <!-- Meta Optimizer -->
                    <div class="aic-section">
                        <h2>&#x2699;&#xFE0F; بهینه‌سازی متا</h2>
                        <form method="post">
                            <?php wp_nonce_field('aic_optimize_meta'); ?>
                            <input type="hidden" name="aic_seo_tab" value="generator">
                            <select name="optimize_post_id" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:11px;font-family:Tahoma;margin-bottom:8px">
                                <?php foreach (get_posts(['post_type' => ['post', 'page'], 'posts_per_page' => 20, 'post_status' => 'publish']) as $p) : ?>
                                    <option value="<?php echo $p->ID; ?>"><?php echo esc_html(mb_substr($p->post_title, 0, 35)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="aic_optimize_meta" class="aic-gen-btn" style="width:100%;padding:8px;font-size:11px">بهینه‌سازی خودکار متا</button>
                        </form>
                    </div>
                </div>
            </div>

            <script>
            function aicGenerateContent() {
                var btn = document.getElementById('aic-gen-btn');
                var progress = document.getElementById('aic-gen-progress');
                var result = document.getElementById('aic-gen-result');
                var topic = document.getElementById('aic-gen-topic').value;
                var instructions = document.getElementById('aic-gen-instructions').value;

                if (!topic && !instructions) {
                    alert('موضوع یا دستورالعمل وارد کنید');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '&#x23F3; در حال تولید...';
                progress.classList.add('active');
                result.style.display = 'none';

                var data = new FormData();
                data.append('action', 'aic_generate_content');
                data.append('nonce', '<?php echo wp_create_nonce('aic_ajax'); ?>');
                data.append('topic', topic);
                data.append('related_post_id', document.getElementById('aic-gen-related').value);
                data.append('instructions', instructions);
                data.append('word_limit', document.getElementById('aic-gen-words').value);
                data.append('content_type', document.getElementById('aic-gen-type').value);
                data.append('include_images', document.getElementById('aic-gen-images').checked ? '1' : '');

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        btn.disabled = false;
                        progress.classList.remove('active');

                        if (d.success) {
                            btn.innerHTML = '&#x2705; تولید تمام شد!';
                            btn.style.background = '#10b981';
                            result.style.display = 'block';
                            document.getElementById('aic-gen-html-source').innerHTML = d.data.content;
                            document.getElementById('aic-gen-preview').innerHTML = d.data.content;
                            document.getElementById('aic-gen-wordcount').textContent = d.data.word_count + ' / ' + d.data.target_words + ' کلمه';
                            if (d.data.method === 'ai-agent') {
                                document.getElementById('aic-gen-wordcount').textContent += ' • AI Agent';
                            }
                            result.scrollIntoView({ behavior: 'smooth' });
                            setTimeout(function() {
                                btn.innerHTML = '&#x26A1; تولید محتوا';
                                btn.style.background = '';
                            }, 3000);
                        } else {
                            btn.innerHTML = '&#x26A1; تولید محتوا';
                            alert(d.data);
                        }
                    })
                    .catch(function(e) {
                        btn.disabled = false;
                        btn.innerHTML = '&#x26A1; تولید محتوا';
                        progress.classList.remove('active');
                        alert('خطا: ' + e.message);
                    });
            }

            function aicGenAndSave() {
                var btn = event.target;
                btn.disabled = true;
                btn.innerHTML = '&#x23F3; در حال تولید و ذخیره...';

                aicGenerateContent();
            }

            function aicGenCopyHTML() {
                var src = document.getElementById('aic-gen-html-source').innerHTML;
                navigator.clipboard.writeText(src);
                alert('HTML کپی شد!');
            }

            function aicGenCopyText() {
                var src = document.getElementById('aic-gen-preview').innerText;
                navigator.clipboard.writeText(src);
                alert('متن کپی شد!');
            }

            function aicGenPreview() {
                var preview = document.getElementById('aic-gen-preview');
                var src = document.getElementById('aic-gen-html-source').innerHTML;
                var win = window.open('', '_blank', 'width=800,height=600');
                win.document.write('<html><head><title>پیش‌نمایش محتوا</title><style>body{font-family:Tahoma,sans-serif;max-width:800px;margin:20px auto;padding:20px;line-height:1.8;color:#333}h1{color:#1f2937}h2{color:#374151;margin-top:20px}img{max-width:100%}</style></head><body>' + src + '</body></html>');
                win.document.close();
            }

            function aicSearchImages() {
                var query = document.getElementById('aic-img-search').value;
                if (!query) return;
                var container = document.getElementById('aic-img-results');
                container.innerHTML = '<div style="text-align:center;padding:15px;color:#888;font-size:11px">در حال جستجو...</div>';

                var data = new FormData();
                data.append('action', 'aic_search_images');
                data.append('nonce', '<?php echo wp_create_nonce('aic_ajax'); ?>');
                data.append('query', query);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success && d.data.images.length > 0) {
                            var html = '';
                            d.data.images.forEach(function(img) {
                                html += '<div style="margin-bottom:8px;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;cursor:pointer" onclick="navigator.clipboard.writeText(\'' + img.full + '\');this.style.borderColor=\'#667eea\';this.querySelector(\'.copy-status\').textContent=\'کپی شد!\'">';
                                html += '<img src="' + img.url + '" style="width:100%;height:100px;object-fit:cover" onerror="this.style.display=\'none\'">';
                                html += '<div style="padding:4px 6px;font-size:9px;color:#888;display:flex;justify-content:space-between"><span>' + (img.source || '') + '</span><span class="copy-status"></span></div>';
                                html += '</div>';
                            });
                            container.innerHTML = html;
                        } else {
                            container.innerHTML = '<div style="text-align:center;padding:15px;color:#999;font-size:11px">تصویری یافت نشد</div>';
                        }
                    })
                    .catch(function(e) {
                        container.innerHTML = '<div style="text-align:center;padding:15px;color:#e74c3c;font-size:11px">خطا: ' + e.message + '</div>';
                    });
            }

            function aicSearchImagesFor(query) {
                document.getElementById('aic-img-search').value = query;
                aicSearchImages();
            }
            </script>

        <?php elseif ($tab === 'robots') : ?>
            <?php
            if (isset($_POST['aic_save_robots']) && check_admin_referer('aic_robots')) {
                $seo->save_robots_txt($_POST['robots_content'] ?? '');
            }
            $data = $seo->get_robots_txt(); ?>
            <div class="aic-grid-2">
                <div class="aic-section">
                    <h2>&#x270F;&#xFE0F; ویرایش فایل</h2>
                    <form method="post">
                        <?php wp_nonce_field('aic_robots'); ?>
                        <input type="hidden" name="aic_seo_tab" value="robots">
                        <textarea name="robots_content" style="width:100%;height:300px;font-family:monospace;font-size:12px;padding:10px;border:1px solid #ddd;border-radius:6px;direction:ltr;text-align:left"><?php echo esc_textarea($data['content']); ?></textarea>
                        <p style="font-size:11px;color:#888;margin-top:5px">مسیر: <code><?php echo $data['path']; ?></code></p>
                        <p class="submit"><button type="submit" name="aic_save_robots" class="button button-primary">ذخیره robots.txt</button></p>
                    </form>
                </div>
                <div class="aic-section">
                    <h2>&#x1F4D6; راهنما</h2>
                    <div style="font-size:12px;line-height:2">
                        <div><code>User-agent: *</code> - اعمال روی همه ربات‌ها</div>
                        <div><code>Disallow: /path/</code> - مسدود کردن مسیر</div>
                        <div><code>Allow: /path/</code> - اجازه دسترسی</div>
                        <div><code>Sitemap: URL</code> - آدرس نقشه سایت</div>
                        <hr style="margin:10px 0">
                        <pre style="background:#f6f6f6;padding:8px;border-radius:4px;font-size:10px;margin-top:5px">User-agent: *
Allow: /
Disallow: /wp-admin/
Disallow: /wp-includes/
Sitemap: <?php echo home_url('/sitemap.xml'); ?></pre>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// ======================== 4. ANALYTICS ========================
function aic_page_analytics() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $tp = aic_count('post'); $tg = aic_count('page'); $tpd = aic_count('product');
    $tc = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='1'");
    $tu = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
    ?>
    <div class="aic-wrap">
        <div class="aic-header"><h1>&#x1F4CA; آمار</h1></div>
        <div class="aic-grid-3">
            <div class="aic-card"><div class="aic-card-num"><?php echo $tp; ?></div><div class="aic-card-label">نوشته</div></div>
            <div class="aic-card"><div class="aic-card-num"><?php echo $tg; ?></div><div class="aic-card-label">صفحه</div></div>
            <div class="aic-card"><div class="aic-card-num"><?php echo $tpd; ?></div><div class="aic-card-label">محصول</div></div>
            <div class="aic-card"><div class="aic-card-num"><?php echo $tc; ?></div><div class="aic-card-label">دیدگاه</div></div>
            <div class="aic-card"><div class="aic-card-num"><?php echo $tu; ?></div><div class="aic-card-label">کاربر</div></div>
        </div>
        <div class="aic-grid-2">
            <div class="aic-section"><h2>&#x1F525; محبوب‌ترین</h2>
            <?php $pop = $wpdb->get_results("SELECT post_id, meta_value as v FROM {$wpdb->postmeta} WHERE meta_key='post_views_count' ORDER BY CAST(meta_value AS UNSIGNED) DESC LIMIT 5", ARRAY_A);
            if ($pop) : ?><table class="aic-table"><tr><th>#</th><th>صفحه</th><th>بازدید</th></tr>
            <?php $r=1; foreach($pop as $p) { $post=get_post($p['post_id']); if($post):?><tr><td><?php echo $r++; ?></td><td><a href="<?php echo get_edit_post_link($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a></td><td><strong><?php echo number_format($p['v']); ?></strong></td></tr><?php endif; }?>
            </table>
            <?php else : ?>
                <p style="color:#888;font-size:12px">داده‌ای نیست.</p>
            <?php endif; ?></div>
            <div class="aic-section"><h2>&#x1F4AC; دیدگاه‌ها</h2>
            <?php $coms = get_comments(['number'=>5,'status'=>'approve']); if($coms):?><table class="aic-table"><tr><th>نویسنده</th><th>متن</th><th>تاریخ</th></tr>
            <?php foreach($coms as $c):?>
            <tr><td><?php echo esc_html($c->comment_author); ?></td><td style="font-size:11px"><?php echo esc_html(wp_trim_words($c->comment_content,8)); ?></td><td style="font-size:10px"><?php echo $c->comment_date; ?></td></tr>
            <?php endforeach; ?>
            </table>
            <?php else : ?>
                <p style="color:#888;font-size:12px">نداریم.</p>
            <?php endif; ?></div>
        </div>
    </div>
    <?php
}

// ======================== 5. CONTENT ========================
function aic_page_content() {
    if (!current_user_can('manage_options')) return;
    $types = ['post'=>'نوشته‌ها','page'=>'صفحات','product'=>'محصولات'];
    ?>
    <div class="aic-wrap">
        <div class="aic-header"><h1>&#x1F4DD; محتوا</h1></div>
        <?php foreach ($types as $type => $label) :
            $posts = get_posts(['post_type'=>$type,'posts_per_page'=>15,'post_status'=>'publish']); ?>
            <div class="aic-section"><h2><?php echo $label; ?> (<?php echo count($posts); ?>)</h2>
            <?php if ($posts) : ?><table class="aic-table"><tr><th>عنوان</th><th>تاریخ</th><th>عملیات</th></tr>
            <?php foreach ($posts as $p) : ?><tr>
                <td><?php echo esc_html(mb_substr($p->post_title,0,35)); ?></td>
                <td style="font-size:10px"><?php echo $p->post_date; ?></td>
                <td><a href="<?php echo get_edit_post_link($p->ID); ?>" class="aic-btn aic-btnPri">ویرایش</a> <a href="<?php echo get_permalink($p->ID); ?>" class="aic-btn aic-btnSec" target="_blank">مشاهده</a></td>
            </tr><?php endforeach; ?></table><?php else: echo '<p style="color:#888;font-size:12px">خالیه.</p>'; endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// ======================== 6. SECURITY + DEVOPS ========================
function aic_page_security() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $log = get_option('aic_log_requests', '1');
    $bots = get_option('aic_block_bad_bots', '0');

    // Server health
    $php_ver = PHP_VERSION;
    $mysql_ver = $wpdb->get_var("SELECT VERSION()");
    $mem = ini_get('memory_limit');
    $disk_total = @disk_total_space('/') ?: 'N/A';
    $disk_free = @disk_free_space('/') ?: 'N/A';

    // Scan results
    $last_scan = get_option('aic_last_scan', null);

    // Failed logins
    $log_table = $wpdb->prefix . 'aic_logs';
    $has_logs = $wpdb->get_var("SHOW TABLES LIKE '$log_table'") === $log_table;
    $failed_logins = 0;
    if ($has_logs) {
        $failed_logins = (int) $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE method='FAILED'");
    }

    // Blocked IPs
    $blocked_ips = get_option('aic_blocked_ips', []);
    ?>
    <div class="aic-wrap">
        <div class="aic-header"><h1>&#x1F6E1; امنیت و دواپس</h1></div>

        <!-- Server Health -->
        <div class="aic-section">
            <h2>&#x1F3E0; سلامت سرور</h2>
            <div class="aic-grid-4">
                <div style="text-align:center;padding:10px;background:#f8f9fa;border-radius:8px">
                    <div style="font-size:18px;font-weight:700;color:<?php echo version_compare($php_ver, '8.0', '>=') ? '#27ae60' : '#e74c3c'; ?>"><?php echo $php_ver; ?></div>
                    <div style="font-size:10px;color:#888">PHP</div>
                </div>
                <div style="text-align:center;padding:10px;background:#f8f9fa;border-radius:8px">
                    <div style="font-size:18px;font-weight:700;color:#667eea"><?php echo $mysql_ver; ?></div>
                    <div style="font-size:10px;color:#888">MySQL</div>
                </div>
                <div style="text-align:center;padding:10px;background:#f8f9fa;border-radius:8px">
                    <div style="font-size:18px;font-weight:700;color:#667eea"><?php echo $mem; ?></div>
                    <div style="font-size:10px;color:#888">حافظه</div>
                </div>
                <div style="text-align:center;padding:10px;background:#f8f9fa;border-radius:8px">
                    <div style="font-size:18px;font-weight:700;color:#667eea"><?php echo is_string($disk_total) ? 'N/A' : round($disk_free/1024/1024/1024, 1) . ' GB'; ?></div>
                    <div style="font-size:10px;color:#888">فضای آزاد</div>
                </div>
            </div>
        </div>

        <div class="aic-grid-2">
            <!-- Security Settings -->
            <div class="aic-section">
                <h2>&#x2699;&#xFE0F; تنظیمات امنیتی</h2>
                <form method="post"><?php wp_nonce_field('aic_settings'); ?>
                <input type="hidden" name="aic_tab" value="security">
                <div class="aic-form-row"><label>لاگ درخواست‌ها</label><label><input type="checkbox" name="aic_log_requests" value="1" <?php checked($log); ?>> فعال</label></div>
                <div class="aic-form-row"><label>فیلتر ربات</label><label><input type="checkbox" name="aic_block_bad_bots" value="1" <?php checked($bots); ?>> فعال</label></div>
                <p class="submit"><button type="submit" name="aic_save" class="button button-primary">ذخیره</button></p>
                </form>
            </div>

            <!-- Firewall -->
            <div class="aic-section">
                <h2>&#x1F6E1; فایروال</h2>
                <div style="margin-bottom:8px"><strong>IP های مسدود شده:</strong></div>
                <?php if (!empty($blocked_ips)) : ?>
                    <table class="aic-table"><tr><th>IP</th><th>تاریخ</th></tr>
                    <?php foreach ($blocked_ips as $ip => $time) : ?>
                        <tr><td><code><?php echo $ip; ?></code></td><td style="font-size:10px"><?php echo $time; ?></td></tr>
                    <?php endforeach; ?></table>
                <?php else : '<p style="color:#888;font-size:12px">IP مسدودی نیست.</p>'; endif; ?>
                <div style="margin-top:8px"><strong>هشدار حملات:</strong>
                <div style="margin-top:4px;font-size:12px">
                    <?php if ($failed_logins > 0) : ?>
                        <span class="aic-badge aic-bErr">&#x26A0; <?php echo $failed_logins; ?> تلاش ناموفق</span>
                    <?php else : ?>
                        <span class="aic-badge aic-bOk">&#x2705; بدون حمله</span>
                    <?php endif; ?>
                </div></div>
            </div>
        </div>

        <!-- Security Scan -->
        <div class="aic-section">
            <h2>&#x1F50D; اسکن امنیتی</h2>
            <?php if ($last_scan) : ?>
                <div style="margin-bottom:10px;font-size:12px;color:#888">آخرین اسکن: <?php echo $last_scan['time'] ?? ''; ?></div>
                <?php if (isset($last_scan['results'])) : ?>
                    <table class="aic-table"><tr><th>بررسی</th><th>وضعیت</th></tr>
                    <?php foreach ($last_scan['results'] as $check => $status) : ?>
                        <tr><td><?php echo $check; ?></td><td><span class="aic-badge <?php echo $status === 'ok' ? 'aic-bOk' : 'aic-bWarn'; ?>"><?php echo $status; ?></span></td></tr>
                    <?php endforeach; ?></table>
                <?php endif; ?>
            <?php else : ?>
                <p style="color:#888;font-size:12px">هنوز اسکنی انجام نشده.</p>
            <?php endif; ?>
        </div>

        <!-- Error Guide -->
        <div class="aic-section">
            <h2>&#x1F4D6; راهنمای خطاهای رایج</h2>
            <?php $errors = [
                ['خطای 500 Internal Server Error', 'فایل .htaccess رو حذف و دوباره بساز. حافظه PHP رو بالا ببر. افزونه‌ها رو غیرفعال کن.'],
                ['خطای Connection Timed Out', 'هاست شلوغه یا مشکل شبکه. با پشتیبانی هاست تماس بگیر.'],
                ['خطای 404 Page Not Found', 'پیوند یکتا رو ذخیره مجدد کن. فایل .htaccess بازسازی کن.'],
                ['خطای Syntax Error', 'کد PHP اشتباهه. فایل اصلاح‌شده رو دوباره آپلود کن.'],
                ['خطای White Screen', 'wp-config.php رو چک کن. حافظه PHP رو زیاد کن. Debug رو فعال کن.'],
                ['خطای Establishing Database Connection', 'اطلاعات دیتابیس در wp-config.php رو چک کن.'],
                ['خطای Memory Exhausted', 'در wp-config.php اضافه کن: define("WP_MEMORY_LIMIT", "256M");'],
                ['خطای Maximum Execution Time', 'در .htaccess اضافه کن: php_value max_execution_time 300'],
                ['خطای Upload Max File Size', 'در .htaccess اضافه کن: php_value upload_max_filesize 64M'],
                ['خطای Mixed Content (SSL)', 'آدرس‌های HTTP رو به HTTPS تغییر بده. افزونه Really Simple SSL نصب کن.'],
                ['خطای Briefly Unavailable', 'وردپرس در حال آپدیت خودکاره. چند دقیقه صبر کن.'],
                ['خطای Plugin Conflict', 'افزونه‌ها رو یکی یکی غیرفعال کن تا افزونه مخرب پیدا بشه.'],
                ['خطای Theme Compatibility', 'قالب رو به پیش‌فرض برگردون. قالب رو آپدیت کن.'],
                ['خطای Cron Job', 'تنظیمات زمان‌بندی وردپرس رو بررسی کن. WP-Cron رو ریست کن.'],
                ['خطای Login Loop', 'کوکی‌ها رو پاک کن. فایل .htaccess رو بازسازی کن.'],
            ]; ?>
            <?php foreach ($errors as $err) : ?>
                <div class="aic-error-item">
                    <div class="aic-error-title">&#x274C; <?php echo $err[0]; ?></div>
                    <div class="aic-error-solution">&#x2705; راه‌حل: <?php echo $err[1]; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// ======================== 7. WOOCOMMERCE ========================
function aic_page_woocommerce() {
    if (!current_user_can('manage_options')) return;
    if (!class_exists('WooCommerce')) { ?>
        <div class="aic-wrap">
            <div class="aic-header"><h1>&#x1F6D2; ووکامرس</h1></div>
            <div class="aic-section"><p style="color:#888">ووکامرس نصب نیست.</p></div>
        </div>
        <?php return;
    }

    // Products with stock & SEO
    $products = wc_get_products(['limit' => 20, 'return' => 'ids']);
    $orders = wc_get_orders(['limit' => 100, 'status' => ['completed', 'processing'], 'return' => 'ids']);
    $total_revenue = 0;
    $product_sales = [];
    foreach ($orders as $oid) {
        $o = wc_get_order($oid);
        if (!$o) continue;
        $total_revenue += $o->get_total();
        foreach ($o->get_items() as $item) {
            $pid = $item->get_product_id();
            $product_sales[$pid] = ($product_sales[$pid] ?? 0) + $item->get_quantity();
        }
    }
    arsort($product_sales);

    // 7-day sales
    $daily_sales = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $day_total = 0;
        foreach ($orders as $oid) {
            $o = wc_get_order($oid);
            if ($o && date('Y-m-d', strtotime($o->get_date_created())) === $date) {
                $day_total += $o->get_total();
            }
        }
        $daily_sales[] = ['date' => date('m/d', strtotime($date)), 'total' => $day_total];
    }
    $max_daily = max(array_column($daily_sales, 'total')) ?: 1;
    ?>
    <div class="aic-wrap">
        <div class="aic-header"><h1>&#x1F6D2; ووکامرس</h1></div>

        <!-- Stats -->
        <div class="aic-cards" style="grid-template-columns:repeat(4,1fr)">
            <div class="aic-card"><div class="aic-card-num"><?php echo count($products); ?></div><div class="aic-card-label">محصول</div></div>
            <div class="aic-card"><div class="aic-card-num"><?php echo count($orders); ?></div><div class="aic-card-label">سفارش</div></div>
            <div class="aic-card"><div class="aic-card-num" style="color:#e74c3c"><?php echo wc_price($total_revenue); ?></div><div class="aic-card-label">درآمد کل</div></div>
            <div class="aic-card"><div class="aic-card-num"><?php echo wc_price(count($orders) > 0 ? $total_revenue / count($orders) : 0); ?></div><div class="aic-card-label">میانگین</div></div>
        </div>

        <!-- 7 Day Chart -->
        <div class="aic-section">
            <h2>&#x1F4C8; فروش ۷ روز اخیر</h2>
            <div class="aic-bar">
                <?php foreach ($daily_sales as $ds) :
                    $h = max(4, ($ds['total'] / $max_daily) * 60);
                ?>
                    <div class="aic-bar-item" style="height:<?php echo $h; ?>px" title="<?php echo wc_price($ds['total']); ?>">
                        <span><?php echo $ds['date']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:25px;font-size:11px;color:#888">مبلغ هر ستون: میانه موس رو نگه دارید</div>
        </div>

        <div class="aic-grid-2">
            <!-- Best Selling -->
            <div class="aic-section">
                <h2>&#x1F525; پرفروش‌ها</h2>
                <?php $best = array_slice($product_sales, 0, 5, true);
                if ($best) : ?>
                    <table class="aic-table"><tr><th>محصول</th><th>فروش</th></tr>
                    <?php foreach ($best as $pid => $qty) :
                        $p = wc_get_product($pid);
                        if ($p) : ?>
                            <tr><td><a href="<?php echo get_edit_post_link($pid); ?>"><?php echo esc_html(mb_substr($p->get_name(), 0, 25)); ?></a></td>
                            <td><strong><?php echo $qty; ?></strong></td></tr>
                        <?php endif;
                    endforeach; ?></table>
                <?php else: echo '<p style="color:#888;font-size:12px">فروشی نبوده.</p>'; endif; ?>
            </div>

            <!-- Products Stock -->
            <div class="aic-section">
                <h2>&#x1F4E6; موجودی و SEO</h2>
                <table class="aic-table"><tr><th>محصول</th><th>موجودی</th><th>قیمت</th><th>SEO</th></tr>
                <?php foreach (array_slice($products, 0, 10) as $pid) :
                    $p = wc_get_product($pid);
                    if (!$p) continue;
                    $stock = $p->get_stock_status();
                    $stock_label = $stock === 'instock' ? 'موجود' : 'ناموجود';
                    $stock_class = $stock === 'instock' ? 'aic-bOk' : 'aic-bErr';
                    // SEO
                    $mt = get_post_meta($pid, '_yoast_wpseo_title', true) ?: $p->get_name();
                    $s = 0;
                    if (strlen($mt) >= 30 && strlen($mt) <= 60) $s += 50; elseif (strlen($mt) > 0) $s += 20;
                    $dl = strlen(get_post_meta($pid, '_yoast_wpseo_metadesc', true) ?: '');
                    if ($dl >= 120 && $dl <= 160) $s += 50; elseif ($dl > 0) $s += 20;
                    $g = $s >= 80 ? 'A' : ($s >= 60 ? 'B' : ($s >= 40 ? 'C' : 'D'));
                ?>
                    <tr>
                        <td><a href="<?php echo get_edit_post_link($pid); ?>"><?php echo esc_html(mb_substr($p->get_name(), 0, 20)); ?></a></td>
                        <td><span class="aic-badge <?php echo $stock_class; ?>"><?php echo $stock_label; ?></span></td>
                        <td><?php echo $p->get_price() ? wc_price($p->get_price()) : '-'; ?></td>
                        <td><span class="aic-grade aic-g<?php echo $g; ?>"><?php echo $g; ?></span></td>
                    </tr>
                <?php endforeach; ?></table>
            </div>
        </div>
    </div>
    <?php
}

// ======================== 8. AGENT TASKS ========================
function aic_page_agent() {
    if (!current_user_can('manage_options')) return;
    $history = get_option('aic_agent_history', []);
    ?>
    <div class="aic-wrap">
        <div class="aic-header"><h1>&#x1F916; <?php echo aic_t('menu_agent'); ?></h1></div>

        <div class="aic-grid-2">
            <!-- History -->
            <div class="aic-section">
                <h2>&#x1F4CB; <?php echo aic_t('agent_history'); ?></h2>
                <?php if (!empty($history)) : ?>
                    <table class="aic-table"><tr><th><?php echo aic_t('task_time'); ?></th><th><?php echo aic_t('task_action'); ?></th><th><?php echo aic_t('task_detail'); ?></th><th><?php echo aic_t('task_user'); ?></th></tr>
                    <?php foreach (array_reverse(array_slice($history, -20)) as $h) : ?>
                        <tr>
                            <td style="font-size:10px;white-space:nowrap"><?php echo $h['time']; ?></td>
                            <td><span class="aic-badge aic-bOk"><?php echo esc_html($h['action']); ?></span></td>
                            <td style="font-size:11px"><?php echo esc_html(mb_substr($h['detail'], 0, 50)); ?></td>
                            <td style="font-size:10px"><?php echo esc_html($h['user'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?></table>
                <?php else: echo '<p style="color:#888;font-size:12px">' . aic_t('no_content') . '</p>'; endif; ?>
            </div>

            <!-- Add Entry -->
            <div class="aic-section">
                <h2>&#x2795; <?php echo aic_t('new_task'); ?></h2>
                <form method="post"><?php wp_nonce_field('aic_settings'); ?>
                <input type="hidden" name="aic_tab" value="agent">
                <div class="aic-form-row"><label><?php echo aic_t('task_action'); ?></label>
                    <select name="aic_agent_action"><option value="content_change"><?php echo aic_t('edit') . ' ' . aic_t('content_type'); ?></option><option value="theme_change"><?php echo aic_t('edit'); ?> Theme</option><option value="settings_change"><?php echo aic_t('settings'); ?></option><option value="plugin_install">Plugin</option><option value="optimization"><?php echo aic_t('seo_settings'); ?></option><option value="report"><?php echo aic_t('seo_report'); ?></option><option value="error"><?php echo aic_t('error'); ?></option><option value="other"><?php echo aic_t('general'); ?></option></select>
                </div>
                <div class="aic-form-row"><label><?php echo aic_t('task_detail'); ?></label><textarea name="aic_agent_detail" rows="3" style="flex:1;max-width:350px" placeholder="<?php echo aic_t('description'); ?>..."></textarea></div>
                <p class="submit"><button type="submit" name="aic_save" class="button button-primary"><?php echo aic_t('save'); ?></button></p>
                </form>
            </div>
        </div>

        <!-- Error Guide -->
        <div class="aic-section">
            <h2>&#x1F4D6; <?php echo aic_t('quick_guide'); ?></h2>
            <?php $errors = [
                ['500 Internal Server Error', '.htaccess ' . (aic_t('language') === 'fa' ? 'حذف و بازسازی کن. حافظه PHP رو زیاد کن.' : 'Delete and rebuild. Increase PHP memory.')],
                ['Connection Timed Out', aic_t('language') === 'fa' ? 'هاست شلوغه. با پشتیبانی تماس بگیر.' : 'Server is busy. Contact support.'],
                ['404 Page Not Found', aic_t('language') === 'fa' ? 'پیوند یکتا رو ذخیره مجدد کن.' : 'Re-save permalinks.'],
                ['Syntax Error', aic_t('language') === 'fa' ? 'کد PHP اشتباهه. فایل اصلاح‌شده رو آپلود کن.' : 'PHP code error. Upload corrected file.'],
                ['White Screen of Death', aic_t('language') === 'fa' ? 'wp-config.php چک کن. حافظه PHP رو زیاد کن.' : 'Check wp-config.php. Increase PHP memory.'],
                ['Database Connection Error', aic_t('language') === 'fa' ? 'اطلاعات wp-config.php رو چک کن.' : 'Check wp-config.php credentials.'],
                ['Memory Exhausted', 'define("WP_MEMORY_LIMIT", "256M") ' . (aic_t('language') === 'fa' ? 'اضافه کن.' : 'Add this line.')],
                ['Mixed Content (SSL)', aic_t('language') === 'fa' ? 'آدرس HTTP به HTTPS تغییر بده.' : 'Change HTTP to HTTPS addresses.'],
                ['Plugin Conflict', aic_t('language') === 'fa' ? 'افزونه‌ها رو یکی یکی غیرفعال کن.' : 'Deactivate plugins one by one.'],
                ['Theme Compatibility', aic_t('language') === 'fa' ? 'قالب رو به پیش‌فرض برگردون.' : 'Switch to default theme.'],
                ['Cron Job Issues', aic_t('language') === 'fa' ? 'WP-Cron رو ریست کن.' : 'Reset WP-Cron.'],
                ['Login Loop', aic_t('language') === 'fa' ? 'کوکی‌ها رو پاک کن.' : 'Clear cookies.'],
                ['Upload Size Limit', 'php_value upload_max_filesize 64M ' . (aic_t('language') === 'fa' ? 'اضافه کن.' : 'Add this.')],
                ['Admin Dashboard Blank', aic_t('language') === 'fa' ? 'افزونه‌ها رو غیرفعال کن. حافظه رو زیاد کن.' : 'Deactivate plugins. Increase memory.'],
                ['Broken permalinks', aic_t('language') === 'fa' ? 'Settings > Permalinks > Save را بزن.' : 'Go to Settings > Permalinks > Save.'],
            ]; ?>
            <div class="aic-grid-2">
            <?php foreach ($errors as $err) : ?>
                <div class="aic-error-item">
                    <div class="aic-error-title">&#x274C; <?php echo $err[0]; ?></div>
                    <div class="aic-error-solution">&#x2705; <?php echo $err[1]; ?></div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

// ======================== LANGUAGE PAGE ========================
function aic_page_language() {
    if (!current_user_can('manage_options')) return;
    $current_lang = AIC_i18n::get_instance()->get_lang();
    ?>
    <div class="aic-wrap">
        <div class="aic-header"><h1>&#x1F30D; <?php echo aic_t('language'); ?></h1></div>

        <form method="post"><?php wp_nonce_field('aic_settings'); ?>
        <input type="hidden" name="aic_tab" value="language">
        <div class="aic-section">
            <h2>&#x1F30D; <?php echo aic_t('language'); ?></h2>
            <p style="font-size:12px;color:#666;margin-bottom:15px"><?php echo aic_t('language') === 'fa' ? 'زبان پنل مدیریت افزونه را انتخاب کنید.' : 'Select the plugin admin panel language.'; ?></p>

            <div class="aic-grid-2" style="max-width:600px">
                <label style="display:flex;align-items:center;gap:12px;padding:20px;background:<?php echo $current_lang === 'fa' ? 'linear-gradient(135deg,#667eea,#764ba2)' : '#f8f9fa'; ?>;border-radius:10px;cursor:pointer;border:2px solid <?php echo $current_lang === 'fa' ? '#667eea' : '#e5e7eb'; ?>;color:<?php echo $current_lang === 'fa' ? '#fff' : '#333'; ?>;transition:all .2s">
                    <input type="radio" name="aic_language" value="fa" <?php checked($current_lang, 'fa'); ?> style="display:none">
                    <span style="font-size:32px">&#x1F1EE&#x1F1F7;</span>
                    <div>
                        <div style="font-size:16px;font-weight:700"><?php echo aic_t('persian'); ?></div>
                        <div style="font-size:11px;opacity:.8;margin-top:2px">فارسی - RTL</div>
                    </div>
                </label>

                <label style="display:flex;align-items:center;gap:12px;padding:20px;background:<?php echo $current_lang === 'en' ? 'linear-gradient(135deg,#667eea,#764ba2)' : '#f8f9fa'; ?>;border-radius:10px;cursor:pointer;border:2px solid <?php echo $current_lang === 'en' ? '#667eea' : '#e5e7eb'; ?>;color:<?php echo $current_lang === 'en' ? '#fff' : '#333'; ?>;transition:all .2s">
                    <input type="radio" name="aic_language" value="en" <?php checked($current_lang, 'en'); ?> style="display:none">
                    <span style="font-size:32px">&#x1F1FA&#x1F1F8;</span>
                    <div>
                        <div style="font-size:16px;font-weight:700"><?php echo aic_t('english'); ?></div>
                        <div style="font-size:11px;opacity:.8;margin-top:2px">English - LTR</div>
                    </div>
                </label>
            </div>

            <p class="submit" style="margin-top:20px"><button type="submit" name="aic_save" class="button button-primary"><?php echo aic_t('save_settings'); ?></button></p>
        </div>
        </form>

        <div class="aic-section">
            <h2>&#x1F4D6; <?php echo aic_t('documentation'); ?></h2>
            <div class="aic-grid-2">
                <div style="background:#f8f9fa;border-radius:8px;padding:15px">
                    <h3 style="margin:0 0 8px;font-size:13px">&#x1F1EE&#x1F1F7; <?php echo aic_t('persian'); ?></h3>
                    <ul style="font-size:12px;color:#666;line-height:2;padding-right:15px">
                        <li>رابط کاربری کاملاً فارسی</li>
                        <li>پشتیبانی از RTL (راست به چپ)</li>
                        <li>فونت پیش‌فرض Tahoma</li>
                        <li>تمام متون پنل مدیریت فارسی هستند</li>
                    </ul>
                </div>
                <div style="background:#f8f9fa;border-radius:8px;padding:15px">
                    <h3 style="margin:0 0 8px;font-size:13px">&#x1F1FA&#x1F1F8; <?php echo aic_t('english'); ?></h3>
                    <ul style="font-size:12px;color:#666;line-height:2;padding-right:15px">
                        <li>Fully English interface</li>
                        <li>Left-to-right (LTR) layout</li>
                        <li>Default font: Tahoma</li>
                        <li>All admin panel texts in English</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}
