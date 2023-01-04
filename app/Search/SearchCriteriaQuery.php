<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\Coordinate;

class SearchCriteriaQuery
{
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
     * @var bool|null
     */
    protected $isVirtual;

    /**
     * @var bool|null
     */
    protected $hasWheelchairAccess;

    /**
     * @var bool|null
     */
    protected $hasInductionLoop;

    /**
     * @var bool|null
     */
    protected $hasAccessibleToilet;

    /**
     * @var string|null
     */
    protected $startsAfter;

    /**
     * @var string|null
     */
    protected $endsBefore;

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
     * @param  string|null  $query
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
     * @param  string|null  $type
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
     * @param  string[]|null  $categories
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
     * @param  string[]|null  $personas
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
     * @param  string|null  $waitTime
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
     * @param  bool|null  $isFree
     */
    public function setIsFree(?bool $isFree): void
    {
        $this->isFree = $isFree;
    }

    /**
     * @return bool
     */
    public function hasIsVirtual(): bool
    {
        return $this->isVirtual !== null;
    }

    /**
     * @return bool|null
     */
    public function getIsVirtual(): ?bool
    {
        return $this->isVirtual;
    }

    /**
     * @param  bool|null  $isVirtual
     */
    public function setIsVirtual(?bool $isVirtual): void
    {
        $this->isVirtual = $isVirtual;
    }

    /**
     * @return bool
     */
    public function hasHasWheelchairAccess(): bool
    {
        return $this->hasWheelchairAccess !== null;
    }

    /**
     * @return bool|null
     */
    public function getHasWheelchairAccess(): ?bool
    {
        return $this->hasWheelchairAccess;
    }

    /**
     * @param  bool|null  $hasWheelchairAccess
     */
    public function setHasWheelchairAccess(?bool $hasWheelchairAccess): void
    {
        $this->hasWheelchairAccess = $hasWheelchairAccess;
    }

    /**
     * @return bool
     */
    public function hasHasInductionLoop(): bool
    {
        return $this->hasInductionLoop !== null;
    }

    /**
     * @return bool|null
     */
    public function getHasInductionLoop(): ?bool
    {
        return $this->hasInductionLoop;
    }

    /**
     * @param  bool|null  $hasInductionLoop
     */
    public function setHasInductionLoop(?bool $hasInductionLoop): void
    {
        $this->hasInductionLoop = $hasInductionLoop;
    }

    /**
     * @return bool
     */
    public function hasHasAccessibleToilet(): bool
    {
        return $this->hasAccessibleToilet !== null;
    }

    /**
     * @return bool|null
     */
    public function getHasAccessibleToilet(): ?bool
    {
        return $this->hasAccessibleToilet;
    }

    /**
     * @param  bool|null  $hasAccessibleToilet
     */
    public function setHasAccessibleToilet(?bool $hasAccessibleToilet): void
    {
        $this->hasAccessibleToilet = $hasAccessibleToilet;
    }

    /**
     * @return bool
     */
    public function hasStartsAfter(): bool
    {
        return $this->startsAfter !== null;
    }

    /**
     * @return string|null
     */
    public function getStartsAfter(): ?string
    {
        return $this->startsAfter;
    }

    /**
     * @param  string|null  $startsAfter
     */
    public function setStartsAfter(?string $startsAfter): void
    {
        $this->startsAfter = $startsAfter;
    }

    /**
     * @return bool
     */
    public function hasEndsBefore(): bool
    {
        return $this->endsBefore !== null;
    }

    /**
     * @return string|null
     */
    public function getEndsBefore(): ?string
    {
        return $this->endsBefore;
    }

    /**
     * @param  string|null  $endsBefore
     */
    public function setEndsBefore(?string $endsBefore): void
    {
        $this->endsBefore = $endsBefore;
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
     * @param  \App\Support\Coordinate|null  $location
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
     * @param  int  $distance
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
     * @param  string[]|null  $eligibilities
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
     * @param  string  $order
     */
    public function setOrder(string $order): void
    {
        $this->order = $order;
    }
}
