<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain;

/** Fixed wording of the payment receipt, in French (reference) or English. */
final class ReceiptLabels
{
    /**
     * @return array<string, string>
     */
    public static function for(string $locale): array
    {
        return $locale === 'en'
            ? [
                'lang' => 'en', 'title' => 'PAYMENT RECEIPT', 'number' => 'No.', 'date' => 'Date:', 'order' => 'Order:',
                'received_from' => 'Received from', 'amount' => 'Amount received', 'method' => 'Payment method',
                'reference' => 'Transaction reference', 'refunded' => 'Of which refunded', 'net' => 'Net amount kept',
                'cod' => 'Cash on delivery', 'mobile_money' => 'Mobile money', 'phone' => 'Tel.:',
                'signature' => 'For receipt', 'footer' => 'This receipt confirms the payment of the order above. It is not an invoice.',
            ]
            : [
                'lang' => 'fr', 'title' => 'REÇU DE PAIEMENT', 'number' => 'N°', 'date' => 'Date :', 'order' => 'Commande :',
                'received_from' => 'Reçu de', 'amount' => 'Montant reçu', 'method' => 'Mode de paiement',
                'reference' => 'Référence de transaction', 'refunded' => 'Dont remboursé', 'net' => 'Montant net conservé',
                'cod' => 'Paiement à la livraison', 'mobile_money' => 'Mobile money', 'phone' => 'Tél. :',
                'signature' => 'Pour acquit', 'footer' => 'Ce reçu atteste le paiement de la commande ci-dessus. Il ne tient pas lieu de facture.',
            ];
    }

    public static function normalize(?string $locale): string
    {
        return $locale === 'en' ? 'en' : 'fr';
    }
}
