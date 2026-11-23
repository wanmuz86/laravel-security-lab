<?php

namespace Tests\Feature;

use App\Models\ProjectDocument;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Security acceptance tests for the SecureProject lab.
 *
 * Run:  docker compose exec app php artisan test --filter=SecurityAcceptanceTest
 *
 * On the supplied (vulnerable) application the AC-* tests FAIL for confirmed risks and the
 * CONTROL-* tests PASS (they show which protections already work).
 * After the fixes in /solutions are applied, every test should pass.
 */
class SecurityAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private const BETA_SECRET = 'BETA-PRIVATE-MARKER';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function betaProject(): Project
    {
        return Project::where('description', 'like', '%'.self::BETA_SECRET.'%')->firstOrFail();
    }

    private function assertDenied($response): void
    {
        $this->assertContains($response->getStatusCode(), [403, 404], 'Expected 403 or 404, got '.$response->getStatusCode());
    }

    // ---- Acceptance criteria (R01-R05) ------------------------------------------------

    /** AC-01 / R01: organization isolation on project details */
    public function test_ac01_member_cannot_view_another_organizations_project(): void
    {
        $response = $this->actingAs($this->user('staff.alpha@example.test'))
            ->get('/projects/'.$this->betaProject()->id);

        $this->assertDenied($response);
        $response->assertDontSee(self::BETA_SECRET);
    }

    /** AC-01b / R01: organization isolation on project update */
    public function test_ac01b_admin_cannot_update_another_organizations_project(): void
    {
        $beta = $this->betaProject();

        $response = $this->actingAs($this->user('admin.alpha@example.test'))
            ->put('/projects/'.$beta->id, ['name' => 'Hijacked', 'description' => 'changed by Alpha']);

        $this->assertDenied($response);
        $this->assertSame($beta->name, $beta->fresh()->name);
    }

    /** AC-02 / R02: private document download requires organization authorization */
    public function test_ac02_member_cannot_download_another_organizations_document(): void
    {
        $document = ProjectDocument::where('project_id', $this->betaProject()->id)->firstOrFail();

        $response = $this->actingAs($this->user('staff.alpha@example.test'))
            ->get('/project-documents/'.$document->id.'/download');

        $this->assertDenied($response);
    }

    /** AC-03 / R03: server-side upload validation rejects disallowed types */
    public function test_ac03_upload_rejects_disallowed_file_type(): void
    {
        $alphaProject = Project::where('organization_id', $this->user('staff.alpha@example.test')->organization_id)->firstOrFail();
        $file = UploadedFile::fake()->create('payload.php', 10, 'application/x-httpd-php');

        $response = $this->actingAs($this->user('staff.alpha@example.test'))
            ->post('/projects/'.$alphaProject->id.'/documents', ['file' => $file]);

        $response->assertSessionHasErrors('file');
        Storage::disk('local')->assertMissing('documents/'.$alphaProject->id.'/payload.php');
        $this->assertDatabaseMissing('project_documents', ['original_name' => 'payload.php']);
    }

    /** AC-03b / R03: oversized uploads are rejected */
    public function test_ac03b_upload_rejects_oversized_file(): void
    {
        $alphaProject = Project::where('organization_id', $this->user('staff.alpha@example.test')->organization_id)->firstOrFail();
        $file = UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf'); // ~20 MB

        $response = $this->actingAs($this->user('staff.alpha@example.test'))
            ->post('/projects/'.$alphaProject->id.'/documents', ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    /** AC-04 / R04: search input cannot change the query's tenant scope */
    public function test_ac04_search_is_not_sql_injectable(): void
    {
        $response =$this->actingAs($this->user('staff.alpha@example.test'))
            ->get('/projects/search?'.http_build_query(['q' => "zzz' OR 1=1 --"]));

        $response->assertDontSee(self::BETA_SECRET);
        $response->assertDontSee('Beta Acquisition Plan');
    }

    /** AC-05 / R05: errors must not disclose internals */
    public function test_ac05_errors_do_not_disclose_internal_details(): void
    {
        $response = $this->actingAs($this->user('staff.alpha@example.test'))
            ->get('/projects/search?'.http_build_query(['q' => "'"]));

        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('Exception:');
        $response->assertDontSee('/var/www/html');
        $response->assertDontSee('File:');
    }

    /** AC-06 / Bonus: an organization admin cannot change roles of another organization's users */
    public function test_ac06_admin_cannot_change_role_of_other_organization_user(): void
    {
        $betaStaff = $this->user('staff.beta@example.test');

        $response = $this->actingAs($this->user('admin.alpha@example.test'))
            ->patch('/team/'.$betaStaff->id.'/role', ['role' => 'admin']);

        $this->assertDenied($response);
        $this->assertSame('member', $betaStaff->fresh()->role);
    }

    // ---- Controls that should already pass (distinguish "Not Reproduced" from "Confirmed") ----

    public function test_control_guests_are_redirected_to_login(): void
    {
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/projects/1')->assertRedirect('/login');
        $this->get('/project-documents/1/download')->assertRedirect('/login');
    }

    public function test_control_project_listing_is_tenant_filtered(): void
    {
        $this->actingAs($this->user('staff.alpha@example.test'))
            ->get('/projects')
            ->assertOk()
            ->assertSee('Alpha Website Redesign')
            ->assertDontSee('Beta Acquisition Plan');
    }

    public function test_control_cross_organization_delete_is_blocked(): void
    {
        $beta = $this->betaProject();

        $this->assertDenied(
            $this->actingAs($this->user('admin.alpha@example.test'))->delete('/projects/'.$beta->id)
        );

        $this->assertDatabaseHas('projects', ['id' => $beta->id]);
    }

    public function test_control_members_cannot_open_team_admin(): void
    {
        $this->actingAs($this->user('staff.alpha@example.test'))
            ->get('/team')
            ->assertForbidden();
    }

    public function test_control_cross_organization_upload_is_blocked(): void
    {
        $file = UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');

        $response = $this->actingAs($this->user('staff.alpha@example.test'))
            ->post('/projects/'.$this->betaProject()->id.'/documents', ['file' => $file]);

        $this->assertDenied($response);
    }
}
