<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('plans:save {--title= : Plan title} {--slug= : Plan slug} {--description= : Short description saved as summary} {--file= : Path to plan markdown file} {--project= : Project name (defaults to directory basename)} {--directory= : Project root directory} {--created-from=plan-to-markdown-skill : Identifier for the tool or workflow saving the plan}')]
#[Description('Save a plan markdown file as a Scrap in the database')]
class SavePlanCommand extends Command
{
    public function handle(): int
    {
        $title = $this->option('title');
        $requestedSlug = $this->option('slug');
        $file = $this->option('file');

        if (! $title || ! $file) {
            $this->error(__('Both --title and --file are required.'));

            return self::FAILURE;
        }

        if (! file_exists($file)) {
            $this->error(__('File not found: :file', ['file' => $file]));

            return self::FAILURE;
        }

        $user = User::first();

        if (! $user) {
            $this->error(__('No user found.'));

            return self::FAILURE;
        }

        $directory = $this->option('directory') ?: base_path();
        $project = $this->option('project') ?: $this->resolveProjectName($directory);
        $createdFrom = $this->option('created-from');
        $tags = array_values(array_filter([$project]));

        $content = file_get_contents($file);
        $baseSlug = Str::slug($requestedSlug ?: $title);
        $slug = $baseSlug;
        $suffix = 1;

        while (Scrap::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        $description = $this->option('description');

        $scrap = Scrap::create([
            'user_id' => $user->id,
            'source_type' => 'plan',
            'title' => $title,
            'slug' => $slug,
            'summary' => $description ?: null,
            'content' => $content,
            'content_markdown' => $content,
            'status' => 'processed',
            'occurred_at' => now(),
            'meta' => array_filter([
                'project' => $project,
                'directory' => $directory,
                'created_from' => $createdFrom,
                'tags' => $tags,
            ]),
        ]);

        GenerateScrapEmbeddingJob::dispatch($scrap->id);
        if (! $description) {
            SummarizeScrapJob::dispatch($scrap->id);
        }

        $this->info(__('Plan saved: scrap #:id (slug: :slug) ":title"', [
            'id' => $scrap->id,
            'slug' => $scrap->slug,
            'title' => $scrap->title,
        ]));

        return self::SUCCESS;
    }

    private function resolveProjectName(string $directory): string
    {
        $basename = Str::slug(basename($directory));

        if ($basename !== '' && ! in_array($basename, ['html', 'www', 'app'], true)) {
            return $basename;
        }

        return Str::slug((string) config('app.name')) ?: $basename;
    }
}
