<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\AccessResolver;
use App\Modules\Identity\Application\Actions\ManageAccessGroupsAction;
use App\Modules\Identity\Application\Actions\SetFeatureSwitchAction;
use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Identity\Domain\Exceptions\AccessException;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Http\Requests\AssignRolesRequest;
use App\Modules\Identity\Http\Requests\SaveAccessGroupRequest;
use App\Modules\Identity\Http\Requests\SetFeatureSwitchRequest;
use App\Modules\Identity\Infrastructure\Models\FeatureOverride;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Access management, for the super admin only (can:manage-access): which features exist, who has
 * them, which are switched off for whom, the custom user groups and the members of each.
 */
class AccessController extends Controller
{
    public function __construct(
        private readonly AccessResolver $access,
        private readonly ManageAccessGroupsAction $groups,
        private readonly SetFeatureSwitchAction $switches,
    ) {}

    #[OA\Get(
        path: '/admin/access',
        summary: 'Fonctionnalités, groupes (profils d\'origine et personnalisés) et interrupteurs en place',
        security: [['sanctum' => []]],
        tags: ['Accès'],
        responses: [new OA\Response(response: 200, description: 'Catalogue des accès')],
    )]
    public function index(): JsonResponse
    {
        $features = [];
        foreach (AccessCatalogue::features() as $key => $feature) {
            $features[] = ['key' => $key, 'label' => $feature['label'], 'label_en' => $feature['label_en'], 'group' => $feature['group'], 'group_en' => $feature['group_en']];
        }

        return response()->json([
            'features' => $features,
            'groups' => $this->groupList(),
            'switches' => FeatureOverride::query()->orderBy('feature')->get()
                ->map(fn (FeatureOverride $row) => ['feature' => $row->feature, 'subject_type' => $row->subject_type, 'subject' => $row->subject, 'enabled' => $row->enabled])
                ->values(),
        ]);
    }

    #[OA\Get(
        path: '/admin/access/users',
        summary: 'Comptes de l\'équipe (hors clients) avec leurs groupes et leurs interrupteurs',
        security: [['sanctum' => []]],
        tags: ['Accès'],
        responses: [new OA\Response(response: 200, description: 'Comptes')],
    )]
    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with('roles')
            ->whereHas('roles', fn ($q) => $q->where('name', '!=', Role::Client->value))
            ->orderBy('name');

        if ($request->filled('search')) {
            $needle = '%'.addcslashes($request->string('search')->toString(), '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $needle)->orWhere('email', 'like', $needle));
        }

        $users = $query->limit(200)->get();
        $switches = FeatureOverride::query()->where('subject_type', FeatureOverride::USER)->whereIn('subject', $users->pluck('id')->map(fn ($id) => (string) $id))->get()->groupBy('subject');

        return response()->json($users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames()->values(),
            'switches' => ($switches[(string) $user->id] ?? collect())->mapWithKeys(fn (FeatureOverride $row) => [$row->feature => $row->enabled])->all(),
        ])->values());
    }

    #[OA\Put(
        path: '/admin/access/users/{user}/roles',
        summary: 'Remplace les groupes d\'un compte',
        security: [['sanctum' => []]],
        tags: ['Accès'],
        responses: [new OA\Response(response: 200, description: 'Groupes mis à jour')],
    )]
    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        $roles = $request->validated('roles');

        if ($request->user()->is($user) && ! in_array(Role::SuperAdmin->value, $roles, true)) {
            return response()->json(['message' => AccessException::ownSuperAdminRole()->getMessage()], 422);
        }

        $user->syncRoles($roles);
        $this->access->forget();

        return response()->json(['id' => $user->id, 'roles' => $user->refresh()->getRoleNames()->values()]);
    }

    #[OA\Post(
        path: '/admin/access/groups',
        summary: 'Crée un groupe d\'utilisateurs personnalisé avec ses fonctionnalités',
        security: [['sanctum' => []]],
        tags: ['Accès'],
        responses: [new OA\Response(response: 201, description: 'Groupe créé')],
    )]
    public function storeGroup(SaveAccessGroupRequest $request): JsonResponse
    {
        $role = $this->groups->create($request->string('label')->toString(), $request->input('description'), $request->validated('permissions'));

        return response()->json($this->present($role), 201);
    }

    #[OA\Put(
        path: '/admin/access/groups/{name}',
        summary: 'Modifie un groupe personnalisé (nom, description, fonctionnalités)',
        security: [['sanctum' => []]],
        tags: ['Accès'],
        responses: [new OA\Response(response: 200, description: 'Groupe mis à jour'), new OA\Response(response: 422, description: 'Profil d\'origine')],
    )]
    public function updateGroup(SaveAccessGroupRequest $request, string $name): JsonResponse
    {
        try {
            $role = $this->groups->update($name, $request->string('label')->toString(), $request->input('description'), $request->validated('permissions'));
        } catch (AccessException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->present($role));
    }

    #[OA\Delete(
        path: '/admin/access/groups/{name}',
        summary: 'Supprime un groupe personnalisé sans membre',
        security: [['sanctum' => []]],
        tags: ['Accès'],
        responses: [new OA\Response(response: 204, description: 'Supprimé'), new OA\Response(response: 422, description: 'Groupe utilisé ou profil d\'origine')],
    )]
    public function destroyGroup(string $name): JsonResponse
    {
        try {
            $this->groups->delete($name);
        } catch (AccessException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    #[OA\Put(
        path: '/admin/access/switches',
        summary: 'Active ou désactive une fonctionnalité pour un groupe ou un utilisateur (null = valeur par défaut)',
        security: [['sanctum' => []]],
        tags: ['Accès'],
        responses: [new OA\Response(response: 204, description: 'Enregistré'), new OA\Response(response: 422, description: 'Super administrateur non restreignable')],
    )]
    public function setSwitch(SetFeatureSwitchRequest $request): JsonResponse
    {
        try {
            $this->switches->execute(
                $request->string('feature')->toString(),
                $request->string('subject_type')->toString(),
                $request->string('subject')->toString(),
                $request->input('enabled') === null ? null : $request->boolean('enabled'),
                $request->user(),
            );
        } catch (AccessException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function groupList(): array
    {
        $counts = DB::table(config('permission.table_names.model_has_roles'))->selectRaw('role_id, count(*) as total')->groupBy('role_id')->pluck('total', 'role_id');

        return RoleModel::query()->with('permissions')->where('guard_name', 'web')->orderBy('id')->get()
            ->map(fn (RoleModel $role) => $this->present($role, (int) ($counts[$role->id] ?? 0)))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(RoleModel $role, ?int $members = null): array
    {
        $builtin = Role::tryFrom($role->name);

        return [
            'name' => $role->name,
            'label' => $builtin?->label() ?? ($role->label ?: $role->name),
            'description' => $role->description,
            'builtin' => $builtin !== null,
            'members' => $members ?? $role->users()->count(),
            'permissions' => $builtin !== null
                ? array_values(array_filter(AccessCatalogue::keys(), fn (string $key) => $builtin === Role::SuperAdmin || in_array($role->name, AccessCatalogue::builtinRolesFor($key), true)))
                : $role->permissions->pluck('name')->sort()->values()->all(),
        ];
    }
}
