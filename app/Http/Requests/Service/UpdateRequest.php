<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\HasMissingValues;
use App\Models\File;
use App\Models\Role;
use App\Models\Service;
use App\Models\SocialMedia;
use App\Models\Taxonomy;
use App\Models\UserRole;
use App\Rules\CanUpdateCategoryTaxonomyRelationships;
use App\Rules\CanUpdateServiceEligibilityTaxonomyRelationships;
use App\Rules\FileIsMimeType;
use App\Rules\FileIsPendingAssignment;
use App\Rules\InOrder;
use App\Rules\MarkdownMaxLength;
use App\Rules\MarkdownMinLength;
use App\Rules\NullableIf;
use App\Rules\RootTaxonomyIs;
use App\Rules\Slug;
use App\Rules\UkPhoneNumber;
use App\Rules\UserHasRole;
use App\Rules\VideoEmbed;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    use HasMissingValues;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->user()->isServiceAdmin($this->service)) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'organisation_id' => [
                'exists:organisations,id',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->service->organisation_id
                ),
            ],
            'slug' => [
                'string',
                'min:1',
                'max:255',
                new Slug(),
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->service->slug
                ),
            ],
            'name' => ['string', 'min:1', 'max:255'],
            'type' => [
                Rule::in([
                    Service::TYPE_SERVICE,
                    Service::TYPE_ACTIVITY,
                    Service::TYPE_CLUB,
                    Service::TYPE_GROUP,
                ]),
            ],
            'status' => [
                Rule::in([
                    Service::STATUS_ACTIVE,
                    Service::STATUS_INACTIVE,
                ]),
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->service->status
                ),
            ],
            'intro' => ['string', 'min:1', 'max:300'],
            'description' => [
                'string',
                new MarkdownMinLength(1),
                new MarkdownMaxLength(config('local.service_description_max_chars'), 'Description tab - The long description must be ' . config('local.service_description_max_chars') . ' characters or fewer.'),
            ],
            'wait_time' => [
                'nullable',
                Rule::in([
                    Service::WAIT_TIME_ONE_WEEK,
                    Service::WAIT_TIME_TWO_WEEKS,
                    Service::WAIT_TIME_THREE_WEEKS,
                    Service::WAIT_TIME_MONTH,
                    Service::WAIT_TIME_LONGER,
                ]),
            ],
            'is_free' => ['boolean'],
            'fees_text' => ['nullable', 'string', 'min:1', 'max:255'],
            'fees_url' => ['nullable', 'url', 'max:255'],
            'testimonial' => ['nullable', 'string', 'min:1', 'max:255'],
            'video_embed' => ['nullable', 'string', 'url', 'max:255', new VideoEmbed()],
            'url' => ['nullable', 'url', 'max:255'],
            'contact_name' => ['nullable', 'string', 'min:1', 'max:255'],
            'contact_phone' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                new UkPhoneNumber('Service Contact Phone - Please enter a valid UK telephone number.'),
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'cqc_location_id' => ['nullable', 'string', 'regex:/^\d\-\d+$/'],
            'show_referral_disclaimer' => [
                'boolean',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::superAdmin()->id,
                    ]),
                    $this->showReferralDisclaimerOriginalValue()
                ),
            ],
            'referral_method' => [
                Rule::in([
                    Service::REFERRAL_METHOD_INTERNAL,
                    Service::REFERRAL_METHOD_EXTERNAL,
                    Service::REFERRAL_METHOD_NONE,
                ]),
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->service->referral_method
                ),
            ],
            'referral_button_text' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->service->referral_button_text
                ),
            ],
            'referral_email' => [
                Rule::requiredIf(function () {
                    $referralMethod = $this->input('referral_method', $this->service->referral_method);

                    return $referralMethod === Service::REFERRAL_METHOD_INTERNAL
                    && $this->service->referral_email === null;
                }),
                new NullableIf(function () {
                    $referralMethod = $this->input('referral_method', $this->service->referral_method);

                    return $referralMethod !== Service::REFERRAL_METHOD_INTERNAL;
                }),
                'email',
                'max:255',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->service->referral_email
                ),
            ],
            'referral_url' => [
                Rule::requiredIf(function () {
                    $referralMethod = $this->input('referral_method', $this->service->referral_method);

                    return $referralMethod === Service::REFERRAL_METHOD_EXTERNAL
                    && $this->service->referral_url === null;
                }),
                new NullableIf(function () {
                    $referralMethod = $this->input('referral_method', $this->service->referral_method);

                    return $referralMethod !== Service::REFERRAL_METHOD_EXTERNAL;
                }),
                'url',
                'max:255',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->service->referral_url
                ),
            ],
            'ends_at' => ['nullable', 'date_format:' . CarbonImmutable::ISO8601],
            'useful_infos' => ['array'],
            'useful_infos.*' => ['array'],
            'useful_infos.*.title' => ['required_with:useful_infos.*', 'string', 'min:1', 'max:255'],
            'useful_infos.*.description' => ['required_with:useful_infos.*', 'string', new MarkdownMinLength(1), new MarkdownMaxLength(config('local.useful_info_description_max_chars'))],
            'useful_infos.*.order' => [
                'required_with:useful_infos.*',
                'integer',
                'min:1',
                new InOrder(array_pluck_multi(
                    $this->input('useful_infos', []),
                    'order'
                )),
            ],

            'offerings' => ['nullable', 'array'],
            'offerings.*' => ['array'],
            'offerings.*.offering' => ['required_with:offerings.*', 'string', 'min:1', 'max:255'],
            'offerings.*.order' => [
                'required_with:offerings.*',
                'integer',
                'min:1',
                new InOrder(array_pluck_multi(
                    $this->input('offerings', []),
                    'order'
                )),
            ],

            'social_medias' => ['array'],
            'social_medias.*' => ['array'],
            'social_medias.*.type' => [
                'required_with:social_medias.*',
                Rule::in([
                    SocialMedia::TYPE_FACEBOOK,
                    SocialMedia::TYPE_INSTAGRAM,
                    SocialMedia::TYPE_OTHER,
                    SocialMedia::TYPE_TIKTOK,
                    SocialMedia::TYPE_TWITTER,
                    SocialMedia::TYPE_SNAPCHAT,
                    SocialMedia::TYPE_YOUTUBE,
                ]),
            ],
            'social_medias.*.url' => ['required_with:social_medias.*', 'url', 'max:255'],

            'gallery_items' => ['array', 'max:' . config('local.max_gallery_images')],
            'gallery_items.*' => ['array'],
            'gallery_items.*.file_id' => [
                'required_with:gallery_items.*',
                'exists:files,id',
                new FileIsMimeType(File::MIME_TYPE_PNG, File::MIME_TYPE_SVG, File::MIME_TYPE_JPG, File::MIME_TYPE_JPEG),
                new FileIsPendingAssignment(function (File $file) {
                    return $this->service
                        ->serviceGalleryItems()
                        ->where('file_id', '=', $file->id)
                        ->exists();
                }),
            ],

            'tags' => [
                'array',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->service->tags->only(['slug', 'label'])->all()
                ),
            ],
            'tags.*' => ['array'],
            'tags.*.slug' => ['required_with:tags.*', 'string', 'min:1', 'max:255', new Slug()],
            'tags.*.label' => ['required_with:tags.*', 'string', 'min:1', 'max:255'],

            'category_taxonomies' => $this->categoryTaxonomiesRules(),
            'category_taxonomies.*' => [
                'exists:taxonomies,id',
                new RootTaxonomyIs(Taxonomy::NAME_CATEGORY),
            ],

            'eligibility_types' => $this->serviceEligibilityRules(),
            'eligibility_types.taxonomies' => ['array'],
            'eligibility_types.taxonomies.*' => [
                'uuid',
                'exists:taxonomies,id',
                new RootTaxonomyIs(Taxonomy::NAME_SERVICE_ELIGIBILITY),
            ],

            'eligibility_types.custom.age_group' => ['nullable', 'string', 'min:1', 'max:255'],
            'eligibility_types.custom.disability' => ['nullable', 'string', 'min:1', 'max:255'],
            'eligibility_types.custom.gender' => ['nullable', 'string', 'min:1', 'max:255'],
            'eligibility_types.custom.income' => ['nullable', 'string', 'min:1', 'max:255'],
            'eligibility_types.custom.language' => ['nullable', 'string', 'min:1', 'max:255'],
            'eligibility_types.custom.ethnicity' => ['nullable', 'string', 'min:1', 'max:255'],
            'eligibility_types.custom.housing' => ['nullable', 'string', 'min:1', 'max:255'],
            'eligibility_types.custom.other' => ['nullable', 'string', 'min:1', 'max:255'],

            'logo_file_id' => [
                'nullable',
                'exists:files,id',
                new FileIsMimeType(File::MIME_TYPE_PNG, File::MIME_TYPE_SVG, File::MIME_TYPE_JPG, File::MIME_TYPE_JPEG),
                new FileIsPendingAssignment(),
            ],
            'score' => [
                'nullable',
                'numeric',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::superAdmin()->id,
                    ]),
                    $this->service->score
                ),
                function ($attribute, $value, $fail) {
                    if ($this->service->score !== $value &&
                        !in_array($value, [
                            Service::SCORE_POOR,
                            Service::SCORE_BELOW_AVERAGE,
                            Service::SCORE_AVERAGE,
                            Service::SCORE_ABOVE_AVERAGE,
                            Service::SCORE_EXCELLENT,
                        ])) {
                        $fail($attribute . ' should be between 1 and 5');
                    }
                },
            ],
        ];
    }

    /**
     * Check if the user requested only a preview of the update request.
     */
    public function isPreview(): bool
    {
        return $this->preview === true;
    }

    protected function categoryTaxonomiesRules(): array
    {
        // If global admin and above.
        if ($this->user()->isGlobalAdmin()) {
            return [
                Rule::requiredIf(function () {
                    // Only required if the service currently has no taxonomies.
                    return $this->service->serviceTaxonomies()->doesntExist();
                }),
                'array',
                new CanUpdateCategoryTaxonomyRelationships($this->user(), $this->service),
            ];
        }

        // If not a global admin.
        return [
            'array',
            new CanUpdateCategoryTaxonomyRelationships($this->user(), $this->service),
        ];
    }

    protected function serviceEligibilityRules(): array
    {
        return [
            'array',
            new CanUpdateServiceEligibilityTaxonomyRelationships($this->user(), $this->service),
        ];
    }

    protected function showReferralDisclaimerOriginalValue(): bool
    {
        // If the new referral method is none, then always require false.
        if ($this->referral_method === Service::REFERRAL_METHOD_NONE) {
            return false;
        }

        /*
         * If the original referral method was not none, and the referral disclaimer was hidden,
         * then continue hiding the disclaimer.
         */
        if (
            $this->service->referral_method !== Service::REFERRAL_METHOD_NONE
            && $this->service->show_referral_disclaimer === false
        ) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        $type = $this->input('type', $this->service->type);

        return [
            'url.url' => 'Details tab - Please enter a valid web address in the correct format (starting with https:// or http://).',
            'video_embed.url' => 'Additional info tab - Please enter a valid video link (eg. https://www.youtube.com/watch?v=JyHR_qQLsLM).',
            'contact_email.email' => "Additional Info tab -  Please enter an email address users can use to contact your {$type} (eg. name@example.com).",
            'useful_infos.*.title.required_with' => 'Good to know tab - Please select a title.',
            'useful_infos.*.description.required_with' => 'Good to know tab - Please enter a description.',
            'social_medias.*.url.url' => 'Additional info tab - Please enter a valid social media web address (eg. https://www.youtube.com/watch?v=h-2sgpokvGI).',
        ];
    }
}
