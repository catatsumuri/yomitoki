<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('scraps')
            ->where('slug', 'syntax-highlighting')
            ->update([
                'summary' => '`MarkdownPreview` のコードブロックに `shiki` を使ったシンタックスハイライトを追加するプランです。コードフェンスから言語を判定し、ハイライト済み HTML とテーマ切り替え対応の CSS でコードの可読性を高めます。',
            ]);

        DB::table('scraps')
            ->where('slug', 'codex-plan-skill-port')
            ->update([
                'summary' => '既存のプラン保存・実行結果保存の仕組みを Claude だけでなく Codex でも使えるように拡張するプランです。保存元の識別、Codex 用スキル追加、Sail と直接 PHP 実行の両対応、関連テストの整備を進めます。',
            ]);

        DB::table('scraps')
            ->where('slug', 'execution-result-codex-plan-skill-port')
            ->update([
                'summary' => 'Codex 向けのプランスキルを、従来の Claude 中心の運用を壊さずに移植した実装結果です。Sail と直接 PHP Artisan 実行の両対応を加え、メタデータ拡張と Pest テストで新しい保存フローを検証しています。',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('scraps')
            ->where('slug', 'syntax-highlighting')
            ->update([
                'summary' => 'This plan adds syntax highlighting to Markdown code blocks in the MarkdownPreview component using the `shiki` library. The implementation includes creating a lazy-loaded shiki singleton, extracting language identifiers from code fences, building a CodeBlock component that renders highlighted HTML, and adding CSS variables for dark/light mode support to improve code readability across 15+ programming languages.',
            ]);

        DB::table('scraps')
            ->where('slug', 'codex-plan-skill-port')
            ->update([
                'summary' => 'The plan aims to adapt the existing plan/result storage system in a Laravel app to support Codex in addition to Claude, enabling Codex to save approved plans and attach execution results. This involves generalizing the save commands to identify their source, adding Codex-native skill definitions and scripts, ensuring compatibility with both containerized (Sail) and direct PHP execution, and adding test coverage for the new metadata functionality.',
            ]);

        DB::table('scraps')
            ->where('slug', 'execution-result-codex-plan-skill-port')
            ->update([
                'summary' => 'This development work successfully ported Codex plan skills to support both Sail and direct PHP Artisan execution, while maintaining backward compatibility with the previous Claude-oriented workflow. The implementation adds new skill files for plan-to-markdown conversion and execution result handling, with focused Pest test coverage to verify the new metadata override functionality.',
            ]);
    }
};
