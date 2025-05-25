<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    public function save(Expense $expense): void
    {
        if ($expense->id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO expenses (user_id, date, category, amount_cents, description)
             VALUES (:user_id, :date, :category, :amount_cents, :description)'
            );
            $statement->execute([
                ':user_id'      => $expense->userId,
                ':date'         => $expense->date->format('c'),
                ':category'     => $expense->category,
                ':amount_cents' => $expense->amountCents,
                ':description'  => $expense->description,
            ]);

            $expense->id = (int) $this->pdo->lastInsertId();
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE expenses 
             SET user_id = :user_id, date = :date, category = :category, amount_cents = :amount_cents, description = :description
             WHERE id = :id'
            );
            $statement->execute([
                ':user_id'      => $expense->userId,
                ':date'         => $expense->date->format('c'),
                ':category'     => $expense->category,
                ':amount_cents' => $expense->amountCents,
                ':description'  => $expense->description,
                ':id'           => $expense->id,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id=?');
        $statement->execute([$id]);
    }

    public function findBy(array $criteria, int $from, int $limit): array
    {
        $sql = 'SELECT * FROM expenses';
        $params = [];
        $conditions = [];

        foreach ($criteria as $key => $value) {
            // Handle date range operators
            if ($key === 'date >=' || $key === 'date <') {
                $operator = str_replace('date', '', $key);
                $paramName = ':date' . str_replace([' ', '>', '<', '='], '_', $operator);
                $conditions[] = "date $operator $paramName";
                $params[$paramName] = $value;
            } else {
                $paramName = ':' . $key;
                $conditions[] = "$key = $paramName";
                $params[$paramName] = $value;
            }
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY date DESC LIMIT :limit OFFSET :from';

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':from', $from, PDO::PARAM_INT);

        $statement->execute();

        $expensesData = $statement->fetchAll();

        $expenses = [];
        foreach ($expensesData as $data) {
            $expenses[] = $this->createExpenseFromData($data);
        }

        return $expenses;
    }

    public function countBy(array $criteria): int
    {
        $sql = 'SELECT COUNT(*) FROM expenses';
        $params = [];
        $conditions = [];

        foreach ($criteria as $key => $value) {
            // Handle date range operators
            if ($key === 'date >=' || $key === 'date <') {
                $operator = str_replace('date', '', $key);
                $paramName = ':date' . str_replace([' ', '>', '<', '='], '_', $operator);
                $conditions[] = "date $operator $paramName";
                $params[$paramName] = $value;
            } else {
                $paramName = ':' . $key;
                $conditions[] = "$key = $paramName";
                $params[$paramName] = $value;
            }
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value);
        }

        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function listExpenditureYears(User $user): array
    {
        $sql = 'SELECT DISTINCT strftime(\'%Y\', date) AS year FROM expenses WHERE user_id = :user_id ORDER BY year DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([':user_id' => $user->id]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        $sql = 'SELECT category, SUM(amount_cents) AS total_cents FROM expenses';
        $params = [];
        $conditions = [];

        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params[':user_id'] = $criteria['user_id'];
        }
        if (isset($criteria['date_from'])) {
            $conditions[] = 'date >= :date_from';
            $params[':date_from'] = $criteria['date_from'];
        }
        if (isset($criteria['date_to'])) {
            $conditions[] = 'date < :date_to';
            $params[':date_to'] = $criteria['date_to'];
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY category';

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value);
        }

        $statement->execute();

        $results = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['category']] = (int) $row['total_cents'];
        }

        return $results;
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        $sql = 'SELECT category, AVG(amount_cents) AS average_cents FROM expenses';
        $params = [];
        $conditions = [];

        foreach ($criteria as $key => $value) {
            $conditions[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY category';

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value);
        }

        $statement->execute();

        $results = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['category']] = floatval($row['average_cents']);
        }

        return $results;
    }

    public function sumAmounts(array $criteria): float
    {
        $sql = 'SELECT SUM(amount_cents) FROM expenses';
        $params = [];
        $conditions = [];

        foreach ($criteria as $key => $value) {
            $conditions[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value);
        }

        $statement->execute();

        $sum = $statement->fetchColumn();

        return $sum !== null ? (float) $sum : 0;
    }

    /**
     * @throws Exception
     */
    private function createExpenseFromData(mixed $data): Expense
    {
        return new Expense(
            $data['id'],
            $data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            $data['amount_cents'],
            $data['description'],
        );
    }
}