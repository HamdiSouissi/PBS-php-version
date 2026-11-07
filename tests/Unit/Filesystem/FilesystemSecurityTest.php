<?php

declare(strict_types=1);

namespace Tests\Unit\Filesystem;

use App\Core\Filesystem\Filesystem;
use Tests\Core\TestCase;

final class FilesystemSecurityTest extends TestCase
{
    public function testTraversalAndSymlinkEscape(): void
    {
        $base = sys_get_temp_dir() . '/pbs-fs-' . bin2hex(random_bytes(6));
        mkdir($base);
        mkdir($base . '/root');
        mkdir($base . '/root-sibling');
        file_put_contents($base . '/root-sibling/secret', 'secret');
        symlink($base . '/root-sibling', $base . '/root/link');
        $fs = new Filesystem($base . '/root');
        try {
            $fs->put('nested/test.txt', 'ok');
            self::assertSame('ok', $fs->get('nested/test.txt'));
            self::assertFalse($fs->exists('../root-sibling/secret'));
            self::assertFalse($fs->exists('link/secret'));
            foreach (['../root-sibling/new', 'link/new', '/absolute', 'link/secret'] as $path) {
                try {
                    $fs->put($path, 'bad');
                    throw new \LogicException('Écriture dangereuse acceptée.');
                } catch (\RuntimeException $expected) {
                }
            }
            self::assertFalse(file_exists($base . '/root-sibling/new'));
        } finally {
            unlink($base . '/root/nested/test.txt');
            rmdir($base . '/root/nested');
            unlink($base . '/root/link');
            unlink($base . '/root-sibling/secret');
            rmdir($base . '/root');
            rmdir($base . '/root-sibling');
            rmdir($base);
        }
    }
}
