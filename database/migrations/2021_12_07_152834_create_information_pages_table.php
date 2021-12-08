<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInformationPagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('information_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('content');
            $table->unsignedInteger('order');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::table('information_pages', function (Blueprint $table) {
            $table->nullableForeignUuid('image_file_id', 'files');
            $table->nullableForeignUuid('parent_id', 'information_pages');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('information_pages', function (Blueprint $table) {
            $table->dropForeign(['image_id']);
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('information_pages');
    }
}
