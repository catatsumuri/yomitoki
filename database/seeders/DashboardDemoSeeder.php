<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = CarbonImmutable::now();

        $user = User::query()->firstOrNew([
            'email' => 'test@example.com',
        ]);

        $user->forceFill([
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'email_verified_at' => $now,
        ])->save();

        DB::transaction(function () use ($now, $user): void {
            $documentIds = DB::table('documents')
                ->where('user_id', $user->id)
                ->pluck('id');

            $scrapIds = DB::table('scraps')
                ->where('user_id', $user->id)
                ->pluck('id');

            DB::table('document_scraps')
                ->whereIn('document_id', $documentIds)
                ->delete();

            DB::table('ai_runs')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('agent_conversation_messages')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('agent_conversations')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('scrap_relations')
                ->whereIn('from_scrap_id', $scrapIds)
                ->orWhereIn('to_scrap_id', $scrapIds)
                ->delete();

            DB::table('documents')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('scraps')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('scrap_sources')
                ->where('user_id', $user->id)
                ->delete();

            $sources = [
                [
                    'source_type' => 'meeting_note',
                    'name' => 'Product Sync',
                    'external_id' => 'meeting-product-sync',
                    'uri' => 'https://example.test/meetings/product-sync',
                    'channel' => 'meeting',
                ],
                [
                    'source_type' => 'inquiry',
                    'name' => 'Support Inbox',
                    'external_id' => 'support-inbox',
                    'uri' => 'https://example.test/support/inbox',
                    'channel' => 'email',
                ],
                [
                    'source_type' => 'daily_report',
                    'name' => 'Daily Reports',
                    'external_id' => 'daily-reports',
                    'uri' => 'https://example.test/reports/daily',
                    'channel' => 'internal',
                ],
                [
                    'source_type' => 'research',
                    'name' => 'Reference Links',
                    'external_id' => 'reference-links',
                    'uri' => 'https://example.test/research/links',
                    'channel' => 'web',
                ],
            ];

            $sourceIds = collect($sources)->mapWithKeys(function (array $source) use ($now, $user): array {
                $id = DB::table('scrap_sources')->insertGetId([
                    'user_id' => $user->id,
                    'source_type' => $source['source_type'],
                    'name' => $source['name'],
                    'external_id' => $source['external_id'],
                    'uri' => $source['uri'],
                    'channel' => $source['channel'],
                    'meta' => json_encode(['seeded' => true], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [$source['external_id'] => $id];
            });

            $scraps = [
                [
                    'key' => 's1',
                    'source_key' => 'meeting-product-sync',
                    'source_type' => 'meeting_note',
                    'title' => '会議メモ: 仕様書生成フローの合意',
                    'slug' => 'spec-generation-flow',
                    'content' => '会議メモ、問い合わせ、調査断片を scrap に集約し、AI が仕様書ドラフトへ再構成する流れで進める。',
                    'summary' => '仕様書生成を主ユースケースに据える。',
                    'status' => 'processed',
                    'occurred_at' => $now->subDays(3),
                    'extracted_data' => ['kind' => 'decision', 'priority' => 'high'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'spec']],
                ],
                [
                    'key' => 's2',
                    'parent_key' => 's1',
                    'source_key' => 'meeting-product-sync',
                    'source_type' => 'meeting_note',
                    'title' => '会議メモ: 未確定論点の抽出',
                    'content' => 'AI は仕様書ドラフトだけでなく、未確定論点や追加確認事項も返すべき。',
                    'summary' => '未確定事項の抽出を AI の役割に含める。',
                    'status' => 'processed',
                    'occurred_at' => $now->subDays(3)->addMinutes(10),
                    'extracted_data' => ['kind' => 'idea', 'priority' => 'medium'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'spec']],
                ],
                [
                    'key' => 's3',
                    'parent_key' => 's1',
                    'source_key' => 'meeting-product-sync',
                    'source_type' => 'meeting_note',
                    'title' => '会議メモ: 章立ての初期案',
                    'content' => '仕様書は概要、背景、課題、機能要件、非機能要件、未確定事項の順で組み立てる。',
                    'summary' => '仕様書ドラフトの章立て案。',
                    'status' => 'raw',
                    'occurred_at' => $now->subDays(2),
                    'extracted_data' => ['kind' => 'task', 'priority' => 'medium'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'spec']],
                ],
                [
                    'key' => 's4',
                    'source_key' => 'support-inbox',
                    'source_type' => 'inquiry',
                    'title' => '問い合わせ: 仕様書の初稿に時間がかかる',
                    'slug' => 'spec-draft-takes-too-long',
                    'content' => '会議後に仕様書の初稿を書くまでの整理に時間がかかり、毎回ゼロから構成を考えてしまう。',
                    'summary' => '仕様書初稿作成の立ち上がりが遅い。',
                    'status' => 'raw',
                    'occurred_at' => $now->subDays(2)->addHours(2),
                    'extracted_data' => ['kind' => 'pain', 'priority' => 'high'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'support']],
                ],
                [
                    'key' => 's5',
                    'parent_key' => 's4',
                    'source_key' => 'support-inbox',
                    'source_type' => 'inquiry',
                    'title' => '問い合わせ: 要件の抜け漏れが起きやすい',
                    'content' => 'メモはあるが、非機能要件や未決事項が仕様書から落ちやすい。',
                    'summary' => '抜け漏れ検知が必要。',
                    'status' => 'processed',
                    'occurred_at' => $now->subDay(),
                    'extracted_data' => ['kind' => 'pain', 'priority' => 'medium'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'support']],
                ],
                [
                    'key' => 's6',
                    'source_key' => 'support-inbox',
                    'source_type' => 'inquiry',
                    'title' => '問い合わせ: 仕様変更の背景も残したい',
                    'slug' => 'keep-spec-change-context',
                    'content' => '最終仕様だけでなく、なぜその判断になったかも scrap から辿れるようにしたい。',
                    'summary' => '判断理由のトレーサビリティが求められている。',
                    'status' => 'processed',
                    'occurred_at' => $now->subDay()->addMinutes(30),
                    'extracted_data' => ['kind' => 'request', 'priority' => 'low'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'support']],
                ],
                [
                    'key' => 's7',
                    'source_key' => 'daily-reports',
                    'source_type' => 'daily_report',
                    'title' => '日報: scrap と document の土台を実装',
                    'slug' => 'scrap-document-foundation',
                    'content' => 'scrap, document, ai_run の migration を追加し、仕様書生成のデータ構造を用意した。',
                    'summary' => '仕様書生成の基盤スキーマが整った。',
                    'status' => 'processed',
                    'occurred_at' => $now->subDays(4),
                    'extracted_data' => ['kind' => 'progress', 'priority' => 'medium'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'dev']],
                ],
                [
                    'key' => 's8',
                    'source_key' => 'daily-reports',
                    'source_type' => 'daily_report',
                    'title' => '日報: AI metadata 提案を接続',
                    'slug' => 'ai-metadata-suggestions',
                    'content' => 'title と slug を AI に提案させ、保存前にユーザーが確認できるようにした。',
                    'summary' => 'AI metadata 提案フローを導入した。',
                    'status' => 'processed',
                    'occurred_at' => $now->subDays(4)->addHours(1),
                    'extracted_data' => ['kind' => 'progress', 'priority' => 'medium'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'dev']],
                ],
                [
                    'key' => 's9',
                    'source_key' => 'daily-reports',
                    'source_type' => 'daily_report',
                    'title' => '日報: 仕様書ドラフト画面の準備',
                    'slug' => 'spec-draft-screen-prep',
                    'content' => 'capture-first の画面から仕様書生成へ進む導線を追加する方針。',
                    'summary' => '仕様書ドラフト生成 UI の準備。',
                    'status' => 'raw',
                    'occurred_at' => $now->subHours(10),
                    'extracted_data' => ['kind' => 'plan', 'priority' => 'high'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'dev']],
                ],
                [
                    'key' => 's10',
                    'source_key' => 'reference-links',
                    'source_type' => 'research',
                    'title' => '調査: 仕様書テンプレートの最小構成',
                    'slug' => 'spec-template-minimum-structure',
                    'content' => '概要、背景、課題、機能要件、非機能要件、未確定事項の6章が最小構成として扱いやすい。',
                    'summary' => '仕様書テンプレートの最小章構成。',
                    'status' => 'processed',
                    'occurred_at' => $now->subDays(5),
                    'extracted_data' => ['kind' => 'research', 'priority' => 'medium'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'research']],
                ],
                [
                    'key' => 's11',
                    'source_key' => 'reference-links',
                    'source_type' => 'research',
                    'title' => '調査: 断片から仕様書へ再構成する価値',
                    'slug' => 'recompose-fragments-into-spec',
                    'content' => 'ゼロから仕様書を書くのではなく、断片情報を AI が再構成する方が実務の流れに近い。',
                    'summary' => '仕様書生成ユースケースの妥当性。',
                    'status' => 'processed',
                    'occurred_at' => $now->subDays(5)->addMinutes(20),
                    'extracted_data' => ['kind' => 'principle', 'priority' => 'high'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'research']],
                ],
                [
                    'key' => 's12',
                    'source_key' => 'reference-links',
                    'source_type' => 'research',
                    'title' => '調査: 旧 dashboard 指標案',
                    'slug' => 'legacy-dashboard-metrics',
                    'content' => '情報カード主体の dashboard は capture の邪魔になる可能性がある。',
                    'summary' => '旧 dashboard 案の残骸。',
                    'status' => 'archived',
                    'occurred_at' => $now->subDays(6),
                    'extracted_data' => ['kind' => 'idea', 'priority' => 'low'],
                    'meta' => ['seeded' => true, 'tags' => ['yomitoki', 'research']],
                ],
            ];

            $scrapIdMap = collect($scraps)->mapWithKeys(function (array $scrap) use ($now, $sourceIds, $user): array {
                $id = DB::table('scraps')->insertGetId([
                    'user_id' => $user->id,
                    'scrap_source_id' => $sourceIds[$scrap['source_key']],
                    'source_type' => $scrap['source_type'],
                    'source_reference' => $scrap['key'],
                    'title' => $scrap['title'],
                    'slug' => $scrap['slug'] ?? null,
                    'content' => $scrap['content'],
                    'content_markdown' => $scrap['content'],
                    'summary' => $scrap['summary'],
                    'status' => $scrap['status'],
                    'language' => 'ja',
                    'occurred_at' => $scrap['occurred_at'],
                    'processed_at' => $scrap['status'] === 'raw' ? null : $scrap['occurred_at']->addMinutes(5),
                    'extracted_data' => json_encode($scrap['extracted_data'], JSON_THROW_ON_ERROR),
                    'meta' => json_encode($scrap['meta'] ?? ['seeded' => true], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [$scrap['key'] => $id];
            });

            collect($scraps)
                ->filter(fn (array $scrap): bool => isset($scrap['parent_key']))
                ->each(function (array $scrap) use ($scrapIdMap, $now): void {
                    DB::table('scraps')
                        ->where('id', $scrapIdMap[$scrap['key']])
                        ->update([
                            'parent_id' => $scrapIdMap[$scrap['parent_key']],
                            'updated_at' => $now,
                        ]);
                });

            $documents = [
                [
                    'key' => 'd1',
                    'title' => '仕様書生成フロー草案',
                    'document_type' => 'spec',
                    'status' => 'draft',
                    'summary' => '断片情報から仕様書ドラフトへ再構成する流れの草案。',
                    'outline' => ['概要', '背景', '課題', '機能要件', '未確定事項'],
                    'scraps' => ['s1', 's2', 's3', 's11'],
                ],
                [
                    'key' => 'd2',
                    'title' => '仕様書生成の課題整理',
                    'document_type' => 'summary',
                    'status' => 'draft',
                    'summary' => '問い合わせから見えた仕様書作成上の痛点。',
                    'outline' => ['痛点', '要求', '未解決論点'],
                    'scraps' => ['s4', 's5', 's6'],
                ],
                [
                    'key' => 'd3',
                    'title' => '実装進捗メモ',
                    'document_type' => 'daily_report',
                    'status' => 'final',
                    'summary' => '仕様書生成アプリの実装進捗。',
                    'outline' => ['完了', '確認済み', '次の作業'],
                    'scraps' => ['s7', 's8', 's9'],
                ],
                [
                    'key' => 'd4',
                    'title' => '仕様書テンプレート調査メモ',
                    'document_type' => 'minutes',
                    'status' => 'final',
                    'summary' => '仕様書テンプレートと再構成方針の調査メモ。',
                    'outline' => ['背景', '判断', '補足'],
                    'scraps' => ['s10', 's11', 's12'],
                ],
            ];

            $documentIdMap = collect($documents)->mapWithKeys(function (array $document, int $index) use ($now, $user): array {
                $id = DB::table('documents')->insertGetId([
                    'user_id' => $user->id,
                    'title' => $document['title'],
                    'document_type' => $document['document_type'],
                    'status' => $document['status'],
                    'content_markdown' => '# '.$document['title']."\n\nSeeded prototype document for spec-generation flow.",
                    'summary' => $document['summary'],
                    'outline' => json_encode($document['outline'], JSON_THROW_ON_ERROR),
                    'meta' => json_encode(['seeded' => true, 'order' => $index + 1], JSON_THROW_ON_ERROR),
                    'published_at' => $document['status'] === 'final' ? $now->subHours($index + 1) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [$document['key'] => $id];
            });

            foreach ($documents as $document) {
                foreach ($document['scraps'] as $position => $scrapKey) {
                    DB::table('document_scraps')->insert([
                        'document_id' => $documentIdMap[$document['key']],
                        'scrap_id' => $scrapIdMap[$scrapKey],
                        'position' => $position + 1,
                        'role' => 'source',
                        'excerpt' => 'Seed excerpt for '.$scrapKey,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $relations = [
                ['from' => 's1', 'to' => 's11', 'type' => 'supports', 'confidence' => 92],
                ['from' => 's4', 'to' => 's5', 'type' => 'related', 'confidence' => 88],
                ['from' => 's5', 'to' => 's6', 'type' => 'extends', 'confidence' => 71],
                ['from' => 's7', 'to' => 's8', 'type' => 'depends_on', 'confidence' => 95],
                ['from' => 's9', 'to' => 's1', 'type' => 'follows_up', 'confidence' => 84],
            ];

            foreach ($relations as $relation) {
                DB::table('scrap_relations')->insert([
                    'from_scrap_id' => $scrapIdMap[$relation['from']],
                    'to_scrap_id' => $scrapIdMap[$relation['to']],
                    'relation_type' => $relation['type'],
                    'confidence' => $relation['confidence'],
                    'meta' => json_encode(['seeded' => true], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $conversationId = (string) Str::uuid();

            DB::table('agent_conversations')->insert([
                'id' => $conversationId,
                'user_id' => $user->id,
                'title' => 'Spec generation seed review',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('agent_conversation_messages')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'conversation_id' => $conversationId,
                    'user_id' => $user->id,
                    'agent' => 'PingAgent',
                    'role' => 'user',
                    'content' => 'Ping',
                    'attachments' => '[]',
                    'tool_calls' => '[]',
                    'tool_results' => '[]',
                    'usage' => '{}',
                    'meta' => '{}',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'conversation_id' => $conversationId,
                    'user_id' => $user->id,
                    'agent' => 'PingAgent',
                    'role' => 'assistant',
                    'content' => 'pong',
                    'attachments' => '[]',
                    'tool_calls' => '[]',
                    'tool_results' => '[]',
                    'usage' => json_encode(['input_tokens' => 3, 'output_tokens' => 1], JSON_THROW_ON_ERROR),
                    'meta' => '{}',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $aiRuns = [
                ['run_type' => 'classify_scraps', 'status' => 'completed', 'document' => null, 'target' => ['type' => null, 'id' => null]],
                ['run_type' => 'extract_open_questions', 'status' => 'completed', 'document' => null, 'target' => ['type' => 'scrap', 'id' => $scrapIdMap['s4']]],
                ['run_type' => 'generate_document', 'status' => 'completed', 'document' => 'd1', 'target' => ['type' => 'document', 'id' => $documentIdMap['d1']]],
                ['run_type' => 'generate_document', 'status' => 'failed', 'document' => 'd2', 'target' => ['type' => 'document', 'id' => $documentIdMap['d2']]],
                ['run_type' => 'summarize_constraints', 'status' => 'completed', 'document' => 'd2', 'target' => ['type' => 'document', 'id' => $documentIdMap['d2']]],
                ['run_type' => 'generate_spec_outline', 'status' => 'queued', 'document' => 'd3', 'target' => ['type' => 'document', 'id' => $documentIdMap['d3']]],
            ];

            foreach ($aiRuns as $index => $run) {
                DB::table('ai_runs')->insert([
                    'user_id' => $user->id,
                    'target_type' => $run['target']['type'] === null ? null : 'App\\Models\\'.Str::studly($run['target']['type']),
                    'target_id' => $run['target']['id'],
                    'result_document_id' => $run['document'] === null ? null : $documentIdMap[$run['document']],
                    'run_type' => $run['run_type'],
                    'agent_name' => 'PingAgent',
                    'conversation_id' => $conversationId,
                    'provider' => 'bedrock',
                    'model' => 'seed-demo-model',
                    'provider_run_id' => 'run-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'status' => $run['status'],
                    'prompt' => 'Seeded dashboard AI run.',
                    'input_payload' => json_encode(['scrap_count' => 3 + $index], JSON_THROW_ON_ERROR),
                    'output_payload' => json_encode(['result' => $run['status']], JSON_THROW_ON_ERROR),
                    'usage' => json_encode(['input_tokens' => 100 + $index, 'output_tokens' => 20 + $index], JSON_THROW_ON_ERROR),
                    'error_message' => $run['status'] === 'failed' ? 'Simulated failure for dashboard prototype.' : null,
                    'started_at' => $now->subMinutes(20 - $index),
                    'completed_at' => in_array($run['status'], ['completed', 'failed'], true) ? $now->subMinutes(19 - $index) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
