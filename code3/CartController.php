<?php

namespace App\Http\Controllers\Front;

use App\Facades\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\cart\CheckoutRequest;
use App\Http\Requests\cart\IdRequiredTrackRequest;
use App\Http\Resources\cart\CartResource;
use App\Http\Resources\ResultResource;
use App\Http\Resources\track\TracksListResource;
use App\Models\BusinessType;
use App\Models\Page;
use App\Models\PurchasedTracks;
use App\Services\Billing\BillingService;
use App\Services\Billing\BillingTypeService;
use App\Services\Billing\CreditCardService;
use App\Services\Billing\OrderService;
use App\Services\Billing\TransactionService;
use App\Services\CartService;
use App\Services\Interfaces\BillsbyServiceInterface;
use App\Services\Interfaces\PayPalServiceInterface;
use App\Services\Interfaces\SettingsServiceInterface;
use App\Services\Interfaces\UserServiceInterface;
use App\Services\Paypal\PayPalService;
use App\Traits\BillsbyPayable;
use App\Traits\PaymentTrait;
use App\Traits\PaypalPayable;
use App\Traits\SuccessResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    use SuccessResponseTrait, PaymentTrait, BillsbyPayable, PaypalPayable;

    private $billsbyService;
    private $cartService;
    private $settingsService;
    private $userService;
    /**
     * @var PayPalServiceInterface
     */
    private $payPalService;
    /**
     * @var BillingService
     */
    private $billingService;

    public function __construct(CartService $cartService,
                                SettingsServiceInterface $settingsService,
                                UserServiceInterface $userService,
                                BillsbyServiceInterface $billsbyService,
                                PayPalService $payPalService,
                                BillingService $billingService)
    {
        $this->cartService = $cartService;
        $this->settingsService = $settingsService;
        $this->userService = $userService;
        $this->billsbyService = $billsbyService;
        $this->payPalService = $payPalService;
        $this->billingService = $billingService;
    }

    /**
     *
     * @OA\Post( tags={"Cart"},  path="/api/cart/all",
     *     @OA\Response( response=200,  description="all cart items"),
     * )
     *
     * @return TracksListResource
     */
    public function all()
    {
        $tracks = $this->cartService->all();

        return new TracksListResource($tracks);
    }

    /**
     *
     * @OA\Post( tags={"Cart"},  path="/api/cart/addToCart",
     *     @OA\Parameter(  name="id",  in="query",  required=true,  @OA\Schema( type="integer"  )  ),
     *     @OA\Response( response=200,  description="count in cart or error"),
     * )
     *
     *
     * @param IdRequiredTrackRequest $request
     * @return ResultResource|int
     */
    public function addToCart(IdRequiredTrackRequest $request)
    {
        try {
            $this->cartService->addToCart($request->id);
            return $this->cartService->getCountInCart();
        } catch (\Exception $exception) {
            report($exception);
//            return $this->fail($exception->getMessage());
            return $this->cartService->getCountInCart();
        }

    }

    /**
     *
     * @OA\Post( tags={"Cart"},  path="/api/cart/getCountInCart",
     *     @OA\Response( response=200,  description="count items in cart"),
     * )
     *
     * @return int
     */
    public function getCountInCart()
    {
        return $this->cartService->getCountInCart();
    }

    /**
     *
     * @OA\Post( tags={"Cart"},  path="/api/cart/getSumInCart",
     *     @OA\Response( response=200,  description="sum for items in cart"),
     * )
     *
     * @return float|int
     */
    public function getSumInCart()
    {
        return $this->cartService->getSumInCart();
    }

    /**
     *
     * @OA\Post( tags={"Cart"},  path="/api/cart/setCoupon",
     *     @OA\Parameter(  name="coupon_text",  in="query",  required=true,  @OA\Schema( type="string"  )  ),
     *     @OA\Response( response=200,  description="cart data or error"),
     * )
     *
     * @param Request $request
     * @return ResultResource|mixed
     */
    public function setCoupon(Request $request)
    {
        try {
            $this->cartService->setCoupon($request->coupon_text);
            return $this->cartService->getCartData();
        } catch (\Exception $exception) {
            report($exception);
            return $this->fail($exception->getMessage());
        }
    }

    /**
     *
     * @OA\Post( tags={"Cart"},  path="/api/cart/deleteCart",
     *     @OA\Parameter(  name="id",  in="query",  required=true,  @OA\Schema( type="integer"  )  ),
     *     @OA\Response( response=200,  description=""),
     * )
     *
     * @param IdRequiredTrackRequest $request
     */
    public function deleteCart(IdRequiredTrackRequest $request)
    {
        $this->cartService->deleteCart($request->id);
    }

    /**
     *
     * @OA\Post( tags={"Cart"},  path="/api/cart/checkout",
     *   @OA\Parameter(  name="data['terms']",  in="query",  required=true,  @OA\Schema( type="boolean"  )  ),
     *   @OA\Parameter(  name="data['name']",  in="query",  required=false,  @OA\Schema( type="string"  )  ),
     *   @OA\Parameter( name="data['email']", in="query", required=false, @OA\Schema( type="number")  ),
     *   @OA\Parameter( name="data['phone']", in="query", required=false, @OA\Schema( type="string" ) ),
     *   @OA\Parameter( name="data['typeId']", in="query", required=false, @OA\Schema( type="number" ) ),
     *   @OA\Parameter( name="data['password']", in="query", required=false, @OA\Schema( type="string" ) ),
     *   @OA\Parameter( name="data['checkbox']", in="query", required=false, description="type of billing plan",  @OA\Schema( type="number" ) ),
     *
     *   @OA\Parameter( name="billing['company_name']", in="query", required=true, @OA\Schema( type="string" ) ),
     *   @OA\Parameter( name="billing['company_address']", in="query", required=true, @OA\Schema( type="string" ) ),
     *   @OA\Parameter( name="billing['zip_code']", in="query", required=true, @OA\Schema( type="string" ) ),
     *   @OA\Parameter( name="billing['city']", in="query", required=true, @OA\Schema( type="string" ) ),
     *   @OA\Parameter( name="billing['country']", in="query", required=true, @OA\Schema( type="string" ) ),
     *   @OA\Parameter( name="billing['tax_number']", in="query", required=true, @OA\Schema( type="string" ) ),
     *
     *   @OA\Parameter(  name="payment['payment']",  in="query", required=true,  @OA\Schema( type="integer" ) ),
     *   @OA\Parameter(  name="payment['card_number']",  in="query", required=true,  @OA\Schema( type="string" ) ),
     *   @OA\Parameter(  name="payment['name_on_card']",  in="query", required=true,  @OA\Schema( type="string" ) ),
     *   @OA\Parameter(  name="payment['card_type']",  in="query", required=true,  @OA\Schema( type="string" ) ),
     *   @OA\Parameter(  name="payment['token']",  in="query", required=true, description="token from billsby",  @OA\Schema( type="string" ) ),
     *   @OA\Parameter(  name="payment['card_date']",  in="query", required=true,  @OA\Schema( type="string" ) ),
     *
     *   @OA\Response( response=200,  description="succes with message or fail"),
     * )
     *
     *
     * @param CheckoutRequest $request
     * @param BillingService $billingService
     * @param OrderService $orderService
     * @param TransactionService $transactionService
     * @param BillingTypeService $billingTypeService
     * @param CreditCardService $creditCardService
     * @return ResultResource
     */
    public function checkout(CheckoutRequest $request,
                             BillingService $billingService,
                             OrderService $orderService,
                             TransactionService $transactionService,
                             BillingTypeService $billingTypeService,
                             CreditCardService $creditCardService)
    {
        try {
            if (!Auth::check())
                $user = $this->userService->createWithValidatorAndAuth($request->data);

            $billingService->createBillingAddress($request->billing, auth()->id());
            $billingType = $billingTypeService->getBillingType($request->payment['payment']);

            if ($request->payment['card_number'])
                $card = $creditCardService->checkOrCreate($request->payment, auth()->id(), auth()->user());

            $order = $orderService->createOrder(
                $this->cartService->getSumInCart(),
                auth()->id(),
                $this->cartService->getTracksToCheckout(),
                $this->cartService->getCoupon()
            );

            // TODO fill orders table with info from $request->billing ??

            $transaction = $transactionService->create(
                $this->cartService->getSumInCart(),
                auth()->id(),
                $billingType->id,
                $order->id,
                $request->payment['card_number'] ? $card->id : null
            );

            foreach ($this->cartService->getTracksToCheckout() as $track) {
                PurchasedTracks::create([
                    'user_id' => auth()->id(),
                    'track_id' => $track->id,
                    'amount' => $track->price,
                ]);
            }

            if ($billingType->name == 'paypal')
                $message = $this->payPalSinglePay($transaction,
                    $this->cartService->getTracksToCheckout(),
                    [
                        'currency' => Helper::getCurrency(),
                        'sum' => $transaction->sum,
                        'tax_sum' => 0,
                        'return_url' => config('app.order_return_url'),
                        'cancel_url' => config('app.order_cancel_url'),
                    ]);
            else
                $message = $this->oneTimeCharge($request->payment, $transaction, $order);

        } catch (\Exception $exception) {
            report($exception);
            return $this->fail($exception->getMessage());
        }

        return $this->success($message);
    }


    /**
     *Delete coupon
     * @OA\Post( tags={"Cart"},  path="/api/cart/unsetCoupon",
     *     @OA\Response( response=200,  description=""),
     * )
     */
    public function unsetCoupon()
    {
        $this->cartService->unsetCoupon();
    }


    /**
     * @OA\Post( tags={"Cart"},  path="/api/cart/checkoutPage",
     *     @OA\Response( response=200,  description="data for checout page "),
     * )
     *
     * @return CartResource
     */
    public function checkoutPage()
    {
        $pageInfo = Page::where('key', 'cartPage')->first();
        $businessTypes = BusinessType::all();

        return new CartResource([
            "pageInfo" => $pageInfo,
            "businessTypes" => $businessTypes,
            "coupon" => $this->cartService->getCoupon()
        ], $this->settingsService);
    }

}
