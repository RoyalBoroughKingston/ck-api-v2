<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ServiceCriterionResource extends JsonResource
{
    private $criteriaKeys = [
        'age_group',
        'disability',
        'employment',
        'gender',
        'housing',
        'income',
        'language',
        'ethnicity',
        'other',
    ];

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $structuredList = $this->mapTaxonomyNamesToCriteriaName($this->getServiceEligibilities());
        $serializedData = [];

        foreach ($this->criteriaKeys as $criteriaKey) {
            $serializedData[$criteriaKey] = $this->generateCommaSeparatedList($structuredList, $criteriaKey);
        }

        return $serializedData;
    }

    //@TODO: change ->each() to a foreach, so we can truly accept either a Collection or array
    private function mapTaxonomyNamesToCriteriaName(iterable $taxonomyCollection)
    {
        $keyedByCriteriaName = [];
        $taxonomyCollection->each(function ($item) use (&$keyedByCriteriaName) {
            $key = Str::snake($item->parent->name);
            $keyedByCriteriaName[$key][] = $item->name;
        });

        return $keyedByCriteriaName;
    }

    private function generateCommaSeparatedList(iterable $taxonomyList, string $key): ?string
    {
        $customFieldName = 'eligibility_' . $key . '_custom';

        if (array_key_exists($key, $taxonomyList)) {
            if (!empty($this->{$customFieldName})) {
                $taxonomyList[$key][] = $this->{$customFieldName};
            }

            return implode(',', $taxonomyList[$key]);
        }

        if (!empty($this->{$customFieldName})) {
            return $this->{$customFieldName};
        }

        return null;
    }
}
