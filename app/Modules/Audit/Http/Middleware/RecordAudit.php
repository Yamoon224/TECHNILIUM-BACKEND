<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Middleware;

use App\Modules\Audit\Application\RecordAuditEntryAction;
use App\Modules\Audit\Domain\PayloadSanitizer;
use App\Modules\Identity\Domain\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records every state-changing request made by a back-office account (staff
 * and vendors; not customers or guests) - who, what, when, from where, and
 * whether it succeeded - including refused attempts (403/422), which are the
 * interesting ones. Runs in terminate(), after the response is sent: it can
 * neither slow the user down nor fail the request, and the status code is known.
 */
class RecordAudit
{
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! in_array($request->method(), self::MUTATING, true)) {
            return;
        }

        $user = $request->user('sanctum');

        if ($user === null) {
            return;
        }

        $roles = $user->getRoleNames()->all();

        // Staff, vendors and the custom groups the super admin creates: everyone but customers.
        if (array_diff($roles, [Role::Client->value]) === []) {
            return;
        }

        try {
            app(RecordAuditEntryAction::class)->execute([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'roles' => implode(',', $roles),
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route' => $request->route()?->uri(),
                'status' => $response->getStatusCode(),
                'payload' => PayloadSanitizer::sanitize($request->except(['password', 'password_confirmation']) ?: []),
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (Throwable $exception) {
            // Auditing must never take the application down; the failure is reported.
            report($exception);
        }
    }
}
