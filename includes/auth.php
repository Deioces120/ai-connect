<?php
if (!defined('ABSPATH')) exit;

class AIC_Auth {

    public static function check($request) {
        if (get_option('aic_enabled', '1') !== '1') {
            return new WP_Error('aic_disabled', 'API is disabled', ['status' => 403]);
        }

        $key = $request->get_header('X-API-Key');
        if (empty($key)) {
            $key = $request->get_param('api_key');
        }

        $stored = get_option('aic_api_key', '');
        if (empty($stored) || empty($key) || !hash_equals($stored, (string) $key)) {
            return new WP_Error('aic_unauthorized', 'Invalid API Key', ['status' => 401]);
        }

        // Rate limiting
        $rate_limit = (int) get_option('aic_rate_limit', 60);
        if ($rate_limit > 0) {
            $ip = self::get_client_ip();
            $key_rate = 'aic_rate_' . md5($ip);
            $current = (int) get_transient($key_rate);
            if ($current >= $rate_limit) {
                return new WP_Error('aic_rate_limited', 'Rate limit exceeded', ['status' => 429]);
            }
            set_transient($key_rate, $current + 1, 60);
        }

        // IP whitelist
        $ips = trim(get_option('aic_allowed_ips', ''));
        if (!empty($ips)) {
            $client = self::get_client_ip();
            $allowed = array_map('trim', explode(',', $ips));
            if (!in_array($client, $allowed, true)) {
                return new WP_Error('aic_ip_blocked', 'IP not allowed', ['status' => 403]);
            }
        }

        // Log request
        self::log_request($request);

        return true;
    }

    private static function log_request($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'aic_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `ip` varchar(45) NOT NULL,
                `method` varchar(10) NOT NULL,
                `route` varchar(255) NOT NULL,
                `status` int(3) NOT NULL DEFAULT 200,
                `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `ip` (`ip`),
                KEY `time` (`time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $ip = self::get_client_ip();
        $method = $request->get_method();
        $route = $request->get_route();
        $wpdb->insert($table, [
            'ip'     => $ip,
            'method' => $method,
            'route'  => $route,
            'status' => 200,
        ]);
    }

    private static function get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return trim($_SERVER['HTTP_CLIENT_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
}
