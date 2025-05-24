<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Infrastructure\Persistence\PdoExpenseRepository;
use DateTimeImmutable;
use Exception;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class PdoExpenseRepositoryTest extends TestCase
{
    private PDO $pdoMock;
    private PDOStatement $stmtMock;
    private PdoExpenseRepository $repository;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        $this->repository = new PdoExpenseRepository($this->pdoMock);
    }

    public function testFindReturnsExpenseWhenFound(): void
    {
        $data = [
            'id' => 1,
            'user_id' => 2,
            'date' => '2025-05-01T00:00:00+00:00',
            'category' => 'groceries',
            'amount_cents' => 1500,
            'description' => 'Test expense',
        ];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM expenses WHERE id = :id')
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with(['id' => 1]);

        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn($data);

        $expense = $this->repository->find(1);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertSame(1, $expense->id);
        $this->assertSame(2, $expense->userId);
        $this->assertSame('groceries', $expense->category);
        $this->assertSame(1500, $expense->amountCents);
        $this->assertSame('Test expense', $expense->description);
        $this->assertEquals(new DateTimeImmutable('2025-05-01T00:00:00+00:00'), $expense->date);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM expenses WHERE id = :id')
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with(['id' => 999]);

        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $result = $this->repository->find(999);

        $this->assertNull($result);
    }

    public function testSaveInsertsNewExpense(): void
    {
        $expense = new Expense(
            null,
            3,
            new DateTimeImmutable('2025-05-01T00:00:00+00:00'),
            'entertainment',
            2500,
            'Cinema tickets'
        );

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO expenses'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([
                ':user_id' => 3,
                ':date' => $expense->date->format('c'),
                ':category' => 'entertainment',
                ':amount_cents' => 2500,
                ':description' => 'Cinema tickets',
            ]);

        $this->pdoMock->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('42');

        $this->repository->save($expense);

        $this->assertSame(42, $expense->id);
    }

    public function testSaveUpdatesExistingExpense(): void
    {
        $expense = new Expense(
            10,
            4,
            new DateTimeImmutable('2025-06-15T00:00:00+00:00'),
            'utilities',
            3000,
            'Electricity bill'
        );

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE expenses'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([
                ':user_id' => 4,
                ':date' => $expense->date->format('c'),
                ':category' => 'utilities',
                ':amount_cents' => 3000,
                ':description' => 'Electricity bill',
                ':id' => 10,
            ]);

        $this->repository->save($expense);

        $this->assertSame(10, $expense->id);
    }

    public function testDeleteExecutesCorrectQuery(): void
    {
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM expenses WHERE id=?')
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([5]);

        $this->repository->delete(5);
    }

    public function testSumAmountsByCategoryReturnsCorrectData(): void
    {
        $criteria = [
            'user_id' => 1,
            'date_from' => '2025-05-01T00:00:00+00:00',
            'date_to' => '2025-06-01T00:00:00+00:00',
        ];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $this->stmtMock->method('bindValue')->willReturn(true);
        $this->stmtMock->expects($this->once())->method('execute');

        $this->stmtMock->expects($this->exactly(3))
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnOnConsecutiveCalls(
                ['category' => 'groceries', 'total_cents' => 5000],
                ['category' => 'entertainment', 'total_cents' => 3000],
                false
            );

        $result = $this->repository->sumAmountsByCategory($criteria);

        $this->assertEquals([
            'groceries' => 5000,
            'entertainment' => 3000,
        ], $result);
    }


    public function testAverageAmountsByCategoryReturnsCorrectData(): void
    {
        $criteria = ['user_id' => 1];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT category, AVG(amount_cents) AS average_cents FROM expenses'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->method('bindValue')
            ->willReturn(true);

        $this->stmtMock->expects($this->once())
            ->method('execute');

        $this->stmtMock->expects($this->exactly(3))
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnOnConsecutiveCalls(
                ['category' => 'groceries', 'average_cents' => 1250.5],
                ['category' => 'entertainment', 'average_cents' => 800.0],
                false
            );



        $result = $this->repository->averageAmountsByCategory($criteria);

        $this->assertEquals([
            'groceries' => 1250.5,
            'entertainment' => 800.0,
        ], $result);
    }

    public function testCountByReturnsCorrectCount(): void
    {
        $criteria = ['user_id' => 1];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT COUNT(*) FROM expenses'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('bindValue')
            ->with(':user_id', 1);

        $this->stmtMock->expects($this->once())
            ->method('execute');

        $this->stmtMock->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('7');

        $result = $this->repository->countBy($criteria);

        $this->assertSame(7, $result);
    }

    public function testListExpenditureYearsReturnsYears(): void
    {
        $user = new User(1, 'username', 'hash', new DateTimeImmutable());

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT DISTINCT strftime'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([':user_id' => 1]);

        $this->stmtMock->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn(['2023', '2024', '2025']);

        $result = $this->repository->listExpenditureYears($user);

        $this->assertSame(['2023', '2024', '2025'], $result);
    }
}
