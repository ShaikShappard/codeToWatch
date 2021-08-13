<?php

namespace App\Services\Billing;

use App\Models\CreditCard;
use Carbon\Carbon;

class CreditCardService
{
    public $creditCard;

    /**
     * Create new credit card
     *
     * @param $data
     * @param $userId
     * @return CreditCardService
     */
    public function addCreditCard($data, $userId)
    {
        $date = Carbon::createFromFormat('m/y', $data['card_date'])
            ->format('Y-m-d');

        return $this->creditCard = CreditCard::create([
            "card_number" => $data['card_number'],
            "name_on_card" => $data['name_on_card'],
            "card_date" => $date,
            "card_type" => $data['card_type'],
            "payment_token" => $data['token'] ?? null,
            "user_id" => $userId
        ]);
    }

    /**
     * If user have credit card this method will return card data. Or create new card
     *
     * @param $data
     * @param $userId
     * @return CreditCardService
     */
    public function checkOrCreate($data, $userId, $user = false)
    {
        if ($user && $user->card)
            return $user->card;

        return $this->addCreditCard($data, $userId);
    }
}
