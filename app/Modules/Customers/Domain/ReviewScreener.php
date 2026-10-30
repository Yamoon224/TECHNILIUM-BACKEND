<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

/**
 * Automatic half of review moderation (cahier §5.9 "modération automatique et
 * manuelle"): a clean review is published at once, one that looks like
 * advertising or leaks contact details waits for a person. It never rejects
 * on its own - a false positive only costs a delay, not a lost review.
 */
final class ReviewScreener
{
    /**
     * @param  list<string>  $bannedWords
     */
    public static function needsManualReview(string $text, array $bannedWords = []): bool
    {
        return self::containsLink($text)
            || self::containsEmail($text)
            || self::containsPhoneNumber($text)
            || self::containsBannedWord($text, $bannedWords);
    }

    private static function containsLink(string $text): bool
    {
        return (bool) preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|ci|sn|cm|fr|io|me)\b)~iu', $text);
    }

    private static function containsEmail(string $text): bool
    {
        return (bool) preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/u', $text);
    }

    /** Eight or more digits, allowing the spaces, dots and dashes people type in numbers. */
    private static function containsPhoneNumber(string $text): bool
    {
        return (bool) preg_match('/(?:\+?\d[\s.\-]?){8,}/u', $text);
    }

    /**
     * @param  list<string>  $bannedWords
     */
    private static function containsBannedWord(string $text, array $bannedWords): bool
    {
        $haystack = mb_strtolower($text);

        foreach ($bannedWords as $word) {
            $word = trim(mb_strtolower($word));

            if ($word !== '' && str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }
}
