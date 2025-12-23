<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip for SQLite (testing environments)
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // 1. Add unique constraint to users.email if not exists
        if (!$this->hasIndex('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email', 'users_email_unique');
            });
        }

        // 2. Convert float/double columns to decimal for monetary values
        // This ensures financial accuracy and prevents rounding errors
        
        // items table
        if (Schema::hasTable('items')) {
            DB::statement('ALTER TABLE items MODIFY price DECIMAL(12, 2)');
        }

        // packages table
        if (Schema::hasTable('packages')) {
            DB::statement('ALTER TABLE packages MODIFY price DECIMAL(12, 2)');
            DB::statement('ALTER TABLE packages MODIFY final_price DECIMAL(12, 2)');
            // discount_in_percentage can stay as float (it's a percentage, not money)
        }

        // item_offers table
        if (Schema::hasTable('item_offers')) {
            DB::statement('ALTER TABLE item_offers MODIFY amount DECIMAL(12, 2)');
        }

        // payment_transactions table
        if (Schema::hasTable('payment_transactions')) {
            DB::statement('ALTER TABLE payment_transactions MODIFY amount DECIMAL(12, 2)');
        }

        // seller_ratings table - ratings should be decimal for precision
        if (Schema::hasTable('seller_ratings')) {
            DB::statement('ALTER TABLE seller_ratings MODIFY ratings DECIMAL(3, 2)');
        }

        // 3. Fix verification_fields enum values
        // Remove inappropriate 'sold out' and 'featured' values
        if (Schema::hasTable('verification_fields')) {
            try {
                DB::statement("ALTER TABLE verification_fields 
                    MODIFY status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
            } catch (\Exception $e) {
                // If there are existing rows with 'sold out' or 'featured', update them first
                DB::table('verification_fields')
                    ->whereIn('status', ['sold out', 'featured', 'review'])
                    ->update(['status' => 'pending']);
                
                // Try again
                DB::statement("ALTER TABLE verification_fields 
                    MODIFY status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // 1. Remove unique constraint from users.email
        if ($this->hasIndex('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        }

        // 2. Revert decimal back to float/double (not recommended for production)
        if (Schema::hasTable('items')) {
            DB::statement('ALTER TABLE items MODIFY price DOUBLE');
        }

        if (Schema::hasTable('packages')) {
            DB::statement('ALTER TABLE packages MODIFY price FLOAT');
            DB::statement('ALTER TABLE packages MODIFY final_price FLOAT');
        }

        if (Schema::hasTable('item_offers')) {
            DB::statement('ALTER TABLE item_offers MODIFY amount FLOAT');
        }

        if (Schema::hasTable('payment_transactions')) {
            DB::statement('ALTER TABLE payment_transactions MODIFY amount DOUBLE(8, 2)');
        }

        if (Schema::hasTable('seller_ratings')) {
            DB::statement('ALTER TABLE seller_ratings MODIFY ratings FLOAT');
        }

        // 3. Revert verification_fields enum
        if (Schema::hasTable('verification_fields')) {
            DB::statement("ALTER TABLE verification_fields 
                MODIFY status ENUM('review', 'approved', 'rejected', 'sold out', 'featured') DEFAULT 'review'");
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $result = DB::selectOne(
            "SELECT 1 FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1",
            [$db, $table, $index]
        );
        return (bool) $result;
    }
};
