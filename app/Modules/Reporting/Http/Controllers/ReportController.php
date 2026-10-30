<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Application\MarginReport;
use App\Modules\Reporting\Application\ReportingService;
use App\Modules\Reporting\Http\Requests\ReportPeriodRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Management reports (cahier §6.14). Gated by can:view-reports -
 * administrateur or comptable. Read-only.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportingService $reports) {}

    #[OA\Get(
        path: '/admin/reports/dashboard',
        summary: 'Tableau de bord direction : ventes, panier moyen, top produits, finance, retours et alertes (30 derniers jours par défaut)',
        security: [['sanctum' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Synthèse de la période'),
            new OA\Response(response: 422, description: 'Période invalide ou trop longue'),
        ],
    )]
    public function dashboard(ReportPeriodRequest $request): JsonResponse
    {
        [$from, $to] = $request->period();

        return response()->json($this->reports->dashboard($from, $to));
    }

    #[OA\Get(
        path: '/admin/reports/sales-by-seller',
        summary: 'Chiffre d\'affaires par vendeur (Technilium inclus) sur la période',
        security: [['sanctum' => []]],
        tags: ['Reporting'],
        responses: [new OA\Response(response: 200, description: 'Ventes par vendeur')],
    )]
    public function salesBySeller(ReportPeriodRequest $request): JsonResponse
    {
        [$from, $to] = $request->period();

        return response()->json(['data' => $this->reports->salesBySeller($from, $to)]);
    }

    #[OA\Get(
        path: '/admin/reports/margin',
        summary: 'Marge brute de Technilium sur ses ventes en propre livrées (hors TVA, remises comprises, hors vendeurs tiers)',
        security: [['sanctum' => []]],
        tags: ['Reporting'],
        responses: [new OA\Response(response: 200, description: 'Marge par produit et par jour')],
    )]
    public function margin(ReportPeriodRequest $request, MarginReport $report): JsonResponse
    {
        [$from, $to] = $request->period();

        return response()->json($report->build($from, $to));
    }

    #[OA\Get(
        path: '/admin/reports/margin.csv',
        summary: 'Export CSV de la marge par produit (séparateur point-virgule, UTF-8)',
        security: [['sanctum' => []]],
        tags: ['Reporting'],
        responses: [new OA\Response(response: 200, description: 'Fichier CSV')],
    )]
    public function marginCsv(ReportPeriodRequest $request, MarginReport $report): StreamedResponse
    {
        [$from, $to] = $request->period();
        $products = $report->build($from, $to)['by_product'];

        return response()->streamDownload(function () use ($products) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['produit', 'unites', 'chiffre_affaires_ht', 'cout', 'marge', 'marge_pourcent'], ';');
            foreach ($products as $p) {
                fputcsv($out, [$p['product'], $p['units'], $p['revenue'], $p['cost'], $p['margin'], $p['margin_percent'] ?? ''], ';');
            }
            fclose($out);
        }, 'marge-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    #[OA\Get(
        path: '/admin/reports/sales.csv',
        summary: 'Export CSV des ventes par jour (séparateur point-virgule, UTF-8)',
        security: [['sanctum' => []]],
        tags: ['Reporting'],
        responses: [new OA\Response(response: 200, description: 'Fichier CSV')],
    )]
    public function salesCsv(ReportPeriodRequest $request): StreamedResponse
    {
        [$from, $to] = $request->period();
        $rows = $this->reports->salesByDay($from, $to);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens the accents correctly; ";" is the French Excel separator.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['date', 'commandes', 'chiffre_affaires'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [$row['date'], $row['orders'], $row['revenue']], ';');
            }
            fclose($out);
        }, 'ventes-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
