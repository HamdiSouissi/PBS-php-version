<?php

declare(strict_types=1);

namespace Tests\Core;

/**
 * Classe de base des tests, API calquée sur PHPUnit pour migration directe.
 */
abstract class TestCase
{
    private ?string $expectedException = null;

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function runSetup(): void
    {
        $this->setUp();
    }

    public function runTearDown(): void
    {
        $this->tearDown();
    }

    protected function expectException(string $class): void
    {
        $this->expectedException = $class;
    }

    public function expectedExceptionClass(): ?string
    {
        return $this->expectedException;
    }

    public function clearExpectedException(): void
    {
        $this->expectedException = null;
    }

    public static function assertTrue(mixed $value, string $message = ''): void
    {
        if ($value !== true) {
            throw new AssertionFailedException($message ?: 'Valeur vraie attendue.');
        }
    }

    public static function assertFalse(mixed $value, string $message = ''): void
    {
        if ($value !== false) {
            throw new AssertionFailedException($message ?: 'Valeur fausse attendue.');
        }
    }

    public static function assertNull(mixed $value, string $message = ''): void
    {
        if ($value !== null) {
            throw new AssertionFailedException($message ?: 'NULL attendu, obtenu : ' . var_export($value, true));
        }
    }

    public static function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            throw new AssertionFailedException($message ?: 'Valeur non nulle attendue.');
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailedException(
                $message ?: sprintf('Identique attendu : %s, obtenu : %s', var_export($expected, true), var_export($actual, true))
            );
        }
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            throw new AssertionFailedException(
                $message ?: sprintf('Égal attendu : %s, obtenu : %s', var_export($expected, true), var_export($actual, true))
            );
        }
    }

    public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            throw new AssertionFailedException(
                $message ?: sprintf('Valeurs différentes attendues, deux fois %s', var_export($actual, true))
            );
        }
    }

    public static function assertEqualsWithDelta(float $expected, float $actual, float $delta, string $message = ''): void
    {
        if (abs($expected - $actual) > $delta) {
            throw new AssertionFailedException(
                $message ?: sprintf('Attendu %s ± %s, obtenu %s', $expected, $delta, $actual)
            );
        }
    }

    public static function assertCount(int $expected, iterable $haystack, string $message = ''): void
    {
        $count = is_countable($haystack) ? count($haystack) : iterator_count($haystack);
        if ($count !== $expected) {
            throw new AssertionFailedException($message ?: "Nombre attendu {$expected}, obtenu {$count}.");
        }
    }

    public static function assertArrayHasKey(string $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new AssertionFailedException($message ?: "Clé attendue absente : {$key}");
        }
    }

    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailedException($message ?: "La chaîne doit contenir '{$needle}'.");
        }
    }

    public static function assertNotEmpty(mixed $value, string $message = ''): void
    {
        if (empty($value)) {
            throw new AssertionFailedException($message ?: 'Une valeur non vide est attendue.');
        }
    }

    public static function assertEmpty(mixed $value, string $message = ''): void
    {
        if (!empty($value)) {
            throw new AssertionFailedException($message ?: 'Une valeur vide est attendue.');
        }
    }

    public static function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new AssertionFailedException($message ?: "{$actual} devrait être supérieur à {$expected}.");
        }
    }

    public static function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($actual < $expected) {
            throw new AssertionFailedException($message ?: "{$actual} devrait être ≥ {$expected}.");
        }
    }

    public static function fail(string $message = 'Échec déclaré.'): void
    {
        throw new AssertionFailedException($message);
    }
}
