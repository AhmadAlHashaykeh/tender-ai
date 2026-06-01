<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('quantity_unit');
            $table->decimal('market_calculated_price', 18, 6)->nullable()->after('discount_percentage');
            $table->decimal('final_recommended_price', 18, 6)->nullable()->after('market_calculated_price');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn([
                'discount_percentage',
                'market_calculated_price',
                'final_recommended_price',
            ]);
        });
    }
};
