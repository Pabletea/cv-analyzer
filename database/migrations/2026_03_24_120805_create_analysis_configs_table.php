<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position');
            $table->text('prompt_extra')->nullable();
            $table->json('required_skills')->nullable();
            $table->integer('min_years_experience')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_configs');
    }
};
