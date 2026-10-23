<?php

use App\Libraries\AccountCredentials;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AccountCredentialsTest extends CIUnitTestCase
{
    public function testGeneratedPasswordsHaveRequiredLengthAndCharacterClasses(): void
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $password = AccountCredentials::generatePassword();

            $this->assertSame(16, strlen($password));
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[2-9]/', $password);
            $this->assertMatchesRegularExpression('/[!@#$%*_-]/', $password);
        }
    }

    public function testGeneratedEmailVerificationCodesHaveSixDigits(): void
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', AccountCredentials::generateVerificationCode());
        }
    }
}
