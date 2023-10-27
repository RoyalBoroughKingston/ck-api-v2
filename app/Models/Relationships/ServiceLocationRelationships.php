<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\File;
use App\Models\HolidayOpeningHour;
use App\Models\Location;
use App\Models\RegularOpeningHour;
use App\Models\Service;

trait ServiceLocationRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function regularOpeningHours(): HasMany
    {
        return $this->hasMany(RegularOpeningHour::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function holidayOpeningHours(): HasMany
    {
        return $this->hasMany(HolidayOpeningHour::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'image_file_id');
    }
}
