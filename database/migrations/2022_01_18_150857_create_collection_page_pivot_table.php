<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCollectionPagePivotTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('collection_page', function (Blueprint $table) {
            $table->foreignUuidKeyColumn('page_id', 'pages');
            $table->foreignUuidKeyColumn('collection_id', 'collections');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('collection_page');
    }
}
