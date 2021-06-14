<?php

namespace App\UpdateRequest;

use App\Http\Requests\Service\StoreRequest;
use App\Models\Service;
use App\Models\Taxonomy;
use App\Models\UpdateRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class NewServiceCreatedByOrgAdmin implements AppliesUpdateRequests
{
    /**
     * Check if the update request is valid.
     *
     * @param \App\Models\UpdateRequest $updateRequest
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validateUpdateRequest(UpdateRequest $updateRequest): Validator
    {
        $user = Auth::user();
        $rules = (new StoreRequest())
            ->merge($updateRequest->data)
            ->setUserResolver(function () use ($updateRequest) {
                return $updateRequest->user;
            })
            ->rules();

        return ValidatorFacade::make($updateRequest->data, $rules);
    }

    /**
     * Apply the update request.
     *
     * @param \App\Models\UpdateRequest $updateRequest
     * @return \App\Models\UpdateRequest
     */
    public function applyUpdateRequest(UpdateRequest $updateRequest): UpdateRequest
    {
        $data = collect($updateRequest->data);

        $insert = [
            'organisation_id' => $data->get('organisation_id'),
            'slug' => $data->get('slug'),
            'name' => $data->get('name'),
            'type' => $data->get('type'),
            'status' => $data->get('status'),
            'intro' => $data->get('intro'),
            'description' => sanitize_markdown(
                $data->get('description')
            ),
            'wait_time' => $data->get('wait_time'),
            'is_free' => $data->get('is_free'),
            'fees_text' => $data->get('fees_text'),
            'fees_url' => $data->get('fees_url'),
            'testimonial' => $data->get('testimonial'),
            'video_embed' => $data->get('video_embed'),
            'url' => $data->get('url'),
            'contact_name' => $data->get('contact_name'),
            'contact_phone' => $data->get('contact_phone'),
            'contact_email' => $data->get('contact_email'),
            'show_referral_disclaimer' => $data->get('show_referral_disclaimer'),
            'referral_method' => $data->get('referral_method'),
            'referral_button_text' => $data->get('referral_button_text'),
            'referral_email' => $data->get('referral_email'),
            'referral_url' => $data->get('referral_url'),
            'logo_file_id' => $data->get('logo_file_id'),
            // This must always be updated regardless of the fields changed.
            'last_modified_at' => Carbon::now(),
        ];

        $service = Service::create($insert);

        if (array_key_exists('useful_infos', $data)) {
            $service->usefulInfos()->delete();
            foreach ($data['useful_infos'] as $usefulInfo) {
                $service->usefulInfos()->create([
                    'title' => $usefulInfo['title'],
                    'description' => sanitize_markdown($usefulInfo['description']),
                    'order' => $usefulInfo['order'],
                ]);
            }
        }

        // Update the offering records.
        if (array_key_exists('offerings', $data)) {
            $service->offerings()->delete();
            foreach ($data['offerings'] as $offering) {
                $service->offerings()->create([
                    'offering' => $offering['offering'],
                    'order' => $offering['order'],
                ]);
            }
        }

        // Update the gallery item records.
        if (array_key_exists('gallery_items', $updateRequest->data)) {
            $service->serviceGalleryItems()->delete();
            foreach ($data['gallery_items'] as $galleryItem) {
                $service->serviceGalleryItems()->create([
                    'file_id' => $galleryItem['file_id'],
                ]);
            }
        }

        // Update the category taxonomy records.
        if (array_key_exists('category_taxonomies', $data)) {
            $taxonomies = Taxonomy::whereIn('id', $data['category_taxonomies'])->get();
            $service->syncServiceTaxonomies($taxonomies);
        }

        // Ensure conditional fields are reset if needed.
        $service->resetConditionalFields();

        return $updateRequest;
    }

    /**
     * Custom logic for returning the data. Useful when wanting to transform
     * or modify the data before returning it, e.g. removing passwords.
     *
     * @param array $data
     * @return array
     */
    public function getData(array $data): array
    {
        Arr::forget($data, ['user.password']);

        return $data;
    }
}
