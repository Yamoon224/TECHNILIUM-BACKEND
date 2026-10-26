<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Controllers\Admin;

use App\Modules\Vendors\Application\Actions\TransitionVendorStatusAction;
use App\Modules\Vendors\Domain\Exceptions\InvalidVendorTransitionException;
use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Http\Resources\VendorResource;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Vendor onboarding (cahier §4). Gated by can:manage-vendors — administrateur only.
 */
class VendorAdminController extends Controller
{
    public function __construct(
        private readonly TransitionVendorStatusAction $transitionVendorStatus,
    ) {}

    #[OA\Get(
        path: '/admin/vendors',
        summary: 'Liste les candidatures et comptes vendeurs tiers',
        security: [['sanctum' => []]],
        tags: ['Vendors Admin'],
        responses: [new OA\Response(response: 200, description: 'Vendeurs')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Vendor::query()->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return VendorResource::collection($query->get());
    }

    #[OA\Post(
        path: '/admin/vendors/{vendor}/approve',
        summary: 'Valide une candidature vendeur',
        security: [['sanctum' => []]],
        tags: ['Vendors Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Vendeur activé'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function approve(Vendor $vendor): VendorResource|JsonResponse
    {
        return $this->transition($vendor, VendorStatus::Active);
    }

    #[OA\Post(
        path: '/admin/vendors/{vendor}/suspend',
        summary: 'Suspend un vendeur',
        security: [['sanctum' => []]],
        tags: ['Vendors Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Vendeur suspendu'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function suspend(Vendor $vendor): VendorResource|JsonResponse
    {
        return $this->transition($vendor, VendorStatus::Suspended);
    }

    #[OA\Post(
        path: '/admin/vendors/{vendor}/reactivate',
        summary: 'Réactive un vendeur suspendu',
        security: [['sanctum' => []]],
        tags: ['Vendors Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Vendeur réactivé'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function reactivate(Vendor $vendor): VendorResource|JsonResponse
    {
        return $this->transition($vendor, VendorStatus::Active);
    }

    private function transition(Vendor $vendor, VendorStatus $target): VendorResource|JsonResponse
    {
        try {
            $vendor = $this->transitionVendorStatus->execute($vendor, $target);
        } catch (InvalidVendorTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new VendorResource($vendor);
    }
}
