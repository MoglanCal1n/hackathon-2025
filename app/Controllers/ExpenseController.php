<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\ExpenseService;
use App\Domain\Entity\User;
use App\Domain\Entity\Expense;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Views\Twig;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
    ) {
        parent::__construct($view);
    }

    protected function getUserFromSession(): User
    {
        if (!isset($_SESSION['user_id'])) {
            throw new \RuntimeException('User not logged in.');
        }

        return new User(
            (int)$_SESSION['user_id'],
            'John Doe',
            'user@example.com',
            new \DateTimeImmutable()
        );
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->getUserFromSession();

        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['pageSize'] ?? self::PAGE_SIZE);
        $month = (int)($params['month'] ?? date('m'));
        $year = (int)($params['year'] ?? date('Y'));

        $expenses = $this->expenseService->list($user, $year, $month, $page, $pageSize);

        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $expenses,
            'page' => $page,
            'pageSize' => $pageSize,
            'month' => $month,
            'year' => $year
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $categories = $this->getExpenseCategories();

        return $this->render($response, 'expenses/create.twig', [
            'categories' => $categories,
            'old' => [],
            'error' => null,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        try {
            $user = $this->getUserFromSession();

            $data = (array)$request->getParsedBody();
            $categories = $this->getExpenseCategories();

            $amount = (float)($data['amount'] ?? 0);
            $description = trim($data['description'] ?? '');
            $category = trim($data['category'] ?? '');
            $date = new \DateTimeImmutable($data['date'] ?? 'now');

            $this->expenseService->create(
                $user,
                $amount,
                $description,
                $date,
                $category
            );

            return $response->withHeader('Location', '/expenses')->withStatus(302);

        } catch (\RuntimeException $e) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\Throwable $e) {
            return $this->render($response, 'expenses/create.twig', [
                'categories' => $categories,
                'old' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getExpenseCategories(): array
    {
        $json = $_ENV['CATEGORY_BUDGETS_JSON'] ?? '[]';
        $data = json_decode($json, true);
        return is_array($data) ? array_keys($data) : [];
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        $user = $this->getUserFromSession();
        $expenseId = (int)($routeParams['id'] ?? 0);
        $expense = $this->expenseService->findById($expenseId);

        if (!$expense || $expense->userId !== $user->id) {
            throw new HttpForbiddenException($request, 'Not allowed');
        }

        $categories = $this->getExpenseCategories();

        return $this->render($response, 'expenses/edit.twig', [
            'expense' => $expense,
            'categories' => $categories,
            'old' => [],
            'error' => null
        ]);
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        $user = $this->getUserFromSession();
        $expenseId = (int)($routeParams['id'] ?? 0);
        $expense = $this->expenseService->findById($expenseId);

        if (!$expense || $expense->userId !== $user->id) {
            throw new HttpForbiddenException($request, 'Not allowed');
        }

        $data = $request->getParsedBody();
        $categories = $this->getExpenseCategories();

        try {
            $amount = (float)($data['amount'] ?? 0);
            $description = trim($data['description'] ?? '');
            $category = trim($data['category'] ?? '');
            $date = new \DateTimeImmutable($data['date'] ?? 'now');

            $this->expenseService->update($expense, $amount, $description, $date, $category);

            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } catch (\Throwable $e) {
            return $this->render($response, 'expenses/edit.twig', [
                'expense' => $expense,
                'categories' => $categories,
                'old' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        $user = $this->getUserFromSession();
        $expenseId = (int)($routeParams['id'] ?? 0);
        $expense = $this->expenseService->findById($expenseId);

        if (!$expense || $expense->userId !== $user->id) {
            throw new HttpForbiddenException($request, 'Not allowed');
        }

        $this->expenseService->delete($expenseId);

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    public function import(Request $request, Response $response): Response
    {
        $user = $this->getUserFromSession();
        $uploadedFiles = $request->getUploadedFiles();
        $csvFile = $uploadedFiles['csv'] ?? null;

        if (!$csvFile) {
            return $this->render($response, 'expenses/index.twig', [
                'error' => 'No file uploaded.',
            ]);
        }

        try {
            $count = $this->expenseService->importFromCsv($user, $csvFile);

            return $response
                ->withHeader('Location', '/expenses?imported=' . $count)
                ->withStatus(302);

        } catch (\Throwable $e) {
            return $this->render($response, 'expenses/index.twig', [
                'error' => 'Failed to import CSV: ' . $e->getMessage(),
            ]);
        }
    }
}