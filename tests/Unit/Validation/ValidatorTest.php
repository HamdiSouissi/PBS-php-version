<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Core\Exception\ValidationException;
use App\Core\Validation\Validator;
use Tests\Core\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidDataPasses(): void
    {
        $validator = new Validator();
        $data = $validator->validate(
            ['email' => 'toto@entreprise.fr', 'age' => '34', 'amount' => '1250.50'],
            ['email' => 'required|email', 'age' => 'required|integer', 'amount' => 'required|decimal:2']
        );
        self::assertSame('toto@entreprise.fr', $data['email']);
    }

    public function testInvalidEmailRaisesException(): void
    {
        $validator = new Validator();
        $this->expectException(ValidationException::class);
        $validator->validate(['email' => 'pas-un-email'], ['email' => 'required|email']);
    }

    public function testSiretValidation(): void
    {
        $validator = new Validator();
        $this->expectException(ValidationException::class);
        $validator->validate(['siret' => '123'], ['siret' => 'required|siret']);
    }

    public function testMaxOnNumericStringChecksLengthNotValue(): void
    {
        $validator = new Validator();
        // Un code postal numérique de 5 caractères doit passer max:20 (règle de longueur).
        $data = $validator->validate(['postal_code' => '69000'], ['postal_code' => 'nullable|string|max:20']);
        self::assertSame('69000', $data['postal_code']);

        $this->expectException(ValidationException::class);
        $validator->validate(['postal_code' => '123456789012345678901'], ['postal_code' => 'nullable|string|max:20']);
    }

    public function testMaxOnRealIntegerChecksValue(): void
    {
        $validator = new Validator();
        $data = $validator->validate(['quantity' => 5], ['quantity' => 'required|max:10']);
        self::assertSame(5, $data['quantity']);

        $this->expectException(ValidationException::class);
        $validator->validate(['quantity' => 42], ['quantity' => 'required|max:10']);
    }
}
