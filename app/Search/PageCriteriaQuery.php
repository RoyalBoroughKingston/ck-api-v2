<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\Coordinate;

class PageCriteriaQuery
{
    const ORDER_RELEVANCE = 'relevance';

    /**
     * @var string|null
     */
    protected $query;

    /**
     * @return bool
     */
    public function hasQuery(): bool
    {
        return $this->query !== null;
    }

    /**
     * @return string|null
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * @param string|null $query
     */
    public function setQuery(?string $query): void
    {
        $this->query = $query;
    }
}
