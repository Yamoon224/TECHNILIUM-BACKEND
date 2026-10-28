<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\AccessResolver;
use App\Modules\Identity\Domain\Exceptions\AccessException;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\FeatureOverride;
use App\Modules\Identity\Infrastructure\Models\User;

/** Switches a feature on or off for one group or one user, or clears the switch (null = back to the default). */
final class SetFeatureSwitchAction
{
    public function __construct(private readonly AccessResolver $access) {}

    public function execute(string $feature, string $subjectType, string $subject, ?bool $enabled, User $actor): void
    {
        $this->guardSuperAdmin($subjectType, $subject);

        $where = ['feature' => $feature, 'subject_type' => $subjectType, 'subject' => $subject];

        if ($enabled === null) {
            FeatureOverride::query()->where($where)->delete();
        } else {
            FeatureOverride::query()->updateOrCreate($where, ['enabled' => $enabled, 'updated_by' => $actor->id]);
        }

        $this->access->forget();
    }

    private function guardSuperAdmin(string $subjectType, string $subject): void
    {
        $isSuper = $subjectType === FeatureOverride::ROLE
            ? $subject === Role::SuperAdmin->value
            : (User::query()->find((int) $subject)?->hasRole(Role::SuperAdmin->value) ?? false);

        if ($isSuper) {
            throw AccessException::superAdminExempt();
        }
    }
}
