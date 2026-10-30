<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers;

use App\Modules\Procurement\Http\Requests\SupplierRequest;
use App\Modules\Procurement\Http\Resources\SupplierResource;
use App\Modules\Procurement\Infrastructure\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Supplier records (cahier §6.4). Gated by can:manage-procurement -
 * responsable achats or administrateur. A supplier is deactivated, never
 * deleted: its orders and invoices are history.
 */
class SupplierController extends Controller
{
    #[OA\Get(
        path: '/admin/suppliers',
        summary: 'Liste les fournisseurs, filtrables par statut ou nom',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Fournisseurs')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Supplier::query()->orderBy('name');

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('q')) {
            $needle = addcslashes($request->string('q')->toString(), '%_\\');
            $query->where('name', 'like', "%{$needle}%");
        }

        return SupplierResource::collection($query->paginate(max(1, min($request->integer('per_page', 50), 100))));
    }

    #[OA\Post(
        path: '/admin/suppliers',
        summary: 'Crée un fournisseur (coordonnées, conditions de paiement, délai)',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 201, description: 'Fournisseur créé'), new OA\Response(response: 422, description: 'Erreur de validation')],
    )]
    public function store(SupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::query()->create($request->validated() + ['payment_terms_days' => 30, 'active' => true]);

        return (new SupplierResource($supplier->refresh()))->response()->setStatusCode(201);
    }

    #[OA\Patch(
        path: '/admin/suppliers/{supplier}',
        summary: 'Modifie un fournisseur ou le désactive',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Fournisseur mis à jour')],
    )]
    public function update(SupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }
}
