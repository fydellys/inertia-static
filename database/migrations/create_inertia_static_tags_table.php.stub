<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('inertia-static.registry.table', 'inertia_static_tags'), function (Blueprint $table) {
            $table->id();
            $table->string('tag', 1024);
            $table->char('tag_hash', 32)->index();
            $table->text('url');
            $table->char('url_hash', 32);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['tag_hash', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('inertia-static.registry.table', 'inertia_static_tags'));
    }
};
