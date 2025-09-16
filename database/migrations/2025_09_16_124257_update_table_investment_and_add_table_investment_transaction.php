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
        /**
         * Update tabel investments
         */
        Schema::table('investments', function (Blueprint $table) {
            if (Schema::hasColumn('investments', 'current_price_per_unit')) {
                $table->dropColumn('current_price_per_unit');
            }

            if (!Schema::hasColumn('investments', 'units')) {
                $table->decimal('units', 20, 8)->after('asset_id');
            }
            if (!Schema::hasColumn('investments', 'average_buy_price')) {
                $table->decimal('average_buy_price', 15, 2)->after('units');
            }
            if (!Schema::hasColumn('investments', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('average_buy_price');
            }
            if (!Schema::hasColumn('investments', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('investments', 'deleted')) {
                $table->boolean('deleted')->default(false)->after('updated_by');
            }
        });

        /**
         * Buat tabel investment_transactions
         */
        Schema::create('investment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('investment_id');
            $table->unsignedBigInteger('transaction_id'); // relasi ke tabel transactions
            $table->enum('type', ['buy', 'sell']);
            $table->decimal('units', 20, 8);
            $table->decimal('price_per_unit', 15, 2);
            $table->dateTime('transaction_date');

            // Audit trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('deleted')->default(false);

            $table->timestamps();

            // Foreign keys
            $table->foreign('investment_id')->references('id')->on('investments')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_transactions');

        Schema::table('investments', function (Blueprint $table) {
            if (Schema::hasColumn('investments', 'units')) {
                $table->dropColumn('units');
            }
            if (Schema::hasColumn('investments', 'average_buy_price')) {
                $table->dropColumn('average_buy_price');
            }
            if (Schema::hasColumn('investments', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('investments', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('investments', 'deleted')) {
                $table->dropColumn('deleted');
            }

            // rollback kolom current_price_per_unit jika sebelumnya ada
            if (!Schema::hasColumn('investments', 'current_price_per_unit')) {
                $table->decimal('current_price_per_unit', 15, 2)->nullable()->after('asset_id');
            }
        });
    }
};
