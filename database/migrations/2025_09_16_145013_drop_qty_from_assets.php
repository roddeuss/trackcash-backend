<?php

// database/migrations/XXXX_drop_quantity_from_assets.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'quantity')) {
                $table->decimal('quantity', 20, 8)->default(0)->after('asset_name');
            }
        });
    }
};
