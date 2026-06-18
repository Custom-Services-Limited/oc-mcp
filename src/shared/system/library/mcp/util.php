<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */
namespace OpenCartMcp;

class McpException extends \Exception {
    private $mcpCode;
    private $safeData;

    public function __construct($mcpCode, $message, $safeData = array()) {
        parent::__construct($message);
        $this->mcpCode = $mcpCode;
        $this->safeData = $safeData;
    }

    public function getMcpCode() {
        return $this->mcpCode;
    }

    public function getSafeData() {
        return $this->safeData;
    }
}

class Util {
    public static function jsonEncode($value) {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    public static function jsonDecodeArray($value, $fallback = array()) {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    public static function requestId() {
        if (function_exists('random_bytes')) {
            return 'mcp_' . bin2hex(random_bytes(16));
        }

        return 'mcp_' . sha1(uniqid('', true));
    }

    public static function hashInput($value) {
        return hash('sha256', self::jsonEncode(self::canonicalize($value)));
    }

    public static function canonicalize($value) {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        if (!$isList) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }

    public static function redact($value) {
        if (!is_array($value)) {
            return $value;
        }

        $sensitive = array('token', 'access_token', 'authorization', 'password', 'secret', 'confirmation_token');
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string)$key), $sensitive, true)) {
                $value[$key] = '[redacted]';
            } elseif (is_array($item)) {
                $value[$key] = self::redact($item);
            }
        }

        return $value;
    }

    public static function maskEmail($email) {
        $email = (string)$email;
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email === '' ? '' : '[masked]';
        }

        $local = $parts[0];
        $domain = $parts[1];
        return substr($local, 0, 1) . '***@' . $domain;
    }

    public static function maskPhone($phone) {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        if (strlen($digits) <= 4) {
            return '[masked]';
        }

        return '***' . substr($digits, -4);
    }

    public static function clientIp($server) {
        $keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($keys as $key) {
            if (!empty($server[$key])) {
                $ip = trim(explode(',', $server[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    public static function headerValue($headers, $name) {
        $needle = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $needle) {
                return is_array($value) ? reset($value) : $value;
            }
        }

        return '';
    }

    public static function bearerToken($headers) {
        $authorization = (string)self::headerValue($headers, 'Authorization');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    public static function splitList($value) {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), 'strlen'));
        }

        if ($value === null || $value === '') {
            return array();
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)$value)), 'strlen'));
    }

    public static function originHost($origin) {
        $host = parse_url((string)$origin, PHP_URL_HOST);
        return $host ? strtolower($host) : '';
    }

    public static function ipAllowed($ip, $rules) {
        $rules = self::splitList($rules);
        if (!$rules) {
            return true;
        }

        foreach ($rules as $rule) {
            if ($rule === $ip) {
                return true;
            }

            if (strpos($rule, '/') !== false && self::cidrMatch($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    private static function cidrMatch($ip, $cidr) {
        list($subnet, $bits) = explode('/', $cidr, 2);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $bits = (int)$bits;
        $mask = -1 << (32 - $bits);
        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }

    public static function originAllowed($origin, $rules) {
        if (!$origin) {
            return true;
        }

        $rules = self::splitList($rules);
        if (!$rules) {
            return true;
        }

        $host = self::originHost($origin);
        foreach ($rules as $rule) {
            $ruleHost = self::originHost($rule);
            if (!$ruleHost) {
                $ruleHost = strtolower($rule);
            }

            if ($host === $ruleHost) {
                return true;
            }
        }

        return false;
    }
}

