<?php
if (!defined('ABSPATH')) exit;

class AIC_i18n {

    private static $instance = null;
    private $translations = [];
    private $current_lang = 'fa';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->current_lang = get_option('aic_language', 'fa');
        $this->load_translations();
    }

    private function load_translations() {
        $lang_file = AIC_PLUGIN_DIR . 'languages/' . $this->current_lang . '.php';
        if (file_exists($lang_file)) {
            $this->translations = require $lang_file;
        }
        // Fallback to Persian
        if (empty($this->translations)) {
            $fallback = AIC_PLUGIN_DIR . 'languages/fa.php';
            if (file_exists($fallback)) {
                $this->translations = require $fallback;
            }
        }
    }

    public function t($key, $default = '') {
        return $this->translations[$key] ?? $default ?? $key;
    }

    public function get_lang() {
        return $this->current_lang;
    }

    public function set_lang($lang) {
        if (in_array($lang, ['fa', 'en'])) {
            $this->current_lang = $lang;
            update_option('aic_language', $lang);
            $this->translations = [];
            $this->load_translations();
        }
    }

    public function is_rtl() {
        return $this->current_lang === 'fa';
    }
}

function aic_t($key, $default = '') {
    return AIC_i18n::get_instance()->t($key, $default);
}
