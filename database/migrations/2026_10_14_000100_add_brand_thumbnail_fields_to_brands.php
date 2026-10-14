<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('brand_thumbnail_media_id')->nullable()->after('logo_url')->constrained('media')->nullOnDelete();
            $table->string('brand_thumbnail_url')->nullable()->after('brand_thumbnail_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_thumbnail_media_id');
            $table->dropColumn('brand_thumbnail_url');
        });
    }
};
