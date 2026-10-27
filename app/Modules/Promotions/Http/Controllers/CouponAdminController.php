<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers;

use App\Modules\Promotions\Http\Requests\StoreCouponRequest;
use App\Modules\Promotions\Http\Resources\CouponResource;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Coupon management (cahier §6.13). Gated by can:manage-promotions —
 * marketing or administrateur. A coupon is never deleted (its redemptions
 * are history): it is deactivated.
 */
class CouponAdminController extends Controller
{
    #[OA\Get(
        path: '/admin/coupons',
        summary: 'Liste les codes promotionnels',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [new OA\Response(response: 200, description: 'Coupons')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 50), 100));

        return CouponResource::collection(Coupon::query()->orderByDesc('id')->paginate($perPage));
    }

    #[OA\Post(
        path: '/admin/coupons',
        summary: 'Crée un code promotionnel (pourcentage ou montant fixe, à la charge de Technilium)',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [
            new OA\Response(response: 201, description: 'Coupon créé'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::query()->create($request->safe()->only([
            'code', 'type', 'value', 'min_order_amount', 'max_uses', 'max_uses_per_customer', 'starts_at', 'ends_at',
        ]) + ['min_order_amount' => 0, 'created_by' => $request->user()?->id]);

        return (new CouponResource($coupon->refresh()))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/coupons/{coupon}/activate',
        summary: 'Réactive un code promotionnel',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [new OA\Response(response: 200, description: 'Coupon actif')],
    )]
    public function activate(Coupon $coupon): CouponResource
    {
        $coupon->update(['active' => true]);

        return new CouponResource($coupon);
    }

    #[OA\Post(
        path: '/admin/coupons/{coupon}/deactivate',
        summary: 'Désactive un code promotionnel (les commandes déjà passées ne changent pas)',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [new OA\Response(response: 200, description: 'Coupon inactif')],
    )]
    public function deactivate(Coupon $coupon): CouponResource
    {
        $coupon->update(['active' => false]);

        return new CouponResource($coupon);
    }
}
