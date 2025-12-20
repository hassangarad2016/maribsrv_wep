<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        if (! Schema::hasColumn('services', 'expiry_date')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->date('expiry_date')->nullable()->after('views');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        if (Schema::hasColumn('services', 'expiry_date')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->dropColumn('expiry_date');
            });
        }
    }
};
