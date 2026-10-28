<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\AuthenticateUserAction;
use App\Modules\Identity\Application\Actions\LogoutUserAction;
use App\Modules\Identity\Application\Actions\RegisterUserAction;
use App\Modules\Identity\Domain\Exceptions\AccountInactiveException;
use App\Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Http\Requests\UpdatePreferencesRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserAction $registerUser,
        private readonly AuthenticateUserAction $authenticateUser,
        private readonly LogoutUserAction $logoutUser,
    ) {}

    #[OA\Post(
        path: '/auth/register',
        summary: 'Crée un compte client (cahier des charges §5.1)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Awa Diallo'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerUser->execute($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Post(
        path: '/auth/login',
        summary: 'Authentifie un utilisateur et émet un jeton Sanctum',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password', 'device_name'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'device_name', type: 'string', example: 'iphone-de-awa'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Authentification réussie'),
            new OA\Response(response: 401, description: 'Identifiants invalides'),
            new OA\Response(response: 403, description: 'Compte désactivé'),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authenticateUser->execute(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->string('device_name')->toString(),
            );
        } catch (InvalidCredentialsException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (AccountInactiveException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Révoque le jeton courant',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [new OA\Response(response: 204, description: 'Déconnecté')],
    )]
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->logoutUser->execute($user);

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/auth/me',
        summary: "Retourne l'utilisateur authentifié",
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'Utilisateur courant')],
    )]
    public function me(Request $request): JsonResponse
    {
        return (new UserResource($request->user()))->response();
    }

    #[OA\Put(
        path: '/auth/preferences',
        summary: "Enregistre la langue, la couleur principale et le thème de l'utilisateur connecté",
        security: [['sanctum' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'locale', type: 'string', enum: ['fr', 'en']),
            new OA\Property(property: 'accent_color', type: 'string', nullable: true, example: '#f68b1e'),
            new OA\Property(property: 'theme', type: 'string', nullable: true, enum: ['light', 'dark', 'auto']),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Préférences enregistrées'),
            new OA\Response(response: 422, description: 'Valeur refusée'),
        ],
    )]
    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Only the fields sent are touched; accent_color may be sent as null to go back to the default.
        $user->update($request->validated());

        return (new UserResource($user->refresh()))->response();
    }

    #[OA\Post(
        path: '/auth/forgot-password',
        summary: "Envoie un lien de réinitialisation du mot de passe (réponse identique que le compte existe ou non)",
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'Demande enregistrée')],
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $user = User::query()->where('email', $email)->first();

        // Nothing is sent for an unknown or deactivated account — and nothing in the answer tells which.
        if ($user !== null && $user->is_active) {
            Password::broker()->sendResetLink(['email' => $email]);
        }

        return response()->json([
            'message' => "Si un compte correspond à cette adresse, un e-mail de réinitialisation vient d'être envoyé.",
        ]);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        summary: "Choisit un nouveau mot de passe avec le jeton reçu par e-mail (usage unique, durée limitée)",
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe changé'),
            new OA\Response(response: 422, description: 'Lien invalide ou expiré, ou mot de passe refusé'),
        ],
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                // Whoever knew the old password — or held a stolen session — is signed out everywhere.
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => "Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau."], 422);
        }

        return response()->json(['message' => 'Votre mot de passe a été changé. Vous pouvez vous connecter.']);
    }
}
