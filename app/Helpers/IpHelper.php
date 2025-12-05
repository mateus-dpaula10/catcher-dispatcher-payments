<?php

if (!function_exists('getRealIp')) {
    function getRealIp() {

        // 1) Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        }

        // 2) Proxy / Load balancer
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]); // pega o primeiro IP da lista
        }

        // 3) Remoto (último recurso)
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return trim($_SERVER['REMOTE_ADDR']);
        }

        return null;
    }
}
