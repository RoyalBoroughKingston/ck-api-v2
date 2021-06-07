<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceEligibilitiesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('service_eligibilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id', 'services');
            $table->foreignUuid('taxonomy_id', 'taxonomies');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('service_eligibilities');
    }
}
