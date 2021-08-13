<?php


namespace App\Services\Filter;


use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class FilterHistory
{

    /**
     *
     * Add search field to history
     *
     * @param $search
     * @return bool
     */
    public function addToHistory($search)
    {

        $history = collect(Session::get('searchHistory'));

        if (count($history) > 0) {
            $history = $history->search(function ($item, $key) use ($search) {
                return ($item['linkName'] == $search['linkName'] & $item['slug'] == $search['slug']);
            });
            if ($history === false)
                return false;
        }


        $search['hash'] = Str::random(15);

        Session::push('searchHistory', $search);
    }


    /**
     *
     * Remove string from history by hash
     *
     * @param $hash
     */
    public function removeFromHistory($hash)
    {
        $key = collect(Session::get('searchHistory'))->search(function ($item, $key) use ($hash) {
            return $item['hash'] == $hash ;
        });

        Session::forget('searchHistory.'.$key);
    }



    /**
     *
     * Get all history
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFromHistory()
    {
        if (Session::get('searchHistory'))
            return collect(['Search history' => Session::get('searchHistory')]);
    }

}
