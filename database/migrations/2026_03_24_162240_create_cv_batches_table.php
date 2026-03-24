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
        Schema::create('cv_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->nullable()->constrained('analysis_configs')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, processing, completed
            $table->integer('total')->default(0);
            $table->integer('processed')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_batches');
    }
};
