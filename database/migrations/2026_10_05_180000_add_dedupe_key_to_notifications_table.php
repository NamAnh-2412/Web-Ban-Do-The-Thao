<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('dedupe_key')->nullable()->after('payload');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('UPDATE notifications SET dedupe_key = JSON_UNQUOTE(JSON_EXTRACT(payload, \'$.dedupe_key\')) WHERE payload IS NOT NULL');
        } else {
            foreach (DB::table('notifications')->whereNotNull('payload')->cursor() as $row) {
                $payload = json_decode((string) $row->payload, true);
                $key = is_array($payload) ? ($payload['dedupe_key'] ?? null) : null;
                if (is_string($key) && $key !== '') {
                    DB::table('notifications')->where('id', $row->id)->update(['dedupe_key' => $key]);
                }
            }
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->unique('dedupe_key');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropColumn('dedupe_key');
        });
    }
};
