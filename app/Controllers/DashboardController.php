<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AlertGenerator;
use App\Domain\Service\AuthService;
use App\Domain\Service\ExpenseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
        private readonly AlertGenerator $alertGenerator,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        if (empty($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = new \App\Domain\Entity\User(
            $_SESSION['user_id'],
            $_SESSION['username'],
            '',
            new \DateTimeImmutable()
        );

        $queryParams = $request->getQueryParams();
        $year = isset($queryParams['year']) ? (int)$queryParams['year'] : (int)date('Y');
        $month = isset($queryParams['month']) ? (int)$queryParams['month'] : (int)date('m');

        $expenses = $this->expenseService->list($user, $year, $month, 1, 1000);

        $totalCents = array_reduce($expenses, fn($carry, $expense) => $carry + $expense->amountCents, 0);
        $totalForMonth = $totalCents / 100;

        $totalsForCategoriesRaw = [];
        $categoryCounts = [];
        foreach ($expenses as $expense) {
            $cat = $expense->category;
            $totalsForCategoriesRaw[$cat] = ($totalsForCategoriesRaw[$cat] ?? 0) + $expense->amountCents;
            $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
        }

        // Convert to euros and calculate percentage per category (of total)
        $totalsForCategories = [];
        foreach ($totalsForCategoriesRaw as $cat => $cents) {
            $value = $cents / 100;
            $percentage = $totalForMonth > 0 ? ($value / $totalForMonth) * 100 : 0;
            $totalsForCategories[$cat] = ['value' => $value, 'percentage' => $percentage];
        }

        // Calculate averages with percentage relative to totalForMonth (optional)
        $averagesForCategories = [];
        foreach ($totalsForCategories as $cat => $data) {
            $avgValue = $categoryCounts[$cat] > 0 ? $data['value'] / $categoryCounts[$cat] : 0;
            $percentage = $totalForMonth > 0 ? ($avgValue / $totalForMonth) * 100 : 0;
            $averagesForCategories[$cat] = ['value' => $avgValue, 'percentage' => $percentage];
        }

        // Years range for dropdown, e.g. last 5 years
        $currentYear = (int)date('Y');
        $years = range($currentYear - 5, $currentYear);

        $alerts = $this->alertGenerator->generate($user, $year, $month);

        return $this->render($response, 'dashboard.twig', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'alerts' => $alerts,
            'totalExpenditure' => $totalForMonth,
            'totalsForCategories' => $totalsForCategories,
            'averagesForCategories' => $averagesForCategories,
            'currentUserId' => $_SESSION['user_id'] ?? null,
            'currentUserName' => $_SESSION['username'] ?? null,
            'years' => $years,
            'selectedYear' => $year,
            'selectedMonth' => $month,
        ]);
    }

}
