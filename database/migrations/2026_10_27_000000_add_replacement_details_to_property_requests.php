<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_requests', function (Blueprint $table) {
            $table->string('request_type', 30)->default('new')->after('requested_by');
            $table->foreignId('replacement_assignment_id')->nullable()->after('assignment_id')->constrained('assignments')->nullOnDelete();
            $table->text('issue_description')->nullable()->after('additional_notes');
            $table->index('request_type');
        });
    }

    public function down(): void
    {
        Schema::table('property_requests', function (Blueprint $table) {
            $table->dropIndex(['request_type']);
            $table->dropConstrainedForeignId('replacement_assignment_id');
            $table->dropColumn(['request_type', 'issue_description']);
        });
    }
};
