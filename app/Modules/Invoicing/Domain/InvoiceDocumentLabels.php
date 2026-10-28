<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Domain;

/**
 * The fixed wording of an invoice / credit note PDF, in the customer's
 * language. The legal content (numbers, amounts, issuer) is the same in both;
 * only the labels change. French is the reference.
 */
final class InvoiceDocumentLabels
{
    /**
     * @return array<string, string>
     */
    public static function for(string $locale): array
    {
        return $locale === 'en' ? self::english() : self::french();
    }

    /** Anything but "en" is French. */
    public static function normalize(?string $locale): string
    {
        return $locale === 'en' ? 'en' : 'fr';
    }

    /**
     * @return array<string, string>
     */
    private static function french(): array
    {
        return [
            'lang' => 'fr',
            'invoice' => 'FACTURE',
            'credit_note' => 'AVOIR',
            'phone' => 'Tél. :',
            'number' => 'N°',
            'date' => 'Date :',
            'order' => 'Commande :',
            'cancels' => 'Annule la facture :',
            'customer' => 'Client',
            'credit_reason' => "Motif de l'avoir :",
            'description' => 'Désignation',
            'quantity' => 'Qté',
            'unit_price' => 'Prix unitaire TTC',
            'line_total' => 'Total TTC',
            'discount' => 'Remise',
            'total_net' => 'Total HT',
            'vat' => 'TVA',
            'total_gross_invoice' => 'Total TTC',
            'total_gross_credit' => "Total de l'avoir TTC",
            'words_invoice' => 'Arrêté la présente facture à la somme de',
            'words_credit' => 'Arrêté le présent avoir à la somme de',
            'footer_invoice' => 'Document de facturation émis par :issuer — n° :number. Un document émis ne se modifie pas : toute correction passe par un avoir.',
            'footer_credit' => "Document d'avoir émis par :issuer — n° :number. Un document émis ne se modifie pas : toute correction passe par un avoir.",
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function english(): array
    {
        return [
            'lang' => 'en',
            'invoice' => 'INVOICE',
            'credit_note' => 'CREDIT NOTE',
            'phone' => 'Tel.:',
            'number' => 'No.',
            'date' => 'Date:',
            'order' => 'Order:',
            'cancels' => 'Cancels invoice:',
            'customer' => 'Customer',
            'credit_reason' => 'Reason for the credit note:',
            'description' => 'Description',
            'quantity' => 'Qty',
            'unit_price' => 'Unit price incl. VAT',
            'line_total' => 'Total incl. VAT',
            'discount' => 'Discount',
            'total_net' => 'Total excl. VAT',
            'vat' => 'VAT',
            'total_gross_invoice' => 'Total incl. VAT',
            'total_gross_credit' => 'Credit note total incl. VAT',
            'words_invoice' => 'This invoice is closed at the sum of',
            'words_credit' => 'This credit note is closed at the sum of',
            'footer_invoice' => 'Invoice issued by :issuer — no. :number. An issued document cannot be changed: any correction is made through a credit note.',
            'footer_credit' => 'Credit note issued by :issuer — no. :number. An issued document cannot be changed: any correction is made through a credit note.',
        ];
    }
}
