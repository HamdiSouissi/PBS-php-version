<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Core\Support\Env;
use Tests\Core\TestCase;

final class EnvTest extends TestCase
{
    private string $tmpFile;

    /** @var list<string> */
    private array $setKeys = [];

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        foreach ($this->setKeys as $key) {
            putenv($key);
            unset($_ENV[$key]);
        }
        parent::tearDown();
    }

    public function testInlineCommentIsStrippedFromUnquotedValue(): void
    {
        $this->write([
            'PBS_ENVTEST_ENV=development                 # development | production',
            'PBS_ENVTEST_DEBUG=true                      # false en production',
            'PBS_ENVTEST_CACHE=file                      # file | redis',
        ]);
        Env::load($this->tmpFile);

        self::assertSame('development', getenv('PBS_ENVTEST_ENV'));
        self::assertSame('true', getenv('PBS_ENVTEST_DEBUG'));
        self::assertSame('file', getenv('PBS_ENVTEST_CACHE'));
    }

    public function testEmptyValueWithCommentStaysEmpty(): void
    {
        $this->write([
            'PBS_ENVTEST_KEY=                            # générer une clé',
            'PBS_ENVTEST_SECURE=                         # tls | ssl | vide',
        ]);
        Env::load($this->tmpFile);

        self::assertSame('', getenv('PBS_ENVTEST_KEY'));
        self::assertSame('', getenv('PBS_ENVTEST_SECURE'));
    }

    public function testQuotedValueKeepsInternalHash(): void
    {
        $this->write([
            'PBS_ENVTEST_NAME="Prime Business Suite #2026"',
            "PBS_ENVTEST_LABEL='Service #RH'",
        ]);
        Env::load($this->tmpFile);

        self::assertSame('Prime Business Suite #2026', getenv('PBS_ENVTEST_NAME'));
        self::assertSame('Service #RH', getenv('PBS_ENVTEST_LABEL'));
    }

    public function testHashWithoutLeadingWhitespaceIsPartOfTheValue(): void
    {
        $this->write(['PBS_ENVTEST_TOKEN=abc#def123']);
        Env::load($this->tmpFile);

        self::assertSame('abc#def123', getenv('PBS_ENVTEST_TOKEN'));
    }

    public function testPureCommentAndBlankLinesAreIgnored(): void
    {
        $this->write([
            '# une ligne de commentaire pure',
            '',
            'PBS_ENVTEST_PRESENT=valeur',
        ]);
        Env::load($this->tmpFile);

        self::assertSame('valeur', getenv('PBS_ENVTEST_PRESENT'));
    }

    /**
     * @param list<string> $lines
     */
    private function write(array $lines): void
    {
        foreach ($lines as $line) {
            if (str_contains($line, '=')) {
                $this->setKeys[] = explode('=', $line, 2)[0];
            }
        }
        $this->tmpFile = (string) tempnam(sys_get_temp_dir(), 'pbs_env_');
        file_put_contents($this->tmpFile, implode("\n", $lines) . "\n");
    }
}
