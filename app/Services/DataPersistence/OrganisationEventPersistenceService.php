<?php

namespace App\Services\DataPersistence;

use App\Contracts\DataPersistenceService;
use App\Generators\UniqueSlugGenerator;
use App\Models\Model;
use App\Models\OrganisationEvent;
use App\Models\Taxonomy;
use App\Models\UpdateRequest as UpdateRequestModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class OrganisationEventPersistenceService implements DataPersistenceService
{
    use ResizesImages;
    use HasUniqueSlug;

    /**
     * Unique Slug Generator.
     *
     * @var UniqueSlugGenerator
     */
    protected $slugGenerator;

    public function __construct(UniqueSlugGenerator $slugGenerator)
    {
        $this->slugGenerator = $slugGenerator;
    }

    /**
     * Store the model.
     *
     * @return UpdateRequestModel|OrganisationEvent
     */
    public function store(FormRequest $request)
    {
        return $request->user()->isSuperAdmin()
        ? $this->processAsNewEntity($request)
        : $this->processAsUpdateRequest($request, null);
    }

    /**
     * Update the model.
     */
    public function update(FormRequest $request, Model $model): UpdateRequestModel
    {
        return $this->processAsUpdateRequest($request, $model);
    }

    /**
     * Create a new model from the provided request.
     */
    public function processAsNewEntity(FormRequest $request): OrganisationEvent
    {
        return DB::transaction(function () use ($request) {
            // Create the OrganisationEvent.
            $organisationEvent = OrganisationEvent::create([
                'title' => $request->title,
                'slug' => $this->uniqueSlug($request->input('slug', $request->input('title')), (new OrganisationEvent())),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'intro' => $request->intro,
                'description' => sanitize_markdown($request->description),
                'is_free' => $request->is_free,
                'fees_text' => $request->fees_text,
                'fees_url' => $request->fees_url,
                'organiser_name' => $request->organiser_name,
                'organiser_phone' => $request->organiser_phone,
                'organiser_email' => $request->organiser_email,
                'organiser_url' => $request->organiser_url,
                'booking_title' => $request->booking_title,
                'booking_summary' => $request->booking_summary,
                'booking_url' => $request->booking_url,
                'booking_cta' => $request->booking_cta,
                'homepage' => $request->homepage,
                'is_virtual' => $request->is_virtual,
                'location_id' => $request->location_id,
                'image_file_id' => $request->image_file_id,
                'organisation_id' => $request->organisation_id,
            ]);

            if ($request->filled('location_id')) {
                $organisationEvent->load('location');
            }

            if ($request->filled('image_file_id')) {
                $this->resizeImageFile($request->image_file_id);
            }

            // Create the category taxonomy records.
            $taxonomies = Taxonomy::whereIn('id', $request->category_taxonomies)->get();
            $organisationEvent->syncTaxonomyRelationships($taxonomies);

            return $organisationEvent;
        });
    }

    /**
     * Process the requested changes and either update the model or store an update request.
     */
    public function processAsUpdateRequest(FormRequest $request, ?OrganisationEvent $event = null): UpdateRequestModel
    {
        return DB::transaction(function () use ($request, $event) {
            $data = array_filter_missing([
                'title' => $request->missingValue('title'),
                'slug' => $request->missingValue('slug'),
                'start_date' => $request->missingValue('start_date'),
                'end_date' => $request->missingValue('end_date'),
                'start_time' => $request->missingValue('start_time'),
                'end_time' => $request->missingValue('end_time'),
                'intro' => $request->missingValue('intro'),
                'description' => $request->missingValue('description', function ($description) {
                    return sanitize_markdown($description);
                }),
                'is_free' => $request->missingValue('is_free'),
                'fees_text' => $request->missingValue('fees_text'),
                'fees_url' => $request->missingValue('fees_url'),
                'organiser_name' => $request->missingValue('organiser_name'),
                'organiser_phone' => $request->missingValue('organiser_phone'),
                'organiser_email' => $request->missingValue('organiser_email'),
                'organiser_url' => $request->missingValue('organiser_url'),
                'booking_title' => $request->missingValue('booking_title'),
                'booking_summary' => $request->missingValue('booking_summary'),
                'booking_url' => $request->missingValue('booking_url'),
                'booking_cta' => $request->missingValue('booking_cta'),
                'homepage' => $request->missingValue('homepage'),
                'is_virtual' => $request->missingValue('is_virtual'),
                'organisation_id' => $request->missingValue('organisation_id'),
                'location_id' => $request->missingValue('location_id'),
                'image_file_id' => $request->missingValue('image_file_id'),
                'category_taxonomies' => $request->missingValue('category_taxonomies'),
            ]);

            if ($request->filled('image_file_id')) {
                $this->resizeImageFile($request->image_file_id);
            }

            $updateableType = UpdateRequestModel::EXISTING_TYPE_ORGANISATION_EVENT;

            if (!$event) {
                $updateableType = UpdateRequestModel::NEW_TYPE_ORGANISATION_EVENT;
            }
            /** @var UpdateRequestModel $updateRequest */
            $updateRequest = new UpdateRequestModel([
                'updateable_type' => $updateableType,
                'updateable_id' => $event->id ?? null,
                'user_id' => $request->user()->id,
                'data' => $data,
            ]);

            // Only persist to the database if the user did not request a preview.
            if ($updateRequest->updateable_type === UpdateRequestModel::EXISTING_TYPE_ORGANISATION_EVENT) {
                // Preview currently only available for update operations
                if (!$request->isPreview()) {
                    $updateRequest->save();
                }
            } else {
                $updateRequest->save();
            }

            return $updateRequest;
        });
    }
}
