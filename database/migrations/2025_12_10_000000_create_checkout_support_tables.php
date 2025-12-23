<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: 'coupons' table is created in 2024_11_20_000000_create_coupon_support_tables.php
        // Removed duplicate creation to avoid conflicts

        if (!Schema::hasTable('department_notices')) {
            Schema::create('department_notices', function (Blueprint $table) {
                $table->id();
                $table->string('department');
                $table->string('title');
                $table->text('body');
                $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('shipping_overrides')) {
            Schema::create('shipping_overrides', function (Blueprint $table) {
                $table->id();
                $table->string('scope_type')->nullable();
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('department')->nullable();
                $table->string('region')->nullable();
                $table->decimal('delivery_fee', 10, 2);
                $table->decimal('delivery_surcharge', 10, 2)->default(0);
                $table->decimal('delivery_discount', 10, 2)->default(0);
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();

                $table->index(['scope_type', 'scope_id']);
                $table->index(['department', 'region']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_overrides');
        Schema::dropIfExists('department_notices');
        // Note: 'coupons' table is managed in 2024_11_20_000000_create_coupon_support_tables.php
    }
};