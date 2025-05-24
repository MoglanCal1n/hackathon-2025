<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class MonthlySummaryService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function computeTotalExpenditure(User $user, int $year, int $month): float
    {
        $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $endDate = $startDate->modify('first day of next month');

        $criteria = [
            'user_id' => $user->id,
            'date_from' => $startDate->format('c'),
            'date_to' => $endDate->format('c'),
        ];

        $totalCents = $this->expenses->sumAmounts($criteria);
        return $totalCents / 100;
    }

    public function computePerCategoryTotals(User $user, int $year, int $month): array
    {
        $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $endDate = $startDate->modify('first day of next month');

        $criteria = [
            'user_id' => $user->id,
            'date_from' => $startDate->format('c'),
            'date_to' => $endDate->format('c'),
        ];

        $totalsCents = $this->expenses->sumAmountsByCategory($criteria);
        $totals = [];
        foreach ($totalsCents as $category => $cents) {
            $totals[$category] = (float) ($cents / 100);
        }

        return $totals;
    }


    public function computePerCategoryAverages(User $user, int $year, int $month): array
    {
        $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $endDate = $startDate->modify('first day of next month');

        $criteria = [
            'user_id' => $user->id,
            'date_from' => $startDate->format('c'),
            'date_to' => $endDate->format('c'),
        ];

        $averagesCents = $this->expenses->averageAmountsByCategory($criteria);
        $averages = [];
        foreach ($averagesCents as $category => $cents) {
            $averages[$category] = $cents / 100;
        }

        return $averages;
    }
}
