<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        $legacyUniqueExists = $this->indexExists('cart_items', 'cart_items_user_item_variant_department_unique');
        $newUniqueExists = $this->indexExists('cart_items', 'cart_items_user_item_variantkey_department_unique');
        $userForeignExists = $this->foreignKeyExists('cart_items', 'cart_items_user_id_foreign');
        $itemForeignExists = $this->foreignKeyExists('cart_items', 'cart_items_item_id_foreign');

        Schema::table('cart_items', static function (Blueprint $table) {
            if (! Schema::hasColumn('cart_items', 'variant_key')) {
                $table->string('variant_key', 512)->default('')->after('variant_id');
            }
        });

        DB::table('cart_items')->update(['variant_key' => DB::raw("COALESCE(variant_key, '')")]);

        Schema::table('cart_items', function (Blueprint $table) use ($isSqlite, $legacyUniqueExists, $newUniqueExists, $userForeignExists, $itemForeignExists) {
            if (! $isSqlite && Schema::hasColumn('cart_items', 'user_id') && $userForeignExists) {
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $exception) {
                    // ignore missing foreign key
                }
            }

            if (! $isSqlite && Schema::hasColumn('cart_items', 'item_id') && $itemForeignExists) {
                try {
                    $table->dropForeign(['item_id']);
                } catch (\Throwable $exception) {
                    // ignore missing foreign key
                }
            }


            if ($legacyUniqueExists) {
                try {
                    $table->dropUnique('cart_items_user_item_variant_department_unique');
                } catch (\Throwable $exception) {
                    // ignore missing index
                }
            }

            if (! $newUniqueExists) {
                $table->unique(['user_id', 'item_id', 'variant_key', 'department'], 'cart_items_user_item_variantkey_department_unique');
            }

            if (Schema::hasColumn('cart_items', 'user_id')) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }

            if (Schema::hasColumn('cart_items', 'item_id')) {
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            }

        });
    }

    public function down(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        $legacyUniqueExists = $this->indexExists('cart_items', 'cart_items_user_item_variant_department_unique');
        $newUniqueExists = $this->indexExists('cart_items', 'cart_items_user_item_variantkey_department_unique');
        $userForeignExists = $this->foreignKeyExists('cart_items', 'cart_items_user_id_foreign');
        $itemForeignExists = $this->foreignKeyExists('cart_items', 'cart_items_item_id_foreign');

        Schema::table('cart_items', function (Blueprint $table) use ($isSqlite, $legacyUniqueExists, $newUniqueExists, $userForeignExists, $itemForeignExists) {

            if (! $isSqlite && Schema::hasColumn('cart_items', 'user_id') && $userForeignExists) {
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $exception) {
                    // ignore missing foreign key
                }
            }

            if (! $isSqlite && Schema::hasColumn('cart_items', 'item_id') && $itemForeignExists) {
                try {
                    $table->dropForeign(['item_id']);
                } catch (\Throwable $exception) {
                    // ignore missing foreign key
                }
            }

            if ($newUniqueExists) {
                try {
                    $table->dropUnique('cart_items_user_item_variantkey_department_unique');
                } catch (\Throwable $exception) {
                    // ignore missing index
                }
            }

            if (Schema::hasColumn('cart_items', 'variant_key')) {
                $table->dropColumn('variant_key');
            }

            if (! $legacyUniqueExists) {
                $table->unique(['user_id', 'item_id', 'variant_id', 'department'], 'cart_items_user_item_variant_department_unique');
            }

            if (Schema::hasColumn('cart_items', 'user_id')) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }

            if (Schema::hasColumn('cart_items', 'item_id')) {
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            }

        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return false;
        }

        try {
            $prefixedTable = $connection->getTablePrefix() . $table;
            $sql = sprintf('SHOW INDEX FROM `%s` WHERE Key_name = ?', $prefixedTable);
            $result = $connection->select($sql, [$index]);

            return ! empty($result);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return false;
        }

        try {
            $prefixedTable = $connection->getTablePrefix() . $table;
            $database = $connection->getDatabaseName();
            $sql = 'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?';
            $result = $connection->select($sql, [$database, $prefixedTable, $foreignKey, 'FOREIGN KEY']);

            return ! empty($result);
        } catch (\Throwable $exception) {
            return false;
        }
    }
};
