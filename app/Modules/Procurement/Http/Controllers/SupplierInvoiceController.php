<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers;

use App\Modules\Procurement\Application\Actions\PaySupplierInvoiceAction;
use App\Modules\Procurement\Application\Actions\RecordSupplierInvoiceAction;
use App\Modules\Procurement\Domain\Exceptions\ProcurementException;
use App\Modules\Procurement\Http\Requests\PaySupplierInvoiceRequest;
use App\Modules\Procurement\Http\Requests\StoreSupplierInvoiceRequest;
use App\Modules\Procurement\Http\Resources\SupplierInvoiceResource;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use App\Modules\Procurement\Infrastructure\Models\Supplier;
use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/** Supplier invoices and their settlement (cahier §6.4 "suivi des dettes fournisseurs"). */
class SupplierInvoiceController extends Controller
{
    public function __construct(
        private readonly RecordSupplierInvoiceAction $record,
        private readonly PaySupplierInvoiceAction $pay,
    ) {}

    #[OA\Get(
        path: '/admin/supplier-invoices',
        summary: 'Liste les factures fournisseurs (ouvertes, soldées, en retard), filtrables par fournisseur',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Factures')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SupplierInvoice::query()->with('supplier')->orderBy('due_date')->orderBy('id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        match ($request->string('status')->toString()) {
            'open' => $query->whereColumn('paid_amount', '<', 'amount'),
            'settled' => $query->whereColumn('paid_amount', '>=', 'amount'),
            'overdue' => $query->whereColumn('paid_amount', '<', 'amount')->whereDate('due_date', '<', now()->toDateString()),
            default => null,
        };

        return SupplierInvoiceResource::collection($query->paginate(max(1, min($request->integer('per_page', 50), 100))));
    }

    #[OA\Post(
        path: '/admin/supplier-invoices',
        summary: 'Enregistre une facture reçue d\'un fournisseur (échéance = date + conditions de paiement par défaut)',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 201, description: 'Facture enregistrée'), new OA\Response(response: 422, description: 'Numéro déjà saisi ou données invalides')],
    )]
    public function store(StoreSupplierInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->record->execute(
            Supplier::query()->findOrFail($request->integer('supplier_id')),
            $request->string('number')->toString(),
            $request->string('kind')->toString(),
            $request->integer('amount'),
            $request->string('invoice_date')->toString(),
            $request->filled('due_date') ? $request->string('due_date')->toString() : null,
            $request->filled('purchase_order_id') ? PurchaseOrder::query()->find($request->integer('purchase_order_id')) : null,
        );

        return (new SupplierInvoiceResource($invoice->load('supplier')))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/supplier-invoices/{supplierInvoice}/payments',
        summary: 'Règle tout ou partie d\'une facture fournisseur (jamais plus que le reste à payer)',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Facture mise à jour'), new OA\Response(response: 422, description: 'Montant trop élevé ou facture soldée')],
    )]
    public function pay(PaySupplierInvoiceRequest $request, SupplierInvoice $supplierInvoice): SupplierInvoiceResource|JsonResponse
    {
        try {
            $this->pay->execute(
                $supplierInvoice,
                $request->filled('amount') ? $request->integer('amount') : null,
                $request->string('method')->toString(),
                $request->filled('reference') ? $request->string('reference')->toString() : null,
                $request->user()?->id,
            );
        } catch (ProcurementException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new SupplierInvoiceResource($supplierInvoice->refresh()->load('supplier'));
    }
}
