<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Brings already-seeded databases in line with the live Contact page and footer.
 * Only touches values nobody has edited yet (empty note / original seeded copyright text).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('settings')) {
            return;
        }
        $set = function (string $key, array $from, string $to) {
            $row = DB::table('settings')->where('key', $key)->first();
            if ($row && in_array(json_decode((string) $row->value, true), $from, true)) {
                DB::table('settings')->where('key', $key)->update(['value' => json_encode($to, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            }
        };
        $set('hours_note', ['', null], '(except for the last Monday of the month)');
        $set('copyright_text', ['Copyright © {year} Finns Kerrisdale Ladies. All Rights Reserved.'], 'Copyright © 2015 Finns Kerrisdale Ladies. All Rights Reserved.');
        Cache::forget('settings.all');
    }

    public function down(): void
    {
        // data-only change; nothing to undo
    }
};
