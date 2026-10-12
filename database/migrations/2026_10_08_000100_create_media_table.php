<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32)->default('public');
            $table->string('folder', 40)->index();            // pages, posts, galleries, gallery-photos, users, general, brands
            $table->string('path')->unique();                  // uploads/posts/2026/10/abc.webp
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('original_size_bytes')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->json('variants')->nullable();              // {"thumb": "...", "medium": "..."}
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->string('wp_url', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Old WordPress image URL lookup (legacy /wp-content/uploads/... links). On MySQL/MariaDB use a
        // 191-char prefix index so it also works with older row formats (767-byte key limit).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('CREATE INDEX media_wp_url_index ON media (wp_url(191))');
        } else {
            Schema::table('media', fn (Blueprint $table) => $table->index('wp_url'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
