<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

/**
 * The tamper-evidence rule (cahier §8 "journal d'audit inviolable"): each
 * entry's signature covers its own content AND the previous entry's
 * signature, so changing, removing or reordering any past entry breaks every
 * signature after it. The HMAC key is the application secret, so someone who
 * can write to the database but not read the key cannot forge a valid chain.
 */
final class AuditChain
{
    public const GENESIS = 'genesis';

    /**
     * @param  array<string, mixed>  $content  the entry's fields, without its signature
     */
    public static function sign(array $content, string $previousHash, string $key): string
    {
        return hash_hmac(
            'sha256',
            $previousHash.'|'.json_encode(self::canonical($content), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $key,
        );
    }

    /**
     * Keys sorted at every depth: MySQL's JSON column reorders object keys,
     * so the signature must not depend on the order they are read back in.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function canonical(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::canonical($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function isValid(array $content, string $previousHash, string $storedHash, string $key): bool
    {
        return hash_equals(self::sign($content, $previousHash, $key), $storedHash);
    }
}
