<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * The Technilium logo as an inline data URI, so a PDF needs no remote fetch (dompdf runs with
 * remote files disabled). Empty when the file is missing: a document without logo beats no document.
 */
final class BrandLogo
{
    public static function dataUri(): string
    {
        $path = resource_path('images/logo.png');

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }
}
