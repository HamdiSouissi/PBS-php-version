<?php

declare(strict_types=1);

namespace Modules\Employees\Services;

use App\Core\Exception\AppException;

/** Ancien provisionnement automatique désactivé : rattachement explicite obligatoire. */
final class EmployeeAccountService
{
    public function provision(int $employeeId): ?int
    {
        throw new AppException('Provisionnement automatique désactivé. Utilisez la fiche salarié et le parcours administrateur sécurisé.');
    }

    public function provisionAll(): array
    {
        throw new AppException('Provisionnement automatique désactivé. Vérifiez et rattachez chaque compte depuis sa fiche salarié.');
    }
}
