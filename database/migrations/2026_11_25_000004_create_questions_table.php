<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // choose_identity | resolve_conflict | missing | confirm
            $table->string('kind');
            $table->string('field')->nullable();
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->boolean('blocking')->default(true);
            $table->text('answer')->nullable();
            // open | answered | skipped
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
