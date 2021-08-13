<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Resources\filter\SearchHelperResource;
use App\Services\Filter\SearchFilterHelper;
use App\Services\Filter\FilterHistory;
use Illuminate\Http\Request;

class FilterController extends Controller
{
    private $searchFilterHelper;
    private $filterHistory;


    /**
     * FilterController constructor.
     * @param SearchFilterHelper $searchFilterHelper
     * @param FilterHistory $filterHistory
     */
    public function __construct(SearchFilterHelper $searchFilterHelper, FilterHistory $filterHistory)
    {
        $this->searchFilterHelper = $searchFilterHelper;
        $this->filterHistory = $filterHistory;
    }


    /**
     * @OA\Post( tags={"Filter"},  path="/api/filter/searchHelper",
     *     @OA\Parameter( name="search", in="query", required=false, @OA\Schema( type="string" ) ),
     *      @OA\Response( response=200,  description="search data"),
     * )
     *
     * @param Request $request
     * @return SearchHelperResource
     */
    public function searchHelper(Request $request)
    {
        $this->searchFilterHelper ->setSearch($request->search);

        $this->searchFilterHelper->startSearch();

        return new SearchHelperResource( $this->searchFilterHelper->getSearchResult());

    }


    /**
     *
     * @OA\Post( tags={"Filter"},  path="/api/filter/setSearchHistory",
     *     @OA\Parameter( name="search", in="query", required=false, @OA\Schema( type="string" ) ),
     *     @OA\Response( response=200,  description=""),
     * )
     *
     * @param Request $request
     */
    public function setSearchHistory(Request $request)
    {
        $this->filterHistory->addToHistory($request->search);
    }


    /**
     * @OA\Post( tags={"Filter"},  path="/api/filter/getSearchHistory",
     *     @OA\Parameter( name="search", in="query", required=true, @OA\Schema( type="string" ) ),
     *     @OA\Response( response=200,  description=""),
     * )
     *
     * @param Request $request
     */
    public function getSearchHistory(Request $request)
    {
        $this->filterHistory->addToHistory($request->search);
    }


    /**
     *
     * @OA\Post( tags={"Filter"},  path="/api/filter/removeSearchHistory",
     *     @OA\Parameter( name="hash", in="query", required=true, @OA\Schema( type="string" ) ),
     *     @OA\Response( response=200,  description=""),
     * )
     *
     * @param Request $request
     */
    public function removeSearchHistory(Request $request)
    {
        $this->filterHistory->removeFromHistory($request->hash);
    }
}
