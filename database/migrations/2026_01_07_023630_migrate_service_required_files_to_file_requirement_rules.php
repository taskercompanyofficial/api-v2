<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ServiceRequiredFile;
use App\Models\FileRequirementRule;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration transfers existing data from the old service_required_files table
     * to the new file_requirement_rules table. The new table is more flexible and context-aware.
     */
    public function up(): void
    {
        // Migrate existing data from service_required_files to file_requirement_rules
        $oldRules = ServiceRequiredFile::with(['parentService', 'fileType'])->get();

        foreach ($oldRules as $oldRule) {
            // Create equivalent rule in new table
            FileRequirementRule::create([
                'name' => "Migrated: {$oldRule->fileType->name} for {$oldRule->parentService->name}",
                'description' => "Auto-migrated from old service_required_files table",
                'parent_service_id' => $oldRule->parent_service_id,
                'file_type_id' => $oldRule->file_type_id,
                'requirement_type' => $oldRule->is_required ? 'required' : 'optional',
                'display_order' => $oldRule->id,
                'priority' => 50, // Medium priority for legacy rules
                'is_active' => true
            ]);
        }

        // Log migration
        Log::info("Migrated " . $oldRules->count() . " service required files to file requirement rules");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated rules (identified by name prefix)
        FileRequirementRule::where('name', 'like', 'Migrated:%')->delete();
    }
};
