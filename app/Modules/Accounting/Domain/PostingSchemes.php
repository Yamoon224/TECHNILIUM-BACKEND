<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain;

/**
 * Pure accounting rules: which accounts each business event hits. Kept free
 * of persistence so every scheme is unit-testable and an addition (new
 * journal, new VAT account) is a change here only (cahier §10.1, O).
 */
final class PostingSchemes
{
    public const CLIENTS = '411';

    public const CASH = '571';

    public const MOBILE_MONEY = '585';

    public const VAT_COLLECTED = '4431';

    public const SALES = '701';

    public const COMMISSIONS = '706';

    public const VENDOR_PAYABLES = '4011';

    /**
     * Sale: customer owes the VAT-inclusive total; revenue net, VAT to the state.
     * A credit note is the exact mirror.
     *
     * @return list<EntryLine>
     */
    public static function invoice(int $net, int $vat, int $total, bool $isCreditNote): array
    {
        $lines = array_values(array_filter([
            new EntryLine(self::CLIENTS, debit: $total),
            new EntryLine(self::SALES, credit: $net),
            $vat > 0 ? new EntryLine(self::VAT_COLLECTED, credit: $vat) : null,
        ]));

        return $isCreditNote ? array_map(fn (EntryLine $l) => $l->reversed(), $lines) : $lines;
    }

    /**
     * Collection of cash settles the customer receivable; a refund reverses it.
     *
     * @return list<EntryLine>
     */
    public static function payment(string $method, int $amount, bool $refunded): array
    {
        $lines = [
            new EntryLine(self::treasuryAccount($method), debit: $amount),
            new EntryLine(self::CLIENTS, credit: $amount),
        ];

        return $refunded ? array_map(fn (EntryLine $l) => $l->reversed(), $lines) : $lines;
    }

    /**
     * Invoices book every sale to 701; for a vendor's share, the gross leaves
     * revenue, Technilium keeps the commission and owes the net to the vendor.
     *
     * @return list<EntryLine>
     */
    public static function payoutCalculated(int $gross, int $commission, int $net): array
    {
        return array_values(array_filter([
            new EntryLine(self::SALES, debit: $gross),
            $commission > 0 ? new EntryLine(self::COMMISSIONS, credit: $commission) : null,
            $net > 0 ? new EntryLine(self::VENDOR_PAYABLES, credit: $net) : null,
        ]));
    }

    /**
     * The transfer settles the vendor payable out of mobile money / bank.
     *
     * @return list<EntryLine>
     */
    public static function payoutPaid(int $net): array
    {
        return [
            new EntryLine(self::VENDOR_PAYABLES, debit: $net),
            new EntryLine(self::MOBILE_MONEY, credit: $net),
        ];
    }

    public static function paymentJournal(string $method): string
    {
        return $method === 'mobile_money' ? 'MM' : 'CA';
    }

    private static function treasuryAccount(string $method): string
    {
        return $method === 'mobile_money' ? self::MOBILE_MONEY : self::CASH;
    }
}
