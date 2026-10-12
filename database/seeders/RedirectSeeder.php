<?php

namespace Database\Seeders;

use App\Models\Redirect;
use Illuminate\Database\Seeder;

/** WordPress URLs that have no direct equivalent in the new site. Content URLs keep their exact paths. */
class RedirectSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['/sample-page/', '/', 301],
            ['/category/uncategorized/', '/blog/', 301],
            ['/category/uncategorized/page/2/', '/blog/', 301],
            ['/category/uncategorized/page/3/', '/blog/', 301],
            ['/category/blog/', '/blog/', 301],
            ['/author/admin/', '/blog/', 301],
            ['/author/finnsadmin/', '/blog/', 301],
            ['/comments/feed/', '/feed', 301],
            ['/home/', '/', 301],
        ];
        foreach ($rules as [$from, $to, $code]) {
            Redirect::firstOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => $code]);
        }
    }
}
