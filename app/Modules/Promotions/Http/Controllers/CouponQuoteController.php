<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers;

use App\Modules\Promotions\Application\Contracts\CouponGatewayInterface;
use App\Modules\Promotions\Http\Requests\QuoteCouponRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Cart preview for the storefront: what a code would take off. Public but
 * throttled — it must not become a way to enumerate valid codes (refusals
 * for unknown and disabled codes are deliberately identical).
 */
class CouponQuoteController extends Controller
{
    public function __construct(private readonly CouponGatewayInterface $coupons) {}

    #[OA\Post(
        path: '/coupons/quote',
        summary: 'Aperçu de la remise d\'un code sur un sous-total de panier (sans l\'utiliser)',
        tags: ['Promotions'],
        responses: [
            new OA\Response(response: 200, description: 'Remise applicable'),
            new OA\Response(response: 422, description: 'Code refusé (raison fournie)'),
        ],
    )]
    public function __invoke(QuoteCouponRequest $request): JsonResponse
    {
        $quote = $this->coupons->quote(
            $request->string('code')->toString(),
            $request->integer('subtotal'),
            trim($request->string('customer_phone')->toString()),
        );

        if (! $quote->valid) {
            return response()->json(['message' => $quote->reason], 422);
        }

        return response()->json([
            'code' => $quote->code,
            'discount_amount' => $quote->discountAmount,
            'total_after_discount' => $request->integer('subtotal') - $quote->discountAmount,
        ]);
    }
}
