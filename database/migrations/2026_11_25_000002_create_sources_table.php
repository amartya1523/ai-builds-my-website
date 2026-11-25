<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // file | website | social | google_business | web_search | directory | owner_note
            $table->string('kind');
            $table->string('label');
            $table->text('url')->nullable();
            $table->string('path')->nullable();
            $table->string('mime')->nullable();
            // pending | ok | blocked | failed | skipped
            $table->string('status')->default('pending');
            $table->text('status_note')->nullable();
            $table->longText('content')->nullable();
            $table->float('injection_score')->nullable();
            $table->boolean('injection_flagged')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
