<?php

declare(strict_types=1);

use App\Search\ElasticSearch\Settings\ServicesIndexSettings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreateServiceIndex implements MigrationInterface
{
    /**
     * The mapping for the fields.
     *
     * @var array
     */
    protected $mapping = [
        'properties' => [
            'id' => ['type' => 'keyword'],
            'name' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'intro' => ['type' => 'text'],
            'description' => ['type' => 'text'],
            'wait_time' => ['type' => 'keyword'],
            'is_free' => ['type' => 'boolean'],
            'status' => ['type' => 'keyword'],
            'score' => ['type' => 'integer'],
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
            'collection_personas' => ['type' => 'keyword'],
            'service_locations' => [
                'type' => 'nested',
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'location' => ['type' => 'geo_point'],
                ],
            ],
            'service_eligibilities' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
        ],
    ];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $settings = (new ServicesIndexSettings())->getSettings();
        Index::createIfNotExistsRaw('services', $this->mapping, $settings);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('services');
    }
}
