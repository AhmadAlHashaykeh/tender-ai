<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standardized_drugs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->index();
            $table->string('inn')->nullable()->index();
            $table->string('display_name');
            $table->string('product_name_normalized')->nullable();
            $table->string('dosage')->nullable();
            $table->string('form')->nullable();
            $table->string('strength')->nullable();
            $table->string('strength_unit')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index(['inn', 'is_active']);
        });

        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standardized_drug_id')->nullable()->constrained('standardized_drugs')->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('inn')->nullable();
            $table->string('product_name')->nullable();
            $table->string('raw_display_name')->nullable();
            $table->string('source')->default('import');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('standardized_drug_id');
        });

        Schema::create('drug_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standardized_drug_id')->constrained('standardized_drugs')->cascadeOnDelete();
            $table->string('alias_value');
            $table->string('normalized_alias')->index();
            $table->string('alias_type')->default('product_name');
            $table->string('source')->default('import');
            $table->decimal('confidence', 5, 2)->default(0);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['standardized_drug_id', 'normalized_alias']);
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('import');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('company_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('alias_value');
            $table->string('normalized_alias')->index();
            $table->string('alias_type')->default('legal_name');
            $table->string('source')->default('import');
            $table->decimal('confidence', 5, 2)->default(0);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'normalized_alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_aliases');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('drug_aliases');
        Schema::dropIfExists('drugs');
        Schema::dropIfExists('standardized_drugs');
    }
};
