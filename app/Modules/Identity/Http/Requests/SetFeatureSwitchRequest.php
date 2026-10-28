<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Identity\Infrastructure\Models\FeatureOverride;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class SetFeatureSwitchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by can:manage-access on the route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feature' => ['required', 'string', Rule::in(AccessCatalogue::keys())],
            'subject_type' => ['required', Rule::in([FeatureOverride::ROLE, FeatureOverride::USER])],
            'subject' => ['required', 'string', 'max:190'],
            // true = on, false = off, null = back to the default.
            'enabled' => ['present', 'nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $subject = $this->string('subject')->toString();
            $exists = $this->string('subject_type')->toString() === FeatureOverride::ROLE
                ? Role::query()->where('name', $subject)->where('guard_name', 'web')->exists()
                : User::query()->whereKey((int) $subject)->exists();

            if (! $exists) {
                $validator->errors()->add('subject', 'Groupe ou utilisateur introuvable.');
            }
        }];
    }
}
