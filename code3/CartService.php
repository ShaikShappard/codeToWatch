<?php


namespace App\Services;


use App\Facades\SiteSettings;
use App\Models\Coupon;
use App\Repositories\Interfaces\TrackRepositoryInterface;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Tightenco\Collect\Support\Collection;

class CartService
{
    /**
     * @var TrackRepositoryInterface
     */
    private $trackRepository;


    /**
     * CartService constructor.
     * @param TrackRepositoryInterface $trackRepository
     */
    public function __construct(TrackRepositoryInterface $trackRepository)
    {
        $this->trackRepository = $trackRepository;
    }


    /**
     * Return all tracks from cart
     * @return \Illuminate\Support\Collection|Collection
     */
    public function all()
    {
        return collect(Session::get('cart.track'));
    }


    /**
     * Return tracks for checkout
     * @return array
     */
    public function getTracksToCheckout()
    {
        if (!($tracks = collect(Session::get('cart.track')))->isEmpty()) {
            $itemCost  = $this->getSumInCart() / $tracks->count();
            $tracks = $tracks->map(function ($item) use ($itemCost){
                return [
                    'name' => $item->title,
                    'description' => 'track',
                    'quantity' => 1,
                    'id' => $item->id,
                    'price' => $itemCost,
                ];
            });
            return $tracks->toArray();
        };
    }


    /**
     * Add track to cart by ID
     * @param $trackId
     * @throws \Exception
     */
    public function addToCart($trackId)
    {
        $track = $this->trackRepository->trackListBuilder()->where('id', $trackId)->first();

        if (!$track)
            throw new \Exception('The product does not exist or is not active');
        if (Session::has('cart.track.' . $track->id))
            throw new \Exception('This product is already in your shopping cart');

        Session::put('cart.track.' . $track->id, $track);

    }


    /**
     * Return 0 or count uin cart
     *
     * @return int
     */
    public function getCountInCart()
    {
        if (Session::has('cart.track')) {
            return count(Session::get('cart.track'));
        } else {
            return 0;
        }
    }

    /**
     * Return sum in cart
     *
     * @return float|int
     */
    public function getSumInCart()
    {
        $discount = 0;

        if (!Session::has('cart.track')) return 0; // если в корзине что-то есть
        if (Session::has('cart.coupon')) { // если есть купон
            $discount = Session::get('cart.coupon')->amount / 100;
        }

        $total = count(Session::get('cart.track')) * SiteSettings::trackCost();
        $total = round($total - ($total * $discount), 2);

        Session::put('cart.discount_total', $total);
        Session::put('cart.sum_total', (float)$total);

        return $total;
    }


    /**
     * @param $coupon_text
     * @throws \Exception
     */
    public function setCoupon($coupon_text)
    {
        $coupon = Coupon::where([
            ['coupon_text', $coupon_text],
        ])->first();

        $date = Carbon::now();

        if ($coupon !== null) {
            if ($coupon->active == false)
                throw new \Exception('This coupon is not active');
            if ($coupon->max_usage <= 0)
                throw new \Exception('This coupon has already been used the maximum number of times');
            if ($date->gt($coupon->date_expire))
                throw new \Exception('The coupon has expired');
        } else {
            throw new \Exception('There is no such coupon');
        }
        Session::put('cart.coupon', $coupon);

        $this->getSumInCart();
    }

    /**
     * Return all cart data
     * @return mixed
     */
    public function getCartData()
    {
        return Session::get('cart');
    }


    /**
     * @return mixed
     */
    public function getCoupon()
    {
        if (Session::has('cart.coupon'))
            return Session::get('cart.coupon');

    }

    /**
     * delete coupon
     */
    public function unsetCoupon()
    {
        if (Session::has('cart.coupon'))
            Session::forget('cart.coupon');

    }


    /**
     * delete cart items by id or all items
     * @param $id
     */
    public function deleteCart($id)
    {
        if ($id == "all") {
            Session::forget('cart.track');
        } else {
            Session::forget('cart.track.' . $id);
        }
    }

}
