<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_signups', function (Blueprint $table) {
            $table->id();
            $table->string('mobile')->nullable();
            $table->string('normalized_mobile')->unique();
            $table->string('country_code')->nullable();
            $table->string('firebase_id')->nullable();
            $table->string('type')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_signups');
    }
};
