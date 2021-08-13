<?php


namespace App\Services\Filter;


use App\Models\Category;
use App\Models\Tag;
use App\Models\User;

class SearchFilterHelper
{

    public $search;
    private $searchResult;
    private $filterResult;


    /**
     * @param $search
     */
    public function setSearch($search)
    {
        $this->search = $search;
    }


    /**
     * function to start searching by string or display search history
     */
    public function startSearch()
    {
        if (!$this->search)
            $this->getHistory();
        else {
            $this->checkCategories();
            $this->checkArtists();
            $this->checkTags();
        }
    }


    /**
     * Get search history
     */
    private function getHistory()
    {
        $filterHistory = new FilterHistory();

        $this->setSearchResult($filterHistory->getFromHistory());
    }


    /**
     * Filtering by track tags
     */
    private function checkTags()
    {
        $tags = Tag::has('activeTracks')->
        where('name', 'like', '%' . $this->search . '%')
            ->limit(5)
            ->select(['id', 'slug', 'name'])->get();

        if ($tags) {
            $tags = $tags->map(function ($item) {
                return [
                    'title' => $item->name,
                    'slug' => $item->slug,
                    'id' => $item->id,
                    'linkName' => 'tags',
                ];
            });
            if(!$tags->isEmpty()){
                $tags = collect(['tags' => $tags]);
                $this->setSearchResult($tags, 'filterResult');
                $this->setSearchResult($tags);
            }

        }
    }


    /**
     * Filtering by users
     */
    private function checkArtists()
    {
        $artists = User::role('author')->
        where([['active', true], ['artist_name', 'like', '%' . $this->search . '%']])
            ->limit(5)->get();

        if ($artists) {
            $artists = $artists->map(function ($item) {
                return [
                    'title' => $item->artist_name,
                    'slug' => $item->slug,
                    'id' => $item->id,
                    'linkName' => 'artists',
                ];
            });
            if(!$artists->isEmpty()) {
                $artists = collect(['artists' => $artists]);
                $this->setSearchResult($artists, 'filterResult');
                $this->setSearchResult($artists);
            }
        }
    }


    /**
     * Filtering by categories
     */
    private function checkCategories()
    {
        $categories = Category::has('activeTracks')
            ->with('categoryType')
            ->where([['active', true], ['title', 'like', '%' . $this->search . '%']])
            ->limit(5)
            ->get();

        if ($categories) {
            $categories = $categories->map(function ($item) {
                return [
                    'type_id' => $item->categoryType->title,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'id' => $item->id,
                    'linkName' => 'categories',
                ];
            });
            if(!$categories->isEmpty()) {
                $this->setSearchResult(['categories' => $categories], 'filterResult');
                $this->setSearchResult($categories->groupBy('type_id'));
            }
        }
    }


    /**
     *
     * Set data to variable
     *
     * @param $searchResult
     * @param string $variable
     */
    private function setSearchResult($searchResult, $variable = 'searchResult')
    {
        if (!is_object($this->$variable)) {
            $this->$variable = collect([]);
        }

        $this->$variable = $this->$variable->merge($searchResult);
    }

    /**
     * Get results of filtering
     *
     * @return mixed
     */
    public function getSearchResult()
    {
        return $this->searchResult;
    }

    /**
     * @return mixed
     */
    public function getFilterResult()
    {
        return $this->filterResult;
    }

}
