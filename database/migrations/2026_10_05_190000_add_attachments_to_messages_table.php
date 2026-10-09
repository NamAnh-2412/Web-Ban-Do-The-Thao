<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('attachment_type', 20)->nullable()->after('body');
            $table->unsignedBigInteger('attachment_id')->nullable()->after('attachment_type');
            $table->json('attachment_payload')->nullable()->after('attachment_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_type', 'attachment_id', 'attachment_payload']);
        });
    }
};
