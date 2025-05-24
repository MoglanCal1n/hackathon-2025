<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = []; // resetăm sesiunea la fiecare test
    }

    public function testRegisterSuccess(): void
    {
        $username = 'newuser';
        $password = 'secret123';

        $mockRepo = $this->createMock(UserRepositoryInterface::class);

        // Nu există user cu username-ul dat
        $mockRepo->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn(null);

        // Ne așteptăm ca metoda save să fie apelată cu un User (validăm prin callback)
        $mockRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user) use ($username) {
                return $user->username === $username
                    && password_verify('secret123', $user->passwordHash)
                    && $user->id === null;
            }));

        $authService = new AuthService($mockRepo);
        $user = $authService->register($username, $password);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($username, $user->username);
        $this->assertNotEmpty($user->passwordHash);
        $this->assertTrue(password_verify($password, $user->passwordHash));
    }

    public function testRegisterThrowsExceptionIfUsernameTaken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Username 'existinguser' is already taken.");

        $mockRepo = $this->createMock(UserRepositoryInterface::class);

        // Returnăm un user existent
        $mockRepo->method('findByUsername')->willReturn(new User(1, 'existinguser', 'hash', new \DateTimeImmutable()));

        $authService = new AuthService($mockRepo);

        $authService->register('existinguser', 'any_password');
    }

    public function testAttemptSuccess(): void
    {
        $username = 'user1';
        $password = 'password123';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $user = new User(42, $username, $passwordHash, new \DateTimeImmutable());

        $mockRepo = $this->createMock(UserRepositoryInterface::class);
        $mockRepo->method('findByUsername')->with($username)->willReturn($user);

        $authService = new AuthService($mockRepo);

        $result = $authService->attempt($username, $password);

        $this->assertTrue($result);
        $this->assertSame(42, $_SESSION['user_id']);
        $this->assertSame($username, $_SESSION['username']);
    }

    public function testAttemptFailsWhenUserNotFound(): void
    {
        $mockRepo = $this->createMock(UserRepositoryInterface::class);
        $mockRepo->method('findByUsername')->willReturn(null);

        $authService = new AuthService($mockRepo);

        $result = $authService->attempt('unknown', 'any_password');

        $this->assertFalse($result);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testAttemptFailsWithWrongPassword(): void
    {
        $username = 'user1';
        $correctPasswordHash = password_hash('correct_password', PASSWORD_DEFAULT);

        $user = new User(1, $username, $correctPasswordHash, new \DateTimeImmutable());

        $mockRepo = $this->createMock(UserRepositoryInterface::class);
        $mockRepo->method('findByUsername')->with($username)->willReturn($user);

        $authService = new AuthService($mockRepo);

        $result = $authService->attempt($username, 'wrong_password');

        $this->assertFalse($result);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }
}
