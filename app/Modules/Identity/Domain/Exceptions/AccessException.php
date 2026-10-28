<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use DomainException;

final class AccessException extends DomainException
{
    public static function builtinGroup(): self
    {
        return new self("Ce profil d'origine ne peut pas être modifié ni supprimé. Créez un groupe personnalisé, ou désactivez des fonctionnalités pour ce profil.");
    }

    public static function groupInUse(): self
    {
        return new self('Ce groupe a encore des utilisateurs. Retirez-les du groupe avant de le supprimer.');
    }

    public static function superAdminExempt(): self
    {
        return new self("Le super administrateur n'est jamais restreint : ses fonctionnalités ne peuvent pas être désactivées.");
    }

    public static function ownSuperAdminRole(): self
    {
        return new self('Vous ne pouvez pas vous retirer vous-même le rôle de super administrateur.');
    }
}
