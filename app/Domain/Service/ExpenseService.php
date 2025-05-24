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

    public function list(User $user, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        $pageNumber = max(1, $pageNumber);
        $pageSize = max(1, $pageSize);

        $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $endDate = $startDate->modify('+1 month');
        $offset = ($pageNumber - 1) * $pageSize;

        $criteria = [
            'user_id' => $user->id,
            'date >=' => $startDate->format('c'),
            'date <' => $endDate->format('c'),
        ];

        return $this->expenses->findBy($criteria, $offset, $pageSize);
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
        $stream = $csvFile->getStream();
        $stream->rewind();
        $resource = $stream->detach();

        $importedCount = 0;

        try {
            $this->pdo->beginTransaction();

            $header = null;

            while (($row = fgetcsv($resource)) !== false) {
                if (empty(array_filter($row))) {
                    continue;
                }

                if ($header === null) {
                    $header = $row;
                    continue;
                }

                $data = array_combine($header, $row);

                if (
                    empty($data['date']) ||
                    empty($data['category']) ||
                    empty($data['amount'])
                ) {
                    continue;
                }

                $date = \DateTimeImmutable::createFromFormat('Y-m-d', $data['date']);
                if ($date === false) {
                    continue;
                }

                $amount = (float) $data['amount'];
                $description = $data['description'] ?? '';

                $expense = new Expense(
                    null,
                    $user->id,
                    $date,
                    $data['category'],
                    (int) ($amount * 100),
                    $description,
                );

                $this->expenses->save($expense);
                $importedCount++;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        return $importedCount;
    }

    public function findById(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }
}