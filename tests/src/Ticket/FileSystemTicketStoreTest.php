<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Cas\Ticket;

use Exception;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\Ticket\FileSystemTicketStore;

final class FileSystemTicketStoreTest extends TestCase
{
    private string $ticketDir;


    protected function setUp(): void
    {
        parent::setUp();

        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'casserver-ticketstore-test-'
            . bin2hex(random_bytes(8));

        if (!mkdir($base, 0700, true) && !is_dir($base)) {
            self::fail('Failed to create test directory: ' . $base);
        }

        $this->ticketDir = $base;
    }


    protected function tearDown(): void
    {
        $this->rmrf($this->ticketDir);
        parent::tearDown();
    }


    public function testPathTraversalCannotReadOrUnserializeOutsideTicketDirectory(): void
    {
        $outsideFile = dirname($this->ticketDir) . DIRECTORY_SEPARATOR . 'outside-' . bin2hex(random_bytes(6)) . '.ser';
        file_put_contents($outsideFile, serialize(['pwn' => true]));

        $store = $this->createStore($this->ticketDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ticketId provided.');
        $store->getTicket('../' . basename($outsideFile));
    }


    public function testValidateTicketPathThrowsOnEmptyTicketId(): void
    {
        $store = $this->createStore($this->ticketDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ticketId provided.');
        $this->invokeValidateTicketPath($store, '');
    }


    public function testValidateTicketPathThrowsOnForwardSlash(): void
    {
        $store = $this->createStore($this->ticketDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ticketId provided.');
        $this->invokeValidateTicketPath($store, 'ST-123/456');
    }


    public function testValidateTicketPathThrowsOnBackslash(): void
    {
        $store = $this->createStore($this->ticketDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ticketId provided.');
        $this->invokeValidateTicketPath($store, 'ST-123\\456');
    }


    public function testValidateTicketPathThrowsOnDotDot(): void
    {
        $store = $this->createStore($this->ticketDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ticketId provided.');
        $this->invokeValidateTicketPath($store, 'ST-..-123');
    }


    public function testValidateTicketPathThrowsWhenTicketStorageDirectoryIsNotAccessible(): void
    {
        $store = $this->createStore($this->ticketDir);

        $rp = new \ReflectionProperty($store, 'pathToTicketDirectory');
        $rp->setAccessible(true);
        $rp->setValue($store, $this->ticketDir . DIRECTORY_SEPARATOR . 'does-not-exist-' . bin2hex(random_bytes(4)));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Ticket storage directory is not accessible.');
        $this->invokeValidateTicketPath($store, 'ST-12345');
    }


    public function testValidateTicketPathReturnsAFileInsideTicketDirectory(): void
    {
        $store = $this->createStore($this->ticketDir);

        $ticketId = 'ST-' . bin2hex(random_bytes(8));
        $path = $this->invokeValidateTicketPath($store, $ticketId);

        $realBase = realpath($this->ticketDir);
        self::assertIsString($realBase);

        self::assertStringStartsWith($realBase . DIRECTORY_SEPARATOR, $path);
        self::assertSame($realBase . DIRECTORY_SEPARATOR . sha1($ticketId), $path);
    }


    private function createStore(string $ticketDir): FileSystemTicketStore
    {
        $config = Configuration::loadFromArray([
            'ticketstore' => [
                'directory' => $ticketDir,
            ],
        ]);

        return new FileSystemTicketStore($config);
    }


    private function invokeValidateTicketPath(FileSystemTicketStore $store, string $ticketId): string
    {
        $rm = new \ReflectionMethod($store, 'validateTicketPath');
        $rm->setAccessible(true);

        $result = $rm->invoke($store, $ticketId);
        self::assertIsString($result);

        return $result;
    }


    private function rmrf(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->rmrf($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
