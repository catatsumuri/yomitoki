<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class SkillsInstallController extends Controller
{
    /** @var array<string, string[]> */
    private const PROFILE_SKILLS = [
        'basic' => ['scrap-utils'],
        'plan' => ['plan-to-markdown', 'execution-result'],
        'full' => ['scrap-utils', 'plan-to-markdown', 'execution-result'],
    ];

    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->currentAccessToken()?->can('ingest'), 403);

        $agent = $request->query('agent', 'all');
        $profile = $request->query('profile', 'full');

        if (! in_array($agent, ['claude_code', 'codex', 'all'], true)) {
            throw ValidationException::withMessages([
                'agent' => ['Invalid agent. Supported values: claude_code, codex, all.'],
            ]);
        }

        if (! array_key_exists($profile, self::PROFILE_SKILLS)) {
            throw ValidationException::withMessages([
                'profile' => ['Invalid profile. Supported values: basic, plan, full.'],
            ]);
        }

        $skillsDirs = match ($agent) {
            'claude_code' => ['.claude/skills'],
            'codex' => ['.agents/skills'],
            default => ['.claude/skills', '.agents/skills'],
        };

        $url = $request->schemeAndHttpHost();
        $token = $request->bearerToken();

        $script = $this->buildInstallScript($skillsDirs, $url, $token, self::PROFILE_SKILLS[$profile]);

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript',
            'Content-Disposition' => 'attachment; filename="install-yomitoki-skills.sh"',
        ]);
    }

    /**
     * @param  string[]  $skillsDirs
     * @param  string[]  $skills
     */
    private function buildInstallScript(array $skillsDirs, string $url, string $token, array $skills): string
    {
        $lines = [];
        $lines[] = '#!/bin/bash';
        $lines[] = 'set -euo pipefail';
        $lines[] = '';
        $lines[] = 'YOMITOKI_URL="'.$url.'"';
        $lines[] = 'YOMITOKI_TOKEN="'.$token.'"';
        $lines[] = '';
        $lines[] = 'command -v curl >/dev/null 2>&1 || { echo "Error: curl is required" >&2; exit 1; }';
        $lines[] = 'command -v jq >/dev/null 2>&1 || { echo "Error: jq is required. Install with: brew install jq" >&2; exit 1; }';
        $lines[] = '';
        $lines[] = '# Save config';
        $lines[] = 'mkdir -p "$HOME/.config/yomitoki"';
        $lines[] = 'printf "YOMITOKI_URL=%s\\nYOMITOKI_TOKEN=%s\\n" "$YOMITOKI_URL" "$YOMITOKI_TOKEN" > "$HOME/.config/yomitoki/config"';
        $lines[] = 'chmod 600 "$HOME/.config/yomitoki/config"';
        $lines[] = '';

        foreach ($skillsDirs as $skillsDir) {
            $basePath = base_path($skillsDir);

            foreach ($skills as $skillName) {
                $skillPath = $basePath.'/'.$skillName;

                if (! File::isDirectory($skillPath)) {
                    continue;
                }

                $lines[] = "# Skill: $skillName → $skillsDir";
                $lines[] = 'mkdir -p "./'.$skillsDir.'/'.$skillName.'/scripts"';

                $skillMd = $skillPath.'/SKILL.md';
                if (File::exists($skillMd)) {
                    $lines[] = $this->fileBlock("./$skillsDir/$skillName/SKILL.md", File::get($skillMd));
                }

                $scriptsDir = $skillPath.'/scripts';
                if (File::isDirectory($scriptsDir)) {
                    foreach (File::files($scriptsDir) as $script) {
                        $lines[] = $this->fileBlock("./$skillsDir/$skillName/scripts/".$script->getFilename(), File::get($script->getPathname()));
                        $lines[] = 'chmod +x "./'.$skillsDir.'/'.$skillName.'/scripts/'.$script->getFilename().'"';
                    }
                }

                $lines[] = '';
            }

            $lines[] = 'echo "✓ Yomitoki skills installed to ./'.$skillsDir.'"';
        }

        $lines[] = 'echo "  Config saved to ~/.config/yomitoki/config"';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** Wrap file content in a heredoc block, replacing the EOF marker if it appears in content. */
    private function fileBlock(string $path, string $content): string
    {
        $marker = '__YOMITOKI_INSTALL_EOF__';
        $escapedContent = str_replace($marker, $marker.'_', $content);

        return "cat > \"$path\" << '$marker'\n$escapedContent\n$marker";
    }
}
