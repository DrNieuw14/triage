<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('age');
            $table->string('sex');
            $table->unsignedSmallInteger('sbp');
            $table->unsignedSmallInteger('dbp');
            $table->unsignedSmallInteger('hr');
            $table->unsignedSmallInteger('rr');
            $table->decimal('temp', 4, 1);
            $table->unsignedTinyInteger('pain_score');
            $table->string('chief_complaint');
            $table->unsignedTinyInteger('ats_category');
            $table->decimal('confidence', 5, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
