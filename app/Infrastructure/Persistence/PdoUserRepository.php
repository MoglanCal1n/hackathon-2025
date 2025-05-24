<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(mixed $id): ?User
    {
        $query = 'SELECT * FROM users WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return new User(
            $data['id'],
            $data['username'],
            $data['password_hash'],
            new DateTimeImmutable($data['created_at']),
        );
    }


    public function findByUsername(string $username): ?User
    {
        $sql = 'SELECT * FROM users WHERE username = :username LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $data = $stmt->fetch();

        if ($data === false) {
            return null;
        }

        return new User(
            $data['id'],
            $data['username'],
            $data['password_hash'],
            new DateTimeImmutable($data['created_at'])
        );
    }


    public function save(User $user): void
    {
        if ($user->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, password_hash, created_at) 
             VALUES (:username, :password_hash, :created_at)'
            );

            $stmt->execute([
                ':username' => $user->username,
                ':password_hash' => $user->passwordHash,
                ':created_at' => $user->createdAt->format('c'), // ISO 8601
            ]);
        } else {
            throw new \LogicException('User ID ' . $user->id . ' already exists.');
        }
    }

}
