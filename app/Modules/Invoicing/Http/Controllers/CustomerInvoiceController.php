<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Http\Controllers;

use App\Modules\Invoicing\Application\RenderInvoiceDocument;
use App\Modules\Invoicing\Http\Resources\InvoiceResource;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * A customer's own invoices and credit notes. Ownership is the order's
 * customer; anything else — someone else's order, a guest order, an unknown
 * number — answers 404, never 403, so numbers cannot be probed.
 */
class CustomerInvoiceController extends Controller
{
    public function __construct(private readonly OrderLookupGatewayInterface $orders) {}

    #[OA\Get(
        path: '/customer/orders/{orderNumber}/invoices',
        summary: 'Factures et avoirs d\'une commande du client connecté',
        security: [['sanctum' => []]],
        tags: ['Invoicing'],
        responses: [
            new OA\Response(response: 200, description: 'Documents'),
            new OA\Response(response: 404, description: 'Commande introuvable ou non rattachée au client'),
        ],
    )]
    public function forOrder(Request $request, string $orderNumber): JsonResponse
    {
        abort_unless($this->owns($request, $orderNumber), 404);

        $documents = Invoice::query()->where('order_number', $orderNumber)->orderBy('id')->get();

        // Explicit "data" key: resources are unwrapped app-wide, but the other lists carry it.
        return response()->json([
            'data' => InvoiceResource::collection($documents)->resolve($request),
        ]);
    }

    #[OA\Get(
        path: '/customer/invoices/{invoice}/pdf',
        summary: 'Télécharge en PDF une facture ou un avoir du client connecté',
        security: [['sanctum' => []]],
        tags: ['Invoicing'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier PDF'),
            new OA\Response(response: 404, description: 'Introuvable ou non rattaché au client'),
        ],
    )]
    public function pdf(Request $request, Invoice $invoice, RenderInvoiceDocument $document): Response
    {
        abort_unless($this->owns($request, $invoice->order_number), 404);

        return response($document->pdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->filename($invoice).'"',
        ]);
    }

    private function owns(Request $request, string $orderNumber): bool
    {
        $order = $this->orders->find($orderNumber);

        return $order !== null
            && $order->customerId !== null
            && $order->customerId === $request->user()->id;
    }
}
