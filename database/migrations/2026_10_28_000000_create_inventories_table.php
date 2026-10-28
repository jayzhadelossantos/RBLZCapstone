<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('available_quantity')->default(0);
            $table->timestamps();
        });

        DB::table('properties')
            ->where('quantity', '>', 0)
            ->whereNotIn('status', ['disposed', 'for_disposal'])
            ->orderBy('id')
            ->eachById(function (object $property): void {
                DB::table('inventories')->insert([
                    'property_id' => $property->id,
                    'available_quantity' => $property->quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('property_requests', function (Blueprint $table) {
            $table->foreignId('inventory_id')->nullable()->after('selected_property_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('property_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_id');
        });
        Schema::dropIfExists('inventories');
    }
};
