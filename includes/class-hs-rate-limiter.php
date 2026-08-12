<?php
if (!defined('ABSPATH')) exit;

class HS_Rate_Limiter {
    public static function check($action = 'apply', $max = 5, $window = 3600) {
        if (!HS_Settings::get('enable_rate_limiting')) return true;
        $ip = self::get_client_ip();
        $key = "hs_rate_{$action}_" . md5($ip);
        $count = get_transient($key) ?: 0;
        if ($count >= $max) return false;
        set_transient($key, $count + 1, $window);
        return true;
    }

    public static function get_client_ip() {
        $headers = ['HTTP_CF_CONNECTING_IP','HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) return sanitize_text_field($_SERVER[$header]);
        }
        return '0.0.0.0';
    }
}
