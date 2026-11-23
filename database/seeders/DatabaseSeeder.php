<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\ProjectDocument;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Deterministic training data. Project IDs are fixed so instructions can reference them:
 *   Alpha Sdn Bhd -> projects 1, 2, 3
 *   Beta  Sdn Bhd -> projects 4, 5, 6   (project 4 carries a confidential marker)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('LAB_PASSWORD', 'Lab-Passw0rd!');

        $alpha = Organization::create(['name' => 'Alpha Sdn Bhd']);
        $beta = Organization::create(['name' => 'Beta Sdn Bhd']);

        $users = [
            ['Alpha Admin', 'admin.alpha@example.test', $alpha, 'admin'],
            ['Alpha Staff', 'staff.alpha@example.test', $alpha, 'member'],
            ['Beta Admin', 'admin.beta@example.test', $beta, 'admin'],
            ['Beta Staff', 'staff.beta@example.test', $beta, 'member'],
        ];

        $created = [];
        foreach ($users as [$name, $email, $org, $role]) {
            $created[$email] = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'organization_id' => $org->id,
                'role' => $role,
            ]);
        }

        $projects = [
            [$alpha, 'admin.alpha@example.test', 'Alpha Website Redesign', 'Public-facing website refresh for Alpha.'],
            [$alpha, 'staff.alpha@example.test', 'Alpha Mobile App', 'Customer app MVP. Internal notes only.'],
            [$alpha, 'admin.alpha@example.test', 'Alpha Q4 Budget', 'Budget planning for Alpha Q4.'],
            [$beta, 'admin.beta@example.test', 'Beta Acquisition Plan', 'BETA-PRIVATE-MARKER: confidential acquisition strategy.'],
            [$beta, 'staff.beta@example.test', 'Beta Payroll Migration', 'Payroll system migration plan for Beta.'],
            [$beta, 'admin.beta@example.test', 'Beta Security Audit', 'Findings from the Beta security audit.'],
        ];

        foreach ($projects as [$org, $creatorEmail, $name, $description]) {
            $project = Project::create([
                'organization_id' => $org->id,
                'name' => $name,
                'description' => $description,
                'created_by' => $created[$creatorEmail]->id,
            ]);

            $this->createDocument($project, $created[$creatorEmail], $org->name);
        }
        $this->seedOrders($alpha, $beta, $password);

        Project::where('name', 'Alpha Website Redesign')->firstOrFail()->comments()->create([
            'user_id' => $created['staff.alpha@example.test']->id,
            'content' => 'Project meeting scheduled for Monday.',
        ]);
    }

    /**
     * Lab 7: fictional customers Alice and Bob, their orders and fixed training API tokens.
     * Orders are created with forceCreate() so the seeder keeps working after $fillable is restricted.
     */
    private function seedOrders(Organization $alpha, Organization $beta, string $password): void
    {
        $alice = User::create(['name' => 'Alice Customer', 'email' => 'alice@example.test', 'password' => $password, 'organization_id' => $alpha->id, 'role' => 'member']);
        $bob = User::create(['name' => 'Bob Customer', 'email' => 'bob@example.test', 'password' => $password, 'organization_id' => $beta->id, 'role' => 'member']);

        $orders = [
            [$alice, 'ORD-001', 'Training order', 15000, 'PAY-PRIVATE-001', 'Internal finance review'],
            [$bob, 'ORD-002', 'Bob training order', 25000, 'PAY-PRIVATE-002', 'BOB-PRIVATE-MARKER: internal finance notes'],
            [$alice, 'ORD-003', 'Alice second order', 9900, 'PAY-PRIVATE-003', 'Alice repeat customer'],
            [$bob, 'ORD-004', 'Bob second order', 4500, 'PAY-PRIVATE-004', 'Bob repeat customer'],
            [$alice, 'ORD-005', 'Alice spare draft order', 3200, 'PAY-PRIVATE-005', 'Spare order for processing demos'],
        ];

        foreach ($orders as [$owner, $reference, $description, $total, $payment, $notes]) {
            Order::forceCreate([
                'user_id' => $owner->id,
                'reference' => $reference,
                'description' => $description,
                'total_cents' => $total,
                'status' => 'draft',
                'payment_reference' => $payment,
                'internal_notes' => $notes,
            ]);
        }

        // Fixed, fictional training tokens so Postman can start immediately (Lab 7 Part A6).
        foreach ([[$alice, 'lab7-alice-training-token-0001'], [$bob, 'lab7-bob-training-token-0002']] as [$owner, $plain]) {
            PersonalAccessToken::forceCreate([
                'tokenable_type' => User::class,
                'tokenable_id' => $owner->id,
                'name' => 'lab7-training',
                'token' => hash('sha256', $plain),
                'abilities' => ['*'],
                'expires_at' => now()->addDays(30),
            ]);
        }
    }

    private function createDocument(Project $project, User $uploader, string $orgName): void
    {
        $filename = 'project-'.$project->id.'-notes.txt';
        $path = "documents/{$project->id}/{$filename}";
        $contents = "Document for project #{$project->id} ({$project->name}) owned by {$orgName}.\n"
            .($project->id === 4 ? "BETA-CONFIDENTIAL-DOCUMENT: do not disclose outside Beta Sdn Bhd.\n" : '');

        Storage::disk('local')->put($path, $contents);

        ProjectDocument::create([
            'project_id' => $project->id,
            'original_name' => $filename,
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => strlen($contents),
            'uploaded_by' => $uploader->id,
        ]);
    }
}
