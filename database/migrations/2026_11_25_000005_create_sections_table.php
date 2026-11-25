<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // hero | about | services | pricing | gallery | hours | contact | social
            $table->string('type');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('visible')->default(true);
            $table->json('content');
            // ai | template | owner
            $table->string('written_by')->default('ai');
            // Sentences the claim guard removed because no source supported them.
            $table->json('removed_claims')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
