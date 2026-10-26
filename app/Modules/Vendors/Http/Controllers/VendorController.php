<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Controllers;

use App\Modules\Vendors\Application\Actions\ApplyAsVendorAction;
use App\Modules\Vendors\Http\Requests\ApplyVendorRequest;
use App\Modules\Vendors\Http\Resources\VendorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class VendorController extends Controller
{
    public function __construct(
        private readonly ApplyAsVendorAction $applyAsVendor,
    ) {}

    #[OA\Post(
        path: '/vendors/apply',
        summary: 'Candidature vendeur tiers : crée un compte et un profil vendeur en attente de validation',
        tags: ['Vendors'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'phone', 'password', 'password_confirmation', 'company_name'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'phone', type: 'string'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                new OA\Property(property: 'company_name', type: 'string'),
                new OA\Property(property: 'address', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Candidature enregistrée, en attente de validation'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function apply(ApplyVendorRequest $request): JsonResponse
    {
        $vendor = $this->applyAsVendor->execute($request->validated());

        return (new VendorResource($vendor))->response()->setStatusCode(201);
    }
}
