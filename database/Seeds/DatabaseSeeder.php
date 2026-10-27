<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Container;

/**
 * Orchestrateur idempotent des données de référence.
 */
final class DatabaseSeeder
{
    public function __construct(private readonly Container $container)
    {
    }

    public function run(): void
    {
        (new RbacSeeder($this->container))->run();
        (new CompanySeeder($this->container))->run();
        (new ChartOfAccountsSeeder($this->container))->run();
        (new PayrollRulesSeeder($this->container))->run();
        (new ReferenceSeeder($this->container))->run();
    }
}
