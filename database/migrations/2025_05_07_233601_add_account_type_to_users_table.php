<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // تعريف أنواع الحسابات:
            // 1 = عميل (customer)
            // 2 = تاجر (seller)
            // 3 = مسوق (marketer)
            // null = غير محدد
            if (! Schema::hasColumn('users', 'account_type')) {
                // 1 = Customer, 2 = Seller, 3 = Marketer, null = undefined
                $table->tinyInteger('account_type')->nullable()->after('email');
            }
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'account_type')) {
                $table->dropColumn('account_type');
            }
        });
    }
};
