<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(['email' => 'alice@example.com'], ['name' => 'Alice Secret', 'password' => bcrypt('password')]);

        foreach (range(1, 12) as $i) {
            Post::query()->updateOrCreate(['slug' => "post-{$i}"], [
                'title' => "Post number {$i}",
                'body' => "Public content of post {$i}.",
            ]);
        }
    }
}
