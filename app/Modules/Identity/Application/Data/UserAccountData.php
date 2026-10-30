<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Data;

/**
 * Identity's public, read-only view of a created account - the only shape
 * other modules (Vendors) are allowed to depend on. Never expose
 * Infrastructure\Models\User outside this module (cahier §9.1).
 */
final readonly class UserAccountData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {}
}
