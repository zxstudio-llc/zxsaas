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
        Schema::create('adjustmentables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('adjustment_id');   // No foreign key constraint due to polymorphism
            $table->string('adjustment_type');             // Type of adjustment (e.g., "App\Models\Tax" or "App\Models\Discount")
            $table->morphs('adjustmentable');              // Creates adjustmentable_id and adjustmentable_type
            $table->timestamps();

            // Optional indexes for efficient querying
            $table->index(['adjustment_id', 'adjustment_type']);
            $table->index(['adjustmentable_id', 'adjustmentable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustmentables');
    }
};
