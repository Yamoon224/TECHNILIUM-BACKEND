<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Identity\Domain\Role;
use App\Modules\Reporting\Application\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/** The back-office home page. Open to every staff account: each only gets the blocks of the features it holds. */
class DashboardController extends Controller
{
    private const PERIODS = ['today' => 0, '7d' => 6, '30d' => 29, '90d' => 89];

    public function __construct(private readonly DashboardService $dashboard) {}

    #[OA\Get(
        path: '/admin/dashboard',
        summary: 'Tableau de bord du compte connecté : les blocs des fonctionnalités qu\'il possède, ceux de son profil en premier',
        security: [['sanctum' => []]],
        tags: ['Reporting'],
        parameters: [new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['today', '7d', '30d', '90d']))],
        responses: [new OA\Response(response: 200, description: 'Blocs du tableau de bord')],
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [];
        // The customer role belongs to the storefront.
        abort_if($roles === [Role::Client->value], 403);

        $period = $request->query('period');
        $period = is_string($period) && array_key_exists($period, self::PERIODS) ? $period : '30d';
        $to = Carbon::now()->endOfDay();
        $from = $to->copy()->subDays(self::PERIODS[$period])->startOfDay();

        return response()->json([
            'period' => ['key' => $period, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'roles' => array_map(fn (string $slug) => ['slug' => $slug, 'label' => Role::tryFrom($slug)?->label() ?? $slug], $roles),
            'generated_at' => now()->toIso8601String(),
            'sections' => $this->dashboard->sectionsFor($roles, fn (string $ability) => $user->can($ability), $from, $to),
        ]);
    }
}
