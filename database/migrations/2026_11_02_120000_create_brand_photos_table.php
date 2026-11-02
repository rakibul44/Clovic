<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_photos', function (Blueprint $t) {
            $t->id();
            $t->string('path');
            $t->string('source_key')->nullable()->unique();
            $t->string('source_url', 500)->nullable();
            $t->string('alt', 200);
            $t->string('caption', 500)->nullable();
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->boolean('published')->default(false);
            $t->unsignedInteger('position')->default(0);
            $t->index(['published', 'position']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_photos');
    }
};
