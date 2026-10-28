<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Application\AccessResolver;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $access = app(AccessResolver::class);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'accent_color' => $user->accent_color,
            'theme' => $user->theme,
            'roles' => $user->getRoleNames(),
            // What this account may use and what is switched off for it: the interface hides the rest.
            'abilities' => $access->abilitiesFor($user),
            'disabled_features' => $access->disabledFeaturesFor($user),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
