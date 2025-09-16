<?php

// database/migrations/xxxx_add_lot_size_to_assets.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'lot_size')) {
                $table->unsignedInteger('lot_size')->default(1)->after('asset_name');
            }
        });
    }
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'lot_size')) {
                $table->dropColumn('lot_size');
            }
        });
    }
};
