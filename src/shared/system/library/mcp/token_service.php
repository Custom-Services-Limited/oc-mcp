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

class TokenService {
    private $secret;

    public function __construct($secret) {
        $this->secret = (string)$secret;
    }

    public function generateToken() {
        if (function_exists('random_bytes')) {
            return 'ocmcp_' . bin2hex(random_bytes(32));
        }

        return 'ocmcp_' . sha1(uniqid('', true)) . sha1(mt_rand());
    }

    public function hashToken($token) {
        return hash_hmac('sha256', (string)$token, $this->secret);
    }

    public function hint($token) {
        return substr((string)$token, 0, 10) . '...' . substr((string)$token, -6);
    }

    public function verify($token, $hash) {
        return function_exists('hash_equals')
            ? hash_equals((string)$hash, $this->hashToken($token))
            : (string)$hash === $this->hashToken($token);
    }
}

