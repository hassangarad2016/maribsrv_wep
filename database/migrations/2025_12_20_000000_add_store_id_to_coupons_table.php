<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        if (Schema::hasColumn('coupons', 'store_id')) {
            return;
        }

        // Add column first
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')
                ->after('id')
                ->nullable();
        });
        
        // Add FK only if stores table exists
        if (Schema::hasTable('stores')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->foreign('store_id')
                    ->references('id')
                    ->on('stores')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        if (! Schema::hasColumn('coupons', 'store_id')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
