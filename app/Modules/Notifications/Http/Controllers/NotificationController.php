<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Application\Jobs\SendNotificationJob;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Http\Resources\NotificationResource;
use App\Modules\Notifications\Infrastructure\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Journal of customer messages (cahier §5.11 / §6.12) and manual retry of
 * failed ones. Gated by can:manage-notifications - support or administrateur.
 */
class NotificationController extends Controller
{
    #[OA\Get(
        path: '/admin/notifications',
        summary: 'Journal des messages envoyés aux clients, filtrable par statut, canal ou commande',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        responses: [new OA\Response(response: 200, description: 'Messages')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 50), 100));

        $query = Notification::query()->orderByDesc('id');

        foreach (['status', 'channel', 'reference'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->toString());
            }
        }

        return NotificationResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/admin/notifications/{notification}/retry',
        summary: 'Relance l\'envoi d\'un message en échec',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Message remis en file'),
            new OA\Response(response: 422, description: 'Seul un message en échec peut être relancé'),
        ],
    )]
    public function retry(Notification $notification): NotificationResource|JsonResponse
    {
        if ($notification->status !== NotificationStatus::Failed) {
            return response()->json(['message' => 'Seul un message en échec peut être relancé.'], 422);
        }

        $notification->update(['status' => NotificationStatus::Pending, 'error' => null]);
        SendNotificationJob::dispatch($notification->id);

        return new NotificationResource($notification->refresh());
    }
}
