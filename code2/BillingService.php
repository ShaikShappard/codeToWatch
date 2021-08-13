<?php

namespace App\Services\Billing;

use App\Helpers\User\UserPaymentHelper;
use App\Models\BillingAddress;
use App\Models\BillingPlan;
use App\Models\PurchasedPlan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class BillingService
{
    /**
     * Billing groups
     */
    const GROUPS = [
      1 => 'month',
      2 => 'adopter',
      3 => 'lifetime',
    ];

    /**
     * Return all billing plans
     *
     * @param int $group
     * @return $plans
     */
    public function getAllBillingPlans($group = 1)
    {
        $plans = BillingPlan::where([['active', true], ['group', $group]])->orderBy('sort')->get() ;

        return $plans->keyBy('id');
    }

    /**
     * Get billing plan by id
     *
     * @param $planId
     * @return bool
     */
    public function getPlan($planId)
    {
        if (!$planId) return false;

        return BillingPlan::where('active', true)->where('id', $planId)->first();
    }

    /**
     * Create new PurchasedPlan
     *
     * @param $billingPlan
     * @param $billingType
     * @param string $token
     * @return
     */
    public function purchasePlan($billingPlan, $billingType, $token = "", $subscription_id = "")
    {
        if (Auth::check())
            return PurchasedPlan::create([
                'user_id' => Auth::id(),
                'plan_id' => $billingPlan->id,
                'amount' => $billingPlan->cost,
                'credits' => $billingPlan->credits,
                'external_plan_id' => $billingType->name == 'paypal' ? $billingPlan->id_pay_pal : $billingPlan->id_plan_billsby,
                'subscription_id' => $subscription_id,
                'token' => $token,
                'billing_type_id' => $billingType->id,
            ]);
    }

    /**
     * Get already purchased plan by token
     *
     * @param $token
     * @return mixed
     */
    public function getPurchasedPlanByToken($token)
    {
        return PurchasedPlan::where('token', $token)->first();
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function getPurchasedPlanBySubscriptionId(string $id)
    {
        return PurchasedPlan::where('subscription_id', $id)->first();
    }

    /**
     * Check active purchased plan
     *
     * @param $plan
     * @return bool
     */
    public function checkIsActivePurchasedPlan($plan)
    {
        if ($plan && $plan->status_id == 2)
            return true;
        return false;
    }

    /**
     * Update purchased plan
     *
     * @param $status
     * @param $plan
     */
    public function updatePurchasePlanStatus($status, $plan)
    {
        if ($plan)
            $plan->update(['status_id' => $status]);
    }

    /**
     * Update external id or purchased plan
     *
     * @param $id
     * @param $plan
     */
    public function updatePurchasePlanId($id, $plan)
    {
        if ($plan) {
            $plan->update(['external_plan_id' => $id]);
            $this->updatePurchasePlanStatus(2, $plan);
        }
    }

    /**
     * @param $data
     * @param $userId
     */
    public function createBillingAddress($data, $userId)
    {
        BillingAddress::create([
            'user_id' => $userId,
            'company_name' => $data['company_name'],
            'company_address' => $data['company_address'],
            'zip_code' => $data['zip_code'],
            'city' => $data['city'],
            'country_id' => $data['country'],
            'tax_number' => $data['tax_number'],
        ]);
    }

    public function getAllActivePurchasedPlansWithExternalId()
    {
        return PurchasedPlan::where('external_plan_id', '<>', null)
            ->where('status_id', 2)->get();
    }
}
