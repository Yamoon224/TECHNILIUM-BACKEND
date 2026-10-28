<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Domain;

/** Fixed wording of the delivery note, in French (reference) or English. */
final class DeliveryNoteLabels
{
    /**
     * @return array<string, string>
     */
    public static function for(string $locale): array
    {
        return $locale === 'en'
            ? [
                'lang' => 'en', 'title' => 'DELIVERY NOTE', 'number' => 'No.', 'date' => 'Date:', 'order' => 'Order:',
                'deliver_to' => 'Deliver to', 'phone' => 'Phone:', 'items' => 'Items to hand over', 'item' => 'Item',
                'quantity' => 'Qty', 'notes' => 'Notes', 'receipt' => 'Received in good condition',
                'customer_signature' => 'Customer signature', 'courier_signature' => 'Courier signature',
                'code_note' => 'The customer gives the delivery confirmation code when receiving the parcel. It is not printed here.',
                'phone_short' => 'Tel.:',
            ]
            : [
                'lang' => 'fr', 'title' => 'BON DE LIVRAISON', 'number' => 'N°', 'date' => 'Date :', 'order' => 'Commande :',
                'deliver_to' => 'À livrer à', 'phone' => 'Téléphone :', 'items' => 'Articles à remettre', 'item' => 'Désignation',
                'quantity' => 'Qté', 'notes' => 'Notes', 'receipt' => 'Reçu en bon état',
                'customer_signature' => 'Signature du client', 'courier_signature' => 'Signature du livreur',
                'code_note' => 'Le client donne le code de confirmation de livraison à la remise du colis. Il n\'est pas imprimé ici.',
                'phone_short' => 'Tél. :',
            ];
    }

    public static function normalize(?string $locale): string
    {
        return $locale === 'en' ? 'en' : 'fr';
    }
}
