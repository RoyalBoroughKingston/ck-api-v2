<?php

namespace App\Contracts;

use App\Support\Coordinate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

<<<<<<< HEAD:app/Contracts/ServiceSearch.php
interface ServiceSearch
=======
interface PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Contracts/PageSearch.php
{
    const ORDER_RELEVANCE = 'relevance';
    const ORDER_DISTANCE = 'distance';

    /**
     * @param string $term
<<<<<<< HEAD:app/Contracts/ServiceSearch.php
     * @return \App\Contracts\ServiceSearch
     */
    public function applyQuery(string $term): ServiceSearch;

    /**
     * @param string $category
     * @return \App\Contracts\ServiceSearch
     */
    public function applyCategory(string $category): ServiceSearch;

    /**
     * @param string $persona
     * @return \App\Contracts\ServiceSearch
     */
    public function applyPersona(string $persona): ServiceSearch;

    /**
     * @param string $waitTime
     * @return \App\Contracts\ServiceSearch
     */
    public function applyWaitTime(string $waitTime): ServiceSearch;

    /**
     * @param bool $isFree
     * @return \App\Contracts\ServiceSearch
     */
    public function applyIsFree(bool $isFree): ServiceSearch;
=======
     * @return \App\Contracts\PageSearch
     */
    public function applyQuery(string $term): PageSearch;

    /**
     * @param string $category
     * @return \App\Contracts\PageSearch
     */
    public function applyCategory(string $category): PageSearch;

    /**
     * @param string $persona
     * @return \App\Contracts\PageSearch
     */
    public function applyPersona(string $persona): PageSearch;

    /**
     * @param string $waitTime
     * @return \App\Contracts\PageSearch
     */
    public function applyWaitTime(string $waitTime): PageSearch;

    /**
     * @param bool $isFree
     * @return \App\Contracts\PageSearch
     */
    public function applyIsFree(bool $isFree): PageSearch;
>>>>>>> fcb21b09... Created entities for page search:app/Contracts/PageSearch.php

    /**
     * @param string $order
     * @param \App\Support\Coordinate|null $location
<<<<<<< HEAD:app/Contracts/ServiceSearch.php
     * @return \App\Contracts\ServiceSearch
     */
    public function applyOrder(string $order, Coordinate $location = null): ServiceSearch;
=======
     * @return \App\Contracts\PageSearch
     */
    public function applyOrder(string $order, Coordinate $location = null): PageSearch;
>>>>>>> fcb21b09... Created entities for page search:app/Contracts/PageSearch.php

    /**
     * @param \App\Support\Coordinate $location
     * @param int $radius
<<<<<<< HEAD:app/Contracts/ServiceSearch.php
     * @return \App\Contracts\ServiceSearch
     */
    public function applyRadius(Coordinate $location, int $radius): ServiceSearch;
=======
     * @return \App\Contracts\PageSearch
     */
    public function applyRadius(Coordinate $location, int $radius): PageSearch;
>>>>>>> fcb21b09... Created entities for page search:app/Contracts/PageSearch.php

    /**
     * Returns the underlying query. Only intended for use in testing.
     *
     * @return array
     */
    public function getQuery(): array;

    /**
     * @param int|null $page
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function paginate(int $page = null, int $perPage = null): AnonymousResourceCollection;

    /**
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function get(int $perPage = null): AnonymousResourceCollection;

<<<<<<< HEAD:app/Contracts/ServiceSearch.php
    public function applyEligibilities(array $eligibilityNames): ServiceSearch;
=======
    public function applyEligibilities(array $eligibilityNames): PageSearch;
>>>>>>> fcb21b09... Created entities for page search:app/Contracts/PageSearch.php
}
