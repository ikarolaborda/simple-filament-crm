<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomField;
use App\Models\LeadSource;
use App\Models\PipelineStage;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tags = [
            'Priority',
            'VIP',
        ];

        foreach ($tags as $tag) {
            Tag::create(['name' => $tag]);
        }

        $leadSources = [
            'Website',
            'Online AD',
            'Twitter',
            'LinkedIn',
            'Webinar',
            'Trade Show',
            'Referral',
        ];

        foreach ($leadSources as $leadSource) {
            LeadSource::create(['name' => $leadSource]);
        }

        $randomLeadSource = LeadSource::inRandomOrder()->first();

        $pipelineStages = [
            [
                'name' => 'Lead',
                'position' => 1,
                'is_default' => true,
            ],
            [
                'name' => 'Contact Made',
                'position' => 2,
            ],
            [
                'name' => 'Proposal Made',
                'position' => 3,
            ],
            [
                'name' => 'Proposal Rejected',
                'position' => 4,
            ],
            [
                'name' => 'Customer',
                'position' => 5,
            ],
        ];

        foreach ($pipelineStages as $stage) {
            PipelineStage::create($stage);
        }

        $defaultPipelineStage = PipelineStage::where('is_default', true)->first()->id;

        $customers = Customer::factory()->count(20)->create([
            'pipeline_stage_id' => $defaultPipelineStage,
            'lead_source_id' => $randomLeadSource->id,
        ]);

        $allTagIds = Tag::all()->pluck('id');

        $customers->each(function ($customer) use ($allTagIds) {
            $randomTagIds = $allTagIds->random(rand(1, $allTagIds->count()))->all();
            $customer->tags()->attach($randomTagIds);
        });

        $customFields = [
            'Birth Date',
            'Company',
            'Job Title',
            'Family Members',
        ];

        foreach ($customFields as $customField) {
            CustomField::create(['name' => $customField]);
        }

        $roles = [
            'Admin',
            'Employee'
        ];

        foreach ($roles as $role) {
            Role::create(['name' => $role]);
        }

        User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@admin.com',
            'role_id' => Role::where('name', 'Admin')->first()->id,
        ]);

        // We will seed 10 employees
        User::factory()->count(10)->create([
            'role_id' => Role::where('name', 'Employee')->first()->id,
        ]);
    }
}
