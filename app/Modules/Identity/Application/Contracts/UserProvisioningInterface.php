<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Data\UserAccountData;
use App\Modules\Identity\Domain\Role;
use Illuminate\Database\QueryException;

/**
 * Identity's public port for creating accounts on behalf of other modules
 * (e.g. Vendors, when a vendor application also provisions a login). Callers
 * depend on this interface only - never on Identity's Eloquent models - so
 * the two modules communicate through a contract, not a SQL join (cahier §9.1).
 */
interface UserProvisioningInterface
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string}  $data
     *
     * @throws QueryException if the email/phone is already taken
     */
    public function createUserWithRole(array $data, Role $role): UserAccountData;
}
