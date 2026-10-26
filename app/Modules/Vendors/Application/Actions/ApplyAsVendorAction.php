<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Application\Actions;

use App\Modules\Identity\Application\Contracts\UserProvisioningInterface;
use App\Modules\Identity\Domain\Role;
use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * A vendor application provisions both a login (via Identity's public
 * contract, never its Eloquent models directly — cahier §9.1) and the
 * Vendor profile, starting pending until an administrator approves it.
 */
final class ApplyAsVendorAction
{
    public function __construct(
        private readonly UserProvisioningInterface $userProvisioning,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string, password: string, company_name: string, address?: string|null}  $data
     */
    public function execute(array $data): Vendor
    {
        return DB::transaction(function () use ($data): Vendor {
            $account = $this->userProvisioning->createUserWithRole([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ], Role::VendeurTiers);

            return Vendor::query()->create([
                'user_id' => $account->id,
                'company_name' => $data['company_name'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'status' => VendorStatus::Pending->value,
            ]);
        });
    }
}
