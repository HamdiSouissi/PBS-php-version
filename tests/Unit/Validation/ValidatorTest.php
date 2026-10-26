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
}
