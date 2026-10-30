<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

/**
 * Customer-facing wording (French first, cahier §5.11), kept free of
 * persistence so every message is unit-testable and a rewording touches one
 * file. Messages stay short: they travel by SMS.
 */
final class MessageTemplates
{
    private const STATUS_WORDING_EN = [
        'confirmed' => 'is confirmed',
        'shipped' => 'has shipped and is on its way to you',
        'delivered' => 'has been delivered. Thank you for your trust',
        'cancelled' => 'has been cancelled',
    ];

    /** Order statuses the customer is told about; "pending" is covered by the placed message. */
    private const STATUS_WORDING = [
        'confirmed' => 'est confirmée',
        'shipped' => 'est expédiée, elle est en route vers vous',
        'delivered' => 'a été livrée. Merci de votre confiance',
        'cancelled' => 'a été annulée',
    ];

    public static function formatAmount(int $amount, string $currency): string
    {
        $number = number_format($amount, 0, ',', ' ');

        return in_array($currency, ['XOF', 'XAF'], true) ? "{$number} F CFA" : "{$number} {$currency}";
    }

    public static function orderPlaced(string $customerName, string $orderNumber, int $total, string $currency, ?string $deliveryCode, string $locale = 'fr'): string
    {
        if ($locale === 'en') {
            $message = sprintf(
                'Technilium: thank you %s, your order %s is placed (%s, cash on delivery).',
                self::firstName($customerName),
                $orderNumber,
                self::formatAmount($total, $currency),
            );

            return $deliveryCode === null
                ? $message
                : $message." Delivery code: {$deliveryCode} - only give it to the courier when you receive the parcel.";
        }

        $message = sprintf(
            'Technilium : merci %s, votre commande %s est enregistrée (%s, paiement à la livraison).',
            self::firstName($customerName),
            $orderNumber,
            self::formatAmount($total, $currency),
        );

        if ($deliveryCode !== null) {
            $message .= " Code de livraison : {$deliveryCode} - ne le donnez au livreur qu'à la remise du colis.";
        }

        return $message;
    }

    /** Null for a status the customer is not notified about. */
    public static function orderStatus(string $customerName, string $orderNumber, string $status, string $locale = 'fr'): ?string
    {
        if ($locale === 'en') {
            $wording = self::STATUS_WORDING_EN[$status] ?? null;

            return $wording === null
                ? null
                : sprintf('Technilium: %s, your order %s %s.', self::firstName($customerName), $orderNumber, $wording);
        }

        $wording = self::STATUS_WORDING[$status] ?? null;

        return $wording === null
            ? null
            : sprintf('Technilium : %s, votre commande %s %s.', self::firstName($customerName), $orderNumber, $wording);
    }

    public static function returnRefunded(string $orderNumber, int $amount, string $currency, string $locale = 'fr'): string
    {
        if ($locale === 'en') {
            return sprintf('Technilium: your return on order %s is refunded (%s).', $orderNumber, self::formatAmount($amount, $currency));
        }

        return sprintf(
            'Technilium : votre retour sur la commande %s est remboursé (%s).',
            $orderNumber,
            self::formatAmount($amount, $currency),
        );
    }

    /** Null for a status the customer is not told about. */
    public static function returnDecided(int $returnId, string $orderNumber, string $status, ?string $note, string $locale = 'fr'): ?string
    {
        if ($locale === 'en') {
            $wording = match ($status) {
                'accepted' => 'is accepted: please send the parcel back',
                'received' => 'has been received, we are checking it',
                'rejected' => 'is refused',
                default => null,
            };

            if ($wording === null) {
                return null;
            }

            $message = sprintf('Technilium: your return request RMA-%06d (order %s) %s.', $returnId, $orderNumber, $wording);
            $note = trim((string) $note);

            return $note === '' ? $message : $message.' '.mb_strimwidth($note, 0, 120, '…');
        }

        $wording = match ($status) {
            'accepted' => 'est acceptée : renvoyez le colis',
            'received' => 'a été reçu, nous le vérifions',
            'rejected' => 'est refusée',
            default => null,
        };

        if ($wording === null) {
            return null;
        }

        $message = sprintf('Technilium : votre demande de retour RMA-%06d (commande %s) %s.', $returnId, $orderNumber, $wording);
        $note = trim((string) $note);

        // SMS stays short: the full answer is in the customer's account.
        return $note === '' ? $message : $message.' '.mb_strimwidth($note, 0, 120, '…');
    }

    private static function firstName(string $fullName): string
    {
        return explode(' ', trim($fullName))[0] ?: 'client';
    }
}
