<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Application\Actions\OpenTicketAction;
use App\Modules\Customers\Application\Actions\ReplyToTicketAction;
use App\Modules\Customers\Domain\Exceptions\TicketException;
use App\Modules\Customers\Domain\TicketCategory;
use App\Modules\Customers\Http\Requests\OpenTicketRequest;
use App\Modules\Customers\Http\Requests\ReplyTicketRequest;
use App\Modules\Customers\Http\Resources\TicketResource;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * A customer's own support tickets (cahier §6.12, §5.8). Every query is
 * scoped to the authenticated account: someone else's ticket answers 404,
 * exactly like a ticket that does not exist.
 */
class CustomerTicketController extends Controller
{
    public function __construct(
        private readonly OpenTicketAction $openTicket,
        private readonly ReplyToTicketAction $replyToTicket,
    ) {}

    #[OA\Get(
        path: '/support/tickets',
        summary: 'Mes tickets de support',
        security: [['sanctum' => []]],
        tags: ['Support'],
        responses: [new OA\Response(response: 200, description: 'Tickets du client connecté')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $tickets = Ticket::query()
            ->where('customer_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(max(1, min($request->integer('per_page', 20), 100)));

        return TicketResource::collection($tickets);
    }

    #[OA\Post(
        path: '/support/tickets',
        summary: 'Ouvre un ticket de support, éventuellement sur une de mes commandes',
        security: [['sanctum' => []]],
        tags: ['Support'],
        responses: [
            new OA\Response(response: 201, description: 'Ticket ouvert'),
            new OA\Response(response: 422, description: 'Données invalides ou commande introuvable sur le compte'),
        ],
    )]
    public function store(OpenTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->openTicket->execute(
                $request->user()->id,
                $request->string('subject')->toString(),
                TicketCategory::from($request->string('category')->toString()),
                $request->string('message')->toString(),
                $request->filled('order_number') ? $request->string('order_number')->toString() : null,
            );
        } catch (TicketException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/support/tickets/{ticket}',
        summary: 'Un de mes tickets avec la conversation (notes internes exclues)',
        security: [['sanctum' => []]],
        tags: ['Support'],
        responses: [
            new OA\Response(response: 200, description: 'Ticket'),
            new OA\Response(response: 404, description: 'Introuvable (ou appartenant à un autre client)'),
        ],
    )]
    public function show(Request $request, int $ticket): TicketResource
    {
        return new TicketResource($this->own($request, $ticket)->load('messages'));
    }

    #[OA\Post(
        path: '/support/tickets/{ticket}/messages',
        summary: 'Répond sur mon ticket (le rouvre s\'il était résolu)',
        security: [['sanctum' => []]],
        tags: ['Support'],
        responses: [
            new OA\Response(response: 201, description: 'Message ajouté'),
            new OA\Response(response: 422, description: 'Ticket clos'),
        ],
    )]
    public function reply(ReplyTicketRequest $request, int $ticket): JsonResponse
    {
        $own = $this->own($request, $ticket);

        try {
            $this->replyToTicket->customerReply($own, $request->user()->id, $request->string('body')->toString());
        } catch (TicketException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new TicketResource($own->refresh()->load('messages')))->response()->setStatusCode(201);
    }

    private function own(Request $request, int $id): Ticket
    {
        return Ticket::query()->where('customer_id', $request->user()->id)->findOrFail($id);
    }
}
