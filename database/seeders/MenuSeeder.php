<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        if (MenuItem::exists()) {
            return;
        }
        // Same order as the live site's header menu ("Lastest News" typo fixed).
        foreach ([['Home', '/'], ['About Us', '/about/'], ['Designer', '/designer/'], ['Latest News', '/blog/'], ['Contact Us', '/contact/']] as $i => [$label, $url]) {
            MenuItem::create(['location' => 'header', 'label' => $label, 'url' => $url, 'sort_order' => $i]);
        }
    }
}
