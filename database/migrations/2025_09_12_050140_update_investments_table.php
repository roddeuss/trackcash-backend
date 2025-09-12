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
        Schema::table('investments', function (Blueprint $table) {
            // Hapus kolom yang tidak dipakai lagi
            if (Schema::hasColumn('investments', 'current_price_per_unit')) {
                $table->dropColumn('current_price_per_unit');
            }

            // Pastikan kolom yang sesuai model ada
            if (!Schema::hasColumn('investments', 'units')) {
                $table->decimal('units', 20, 8)->after('asset_id');
            }
            if (!Schema::hasColumn('investments', 'buy_price_per_unit')) {
                $table->decimal('buy_price_per_unit', 15, 2)->after('units');
            }
            if (!Schema::hasColumn('investments', 'buy_date')) {
                $table->date('buy_date')->after('buy_price_per_unit');
            }
            if (!Schema::hasColumn('investments', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('buy_date');
            }
            if (!Schema::hasColumn('investments', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('investments', 'deleted')) {
                $table->boolean('deleted')->default(false)->after('updated_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // Rollback: tambahkan lagi kolom yang dihapus
            if (!Schema::hasColumn('investments', 'current_price_per_unit')) {
                $table->decimal('current_price_per_unit', 15, 2)->nullable()->after('buy_date');
            }

            // Drop tambahan update
            if (Schema::hasColumn('investments', 'units')) {
                $table->dropColumn('units');
            }
            if (Schema::hasColumn('investments', 'buy_price_per_unit')) {
                $table->dropColumn('buy_price_per_unit');
            }
            if (Schema::hasColumn('investments', 'buy_date')) {
                $table->dropColumn('buy_date');
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
        });
    }
};
