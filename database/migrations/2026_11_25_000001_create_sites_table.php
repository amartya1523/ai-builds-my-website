<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('business_name');
            $table->string('city')->nullable();
            // draft -> needs_input -> ready -> published
            $table->string('status')->default('draft');
            $table->string('theme')->default('classic');
            $table->string('accent_color', 9)->default('#0f766e');
            // The real-world listing the owner confirmed as theirs (when the name is shared by several businesses).
            $table->json('identity')->nullable();
            // ok | fallback | error  (fallback = AI was down, template copy used)
            $table->string('ai_status')->nullable();
            $table->text('ai_error')->nullable();
            $table->longText('published_html')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
