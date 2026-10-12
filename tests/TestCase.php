<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Serialize cached values like the real stores (database/redis) do, so caching bugs show up in tests.
        config(['cache.stores.array.serialize' => true]);
        app('cache')->forgetDriver('array');
    }

    protected function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsEditor(): User
    {
        $user = User::factory()->create(['role' => 'editor']);
        Sanctum::actingAs($user);

        return $user;
    }

    /** Create a real JPEG on disk for upload tests. */
    protected function fakeJpeg(int $w = 2400, int $h = 1600, string $name = 'photo.jpg'): \Illuminate\Http\UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 40; $i++) {
            imagefilledrectangle($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        imagejpeg($img, $path, 100);

        return new \Illuminate\Http\UploadedFile($path, $name, 'image/jpeg', null, true);
    }
}
