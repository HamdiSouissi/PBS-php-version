<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Container;
use App\Core\Exception\AppException;

/**
 * Noyau des commandes console maison.
 */
final class ConsoleKernel
{
    /** @var array<string,class-string<Command>> */
    private array $commands = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function register(string $commandClass): void
    {
        /** @var Command $instance */
        $instance = $this->container->get($commandClass);
        $this->commands[$instance->getName()] = $commandClass;
    }

    public function run(Input $input, Output $output): int
    {
        $name = $input->commandName();
        if ($name === null || in_array($name, ['list', 'help'], true)) {
            $this->printHelp($output);
            return 0;
        }
        if (!isset($this->commands[$name])) {
            $output->error("Commande inconnue : {$name}");
            $this->printHelp($output);
            return 1;
        }
        try {
            /** @var Command $command */
            $command = $this->container->get($this->commands[$name]);
            return $command->handle($input, $output);
        } catch (AppException $exception) {
            $output->error($exception->getMessage());
            return 1;
        } catch (\Throwable $exception) {
            $output->error($exception::class . ' : ' . $exception->getMessage());
            $output->writeln($exception->getFile() . ':' . $exception->getLine());
            return 1;
        }
    }

    private function printHelp(Output $output): void
    {
        $output->writeln('Prime Business Suite - Console');
        $output->writeln('Usage : php bin/console <commande> [options]');
        $output->writeln('');
        ksort($this->commands);
        foreach ($this->commands as $name => $class) {
            /** @var Command $instance */
            $instance = $this->container->get($class);
            $output->writeln(sprintf('  %-32s %s', $name, $instance->getDescription()));
        }
    }

    /** @return array<string,class-string<Command>> */
    public function commands(): array
    {
        return $this->commands;
    }
}
