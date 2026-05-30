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

        $agent = $request->query('agent', 'claude_code');
        $profile = $request->query('profile', 'full');

        if (! in_array($agent, ['claude_code', 'codex'], true)) {
            throw ValidationException::withMessages([
                'agent' => ['Invalid agent. Supported values: claude_code, codex.'],
            ]);
        }

        if (! array_key_exists($profile, self::PROFILE_SKILLS)) {
            throw ValidationException::withMessages([
                'profile' => ['Invalid profile. Supported values: basic, plan, full.'],
            ]);
        }

        $skillsDir = match ($agent) {
            'codex' => '.agents/skills',
            default => '.claude/skills',
        };

        $url = $request->schemeAndHttpHost();
        $token = $request->bearerToken();

        $script = $this->buildInstallScript($skillsDir, $url, $token, self::PROFILE_SKILLS[$profile]);

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript',
            'Content-Disposition' => 'attachment; filename="install-yomitoki-skills.sh"',
        ]);
    }

    /** @param string[] $skills */
    private function buildInstallScript(string $skillsDir, string $url, string $token, array $skills): string
    {
        $basePath = base_path($skillsDir);

        $lines = [];
        $lines[] = '#!/bin/bash';
        $lines[] = 'set -euo pipefail';
        $lines[] = '';
        $lines[] = 'YOMITOKI_URL="'.$url.'"';
        $lines[] = 'YOMITOKI_TOKEN="'.$token.'"';
        $lines[] = 'SKILLS_DIR="'.$skillsDir.'"';
        $lines[] = '';
        $lines[] = 'command -v curl >/dev/null 2>&1 || { echo "Error: curl is required" >&2; exit 1; }';
        $lines[] = 'command -v jq >/dev/null 2>&1 || { echo "Error: jq is required. Install with: brew install jq" >&2; exit 1; }';
        $lines[] = '';
        $lines[] = '# Save config';
        $lines[] = 'mkdir -p "$HOME/.config/yomitoki"';
        $lines[] = 'printf "YOMITOKI_URL=%s\\nYOMITOKI_TOKEN=%s\\n" "$YOMITOKI_URL" "$YOMITOKI_TOKEN" > "$HOME/.config/yomitoki/config"';
        $lines[] = 'chmod 600 "$HOME/.config/yomitoki/config"';
        $lines[] = '';

        foreach ($skills as $skillName) {
            $skillPath = $basePath.'/'.$skillName;

            if (! File::isDirectory($skillPath)) {
                continue;
            }

            $lines[] = "# Skill: $skillName";
            $lines[] = 'mkdir -p "./$SKILLS_DIR/'.$skillName.'/scripts"';

            $skillMd = $skillPath.'/SKILL.md';
            if (File::exists($skillMd)) {
                $lines[] = $this->fileBlock("./$skillsDir/$skillName/SKILL.md", File::get($skillMd));
            }

            $scriptsDir = $skillPath.'/scripts';
            if (File::isDirectory($scriptsDir)) {
                foreach (File::files($scriptsDir) as $script) {
                    $lines[] = $this->fileBlock("./$skillsDir/$skillName/scripts/".$script->getFilename(), File::get($script->getPathname()));
                    $lines[] = 'chmod +x "./$SKILLS_DIR/'.$skillName.'/scripts/'.$script->getFilename().'"';
                }
            }

            $lines[] = '';
        }

        $lines[] = 'echo "✓ Yomitoki skills installed to ./$SKILLS_DIR"';
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
