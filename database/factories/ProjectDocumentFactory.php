<?php

namespace Database\Factories;

use App\Models\ProjectDocument;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProjectDocument> */
class ProjectDocumentFactory extends Factory
{
    protected $model = ProjectDocument::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'original_name' => 'notes.txt',
            'path' => 'documents/factory/'.Str::uuid().'.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'uploaded_by' => null,
        ];
    }
}
