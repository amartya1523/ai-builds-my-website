<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            // phone | email | address | hours | about | service | price_item | tagline | social_link | website | ...
            $table->string('field');
            $table->text('value');
            // Exact text from the source that supports the value.
            $table->text('evidence')->nullable();
            // candidate | accepted | rejected
            $table->string('status')->default('candidate');
            $table->string('origin')->default('parser'); // parser | ai | owner
            $table->timestamps();

            $table->index(['site_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facts');
    }
};
