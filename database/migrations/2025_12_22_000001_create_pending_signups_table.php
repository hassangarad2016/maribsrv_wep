<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pending_signups', function (Blueprint $table) {
            $table->id();
            $table->string('mobile')->index();
            $table->string('normalized_mobile')->index();
            $table->string('country_code')->nullable();
            $table->string('firebase_id')->nullable();
            $table->string('type')->default('phone');
            $table->longText('payload');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_signups');
    }
};
