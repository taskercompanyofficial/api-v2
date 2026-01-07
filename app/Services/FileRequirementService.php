<?php

namespace App\Services;

use App\Models\FileRequirementRule;
use App\Models\ServiceRequiredFile;

class FileRequirementService
{
    /**
     * Get applicable file requirements for given context
     * This method combines rules from both old and new systems for backward compatibility
     * 
     * @param array $context Context containing parent_service_id, service_concern_id, etc.
     * @return array Array of file requirements with their details
     */
    public function getRequirementsForContext(array $context): array
    {
        // Get all active rules matching the context from NEW table
        $rules = FileRequirementRule::active()
            ->forContext($context)
            ->with('fileType')
            ->byPriority()
            ->get();

        // Group by file_type_id, keeping most specific rule for each file type
        $fileRequirements = [];

        foreach ($rules as $rule) {
            $fileTypeId = $rule->file_type_id;

            // If this file type should be hidden, mark it
            if ($rule->requirement_type === 'hidden') {
                $fileRequirements[$fileTypeId] = 'HIDDEN';
                continue;
            }

            // Skip if already marked as hidden
            if (isset($fileRequirements[$fileTypeId]) && $fileRequirements[$fileTypeId] === 'HIDDEN') {
                continue;
            }

            // If already have a rule for this file type, compare specificity
            if (isset($fileRequirements[$fileTypeId])) {
                $existingScore = $fileRequirements[$fileTypeId]['_specificity'];
                $newScore = $rule->getSpecificityScore();

                // Keep more specific rule (higher score wins)
                if ($newScore <= $existingScore) {
                    continue;
                }
            }

            // Store this rule's requirements
            $fileRequirements[$fileTypeId] = [
                'file_type' => $rule->fileType,
                'is_required' => $rule->requirement_type === 'required',
                'is_optional' => $rule->requirement_type === 'optional',
                'help_text' => $rule->help_text,
                'validation_rules' => $rule->validation_rules,
                'required_if_field' => $rule->required_if_field,
                'required_if_value' => $rule->required_if_value,
                'rule_id' => $rule->id,
                '_specificity' => $rule->getSpecificityScore(),
                '_source' => 'new'
            ];
        }

        // Remove hidden entries and internal fields
        $finalRequirements = [];
        foreach ($fileRequirements as $fileTypeId => $requirement) {
            if ($requirement === 'HIDDEN') continue;

            // Remove internal fields
            unset($requirement['_specificity']);
            unset($requirement['_source']);
            $finalRequirements[] = $requirement;
        }

        // Sort by file type display order
        usort($finalRequirements, function ($a, $b) {
            return ($a['file_type']->sort_order ?? 0) <=> ($b['file_type']->sort_order ?? 0);
        });

        return $finalRequirements;
    }

    /**
     * Validate uploaded files against requirements
     * 
     * @param array $context Work order context
     * @param array $uploadedFiles Array of uploaded files with their file_type_id
     * @return array Validation result with errors
     */
    public function validateFileUpload(array $context, array $uploadedFiles): array
    {
        $requirements = $this->getRequirementsForContext($context);
        $errors = [];

        foreach ($requirements as $req) {
            if (!$req['is_required']) continue;

            $fileTypeId = $req['file_type']->id;
            $hasFile = collect($uploadedFiles)->contains('file_type_id', $fileTypeId);

            if (!$hasFile) {
                // Check conditional requirement
                if ($req['required_if_field'] && isset($context[$req['required_if_field']])) {
                    $actualValue = $context[$req['required_if_field']];
                    $requiredValue = $req['required_if_value'];

                    // Convert to comparable types
                    if (is_bool($actualValue)) {
                        $requiredValue = filter_var($requiredValue, FILTER_VALIDATE_BOOLEAN);
                    }

                    if ($actualValue == $requiredValue) {
                        $errors[] = "File '{$req['file_type']->name}' is required when {$req['required_if_field']} is {$req['required_if_value']}";
                    }
                } else {
                    $errors[] = "File '{$req['file_type']->name}' is required";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
