<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

/**
 * The language a catalogue response is written in. French is the reference
 * and the fallback: an English text is used only when asked for (?lang=en) and
 * only when the catalogue team wrote one.
 */
final class CatalogLocale
{
    public static function resolve(mixed $requested): string
    {
        return $requested === 'en' ? 'en' : 'fr';
    }

    /** The English text when English was asked for and one exists, else the French reference. */
    public static function pick(string $locale, ?string $french, ?string $english): ?string
    {
        return $locale === 'en' && $english !== null && trim($english) !== '' ? $english : $french;
    }
}
