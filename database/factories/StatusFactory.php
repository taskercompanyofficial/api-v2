<?php

namespace Database\Factories;

use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Status>
 */
class StatusFactory extends Factory
{
    protected $model = Status::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Use a fixed set of statuses instead of random selection
        static $index = 0;
        $statuses = ['Active', 'Inactive', 'Pending', 'Suspended', 'Banned'];
        $name = $statuses[$index % count($statuses)];
        $index++;

        $descriptions = [
            'Active'    => 'Account is fully operational and accessible.',
            'Inactive'  => 'Account is temporarily disabled but can be reactivated.',
            'Pending'   => 'Account is awaiting approval or verification.',
            'Suspended' => 'Account has been temporarily restricted due to policy violations.',
            'Banned'    => 'Account has been permanently disabled and cannot be restored.',
        ];

        return [
            'name'        => $name,
            'slug'        => Str::slug($name),
            'description' => $descriptions[$name],
            'status'      => true, // Always active for factory consistency
            'created_by'  => 1,
            'updated_by'  => 1,
        ];
    }
}
