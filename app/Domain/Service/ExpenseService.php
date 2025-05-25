<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use PDO;
use Psr\Http\Message\UploadedFileInterface;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly PDO $pdo
    ) {}

    /**
     * @throws \DateMalformedStringException
     */
    public function list(User $user, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        $pageNumber = max(1, $pageNumber);
        $pageSize = max(1, $pageSize);

        $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $endDate = $startDate->modify('+1 month');
        $offset = ($pageNumber - 1) * $pageSize;

        $criteria = [
            'user_id' => $user->id,
            'date >=' => $startDate->format('Y-m-d'),
            'date <' => $endDate->format('Y-m-d'),
        ];

        $expenses = $this->expenses->findBy($criteria, $offset, $pageSize);
        $total = $this->expenses->countBy($criteria);

        return [
            'expenses' => $expenses,
            'total' => $total,
            'page' => $pageNumber,
            'pageSize' => $pageSize,
        ];
    }

    public function create(
        User $user,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): Expense {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }
        if (trim($description) === '') {
            throw new \InvalidArgumentException('Description cannot be empty.');
        }
        if (trim($category) === '') {
            throw new \InvalidArgumentException('Category cannot be empty.');
        }

        $amountCents = (int) round($amount * 100);

        $expense = new Expense(
            null,
            $user->id,
            $date,
            $category,
            $amountCents,
            $description
        );

        $this->expenses->save($expense);

        return $expense;
    }

    public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }
        if (trim($description) === '') {
            throw new \InvalidArgumentException('Description cannot be empty.');
        }
        if (trim($category) === '') {
            throw new \InvalidArgumentException('Category cannot be empty.');
        }

        $expense->amountCents = (int) round($amount * 100);
        $expense->description = $description;
        $expense->date = $date;
        $expense->category = $category;

        $this->expenses->save($expense);
    }

    public function delete(int $id): void
    {
        $this->expenses->delete($id);
    }

    public function importFromCsv(User $user, UploadedFileInterface $csvFile): int
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'expense_import');
        $csvFile->moveTo($tempFile);

        $importedCount = 0;
        $validCategories = $this->getValidCategories(); // Add this method

        try {
            $this->pdo->beginTransaction();

            if (($handle = fopen($tempFile, 'r')) !== false) {
                $header = fgetcsv($handle); // Get header row

                // Validate header
                if (!$this->validateHeader($header)) {
                    throw new \RuntimeException('Invalid CSV header format');
                }

                while (($row = fgetcsv($handle)) !== false) {
                    if ($this->isRowEmpty($row)) {
                        continue;
                    }

                    $data = array_combine($header, $row);

                    // Skip rows with empty description (like your example row 5)
                    if (empty(trim($data['description'] ?? ''))) {
                        error_log('Skipping row with empty description');
                        continue;
                    }

                    // Skip rows with invalid category (like your "Abracadabra" example)
                    if (!in_array($data['category'], $validCategories)) {
                        error_log('Skipping row with invalid category: ' . $data['category']);
                        continue;
                    }

                    $expense = $this->createExpenseFromRow($user, $data);
                    if ($expense === null) {
                        continue;
                    }

                    $this->expenses->save($expense);
                    $importedCount++;
                }
                fclose($handle);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        return $importedCount;
    }

    private function getValidCategories(): array
    {
        // Get categories from your environment config or database
        $json = $_ENV['CATEGORY_BUDGETS_JSON'] ?? '[]';
        $data = json_decode($json, true);
        return is_array($data) ? array_keys($data) : [];
    }

    private function validateHeader(array $header): bool
    {
        $required = ['date', 'amount', 'description', 'category'];
        foreach ($required as $field) {
            if (!in_array($field, $header, true)) {
                return false;
            }
        }
        return true;
    }

    private function isRowEmpty(array $row): bool
    {
        return empty(array_filter($row, function($value) {
            return trim($value) !== '';
        }));
    }

    private function createExpenseFromRow(User $user, array $data): ?Expense
    {
        try {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['date'])
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', $data['date']);

            if (!$date) {
                error_log("Invalid date format: " . $data['date']);
                return null;
            }

            // Parse amount (handles both "100.00" and "100,00")
            $amount = str_replace(',', '.', $data['amount']);
            if (!is_numeric($amount)) {
                error_log("Invalid amount: " . $data['amount']);
                return null;
            }

            return new Expense(
                null,
                $user->id,
                $date,
                $data['category'],
                (int) round((float) $amount * 100),
                $data['description']
            );
        } catch (\Throwable $e) {
            error_log("Error creating expense from row: " . $e->getMessage());
            return null;
        }
    }


    public function findById(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }
}