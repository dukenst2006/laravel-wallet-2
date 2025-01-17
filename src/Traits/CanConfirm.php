<?php

namespace Bavix\Wallet\Traits;

use Bavix\Wallet\Exceptions\ConfirmedInvalid;
use Bavix\Wallet\Exceptions\WalletOwnerInvalid;
use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Services\CommonService;
use Bavix\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;

trait CanConfirm
{


    /**
     * @param Transaction $transaction
     * @return bool
     */
    public function confirm(Transaction $transaction): bool
    {
        $self = $this;
        return DB::transaction(static function() use ($self, $transaction) {
            $wallet = app(WalletService::class)
                ->getWallet($self);

            if (!$wallet->refreshBalance()) {
                return false;
            }

            if ($transaction->type === Transaction::TYPE_WITHDRAW) {
                app(CommonService::class)->verifyWithdraw(
                    $wallet,
                    \abs($transaction->amount)
                );
            }

            return $self->forceConfirm($transaction);
        });
    }

    /**
     * @param Transaction $transaction
     * @return bool
     */
    public function safeConfirm(Transaction $transaction): bool
    {
        try {
            return $this->confirm($transaction);
        } catch (\Throwable $throwable) {
            return false;
        }
    }

    /**
     * @param Transaction $transaction
     * @return bool
     * @throws ConfirmedInvalid
     * @throws WalletOwnerInvalid
     */
    public function forceConfirm(Transaction $transaction): bool
    {
        $self = $this;
        return DB::transaction(static function() use ($self, $transaction) {

            $wallet = app(WalletService::class)
                ->getWallet($self);

            if ($transaction->confirmed) {
                throw new ConfirmedInvalid(trans('wallet::errors.confirmed_invalid'));
            }

            if ($wallet->id !== $transaction->wallet_id) {
                throw new WalletOwnerInvalid(trans('wallet::errors.owner_invalid'));
            }

            return $transaction->update(['confirmed' => true]) &&

                // update balance
                app(CommonService::class)
                    ->addBalance($wallet, $transaction->amount);

        });
    }

}
