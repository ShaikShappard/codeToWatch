<?php

namespace App\Services\Billing;

use App\Models\BillingType;

class BillingTypeService
{
    /**
     * Return all billing type with images
     *
     * @return BillingType[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    public function getAllBillingTypes()
    {
        return BillingType::all()->map(function ($item) {
            return [
                'img' => $item->id == 1 ? "/images/paypal.png" : "/images/cardPay.png",
                'name' => $item->name,
                'id' => $item->id,
            ];
        });
    }

    /**
     * Return billing type by id
     *
     * @param $id
     * @return bool
     */
    public function getBillingType($id)
    {
        if (!$id) return false;

        return BillingType::where('id', $id)->first();
    }
}
