<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Resources\JournalEntryResource;
use App\Modules\Accounting\Infrastructure\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * Read-only ledger views (cahier §6.9). Entries are only ever produced by
 * domain events - there is deliberately no write endpoint yet (manual entries
 * need the double-validation workflow).
 */
class AccountingController extends Controller
{
    #[OA\Get(
        path: '/admin/accounting/entries',
        summary: 'Liste les écritures comptables, filtrables par journal ou référence',
        security: [['sanctum' => []]],
        tags: ['Accounting'],
        responses: [new OA\Response(response: 200, description: 'Écritures')],
    )]
    public function entries(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $query = JournalEntry::query()->with('lines')->orderByDesc('id');

        if ($request->filled('journal')) {
            $query->where('journal', $request->string('journal')->toString());
        }

        if ($request->filled('reference')) {
            $query->where('reference', $request->string('reference')->toString());
        }

        return JournalEntryResource::collection($query->paginate($perPage));
    }

    #[OA\Get(
        path: '/admin/accounting/trial-balance',
        summary: 'Balance générale : débit, crédit et solde par compte',
        security: [['sanctum' => []]],
        tags: ['Accounting'],
        responses: [new OA\Response(response: 200, description: 'Balance')],
    )]
    public function trialBalance(): JsonResponse
    {
        $rows = DB::table('accounts')
            ->leftJoin('journal_entry_lines as l', 'l.account_code', '=', 'accounts.code')
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get([
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('COALESCE(SUM(l.debit), 0) as debit'),
                DB::raw('COALESCE(SUM(l.credit), 0) as credit'),
            ]);

        $accounts = $rows->map(fn ($r) => [
            'code' => $r->code,
            'name' => $r->name,
            'type' => $r->type,
            'debit' => (int) $r->debit,
            'credit' => (int) $r->credit,
            'balance' => (int) $r->debit - (int) $r->credit,
        ])->all();

        return response()->json([
            'accounts' => $accounts,
            'total_debit' => array_sum(array_column($accounts, 'debit')),
            'total_credit' => array_sum(array_column($accounts, 'credit')),
        ]);
    }
}
