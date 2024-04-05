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
        Schema::create('customer_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\PipelineStage::class)->nullable()->constrained();
            $table->foreignIdFor(\App\Models\Customer::class)->constrained();
            $table->foreignIdFor(App\Models\User::class)->nullable()->constrained();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_pipeline_stages');
    }
};