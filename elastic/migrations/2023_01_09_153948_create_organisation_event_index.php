<?php
declare(strict_types=1);

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;
use App\Search\ElasticSearch\Settings\EventsIndexSettings;

final class CreateOrganisationEventIndex implements MigrationInterface
{
    /**
     * The mapping for the fields.
     *
     * @var array
     */
    protected $mapping = [
        'properties' => [
            'id' => ['type' => 'keyword'],
            'enabled' => ['type' => 'boolean'],
            'title' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'intro' => ['type' => 'text'],
            'description' => ['type' => 'text'],
            'start_date' => [
                'type' => 'date',
                'format' => 'strict_date_hour_minute_second',
            ],
            'end_date' => [
                'type' => 'date',
                'format' => 'strict_date_hour_minute_second',
            ],
            'is_free' => ['type' => 'boolean'],
            'is_virtual' => ['type' => 'boolean'],
            'has_wheelchair_access' => ['type' => 'boolean'],
            'has_induction_loop' => ['type' => 'boolean'],
            'organisation_name' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'taxonomy_categories' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'collection_categories' => ['type' => 'keyword'],
            'event_location' => [
                'type' => 'nested',
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'location' => ['type' => 'geo_point'],
                    'has_wheelchair_access' => ['type' => 'boolean'],
                    'has_induction_loop' => ['type' => 'boolean'],
                    'has_accessible_toilet' => ['type' => 'boolean'],
                ],
            ],
        ],
    ];

    /**
     * The settings for the index.
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $settings = (new EventsIndexSettings())->getSettings();
        Index::createIfNotExistsRaw('organisation-events', $this->mapping, $settings);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('organisation-events');
    }
}
