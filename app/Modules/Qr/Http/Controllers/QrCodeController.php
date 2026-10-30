<?php

declare(strict_types=1);

namespace App\Modules\Qr\Http\Controllers;

use App\Modules\Qr\Http\Requests\QrPayloadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * What a QR code says: `TECHNILIUM:<KIND>:<REFERENCE>`, the same text a barcode scanner or the
 * back-office search can read back. The record must exist (looked up by table name - this module
 * never reaches into another module's models), and the caller needs the ability that already
 * guards that kind of record, on top of the QR feature itself.
 */
class QrCodeController extends Controller
{
    /** The abilities that already open each kind of record; any one is enough. */
    private const ABILITIES = [
        'product' => ['manage-catalog', 'manage-stock'],
        'order' => ['manage-orders'],
        'invoice' => ['manage-invoices'],
        'supply' => ['manage-procurement'],
    ];

    #[OA\Get(
        path: '/admin/qrcodes/payload',
        summary: 'Contenu du code QR qui identifie un produit (SKU), une commande, une facture ou un approvisionnement',
        security: [['sanctum' => []]],
        tags: ['Codes QR'],
        responses: [
            new OA\Response(response: 200, description: 'Contenu et légende du code'),
            new OA\Response(response: 403, description: 'Fonctionnalité non accordée'),
            new OA\Response(response: 404, description: 'Référence inconnue'),
        ],
    )]
    public function payload(QrPayloadRequest $request): JsonResponse
    {
        $kind = $request->string('kind')->toString();
        $reference = $request->string('reference')->toString();

        if (! Gate::any(self::ABILITIES[$kind])) {
            return response()->json(['message' => "Vous n'avez pas accès à ce type de document."], 403);
        }

        $caption = $this->caption($kind, $reference);

        if ($caption === null) {
            return response()->json(['message' => 'Référence introuvable.'], 404);
        }

        return response()->json([
            'kind' => $kind,
            'reference' => $reference,
            'caption' => $caption,
            'payload' => 'TECHNILIUM:'.strtoupper($kind).':'.$reference,
        ]);
    }

    private function caption(string $kind, string $reference): ?string
    {
        return match ($kind) {
            'product' => $this->productCaption($reference),
            'order' => $this->first(DB::table('orders')->where('order_number', $reference)->value('customer_name'), "Commande {$reference}"),
            'invoice' => DB::table('invoices')->where('number', $reference)->exists() ? "Facture {$reference}" : null,
            // A supply is written as on the purchase order: BC-000012 (the bare number is accepted too).
            'supply' => preg_match('/^(?:BC-)?0*(\d+)$/i', $reference, $found) === 1 ? $this->supplyCaption((int) $found[1]) : null,
            default => null,
        };
    }

    private function productCaption(string $sku): ?string
    {
        $name = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.sku', $sku)
            ->value('products.name');

        return $name === null ? null : "{$name} - {$sku}";
    }

    private function supplyCaption(int $id): ?string
    {
        $row = DB::table('purchase_orders')->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.id', $id)->first(['suppliers.name as supplier']);

        return $row === null ? null : sprintf('Approvisionnement BC-%06d - %s', $id, $row->supplier);
    }

    /** The caption when the record exists; the lookup returns null for none (a customer name is never null). */
    private function first(mixed $found, string $caption): ?string
    {
        return $found === null ? null : $caption;
    }
}
