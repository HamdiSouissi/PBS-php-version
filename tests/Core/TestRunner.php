<?php

declare(strict_types=1);

namespace Tests\Core;

/**
 * Moteur d'exécution maison des tests (même périmètre que phpunit.xml.dist).
 * Découvre les classes *Test, exécute les méthodes test*, agrège les résultats.
 */
final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private int $errors = 0;

    /** @var list<string> */
    private array $failures = [];

    /**
     * @param list<string> $paths
     */
    public function run(array $paths): int
    {
        $files = [];
        foreach ($paths as $path) {
            foreach (glob($path . '/**/*Test.php') ?: [] as $file) {
                $files[] = $file;
            }
            foreach (glob($path . '/*Test.php') ?: [] as $file) {
                $files[] = $file;
            }
        }
        $files = array_unique($files);
        sort($files);

        foreach ($files as $file) {
            require_once $file;
            $class = $this->classFromFile($file);
            if ($class === null || !is_subclass_of($class, TestCase::class)) {
                continue;
            }
            $this->runClass($class);
        }
        return $this->failed === 0 && $this->errors === 0 ? 0 : 1;
    }

    private function runClass(string $class): void
    {
        $methods = array_filter(
            get_class_methods($class),
            static fn (string $method) => str_starts_with($method, 'test')
        );
        foreach ($methods as $method) {
            $label = $class . '::' . $method;
            /** @var TestCase $instance */
            $instance = new $class();
            try {
                $instance->runSetup();
                $instance->$method();
                $expected = $instance->expectedExceptionClass();
                if ($expected !== null) {
                    throw AssertionFailedException::class === $expected
                        ? new \RuntimeException("Exception attendue non levée : {$expected}")
                        : new \RuntimeException("Exception attendue non levée : {$expected}");
                }
                $this->passed++;
                echo "\033[32m.\033[0m";
            } catch (\Throwable $exception) {
                $this->handleFailure($label, $exception, $instance);
            } finally {
                try {
                    $instance->runTearDown();
                } catch (\Throwable) {
                    // ignore
                }
            }
        }
    }

    private function handleFailure(string $label, \Throwable $exception, TestCase $instance): void
    {
        $expected = $instance->expectedExceptionClass();
        $instance->clearExpectedException();
        if ($expected !== null && $exception instanceof $expected) {
            $this->passed++;
            echo "\033[32m.\033[0m";
            return;
        }
        if ($exception instanceof AssertionFailedException) {
            $this->failed++;
            echo "\033[31mF\033[0m";
        } else {
            $this->errors++;
            echo "\033[31mE\033[0m";
        }
        $this->failures[] = sprintf(
            "\n\033[31m[ÉCHEC]\033[0m %s\n  %s: %s\n  dans %s:%d",
            $label,
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
    }

    private function classFromFile(string $file): ?string
    {
        $contents = (string) file_get_contents($file);
        if (!preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch)) {
            return null;
        }
        if (!preg_match('/class\s+(\w+)/', $contents, $classMatch)) {
            return null;
        }
        return trim($namespaceMatch[1]) . '\\' . $classMatch[1];
    }

    public function summary(): string
    {
        $total = $this->passed + $this->failed + $this->errors;
        $lines = ['', '=== Résultats des tests ==='];
        foreach ($this->failures as $failure) {
            $lines[] = $failure;
        }
        $lines[] = sprintf(
            'Total : %d | Réussis : %d | Échecs : %d | Erreurs : %d',
            $total,
            $this->passed,
            $this->failed,
            $this->errors
        );
        return implode(PHP_EOL, $lines);
    }
}
