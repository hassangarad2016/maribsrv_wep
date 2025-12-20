<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')
                ->after('id')
                ->nullable()
                ->index();
        });

        try {
            DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_store_id_foreign FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL');
        } catch (Throwable $exception) {
            // Ignore FK creation failures on hosts that reject cross-table constraints.
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
            try {
                $table->dropForeign(['store_id']);
            } catch (Throwable $exception) {
                // FK may not exist; ignore.
            }

            try {
                $table->dropIndex('coupons_store_id_index');
            } catch (Throwable $exception) {
                // Index may not exist; ignore.
            }

            $table->dropColumn('store_id');
        });
    }
};
