<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Http\Controllers;

use App\Modules\Invoicing\Application\Actions\IssueCreditNoteAction;
use App\Modules\Invoicing\Application\Actions\IssueInvoiceAction;
use App\Modules\Invoicing\Domain\Exceptions\InvoiceException;
use App\Modules\Invoicing\Http\Requests\IssueCreditNoteRequest;
use App\Modules\Invoicing\Http\Requests\IssueInvoiceRequest;
use App\Modules\Invoicing\Http\Resources\InvoiceResource;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Invoices and credit notes (cahier §6.7). Gated by can:manage-invoices —
 * comptable or administrateur. Documents are immutable: there is no update
 * or delete endpoint, only credit notes.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly IssueInvoiceAction $issueInvoice,
        private readonly IssueCreditNoteAction $issueCreditNote,
    ) {}

    #[OA\Get(
        path: '/admin/invoices',
        summary: 'Liste les factures et avoirs, filtrables par commande ou type',
        security: [['sanctum' => []]],
        tags: ['Invoicing'],
        responses: [new OA\Response(response: 200, description: 'Documents')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $query = Invoice::query()->orderByDesc('id');

        if ($request->filled('order_number')) {
            $query->where('order_number', $request->string('order_number')->toString());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        return InvoiceResource::collection($query->paginate($perPage));
    }

    #[OA\Get(
        path: '/admin/invoices/{invoice}',
        summary: 'Détail d\'une facture ou d\'un avoir, avec ses lignes',
        security: [['sanctum' => []]],
        tags: ['Invoicing'],
        responses: [
            new OA\Response(response: 200, description: 'Document'),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('lines'));
    }

    #[OA\Post(
        path: '/admin/invoices',
        summary: 'Émet la facture d\'une commande confirmée',
        security: [['sanctum' => []]],
        tags: ['Invoicing'],
        responses: [
            new OA\Response(response: 201, description: 'Facture émise'),
            new OA\Response(response: 422, description: 'Commande non facturable ou déjà facturée'),
        ],
    )]
    public function store(IssueInvoiceRequest $request): InvoiceResource|JsonResponse
    {
        try {
            $invoice = $this->issueInvoice->execute(
                $request->string('order_number')->toString(),
                $request->user()?->id,
            );
        } catch (InvoiceException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/invoices/{invoice}/credit-note',
        summary: 'Annule une facture par un avoir (total)',
        security: [['sanctum' => []]],
        tags: ['Invoicing'],
        responses: [
            new OA\Response(response: 201, description: 'Avoir émis'),
            new OA\Response(response: 422, description: 'Document non créditable'),
        ],
    )]
    public function creditNote(IssueCreditNoteRequest $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        try {
            $creditNote = $this->issueCreditNote->execute(
                $invoice,
                $request->string('reason')->toString(),
                $request->user()?->id,
            );
        } catch (InvoiceException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new InvoiceResource($creditNote))->response()->setStatusCode(201);
    }
}
