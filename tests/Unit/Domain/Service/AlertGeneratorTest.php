<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Service\AlertGenerator;
use App\Domain\Service\CategoryBudgetConfig;
use PHPUnit\Framework\TestCase;

class AlertGeneratorTest extends TestCase
{
    public function testGenerateReturnsEmptyArrayWhenNoBudgetsExceeded(): void
    {
        $user = new User(1, 'user1', 'hash', new \DateTimeImmutable());

        $categoryBudgetConfigMock = $this->createMock(CategoryBudgetConfig::class);
        $categoryBudgetConfigMock->method('getBudgets')->willReturn([
            'groceries' => 100,
            'entertainment' => 50,
        ]);

        $expenseRepoMock = $this->createMock(ExpenseRepositoryInterface::class);
        $expenseRepoMock->method('sumAmountsByCategory')->willReturn([
            'groceries' => 8000,       // $80.00 spent
            'entertainment' => 4000,   // $40.00 spent
        ]);

        $alertGenerator = new AlertGenerator($categoryBudgetConfigMock, $expenseRepoMock);

        $result = $alertGenerator->generate($user, 2025, 5);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGenerateReturnsAlertsWhenBudgetsExceeded(): void
    {
        $user = new User(1, 'user1', 'hash', new \DateTimeImmutable());

        $categoryBudgetConfigMock = $this->createMock(CategoryBudgetConfig::class);
        $categoryBudgetConfigMock->method('getBudgets')->willReturn([
            'groceries' => 100,     // $100 budget
            'entertainment' => 50,  // $50 budget
        ]);

        $expenseRepoMock = $this->createMock(ExpenseRepositoryInterface::class);
        $expenseRepoMock->method('sumAmountsByCategory')->willReturn([
            'groceries' => 10500,      // $105 spent - over budget
            'entertainment' => 6000,   // $60 spent - over budget
            'transport' => 2000,       // $20 spent - no budget set
        ]);

        $alertGenerator = new AlertGenerator($categoryBudgetConfigMock, $expenseRepoMock);

        $alerts = $alertGenerator->generate($user, 2025, 5);

        $this->assertCount(2, $alerts);

        $this->assertEquals('groceries', $alerts[0]['category']);
        $this->assertEquals(100, $alerts[0]['budget']);
        $this->assertEquals(105.00, $alerts[0]['spent']);
        $this->assertStringContainsString('Overspent on groceries', $alerts[0]['message']);

        $this->assertEquals('entertainment', $alerts[1]['category']);
        $this->assertEquals(50, $alerts[1]['budget']);
        $this->assertEquals(60.00, $alerts[1]['spent']);
        $this->assertStringContainsString('Overspent on entertainment', $alerts[1]['message']);
    }

    public function testGenerateHandlesNoExpensesForCategory(): void
    {
        $user = new User(1, 'user1', 'hash', new \DateTimeImmutable());

        $categoryBudgetConfigMock = $this->createMock(CategoryBudgetConfig::class);
        $categoryBudgetConfigMock->method('getBudgets')->willReturn([
            'groceries' => 100,
            'entertainment' => 50,
        ]);

        // No expenses for entertainment category
        $expenseRepoMock = $this->createMock(ExpenseRepositoryInterface::class);
        $expenseRepoMock->method('sumAmountsByCategory')->willReturn([
            'groceries' => 12000, // $120 spent - over budget
            // entertainment key missing
        ]);

        $alertGenerator = new AlertGenerator($categoryBudgetConfigMock, $expenseRepoMock);

        $alerts = $alertGenerator->generate($user, 2025, 5);

        $this->assertCount(1, $alerts);

        $this->assertEquals('groceries', $alerts[0]['category']);
        $this->assertEquals(100, $alerts[0]['budget']);
        $this->assertEquals(120.00, $alerts[0]['spent']);
    }
}
