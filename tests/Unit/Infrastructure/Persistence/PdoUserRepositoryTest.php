<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Entity\User;
use App\Infrastructure\Persistence\PdoUserRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class PdoUserRepositoryTest extends TestCase
{
    private PDO $pdoMock;
    private PDOStatement $stmtMock;
    private PdoUserRepository $repository;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        $this->repository = new PdoUserRepository($this->pdoMock);
    }

    public function testFindReturnsUserWhenFound(): void
    {
        $data = [
            'id' => 1,
            'username' => 'johndoe',
            'password_hash' => 'hashed_password',
            'created_at' => '2025-05-24T12:00:00+00:00',
        ];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE id = :id')
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with(['id' => 1]);

        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn($data);

        $user = $this->repository->find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(1, $user->id);
        $this->assertSame('johndoe', $user->username);
        $this->assertSame('hashed_password', $user->passwordHash);
        $this->assertEquals(new DateTimeImmutable('2025-05-24T12:00:00+00:00'), $user->createdAt);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE id = :id')
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with(['id' => 999]);

        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $user = $this->repository->find(999);

        $this->assertNull($user);
    }

    public function testFindByUsernameReturnsUserWhenFound(): void
    {
        $data = [
            'id' => 2,
            'username' => 'janedoe',
            'password_hash' => 'hashed_pw',
            'created_at' => '2025-05-20T08:00:00+00:00',
        ];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE username = :username LIMIT 1')
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([':username' => 'janedoe']);

        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn($data);

        $user = $this->repository->findByUsername('janedoe');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(2, $user->id);
        $this->assertSame('janedoe', $user->username);
        $this->assertSame('hashed_pw', $user->passwordHash);
        $this->assertEquals(new DateTimeImmutable('2025-05-20T08:00:00+00:00'), $user->createdAt);
    }

    public function testFindByUsernameReturnsNullWhenNotFound(): void
    {
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE username = :username LIMIT 1')
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([':username' => 'nonexistent']);

        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $user = $this->repository->findByUsername('nonexistent');

        $this->assertNull($user);
    }

    public function testSaveInsertsNewUser(): void
    {
        $user = new User(
            null,
            'newuser',
            'newpasswordhash',
            new DateTimeImmutable('2025-05-24T15:00:00+00:00'),
        );

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO users'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([
                ':username' => 'newuser',
                ':password_hash' => 'newpasswordhash',
                ':created_at' => '2025-05-24T15:00:00+00:00',
            ]);

        $this->repository->save($user);

        // No return value, just test no exception thrown and execute called properly
        $this->assertTrue(true);
    }

    public function testSaveThrowsExceptionIfUserIdExists(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('User ID 5 already exists.');

        $user = new User(
            5,
            'existinguser',
            'somehash',
            new DateTimeImmutable(),
        );

        $this->repository->save($user);
    }
}
