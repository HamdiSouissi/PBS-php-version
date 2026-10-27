<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Authentication\Services\UserService;

final class UserCreateCommand extends Command
{
    protected string $name = 'user:create';

    protected string $description = 'Crée un utilisateur (--email, --password, --role)';

    public function handle(Input $input, Output $output): int
    {
        $email = $input->option('email');
        $password = $input->option('password');
        $role = $input->option('role', 'admin');
        if ($email === null || $password === null) {
            $output->error('Options --email et --password obligatoires.');
            return $this->failure();
        }
        $user = Application::container()->get(UserService::class)->createFromConsole($email, $password, $role);
        $output->success("Utilisateur créé #{$user->id} ({$email}) avec le rôle {$role}.");
        return $this->success();
    }
}
