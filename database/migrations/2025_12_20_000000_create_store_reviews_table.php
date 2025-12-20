<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'user_id']);
            $table->index(['store_id', 'rating']);
            $table->index('user_id');
        });

        try {
            DB::statement('ALTER TABLE store_reviews ADD CONSTRAINT store_reviews_store_id_foreign FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE store_reviews ADD CONSTRAINT store_reviews_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
        } catch (Throwable $exception) {
            // Ignore FK creation failures on hosts that reject cross-table constraints.
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_reviews')) {
            try {
                DB::statement('ALTER TABLE store_reviews DROP FOREIGN KEY store_reviews_store_id_foreign');
            } catch (Throwable $exception) {
                // FK may not exist; ignore.
            }

            try {
                DB::statement('ALTER TABLE store_reviews DROP FOREIGN KEY store_reviews_user_id_foreign');
            } catch (Throwable $exception) {
                // FK may not exist; ignore.
            }
        }

        Schema::dropIfExists('store_reviews');
    }
};
