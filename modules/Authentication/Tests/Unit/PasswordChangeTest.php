<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\ValidationException;
use App\Core\Security\Password;
use Modules\Authentication\Repositories\UserRepository;
use Modules\Authentication\Services\UserService;
use Tests\Core\DatabaseTestCase;

/**
 * Politique de mot de passe lors du changement forcé d'un compte salarié.
 * Un mot de passe faible (par exemple le motif temporaire « change_me@… »)
 * doit être refusé par le service ; le contrôleur traduit l'exception en
 * message flash sur le profil plutôt qu'en page 422 brute.
 */
final class PasswordChangeTest extends DatabaseTestCase
{
    private function createUserWithTemporaryPassword(): int
    {
        $hasher = Application::container()->get(Password::class);
        $repository = Application::container()->get(UserRepository::class);
        return $repository->create([
            'email' => 'temporaire' . uniqid() . '@entreprise.fr',
            'password_hash' => $hasher->hash('change_me@'),
            'first_name' => 'Tempo',
            'last_name' => 'Test',
            'is_active' => true,
            'must_change_password' => true,
        ]);
    }

    public function testWeakPasswordIsRejected(): void
    {
        $service = Application::container()->get(UserService::class);
        $userId = $this->createUserWithTemporaryPassword();

        $this->expectException(ValidationException::class);
        // Ni majuscule, ni chiffre : refusé malgré 16 caractères.
        $service->changePassword($userId, 'change_me@', 'change_me@changed', 'change_me@changed');
    }

    public function testWrongCurrentPasswordIsRejected(): void
    {
        $service = Application::container()->get(UserService::class);
        $userId = $this->createUserWithTemporaryPassword();

        $this->expectException(ValidationException::class);
        $service->changePassword($userId, 'mauvais-mot-de-passe', 'UnMotDePasseFort2026!', 'UnMotDePasseFort2026!');
    }

    public function testStrongPasswordClearsForcedChangeFlag(): void
    {
        $service = Application::container()->get(UserService::class);
        $repository = Application::container()->get(UserRepository::class);
        $userId = $this->createUserWithTemporaryPassword();

        $service->changePassword(
            $userId,
            'change_me@',
            'UnMotDePasseFort2026!',
            'UnMotDePasseFort2026!'
        );

        $user = $repository->findWithRights($userId);
        self::assertNotNull($user);
        self::assertFalse($user->mustChangePassword);
        $hasher = Application::container()->get(Password::class);
        self::assertTrue($hasher->verify('UnMotDePasseFort2026!', $user->passwordHash));
        self::assertFalse($hasher->verify('change_me@', $user->passwordHash));
    }

    public function testMismatchedConfirmationIsRejected(): void
    {
        $service = Application::container()->get(UserService::class);
        $userId = $this->createUserWithTemporaryPassword();

        $this->expectException(ValidationException::class);
        $service->changePassword($userId, 'change_me@', 'UnMotDePasseFort2026!', 'AutreMotDePasse99!');
    }
}
