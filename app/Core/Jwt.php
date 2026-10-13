<?php

namespace App\Core;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;

class Jwt
{
    /**
     * Generate a JWT token for the given claims.
     *
     * @param array $claims Custom claims to add to the token.
     * @param int|null $ttl Time-to-live in seconds. Defaults to JWT_EXPIRES_IN.
     */
    public static function encode(array $claims = [], ?int $ttl = null): string
    {
        $issuedAt = time();
        $ttl = $ttl ?? JWT_EXPIRES_IN;

        $payload = array_merge([
            'iss' => JWT_ISSUER,
            'aud' => JWT_AUDIENCE,
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $issuedAt + $ttl,
        ], $claims);

        return FirebaseJwt::encode($payload, JWT_SECRET, 'HS256');
    }

    /**
     * Decode and validate a JWT token.
     *
     * @throws \Firebase\JWT\ExpiredException|\UnexpectedValueException
     */
    public static function decode(string $token)
    {
        return FirebaseJwt::decode($token, new Key(JWT_SECRET, 'HS256'));
    }

    /**
     * Check if a JWT token is expired.
     *
     * @param string $token The JWT token string.
     * @return bool Returns true if expired, false otherwise.
     */
    public static function isExpired(string $token): bool
    {
        try {
            // Decode the payload without verifying signature first (for expiration check only)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return true; // malformed token treated as expired
            }
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (!is_array($payload) || !isset($payload['exp'])) {
                return true; // no exp claim, treat as expired
            }
            return time() >= $payload['exp'];
        } catch (\Exception $e) {
            // Any error means the token can't be trusted or is malformed => treat as expired
            return true;
        }
    }
}


