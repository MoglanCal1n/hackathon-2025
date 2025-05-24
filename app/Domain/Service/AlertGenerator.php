<?php
namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class AlertGenerator
{
    public function __construct(
        private CategoryBudgetConfig $categoryBudgetConfig,
        private ExpenseRepositoryInterface $expenseRepository
    ) {}

    public function generate(User $user, int $year, int $month): array
    {
        $alerts = [];
        $budgets = $this->categoryBudgetConfig->getBudgets();

        $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $endDate = $startDate->modify('first day of next month');

        $criteria = [
            'user_id' => $user->id,
            'date_from' => $startDate->format('c'),
            'date_to' => $endDate->format('c'),
        ];

        $spentByCategory = $this->expenseRepository->sumAmountsByCategory($criteria);

        foreach ($budgets as $category => $budgetDollars) {
            $budgetCents = (int)($budgetDollars * 100);
            $spentCents = $spentByCategory[$category] ?? 0;

            if ($spentCents > $budgetCents) {
                $alerts[] = [
                    'category' => $category,
                    'budget' => $budgetDollars,
                    'spent' => $spentCents / 100,
                    'message' => "Overspent on {$category}: spent " . number_format($spentCents / 100, 2) . ", budget was {$budgetDollars}",
                ];
            }
        }

        return $alerts;
    }
}

