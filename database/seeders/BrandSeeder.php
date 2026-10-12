<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // Logos currently live in the WordPress theme folder (not the media library).
        $t = 'https://finnskerrisdaleladies.com/wp-content/themes/wp_nuvo/images/designer/';
        $brands = [
            ['Luisa Cerano', 'Luisa Cerano Brand http://www.luisacerano.com', 'http://www.luisacerano.com/', $t.'Tag_cerano.jpg', true],
            ['Riani', 'Riani Brand Vancouver http://www.riani.de/', 'http://www.riani.de/', $t.'Tag_riani.jpg', true],
            ['Cambio', null, 'http://www.cambio.de', $t.'Tag_cambio.jpg', true],
            ['Twin-Set', 'Twin Set http://www.twin-set.it/', 'http://www.twin-set.it', $t.'Tag_twinset.jpg', true],
            // Named on the site but without a logo tile today — hidden until a logo is uploaded
            ['Save The Queen!', null, null, null, false],
            ['Laurèl', null, null, null, false],
        ];
        foreach ($brands as $i => [$name, $label, $url, $logo, $active]) {
            Brand::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], [
                'name' => $name, 'label' => $label, 'website_url' => $url, 'logo_url' => $logo, 'sort_order' => $i, 'is_active' => $active,
            ]);
        }
    }
}
