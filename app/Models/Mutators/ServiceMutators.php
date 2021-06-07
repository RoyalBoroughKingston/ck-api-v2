<?php

namespace App\Models\Mutators;

use Illuminate\Support\Collection;

trait ServiceMutators
{
    public function getServiceEligibilitiesAttribute(): Collection
    {
        return new Collection([
            'custom' => [
                'age_group' => $this->eligibility_age_group_custom,
                'disability' => $this->eligibility_disability_custom,
                'ethnicity' => $this->eligibility_ethnicity_custom,
                'gender' => $this->eligibility_gender_custom,
                'income' => $this->eligibility_income_custom,
                'language' => $this->eligibility_language_custom,
                'other' => $this->eligibility_other_custom,
            ],
            'taxonomies' => $this->serviceEligibilities()->pluck('taxonomy_id')->all(),
        ]);
    }
}
