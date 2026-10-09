<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sports', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('slug');
            $table->string('headline')->nullable()->after('image_url');
            $table->text('description')->nullable()->after('headline');
        });
    }

    public function down(): void
    {
        Schema::table('sports', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'headline', 'description']);
        });
    }
};
