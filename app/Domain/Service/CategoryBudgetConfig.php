<?php

namespace App\Domain\Service;

class CategoryBudgetConfig
{
    private array $budgets;

    public function __construct(string $jsonBudgets)
    {
        $decoded = json_decode($jsonBudgets, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid category budgets JSON');
        }
        $this->budgets = $decoded;
    }

    public function getBudgets(): array
    {
        return $this->budgets;
    }

    public function getBudgetForCategory(string $category): ?float
    {
        return $this->budgets[$category] ?? null;
    }
}
