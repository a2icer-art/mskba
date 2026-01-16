<?php

namespace App\Domain\Balances\Services;

use App\Domain\Balances\Enums\BalanceStatus;
use App\Domain\Balances\Enums\BalanceTransactionType;
use App\Domain\Balances\Models\Balance;
use App\Domain\Balances\Models\BalanceTransaction;
use App\Domain\Payments\Enums\PaymentCurrency;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BalanceService
{
    public function getOrCreate(User $user): Balance
    {
        return Balance::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_amount' => 0,
                'held_amount' => 0,
                'currency' => PaymentCurrency::Rub,
                'status' => BalanceStatus::Active,
            ]
        );
    }

    public function topUp(User $user, int $amount, ?array $meta = null): Balance
    {
        return $this->applyTransaction($user, BalanceTransactionType::TopUp, $amount, $meta);
    }

    public function hold(User $user, int $amount, ?array $meta = null): Balance
    {
        return $this->applyTransaction($user, BalanceTransactionType::Hold, $amount, $meta);
    }

    public function release(User $user, int $amount, ?array $meta = null): Balance
    {
        return $this->applyTransaction($user, BalanceTransactionType::Release, $amount, $meta);
    }

    public function debit(User $user, int $amount, ?array $meta = null): Balance
    {
        return $this->applyTransaction($user, BalanceTransactionType::Debit, $amount, $meta);
    }

    public function block(User $user, string $reason, ?array $meta = null): Balance
    {
        return $this->applyTransaction($user, BalanceTransactionType::Block, 0, array_merge($meta ?? [], [
            'reason' => $reason,
        ]));
    }

    public function unblock(User $user, ?array $meta = null): Balance
    {
        return $this->applyTransaction($user, BalanceTransactionType::Unblock, 0, $meta);
    }

    private function applyTransaction(User $user, BalanceTransactionType $type, int $amount, ?array $meta): Balance
    {
        if ($amount < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма должна быть неотрицательной.',
            ]);
        }

        return DB::transaction(function () use ($user, $type, $amount, $meta): Balance {
            $balance = $this->getOrCreate($user);

            if ($balance->status === BalanceStatus::Blocked && !in_array($type, [BalanceTransactionType::Unblock, BalanceTransactionType::Block], true)) {
                throw ValidationException::withMessages([
                    'balance' => 'Баланс заблокирован.',
                ]);
            }

            switch ($type) {
                case BalanceTransactionType::TopUp:
                    if ($amount === 0) {
                        break;
                    }
                    $balance->available_amount += $amount;
                    break;
                case BalanceTransactionType::Hold:
                    if ($amount === 0) {
                        break;
                    }
                    if ($balance->available_amount < $amount) {
                        throw ValidationException::withMessages([
                            'amount' => 'Недостаточно средств для холда.',
                        ]);
                    }
                    $balance->available_amount -= $amount;
                    $balance->held_amount += $amount;
                    break;
                case BalanceTransactionType::Release:
                    if ($amount === 0) {
                        break;
                    }
                    if ($balance->held_amount < $amount) {
                        throw ValidationException::withMessages([
                            'amount' => 'Недостаточно средств для освобождения.',
                        ]);
                    }
                    $balance->held_amount -= $amount;
                    $balance->available_amount += $amount;
                    break;
                case BalanceTransactionType::Debit:
                    if ($amount === 0) {
                        break;
                    }
                    if ($balance->held_amount >= $amount) {
                        $balance->held_amount -= $amount;
                        break;
                    }
                    $available = $balance->available_amount + $balance->held_amount;
                    if ($available < $amount) {
                        throw ValidationException::withMessages([
                            'amount' => 'Недостаточно средств для списания.',
                        ]);
                    }
                    $remaining = $amount - $balance->held_amount;
                    $balance->held_amount = 0;
                    $balance->available_amount -= $remaining;
                    break;
                case BalanceTransactionType::Block:
                    $balance->status = BalanceStatus::Blocked;
                    $balance->block_reason = (string) ($meta['reason'] ?? '');
                    $balance->blocked_at = now();
                    break;
                case BalanceTransactionType::Unblock:
                    $balance->status = BalanceStatus::Active;
                    $balance->block_reason = null;
                    $balance->blocked_at = null;
                    break;
            }

            $balance->save();

            BalanceTransaction::query()->create([
                'balance_id' => $balance->id,
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'currency' => $balance->currency,
                'meta' => $meta,
            ]);

            return $balance;
        });
    }
}
