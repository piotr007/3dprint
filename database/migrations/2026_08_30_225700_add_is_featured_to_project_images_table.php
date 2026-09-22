<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: fresh databases already get this column from the create migration.
        if (Schema::hasColumn('project_images', 'is_featured')) {
            return;
        }

        Schema::table('project_images', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('alt');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('project_images', 'is_featured')) {
            return;
        }

        Schema::table('project_images', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
