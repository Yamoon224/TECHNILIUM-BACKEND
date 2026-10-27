<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Application\Actions\ChangeTicketStatusAction;
use App\Modules\Customers\Application\Actions\ReplyToTicketAction;
use App\Modules\Customers\Domain\Exceptions\TicketException;
use App\Modules\Customers\Domain\TicketPriority;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Http\Requests\ReplyTicketRequest;
use App\Modules\Customers\Http\Requests\StaffTicketUpdateRequest;
use App\Modules\Customers\Http\Resources\StaffTicketResource;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * The support desk (cahier §6.12). Gated by can:manage-support — support or
 * administrateur.
 */
class StaffTicketController extends Controller
{
    public function __construct(
        private readonly ReplyToTicketAction $replyToTicket,
        private readonly ChangeTicketStatusAction $changeStatus,
    ) {}

    #[OA\Get(
        path: '/admin/tickets',
        summary: 'File des tickets, filtrable par statut, priorité, assignation, retard ou client',
        security: [['sanctum' => []]],
        tags: ['Support'],
        responses: [new OA\Response(response: 200, description: 'Tickets')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ticket::query();

        foreach (['status', 'priority'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->toString());
            }
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        match ($request->string('assigned')->toString()) {
            'me' => $query->where('assigned_to', $request->user()->id),
            'none' => $query->whereNull('assigned_to'),
            default => null,
        };

        if ($request->filled('q')) {
            $needle = addcslashes($request->string('q')->toString(), '%_\\');
            $query->where('subject', 'like', "%{$needle}%");
        }

        if ($request->boolean('overdue')) {
            $query->where('status', TicketStatus::Open)->whereNull('first_responded_at')
                ->where(function ($q) {
                    foreach (TicketPriority::cases() as $priority) {
                        $hours = (int) config("support.first_response_hours.{$priority->value}", 24);
                        $q->orWhere(fn ($w) => $w->where('priority', $priority)->where('created_at', '<', now()->subHours($hours)));
                    }
                });
        }

        // Oldest first: the queue is worked in the order customers started waiting.
        $query->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'pending_customer' THEN 1 ELSE 2 END")->orderBy('id');

        return StaffTicketResource::collection($query->paginate(max(1, min($request->integer('per_page', 30), 100))));
    }

    #[OA\Get(
        path: '/admin/tickets/{ticket}',
        summary: 'Un ticket avec toute la conversation, notes internes comprises',
        security: [['sanctum' => []]],
        tags: ['Support'],
        responses: [new OA\Response(response: 200, description: 'Ticket'), new OA\Response(response: 404, description: 'Introuvable')],
    )]
    public function show(Ticket $ticket): StaffTicketResource
    {
        return new StaffTicketResource($ticket->load('messages'));
    }

    #[OA\Post(
        path: '/admin/tickets/{ticket}/messages',
        summary: 'Répond au client, ou ajoute une note interne (internal=true) invisible du client',
        security: [['sanctum' => []]],
        tags: ['Support'],
        responses: [new OA\Response(response: 201, description: 'Message ajouté'), new OA\Response(response: 422, description: 'Ticket clos')],
    )]
    public function reply(ReplyTicketRequest $request, Ticket $ticket): JsonResponse
    {
        try {
            $this->replyToTicket->staffReply(
                $ticket,
                $request->user()->id,
                $request->string('body')->toString(),
                $request->boolean('internal'),
            );
        } catch (TicketException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new StaffTicketResource($ticket->refresh()->load('messages')))->response()->setStatusCode(201);
    }

    #[OA\Patch(
        path: '/admin/tickets/{ticket}',
        summary: 'Change le statut, la priorité ou l\'assignation d\'un ticket',
        security: [['sanctum' => []]],
        tags: ['Support'],
        responses: [new OA\Response(response: 200, description: 'Ticket mis à jour'), new OA\Response(response: 422, description: 'Transition interdite')],
    )]
    public function update(StaffTicketUpdateRequest $request, Ticket $ticket): StaffTicketResource|JsonResponse
    {
        try {
            if ($request->filled('status')) {
                $ticket = $this->changeStatus->execute($ticket, TicketStatus::from($request->string('status')->toString()));
            }
        } catch (TicketException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $changes = [];

        if ($request->filled('priority')) {
            $changes['priority'] = $request->string('priority')->toString();
        }

        if ($request->has('assigned_to_me')) {
            $changes['assigned_to'] = $request->boolean('assigned_to_me') ? $request->user()->id : null;
        }

        if ($changes !== []) {
            $ticket->update($changes);
        }

        return new StaffTicketResource($ticket->refresh()->load('messages'));
    }
}
