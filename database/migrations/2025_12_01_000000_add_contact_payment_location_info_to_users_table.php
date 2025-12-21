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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'additional_contacts')) {
                $table->json('additional_contacts')->nullable()->after('mobile');
            }

            if (! Schema::hasColumn('users', 'payment_info')) {
                $table->json('payment_info')->nullable()->after('address');
            }

            if (! Schema::hasColumn('users', 'location')) {
                $table->string('location')->nullable()->after('address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'additional_contacts')) {
                $table->dropColumn('additional_contacts');
            }
            if (Schema::hasColumn('users', 'payment_info')) {
                $table->dropColumn('payment_info');
            }
            if (Schema::hasColumn('users', 'location')) {
                $table->dropColumn('location');
            }
        });
    }
};
