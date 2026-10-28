<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Application\Actions\CloseTillAction;
use App\Modules\Sales\Application\InternalSaleException;
use App\Modules\Sales\Application\RenderClosingReport;
use App\Modules\Sales\Application\TillSummary;
use App\Modules\Sales\Http\Requests\CloseTillRequest;
use App\Modules\Sales\Infrastructure\Models\CashClosing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/** End-of-day till closing, gated by can:manage-internal-sales. A cashier closes their own till. */
class CashClosingController extends Controller
{
    #[OA\Get(
        path: '/admin/cash-closings/current',
        summary: "Ce que la caisse du caissier connecté doit contenir depuis sa dernière clôture",
        security: [['sanctum' => []]],
        tags: ['Sales'],
        responses: [new OA\Response(response: 200, description: 'Période en cours')],
    )]
    public function current(Request $request, TillSummary $summary): JsonResponse
    {
        $current = $summary->forCashier((int) $request->user()->id);
        $current['period_start'] = $current['period_start']?->toIso8601String();

        return response()->json($current);
    }

    #[OA\Get(
        path: '/admin/cash-closings',
        summary: 'Historique des clôtures de caisse',
        security: [['sanctum' => []]],
        tags: ['Sales'],
        responses: [new OA\Response(response: 200, description: 'Clôtures')],
    )]
    public function index(Request $request): JsonResponse
    {
        $closings = CashClosing::query()->orderByDesc('id')->limit(max(1, min($request->integer('limit', 50), 200)))->get();

        return response()->json(['data' => $closings->map(fn (CashClosing $closing) => $this->present($closing))->all()]);
    }

    #[OA\Post(
        path: '/admin/cash-closings',
        summary: 'Clôture la caisse : fige le attendu, enregistre le compté et l\'écart',
        security: [['sanctum' => []]],
        tags: ['Sales'],
        responses: [
            new OA\Response(response: 201, description: 'Caisse clôturée'),
            new OA\Response(response: 422, description: 'Rien à clôturer'),
        ],
    )]
    public function store(CloseTillRequest $request, CloseTillAction $close): JsonResponse
    {
        try {
            $closing = $close->execute(
                (int) $request->user()->id,
                (string) $request->user()->name,
                $request->integer('counted_cash'),
                $request->filled('note') ? $request->string('note')->toString() : null,
            );
        } catch (InternalSaleException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->present($closing), 201);
    }

    #[OA\Get(
        path: '/admin/cash-closings/{closing}/pdf',
        summary: 'Rapport de clôture de caisse en PDF',
        security: [['sanctum' => []]],
        tags: ['Sales'],
        responses: [new OA\Response(response: 200, description: 'Fichier PDF'), new OA\Response(response: 404, description: 'Introuvable')],
    )]
    public function pdf(Request $request, CashClosing $closing, RenderClosingReport $report): Response
    {
        return response($report->pdf($closing, $closing->user_name, (string) $request->query('lang', 'fr')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$report->number($closing).'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CashClosing $closing): array
    {
        return [
            'id' => $closing->id,
            'number' => 'CLO-'.sprintf('%06d', $closing->id),
            'user_name' => $closing->user_name,
            'currency' => $closing->currency,
            'period_start' => $closing->period_start?->toIso8601String(),
            'closed_at' => $closing->closed_at->toIso8601String(),
            'sales_count' => $closing->sales_count,
            'cancellations_count' => $closing->cancellations_count,
            'cash_sales' => $closing->cash_sales,
            'cash_refunds' => $closing->cash_refunds,
            'expected_cash' => $closing->expected_cash,
            'counted_cash' => $closing->counted_cash,
            'variance' => $closing->variance,
            'mobile_money_net' => $closing->mobile_money_net,
            'note' => $closing->note,
        ];
    }
}
