<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_requests', function (Blueprint $table) {
            $table->string('reference_no', 40)->nullable()->unique()->after('id');
        });

        Schema::create('property_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_request_id')->constrained('property_requests')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->string('requested_item_name');
            $table->unsignedInteger('requested_quantity')->default(1);
            $table->foreignId('approved_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_request_id', 'id']);
        });

        DB::table('property_requests')->orderBy('id')->each(function (object $request): void {
            $propertyId = $request->selected_property_id;

            if (! $propertyId && $request->inventory_id) {
                $propertyId = DB::table('inventories')->where('id', $request->inventory_id)->value('property_id');
            }

            if (! $propertyId && $request->replacement_assignment_id) {
                $propertyId = DB::table('assignments')->where('id', $request->replacement_assignment_id)->value('property_id');
            }

            DB::table('property_request_items')->insert([
                'property_request_id' => $request->id,
                'property_id' => $propertyId,
                'inventory_id' => $request->inventory_id,
                'requested_item_name' => $request->requested_item_name,
                'requested_quantity' => max(1, (int) $request->requested_quantity),
                'approved_property_id' => $request->selected_property_id,
                'assignment_id' => $request->assignment_id,
                'created_at' => $request->created_at ?? now(),
                'updated_at' => $request->updated_at ?? now(),
            ]);

            $createdAt = $request->created_at ? \Illuminate\Support\Carbon::parse($request->created_at) : now();
            DB::table('property_requests')->where('id', $request->id)->update([
                'reference_no' => 'PR-'.$createdAt->format('Y').'-'.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_request_items');

        Schema::table('property_requests', function (Blueprint $table) {
            $table->dropUnique(['reference_no']);
            $table->dropColumn('reference_no');
        });
    }
};
