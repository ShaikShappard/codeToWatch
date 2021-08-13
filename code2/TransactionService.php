<?php

namespace App\Services\Billing;

use App\Models\Transaction;

class TransactionService
{
    /**
     * Return all transactions
     *
     * @return Transaction[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function all()
    {
        return Transaction::all();
    }

    /**
     * Create new transaction
     *
     * @param $sum
     * @param $user_id
     * @param $billingType_id
     * @param null $order_id
     * @return mixed
     */
    public function create($sum,
                           $user_id,
                           $billingType_id = 1,
                           $order_id = null,
                           $card_id = null,
                           $external_id = null,
                           $recurring = 0,
                           $plan_id = null)
    {
        return Transaction::create([
            'sum' => $sum,
            'user_id' => $user_id,
            'order_id' => $order_id,
            'billingType_id' => $billingType_id,
            'card_id' => $card_id,
            'external_id' => $external_id,
            'recurring' => $recurring,
            'plan_id' => $plan_id,
        ]);
    }

    /**
     * Get transactions by user_id
     *
     * @param $id
     * @return mixed
     */
    public static function getWithUser($id)
    {
        return Transaction::where('user_id', $id)->get();
    }

    /**
     * Get transaction by payment ID
     *
     * @param $id
     * @return mixed
     */
    public static function getTransactionByPaymentId($id)
    {
        return Transaction::where('payment_id', $id)->with('order')->first();
    }

    /**
     * @param $billingTypeId
     * @param int $recurring
     * @return mixed
     */
    public function getAllTransactionsWithOptions($billingTypeId, $recurring = 0)
    {
        return Transaction::where('external_id', '<>', null)
            ->where('billingType_id', $billingTypeId)
            ->where('recurring', $recurring)->get();
    }
}
