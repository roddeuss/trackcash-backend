<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Hapus kolom lama asset_type
            if (Schema::hasColumn('assets', 'asset_type')) {
                $table->dropColumn('asset_type');
            }

            // Hapus kolom value dan current_value jika ada
            if (Schema::hasColumn('assets', 'value')) {
                $table->dropColumn('value');
            }
            if (Schema::hasColumn('assets', 'current_value')) {
                $table->dropColumn('current_value');
            }

            // Tambah kolom type_id
            if (!Schema::hasColumn('assets', 'type_id')) {
                $table->foreignId('type_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('types')
                    ->onDelete('cascade');
            }

            // Tambah kolom quantity
            if (!Schema::hasColumn('assets', 'quantity')) {
                $table->decimal('quantity', 20, 8)->default(0)->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Rollback: hapus kolom baru
            if (Schema::hasColumn('assets', 'type_id')) {
                $table->dropForeign(['type_id']);
                $table->dropColumn('type_id');
            }
            if (Schema::hasColumn('assets', 'quantity')) {
                $table->dropColumn('quantity');
            }

            // Kembalikan kolom lama
            if (!Schema::hasColumn('assets', 'asset_type')) {
                $table->string('asset_type')->nullable()->after('id');
            }
            if (!Schema::hasColumn('assets', 'value')) {
                $table->decimal('value', 15, 2)->default(0)->after('asset_name');
            }
            if (!Schema::hasColumn('assets', 'current_value')) {
                $table->decimal('current_value', 15, 2)->default(0)->after('value');
            }
        });
    }
};
