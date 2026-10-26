<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $t) {
            $t->string('token', 64)->primary();
            $t->string('owner', 64);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
    }
};
