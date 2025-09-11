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

            // Tambah kolom type_id
            $table->foreignId('type_id')
                ->nullable()
                ->after('id')
                ->constrained('types')
                ->onDelete('cascade');

            // Tambah kolom value dan current_value
            $table->decimal('value', 15, 2)->default(0)->after('asset_name');
            $table->decimal('current_value', 15, 2)->default(0)->after('value');
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

            if (Schema::hasColumn('assets', 'value')) {
                $table->dropColumn('value');
            }

            if (Schema::hasColumn('assets', 'current_value')) {
                $table->dropColumn('current_value');
            }

            // Kembalikan asset_type
            $table->string('asset_type')->nullable()->after('id');
        });
    }
};
