<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Gateways;

use App\Modules\Promotions\Application\Contracts\CouponGatewayInterface;
use App\Modules\Promotions\Application\Data\CouponQuoteData;
use App\Modules\Promotions\Domain\DiscountCalculator;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Modules\Promotions\Infrastructure\Models\CouponRedemption;
use Illuminate\Support\Facades\DB;

final class EloquentCouponGateway implements CouponGatewayInterface
{
    public function quote(string $code, int $subtotal, string $customerKey): CouponQuoteData
    {
        $normalized = Coupon::normalize($code);
        $coupon = Coupon::query()->where('code', $normalized)->first();

        return $this->evaluate($coupon, $normalized, $subtotal, $customerKey);
    }

    public function redeem(string $code, int $subtotal, string $customerKey, string $orderNumber): CouponQuoteData
    {
        return DB::transaction(function () use ($code, $subtotal, $customerKey, $orderNumber) {
            $normalized = Coupon::normalize($code);

            // Lock the coupon so concurrent checkouts are serialised and the
            // usage checks below see the true counters.
            $coupon = Coupon::query()->where('code', $normalized)->lockForUpdate()->first();
            $quote = $this->evaluate($coupon, $normalized, $subtotal, $customerKey);

            if (! $quote->valid || $coupon === null) {
                return $quote;
            }

            CouponRedemption::query()->create([
                'coupon_id' => $coupon->id,
                'order_number' => $orderNumber,
                'customer_key' => $customerKey,
                'discount_amount' => $quote->discountAmount,
            ]);
            $coupon->increment('used_count');

            return $quote;
        });
    }

    private function evaluate(?Coupon $coupon, string $code, int $subtotal, string $customerKey): CouponQuoteData
    {
        // One message for "unknown" and "disabled": no oracle on which codes exist.
        if ($coupon === null || ! $coupon->active) {
            return CouponQuoteData::refused($code, "Ce code promotionnel n'est pas valable.");
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            return CouponQuoteData::refused($code, "Ce code promotionnel n'est pas encore valable.");
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->isPast()) {
            return CouponQuoteData::refused($code, 'Ce code promotionnel a expiré.');
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return CouponQuoteData::refused($code, "Ce code promotionnel a atteint son nombre maximal d'utilisations.");
        }

        if ($coupon->max_uses_per_customer !== null) {
            $used = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('customer_key', $customerKey)
                ->count();

            if ($used >= $coupon->max_uses_per_customer) {
                return CouponQuoteData::refused($code, 'Vous avez déjà utilisé ce code promotionnel.');
            }
        }

        if ($subtotal < $coupon->min_order_amount) {
            return CouponQuoteData::refused($code, "Ce code s'applique à partir de {$coupon->min_order_amount} d'achat.");
        }

        $discount = DiscountCalculator::discount($coupon->type, $coupon->value, $subtotal);

        return $discount > 0
            ? CouponQuoteData::accepted($coupon->code, $discount)
            : CouponQuoteData::refused($code, "Ce code promotionnel n'a aucun effet sur cette commande.");
    }
}
