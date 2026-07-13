<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Generate slugs for existing workflows
        $workflows = DB::table('workflows')->get();
        foreach ($workflows as $workflow) {
            $baseSlug = Str::slug($workflow->name ?: 'workflow');
            $slug = $baseSlug;
            $counter = 1;

            // Ensure unique slug per tenant
            while (DB::table('workflows')->where('tenant_id', $workflow->tenant_id)->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            DB::table('workflows')->where('id', $workflow->id)->update(['slug' => $slug]);
        }

        // Now that all rows have a slug, we can make it unique per tenant
        Schema::table('workflows', function (Blueprint $table) {
            $table->unique(['tenant_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
