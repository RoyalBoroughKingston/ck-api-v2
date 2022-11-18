<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\Coordinate;

class ServiceCriteriaQuery
{
    const ORDER_RELEVANCE = 'relevance';

    const ORDER_DISTANCE = 'distance';

    /**
     * @var string|null
     */
    protected $query;

    /**
     * @var string|null
     */
    protected $type;

    /**
     * @var string[]|null
     */
    protected $categories;

    /**
     * @var string[]|null
     */
    protected $personas;

    /**
     * @var string|null
     */
    protected $waitTime;

    /**
     * @var bool|null
     */
    protected $isFree;

    /**
     * @var string[]|null
     */
    protected $eligibilities;

    /**
     * @var \App\Support\Coordinate|null
     */
    protected $location;

    /**
     * @var int|null
     */
    protected $distance;

    /**
     * @var string
     */
    protected $order;

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

    /**
     * @return bool
     */
    public function hasType(): bool
    {
        return $this->type !== null;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string|null $type
     */
    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return bool
     */
    public function hasCategories(): bool
    {
        return $this->categories !== null;
    }

    /**
     * @return string[]|null
     */
    public function getCategories(): ?array
    {
        return $this->categories;
    }

    /**
     * @param string[]|null $categories
     */
    public function setCategories(?array $categories): void
    {
        $this->categories = $categories;
    }

    /**
     * @return bool
     */
    public function hasPersonas(): bool
    {
        return $this->personas !== null;
    }

    /**
     * @return string[]|null
     */
    public function getPersonas(): ?array
    {
        return $this->personas;
    }

    /**
     * @param string[]|null $personas
     */
    public function setPersonas(?array $personas): void
    {
        $this->personas = $personas;
    }

    /**
     * @return bool
     */
    public function hasWaitTime(): bool
    {
        return $this->waitTime !== null;
    }

    /**
     * @return string|null
     */
    public function getWaitTime(): ?string
    {
        return $this->waitTime;
    }

    /**
     * @param string|null $waitTime
     */
    public function setWaitTime(?string $waitTime): void
    {
        $this->waitTime = $waitTime;
    }

    /**
     * @return bool
     */
    public function hasIsFree(): bool
    {
        return $this->isFree !== null;
    }

    /**
     * @return bool|null
     */
    public function getIsFree(): ?bool
    {
        return $this->isFree;
    }

    /**
     * @param bool|null $isFree
     */
    public function setIsFree(?bool $isFree): void
    {
        $this->isFree = $isFree;
    }

    /**
     * @return bool
     */
    public function hasLocation(): bool
    {
        return $this->location !== null;
    }

    /**
     * @return \App\Support\Coordinate|null
     */
    public function getLocation(): ?Coordinate
    {
        return $this->location;
    }

    /**
     * @param \App\Support\Coordinate|null $location
     */
    public function setLocation(?Coordinate $location): void
    {
        $this->location = $location;
    }

    /**
     * @return bool
     */
    public function hasDistance(): bool
    {
        return $this->distance !== null;
    }

    /**
     * @return int|null
     */
    public function getDistance(): ?int
    {
        return $this->distance;
    }

    /**
     * @param int $distance
     */
    public function setDistance(int $distance): void
    {
        $this->distance = $distance;
    }

    /**
     * @return bool
     */
    public function hasEligibilities(): bool
    {
        return $this->eligibilities !== null;
    }

    /**
     * @return string[]|null
     */
    public function getEligibilities(): ?array
    {
        return $this->eligibilities;
    }

    /**
     * @param string[]|null $eligibilities
     */
    public function setEligibilities(?array $eligibilities): void
    {
        $this->eligibilities = $eligibilities;
    }

    /**
     * @return bool
     */
    public function hasOrder(): bool
    {
        return $this->order !== null;
    }

    /**
     * @return string
     */
    public function getOrder(): string
    {
        return $this->order;
    }

    /**
     * @param string $order
     */
    public function setOrder(string $order): void
    {
        $this->order = $order;
    }
}
