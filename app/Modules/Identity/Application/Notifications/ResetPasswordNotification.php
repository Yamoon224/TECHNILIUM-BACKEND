<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Notifications;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The e-mail that carries a password-reset link, in the account's language. The link opens the
 * storefront for a customer and the back office for anyone with a staff role.
 */
final class ResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire');
        $english = $notifiable->locale === 'en';

        return (new MailMessage)
            ->subject($english ? 'Reset your password' : 'Réinitialisation de votre mot de passe')
            ->greeting($english ? 'Hello '.$notifiable->name.',' : 'Bonjour '.$notifiable->name.',')
            ->line($english
                ? 'You are receiving this e-mail because a password reset was requested for your account.'
                : 'Vous recevez cet e-mail car une réinitialisation du mot de passe a été demandée pour votre compte.')
            ->action($english ? 'Choose a new password' : 'Choisir un nouveau mot de passe', $this->url($notifiable))
            ->line($english
                ? "This link expires in {$minutes} minutes and can be used once."
                : "Ce lien expire dans {$minutes} minutes et ne peut servir qu'une fois.")
            ->line($english
                ? 'If you did not ask for this, you can ignore this message: your password stays as it is.'
                : "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.")
            ->salutation($english ? 'Technilium' : 'Technilium');
    }

    /** The reset page of the right front-end, with the token and the e-mail (both URL-encoded). */
    public function url(User $user): string
    {
        $base = $this->isCustomerOnly($user) ? config('app.store_url') : config('app.backoffice_url');

        return rtrim((string) $base, '/').'/reinitialiser-mot-de-passe?'.http_build_query([
            'token' => $this->token,
            'email' => $user->email,
        ]);
    }

    private function isCustomerOnly(User $user): bool
    {
        $roles = $user->getRoleNames();

        return $roles->isEmpty() || $roles->every(fn (string $role) => $role === Role::Client->value);
    }
}
