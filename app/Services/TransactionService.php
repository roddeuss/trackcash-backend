<?php

namespace App\Services;

use App\Models\Transaction;
use App\Helpers\DateRangeHelper;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TransactionService
{
    /**
     * List transactions with filters.
     */
    public function listTransactions(array $filters, int $userId)
    {
        $query = Transaction::with(['bank', 'category', 'asset'])
            ->where('user_id', $userId)
            ->active();

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $start = Carbon::createFromFormat('d-m-Y H:i:s', $filters['start_date'])->startOfSecond();
            $end   = Carbon::createFromFormat('d-m-Y H:i:s', $filters['end_date'])->endOfSecond();
            $query->whereBetween('transaction_date', [$start, $end]);
        }

        if (!empty($filters['range'])) {
            [$start, $end] = DateRangeHelper::getDateRange($filters['range']);
            $query->whereBetween('transaction_date', [$start, $end]);
        }

        return $query->orderBy('transaction_date', 'desc')->get();
    }

    /**
     * Store new transaction.
     */
    public function storeTransaction(array $data, int $userId)
    {
        $transaction = Transaction::create([
            'user_id'          => $userId,
            'bank_id'          => $data['bank_id'] ?? null,
            'asset_id'         => $data['asset_id'] ?? null,
            'category_id'      => $data['category_id'],
            'amount'           => $data['amount'],
            'transaction_date' => Carbon::createFromFormat('d-m-Y H:i:s', $data['transaction_date']),
            'description'      => $data['description'] ?? null,
            'created_by'       => $userId,
            'deleted'          => false,
        ]);

        // 🔔 Notification
        NotificationService::create(
            $userId,
            'transaction_created',
            'Transaksi Baru',
            'Transaksi sebesar '.number_format((float)$transaction->amount, 0, ',', '.').' berhasil dibuat.',
            ['transaction_id' => $transaction->id, 'category_id' => $transaction->category_id],
            'success',
            null
        );

        // 🔎 Budget Evaluation
        BudgetService::onTransactionChanged($userId, (int) $transaction->category_id);

        return $transaction;
    }

    /**
     * Find active transaction.
     */
    public function findTransaction(int $id, int $userId)
    {
        return Transaction::with(['bank', 'category', 'asset'])
            ->where('user_id', $userId)
            ->active()
            ->findOrFail($id);
    }

    /**
     * Update transaction.
     */
    public function updateTransaction(int $id, array $data, int $userId)
    {
        $transaction = $this->findTransaction($id, $userId);
        $oldCategoryId = (int) $transaction->category_id;

        $updateData = [];
        if (isset($data['bank_id'])) $updateData['bank_id'] = $data['bank_id'];
        if (isset($data['asset_id'])) $updateData['asset_id'] = $data['asset_id'];
        if (isset($data['category_id'])) $updateData['category_id'] = $data['category_id'];
        if (isset($data['amount'])) $updateData['amount'] = $data['amount'];
        if (isset($data['transaction_date'])) {
            $updateData['transaction_date'] = Carbon::createFromFormat('d-m-Y H:i:s', $data['transaction_date']);
        }
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        
        $updateData['updated_by'] = $userId;

        $transaction->update($updateData);

        // 🔔 Notification
        NotificationService::create(
            $userId,
            'transaction_updated',
            'Transaksi Diperbarui',
            "Transaksi #{$transaction->id} berhasil diperbarui.",
            ['transaction_id' => $transaction->id],
            'info',
            null
        );

        // 🔎 Budget Evaluation
        $newCategoryId = (int) $transaction->category_id;
        BudgetService::onTransactionChanged($userId, $newCategoryId);
        if ($newCategoryId !== $oldCategoryId) {
            BudgetService::onTransactionChanged($userId, $oldCategoryId);
        }

        return $transaction;
    }

    /**
     * Delete transaction.
     */
    public function deleteTransaction(int $id, int $userId)
    {
        $transaction = Transaction::where('user_id', $userId)->findOrFail($id);
        $categoryId  = (int) $transaction->category_id;

        $transaction->update([
            'deleted'    => true,
            'updated_by' => $userId,
        ]);

        // 🔔 Notification
        NotificationService::create(
            $userId,
            'transaction_deleted',
            'Transaksi Dihapus',
            "Transaksi #{$transaction->id} berhasil dihapus.",
            ['transaction_id' => $transaction->id],
            'warning',
            null
        );

        // 🔎 Budget Evaluation
        BudgetService::onTransactionChanged($userId, $categoryId);

        return true;
    }
}
