<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Application\VerifyAuditChainAction;
use App\Modules\Audit\Http\Requests\AuditFilterRequest;
use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Infrastructure\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Read-only view of the audit trail (cahier §6.15). Gated by can:view-audit -
 * administrateur only. There is deliberately no write or delete endpoint.
 */
class AuditController extends Controller
{
    #[OA\Get(
        path: '/admin/audit',
        summary: 'Journal d\'audit des actions backoffice (qui, quoi, quand, depuis où), du plus récent au plus ancien',
        security: [['sanctum' => []]],
        tags: ['Audit'],
        responses: [new OA\Response(response: 200, description: 'Entrées')],
    )]
    public function index(AuditFilterRequest $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()->orderByDesc('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('method')) {
            $query->where('method', $request->string('method')->toString());
        }

        if ($request->filled('path')) {
            // Escaped so a "%" typed by the user is a literal, not a wildcard.
            $needle = addcslashes($request->string('path')->toString(), '%_\\');
            $query->where('path', 'like', "%{$needle}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        if ($request->boolean('failed')) {
            $query->where('status', '>=', 400);
        }

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->string('from')->toString().' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->string('to')->toString().' 23:59:59');
        }

        $perPage = max(1, min($request->integer('per_page', 50), 100));

        return AuditLogResource::collection($query->paginate($perPage));
    }

    #[OA\Get(
        path: '/admin/audit/verify',
        summary: 'Vérifie l\'intégrité de la chaîne d\'audit (signatures et chaînage)',
        security: [['sanctum' => []]],
        tags: ['Audit'],
        responses: [new OA\Response(response: 200, description: 'Résultat de la vérification')],
    )]
    public function verify(VerifyAuditChainAction $verify): JsonResponse
    {
        return response()->json($verify->execute());
    }
}
