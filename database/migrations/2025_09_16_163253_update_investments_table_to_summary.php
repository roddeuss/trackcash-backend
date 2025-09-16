<?php

// database/migrations/2025_09_16_000001_update_investments_table_to_summary.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // Tambahkan kolom baru bila belum ada
            if (!Schema::hasColumn('investments', 'average_buy_price')) {
                // simpan 20,8 agar fleksibel (saham/crypto)
                $table->decimal('average_buy_price', 20, 8)->default(0)->after('units');
            }

            // Hapus kolom lama yang tidak dipakai (penyebab not null violation)
            if (Schema::hasColumn('investments', 'buy_price_per_unit')) {
                $table->dropColumn('buy_price_per_unit');
            }
            if (Schema::hasColumn('investments', 'buy_date')) {
                $table->dropColumn('buy_date');
            }
            if (Schema::hasColumn('investments', 'current_price_per_unit')) {
                $table->dropColumn('current_price_per_unit');
            }

            // Pastikan kolom units ada (kalau belum)
            if (!Schema::hasColumn('investments', 'units')) {
                $table->decimal('units', 20, 8)->default(0)->after('asset_id');
            }

            // Pastikan kolom audit & soft delete ada
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

        // (Opsional) tambah FK jika mau, dan kolomnya sudah ada:
        // Schema::table('investments', function (Blueprint $table) {
        //     $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        //     $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        // });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // Kembalikan kolom lama (optional)
            if (!Schema::hasColumn('investments', 'buy_price_per_unit')) {
                $table->decimal('buy_price_per_unit', 15, 2)->nullable()->after('units');
            }
            if (!Schema::hasColumn('investments', 'buy_date')) {
                $table->date('buy_date')->nullable()->after('buy_price_per_unit');
            }
            if (!Schema::hasColumn('investments', 'current_price_per_unit')) {
                $table->decimal('current_price_per_unit', 15, 2)->nullable()->after('buy_date');
            }

            // Hapus kolom baru
            if (Schema::hasColumn('investments', 'average_buy_price')) {
                $table->dropColumn('average_buy_price');
            }
        });
    }
};
