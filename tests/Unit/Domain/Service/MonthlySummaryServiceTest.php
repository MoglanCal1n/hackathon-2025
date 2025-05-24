<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Service\MonthlySummaryService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class MonthlySummaryServiceTest extends TestCase
{
    public function testComputeTotalExpenditure(): void
    {
        $repo = $this->createMock(ExpenseRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('sumAmounts')
            ->willReturn(10500.0); // float now

        $service = new MonthlySummaryService($repo);
        $user = new User(1, 'user', 'pass', new DateTimeImmutable());

        $result = $service->computeTotalExpenditure($user, 2025, 1);

        $this->assertSame(105.0, $result); // expect float
    }

    public function testComputePerCategoryTotals(): void
    {
        $repo = $this->createMock(ExpenseRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('sumAmountsByCategory')
            ->willReturn([
                'groceries' => 7500,
                'entertainment' => 2500,
            ]);

        $service = new MonthlySummaryService($repo);
        $user = new User(1, 'user', 'pass', new DateTimeImmutable());

        $result = $service->computePerCategoryTotals($user, 2025, 1);

        $this->assertSame([
            'groceries' => 75.0,
            'entertainment' => 25.0,
        ], $result);
    }

    public function testComputePerCategoryAverages(): void
    {
        $repo = $this->createMock(ExpenseRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('averageAmountsByCategory')
            ->willReturn([
                'groceries' => 3000.5,
                'transport' => 1200.75,
            ]);

        $service = new MonthlySummaryService($repo);
        $user = new User(1, 'user', 'pass', new DateTimeImmutable());

        $result = $service->computePerCategoryAverages($user, 2025, 1);

        $this->assertSame([
            'groceries' => 30.005,
            'transport' => 12.0075,
        ], $result);
    }
}
