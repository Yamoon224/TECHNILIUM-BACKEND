<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

/**
 * Prepares a request body for the audit trail: the log must show what was
 * asked without ever storing a secret (cahier §8: no sensitive data in
 * clear in logs). Pure, so every rule is unit-tested.
 */
final class PayloadSanitizer
{
    public const REDACTED = '[masqué]';

    private const MAX_DEPTH = 4;

    private const MAX_STRING = 200;

    private const MAX_ITEMS = 50;

    /** Substrings that mark a key as secret, whatever its case. */
    private const SECRET_FRAGMENTS = ['password', 'token', 'secret', 'iban', 'card', 'cvv', 'authorization'];

    /** Exact keys that are secret although they hold no telltale fragment. */
    private const SECRET_KEYS = ['confirmation_code', 'delivery_code', 'otp', 'pin'];

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public static function sanitize(array $payload, int $depth = 0): array
    {
        $clean = [];
        $count = 0;

        foreach ($payload as $key => $value) {
            if (++$count > self::MAX_ITEMS) {
                $clean['…'] = '[tronqué]';
                break;
            }

            if (self::isSecretKey((string) $key)) {
                $clean[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $clean[$key] = $depth >= self::MAX_DEPTH ? '[tronqué]' : self::sanitize($value, $depth + 1);
            } elseif (is_string($value) && mb_strlen($value) > self::MAX_STRING) {
                $clean[$key] = mb_substr($value, 0, self::MAX_STRING).'…';
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = '[non affichable]';
            }
        }

        return $clean;
    }

    private static function isSecretKey(string $key): bool
    {
        $lower = strtolower($key);

        if (in_array($lower, self::SECRET_KEYS, true)) {
            return true;
        }

        foreach (self::SECRET_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
