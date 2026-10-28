<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function resetUser(Role $role = Role::Client, array $attributes = []): User
{
    $user = User::factory()->create($attributes + ['email' => 'awa@example.com', 'password' => Hash::make('Ancien123')]);
    $user->assignRole($role->value);

    return $user;
}

it('sends a reset link to an existing account', function () {
    Notification::fake();
    $user = resetUser();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'awa@example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('answers exactly the same for an unknown address, and sends nothing', function () {
    Notification::fake();
    resetUser();

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'awa@example.com'])->assertOk()->json();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'inconnu@example.com'])->assertOk()->json();

    expect($unknown)->toBe($known);
    Notification::assertCount(1);
});

it('sends nothing to a deactivated account, with the same answer', function () {
    Notification::fake();
    resetUser(Role::Client, ['is_active' => false]);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'awa@example.com'])->assertOk();

    Notification::assertNothingSent();
});

it('validates the e-mail address', function () {
    $this->postJson('/api/v1/auth/forgot-password', [])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'pas-un-email'])->assertUnprocessable();
});

it('links a customer to the storefront and a staff member to the back office', function () {
    config(['app.store_url' => 'https://boutique.example', 'app.backoffice_url' => 'https://admin.example']);
    $customer = resetUser();
    $staff = resetUser(Role::Comptable, ['email' => 'compta@example.com']);

    $customerUrl = (new ResetPasswordNotification('tok123'))->url($customer);
    $staffUrl = (new ResetPasswordNotification('tok123'))->url($staff);

    expect($customerUrl)->toStartWith('https://boutique.example/reinitialiser-mot-de-passe?')
        ->and($customerUrl)->toContain('token=tok123')->toContain('email=awa%40example.com')
        ->and($staffUrl)->toStartWith('https://admin.example/reinitialiser-mot-de-passe?');
});

it('writes the e-mail in the account\'s language', function () {
    $french = resetUser(Role::Client, ['locale' => 'fr']);
    $english = resetUser(Role::Client, ['locale' => 'en', 'email' => 'en@example.com']);

    $fr = (new ResetPasswordNotification('t'))->toMail($french);
    $en = (new ResetPasswordNotification('t'))->toMail($english);

    expect($fr->subject)->toBe('Réinitialisation de votre mot de passe')->and($fr->actionText)->toBe('Choisir un nouveau mot de passe')
        ->and($en->subject)->toBe('Reset your password')->and($en->actionText)->toBe('Choose a new password');
});

it('changes the password with a valid token, and signs the user out everywhere', function () {
    $user = resetUser();
    $user->createToken('phone');
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => 'awa@example.com', 'password' => 'Nouveau123', 'password_confirmation' => 'Nouveau123',
    ])->assertOk();

    expect(Hash::check('Nouveau123', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['email' => 'awa@example.com', 'password' => 'Nouveau123', 'device_name' => 'x'])->assertOk();
    $this->postJson('/api/v1/auth/login', ['email' => 'awa@example.com', 'password' => 'Ancien123', 'device_name' => 'x'])->assertUnauthorized();
});

it('accepts a token only once', function () {
    $user = resetUser();
    $token = Password::broker()->createToken($user);
    $payload = ['token' => $token, 'email' => 'awa@example.com', 'password' => 'Nouveau123', 'password_confirmation' => 'Nouveau123'];

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
    $this->postJson('/api/v1/auth/reset-password', ['password' => 'Autre12345', 'password_confirmation' => 'Autre12345'] + $payload)->assertUnprocessable();

    expect(Hash::check('Nouveau123', $user->fresh()->password))->toBeTrue();
});

it('refuses a wrong, foreign or expired token with one generic message', function () {
    $user = resetUser();
    $other = resetUser(Role::Client, ['email' => 'autre@example.com']);
    $good = Password::broker()->createToken($user);
    $payload = ['email' => 'awa@example.com', 'password' => 'Nouveau123', 'password_confirmation' => 'Nouveau123'];

    $this->postJson('/api/v1/auth/reset-password', ['token' => 'faux'] + $payload)->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'invalide ou a expiré'));
    // A token minted for another account does not work for this one.
    $this->postJson('/api/v1/auth/reset-password', ['token' => Password::broker()->createToken($other)] + $payload)->assertUnprocessable();

    $this->travel(61)->minutes();
    $this->postJson('/api/v1/auth/reset-password', ['token' => $good] + $payload)->assertUnprocessable();

    expect(Hash::check('Ancien123', $user->fresh()->password))->toBeTrue();
});

it('applies the registration password rules and requires the confirmation', function () {
    $user = resetUser();
    $token = Password::broker()->createToken($user);

    foreach (['court1', 'seulementdeslettres', '12345678'] as $weak) {
        $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => 'awa@example.com', 'password' => $weak, 'password_confirmation' => $weak])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => 'awa@example.com', 'password' => 'Nouveau123', 'password_confirmation' => 'Different123'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('leaves no trace of the reset token or the new password in the audit trail', function () {
    $user = resetUser();
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => 'awa@example.com', 'password' => 'Nouveau123', 'password_confirmation' => 'Nouveau123'])->assertOk();

    $stored = json_encode(App\Modules\Audit\Infrastructure\Models\AuditLog::query()->get()->toArray());
    expect($stored)->not->toContain($token)->not->toContain('Nouveau123');
});
