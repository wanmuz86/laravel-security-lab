<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab 10: the Customer Document Management API reuses the Lab 5 "documents" table and adds the columns
 * the API exposes: a customer-facing title plus internal, staff-only fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('title')->nullable()->after('user_id');
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->boolean('is_approved')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['title', 'internal_notes', 'is_approved']);
        });
    }
};
