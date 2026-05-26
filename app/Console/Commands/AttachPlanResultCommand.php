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

#[Signature('plans:result {--plan= : Slug of the parent plan} {--file= : Path to result markdown file} {--title= : Result title (defaults to "Execution result")} {--directory= : Project root directory} {--created-from=execution-result-skill : Identifier for the tool or workflow attaching the result}')]
#[Description('Attach an execution result as a child scrap to an existing plan')]
class AttachPlanResultCommand extends Command
{
    public function handle(): int
    {
        $planSlug = $this->option('plan');
        $file = $this->option('file');

        if (! $planSlug || ! $file) {
            $this->error(__('Both --plan and --file are required.'));

            return self::FAILURE;
        }

        if (! file_exists($file)) {
            $this->error(__('File not found: :file', ['file' => $file]));

            return self::FAILURE;
        }

        $plan = Scrap::where('slug', $planSlug)->first();

        if (! $plan) {
            $this->error(__('Plan not found with slug: :slug', ['slug' => $planSlug]));

            return self::FAILURE;
        }

        $user = User::first();

        if (! $user) {
            $this->error(__('No user found.'));

            return self::FAILURE;
        }

        $directory = $this->option('directory') ?: base_path();
        $createdFrom = $this->option('created-from');
        $project = $plan->meta['project'] ?? $this->resolveProjectName($directory);
        $tags = array_values(array_filter([$project]));
        $rawTitle = $this->option('title') ?: __('Execution result: :plan', ['plan' => $planSlug]);
        $baseSlug = Str::slug($rawTitle);
        $slug = $baseSlug;
        $suffix = 1;

        while (Scrap::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        $content = file_get_contents($file);

        $result = Scrap::create([
            'user_id' => $user->id,
            'parent_id' => $plan->id,
            'source_type' => 'execution',
            'title' => $rawTitle,
            'slug' => $slug,
            'content' => $content,
            'content_markdown' => $content,
            'status' => 'processed',
            'occurred_at' => now(),
            'meta' => array_filter([
                'plan_slug' => $planSlug,
                'project' => $project,
                'directory' => $directory,
                'created_from' => $createdFrom,
                'tags' => $tags,
            ]),
        ]);

        GenerateScrapEmbeddingJob::dispatch($result->id);
        SummarizeScrapJob::dispatch($result->id);

        $this->info(__('Result attached: scrap #:id → plan ":plan"', [
            'id' => $result->id,
            'plan' => $planSlug,
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
