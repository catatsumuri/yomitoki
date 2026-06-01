<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KnowledgeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now();

        $existing = DB::table('users')->where('email', 'demo@example.com')->first();

        if ($existing) {
            $userId = $existing->id;
        } else {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::transaction(function () use ($now, $userId): void {
            $scrapIds = DB::table('scraps')->where('user_id', $userId)->pluck('id');
            $documentIds = DB::table('documents')->where('user_id', $userId)->pluck('id');

            DB::table('document_scraps')->whereIn('document_id', $documentIds)->delete();
            DB::table('scrap_relations')->whereIn('from_scrap_id', $scrapIds)->orWhereIn('to_scrap_id', $scrapIds)->delete();
            DB::table('documents')->where('user_id', $userId)->delete();
            DB::table('scraps')->where('user_id', $userId)->delete();
            DB::table('scrap_sources')->where('user_id', $userId)->delete();

            $sourceIds = $this->seedSources($userId, $now);
            $scrapIdMap = $this->seedScraps($userId, $now, $sourceIds);
            $this->wireParents($scrapIdMap);
            $documentData = $this->seedDocuments($userId, $now);
            $this->seedDocumentScraps($now, $documentData, $scrapIdMap);
        });
    }

    /** @return array<string, int> */
    private function seedSources(int $userId, CarbonImmutable $now): array
    {
        $sources = [
            'support-inbox' => ['source_type' => 'inquiry', 'name' => 'サポート受信箱', 'channel' => 'email'],
            'bug-tracker' => ['source_type' => 'daily_report', 'name' => 'バグトラッカー', 'channel' => 'internal'],
            'tech-notes' => ['source_type' => 'research', 'name' => '技術調査ノート', 'channel' => 'internal'],
            'spec-log' => ['source_type' => 'meeting_note', 'name' => '仕様変更ログ', 'channel' => 'internal'],
            'incident-log' => ['source_type' => 'daily_report', 'name' => 'インシデントログ', 'channel' => 'internal'],
        ];

        $ids = [];

        foreach ($sources as $key => $source) {
            $ids[$key] = DB::table('scrap_sources')->insertGetId([
                'user_id' => $userId,
                'source_type' => $source['source_type'],
                'name' => $source['name'],
                'external_id' => $key,
                'uri' => 'https://example.test/'.$key,
                'channel' => $source['channel'],
                'meta' => json_encode(['seeded' => true], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedScraps(int $userId, CarbonImmutable $now, array $sourceIds): array
    {
        $scraps = [

            // ── 問い合わせ対応ログ ──────────────────────────────────────────
            'inq-01' => [
                'source' => 'support-inbox', 'source_type' => 'inquiry',
                'title' => '問い合わせ：ログインができない',
                'slug' => 'inquiry-cannot-login',
                'status' => 'processed',
                'occurred_at' => $now->subDays(10),
                'content_markdown' => <<<'MD'
## 問い合わせ内容

ユーザーより「昨日まで使えていたのに今日はログインできない」との連絡。
ブラウザはChrome最新版、OSはWindows 11。エラーメッセージは「認証に失敗しました」のみで、
詳細なエラーコードは表示されていない。パスワードは間違いなく正しいと言っている。

- 受付日時: 2026-05-21 10:32
- ユーザーID: U-4821
- 優先度: 高
- 過去の問い合わせ: なし

## 状況メモ

同じ時間帯に他のユーザーからも「ログインできない」という問い合わせが2件届いており、
単発の事象ではない可能性がある。まず再現確認と直近のデプロイ・設定変更を確認する。
MD,
                'summary' => 'ユーザーが突然ログインできなくなったという問い合わせ。Chrome/Windows環境で認証失敗が発生している。',
            ],
            'inq-02' => [
                'source' => 'support-inbox', 'source_type' => 'inquiry', 'parent' => 'inq-01',
                'title' => '初期調査：セッションTTL変更による一括無効化が原因',
                'status' => 'processed',
                'occurred_at' => $now->subDays(10)->addHours(1),
                'content_markdown' => <<<'MD'
## 調査結果

キャッシュ・Cookie削除を依頼したが症状が続いた。
ログを確認したところ、先日のセッションTTL短縮（180日→30日）の影響で
90日以上前に作成されたセッションが強制無効化されていた。

影響ユーザー: 約340件。
MD,
                'summary' => 'セッションTTL変更による既存セッション一括無効化が原因。約340ユーザーに影響。',
            ],
            'inq-03' => [
                'source' => 'support-inbox', 'source_type' => 'inquiry', 'parent' => 'inq-01',
                'title' => '対応：影響ユーザー340件へのメール送付とパスワードリセット誘導',
                'status' => 'processed',
                'occurred_at' => $now->subDays(10)->addHours(2),
                'content_markdown' => <<<'MD'
## 対応内容

1. 当該ユーザーにパスワードリセットリンクを個別送付
2. 影響ユーザー340件にメール一斉送信（「再ログインが必要です」の案内）
3. ヘルプページにセッション切れ時の対処法を追記

翌日までにほぼ全員が再ログイン完了。
MD,
                'summary' => '影響ユーザー340件にメール送付、パスワードリセット誘導で翌日ほぼ解消。',
            ],
            'inq-04' => [
                'source' => 'support-inbox', 'source_type' => 'inquiry', 'parent' => 'inq-01',
                'title' => '再発防止：セッション変更時の事前通知フロー整備',
                'status' => 'raw',
                'occurred_at' => $now->subDays(9),
                'content_markdown' => <<<'MD'
## 再発防止策

- セッションTTLなど認証設定変更前に「影響ユーザー数の試算」を必須とする
- 大規模セッション無効化が発生する変更は、リリース1週間前にメール通知を行う
- エラーメッセージを「認証に失敗しました」→「セッションが切れました。再度ログインしてください」に改善

担当: @yamamoto 2026-05-31までに対応
MD,
                'summary' => 'セッション変更時の事前通知フロー整備とエラーメッセージ改善を提案。',
            ],

            'inq-05' => [
                'source' => 'support-inbox', 'source_type' => 'inquiry',
                'title' => '問い合わせ：エクスポートCSVがExcelで文字化けする',
                'slug' => 'inquiry-csv-garbled',
                'status' => 'processed',
                'occurred_at' => $now->subDays(7),
                'content_markdown' => <<<'MD'
「データをCSVエクスポートしてExcelで開くと日本語が文字化けする」との報告。
Excel 2021 (Windows)で発生。macOSのNumbersでは正常に表示される。
Google Chromeで自動ダウンロードされたファイルをそのまま開いている。

- ユーザーID: U-2091 / 優先度: 中

## 補足

先月まで問題なかったとのことで、先週リリースしたエクスポート機能の改修が原因の可能性あり。
CSVを直接テキストエディタで開いた場合は文字化けしていないことをユーザーが確認済み。
つまりExcel側の読み込み処理に起因している可能性が高い。
MD,
                'summary' => 'CSVエクスポートがWindowsのExcelで文字化けする。macOSでは正常。',
            ],
            'inq-06' => [
                'source' => 'support-inbox', 'source_type' => 'inquiry', 'parent' => 'inq-05',
                'title' => '原因・対応：BOM付きUTF-8での出力に変更',
                'status' => 'processed',
                'occurred_at' => $now->subDays(7)->addHours(3),
                'content_markdown' => <<<'MD'
## 原因

現在のCSVエクスポートはUTF-8（BOMなし）で出力している。
WindowsのExcelはBOMなしUTF-8をShift-JISとして解釈するため文字化けが発生。

## 対応

CSVエクスポート処理にBOM（`\xEF\xBB\xBF`）を先頭に付与するよう修正。PR #412マージ済み。
MD,
                'summary' => 'BOMなしUTF-8が原因。BOM付きUTF-8に変更するPRをマージ済み。',
            ],

            'inq-07' => [
                'source' => 'support-inbox', 'source_type' => 'inquiry',
                'title' => '問い合わせ：通知メールが届かない',
                'slug' => 'inquiry-no-notification-email',
                'status' => 'processed',
                'occurred_at' => $now->subDays(5),
                'content_markdown' => <<<'MD'
「タスク完了の通知メールが来なくなった」との報告。3日前（5/24頃）から届いていない。
スパムフォルダも確認済みで、迷惑メール判定されているわけでもないとのこと。
以前はタスクを完了するたびに通知が来ていた。

- ユーザーID: U-7734 / 優先度: 中
- 利用プラン: Business

## 補足

同じ日に別のユーザー（U-8901）からも「通知が来ない」という問い合わせがあった。
複数ユーザーに影響が出ているため、個別設定ではなくシステム側の問題と推測。
SendGridのダッシュボードを確認する。
MD,
                'summary' => '3日前から通知メールが届かなくなった。スパムフォルダにもない。',
            ],
            'inq-08' => [
                'source' => 'support-inbox', 'source_type' => 'inquiry', 'parent' => 'inq-07',
                'title' => '調査結果：SendGridの月間送信上限超過',
                'status' => 'processed',
                'occurred_at' => $now->subDays(5)->addHours(2),
                'content_markdown' => <<<'MD'
SendGridのダッシュボードを確認したところ、月間送信数の上限（50,000通）に
5/27時点で到達。それ以降のメール送信がキューに積まれたまま停止していた。

## 対応

プランをEssentials→Proにアップグレード。滞留メール約2,800通を再送信。
送信制限への接近アラートを設定した。
MD,
                'summary' => 'SendGridの月間送信上限超過が原因。プランアップグレードと滞留メール再送で解消。',
            ],

            // ── バグレポート + 対応記録 ─────────────────────────────────────
            'bug-01' => [
                'source' => 'bug-tracker', 'source_type' => 'daily_report',
                'title' => 'バグ：管理画面のユーザー一覧表示が極端に遅い',
                'slug' => 'bug-admin-user-list-slow',
                'status' => 'processed',
                'occurred_at' => $now->subDays(8),
                'content_markdown' => <<<'MD'
## 症状

/admin/users の表示に平均2.8秒かかっている。
ユーザー数が増えるにつれて悪化しており、5,000件で約8秒に達する。
先月まではほぼ即座に表示されていたが、ユーザー数が5,000件を超えたあたりから急に重くなった。

本番・ステージング両方で再現。ユーザー数: 約5,200件。

## 再現手順

1. 管理画面にadminでログイン
2. /admin/users を開く
3. ページ読み込みが完了するまでの時間を計測（Chromeのネットワークタブ）

## 影響

管理者が日次でユーザー一覧を確認する運用をしており、毎回8秒待ちが発生している。
CSの担当者から「重すぎて使い物にならない」というフィードバックが来ている。
MD,
                'summary' => '管理画面のユーザー一覧が2.8〜8秒かかる。ユーザー増加に比例して悪化している。',
            ],
            'bug-02' => [
                'source' => 'bug-tracker', 'source_type' => 'daily_report', 'parent' => 'bug-01',
                'title' => '原因調査：N+1クエリの発生箇所を特定',
                'status' => 'processed',
                'occurred_at' => $now->subDays(8)->addHours(2),
                'content_markdown' => <<<'MD'
Telescope でクエリを確認したところ、各ユーザーのロール・最終ログイン・所属チームを
取得するクエリがそれぞれN回発行されていた。
5,000件のユーザーで約15,000クエリが発行されていることを確認。

Eager loadingが一切設定されていなかった。
MD,
                'summary' => 'N+1クエリが原因。5,000件で約15,000クエリ発行。Eager loadingが未設定。',
            ],
            'bug-03' => [
                'source' => 'bug-tracker', 'source_type' => 'daily_report', 'parent' => 'bug-01',
                'title' => '修正：with()によるEager loading追加',
                'status' => 'processed',
                'occurred_at' => $now->subDays(7),
                'content_markdown' => <<<'MD'
```php
$users = User::with(['role', 'latestLogin', 'team'])->paginate(50);
```

`with()`で関連モデルを一括取得するよう変更。PR #408、2026-05-26リリース。
MD,
                'summary' => 'with()でEager loading追加。クエリ数を1/30に削減。PR #408でリリース済み。',
            ],
            'bug-04' => [
                'source' => 'bug-tracker', 'source_type' => 'daily_report', 'parent' => 'bug-01',
                'title' => 'パフォーマンス計測：修正後の効果測定',
                'status' => 'processed',
                'occurred_at' => $now->subDays(6),
                'content_markdown' => <<<'MD'
| 件数 | 修正前 | 修正後 |
|------|--------|--------|
| 1,000件 | 1.2s | 0.08s |
| 3,000件 | 3.1s | 0.12s |
| 5,200件 | 8.4s | 0.18s |

クエリ数: 15,600 → 4。目標（1秒以内）を大幅達成。
MD,
                'summary' => '修正後は5,200件でも0.18秒。クエリ数を15,600から4に削減し目標達成。',
            ],

            'bug-05' => [
                'source' => 'bug-tracker', 'source_type' => 'daily_report',
                'title' => 'バグ：夜間バッチ処理が途中で停止する',
                'slug' => 'bug-nightly-batch-stops',
                'status' => 'processed',
                'occurred_at' => $now->subDays(6),
                'content_markdown' => <<<'MD'
毎朝2時に実行する請求書一括生成バッチが約30分で停止している。
ログには何も残っておらず、プロセスが突然終了している。先週の木曜から毎回発生しており、
月次請求書の生成が止まっているため早急に対処が必要。

対象: `artisan invoice:generate-monthly`
発生頻度: 毎回（先週から）
最後に正常完了した日: 2026-05-19

## 影響

月次請求書が生成されず、経理部門から「今月分の請求書が来ない」という問い合わせが複数届いている。
手動での請求書発行に切り替えているが、件数が多く負担が大きい。
MD,
                'summary' => '毎朝2時の請求書一括生成バッチが30分で無音停止。ログなし。',
            ],
            'bug-06' => [
                'source' => 'bug-tracker', 'source_type' => 'daily_report', 'parent' => 'bug-05',
                'title' => 'ログ解析：OOMキラーによるプロセス強制終了',
                'status' => 'processed',
                'occurred_at' => $now->subDays(6)->addHours(3),
                'content_markdown' => <<<'MD'
`/var/log/syslog`を確認したところ、バッチ実行中にOOMキラーが動作していた。

```
kernel: Out of memory: Kill process 12483 (php) score 892
```

バッチがユーザー全件（約12万件）を一括取得しており、
メモリ使用量がピーク時に14GBに達していた（サーバーメモリ: 8GB）。
MD,
                'summary' => 'OOMキラーが原因。12万件一括取得でメモリ14GB使用（サーバー上限8GB）。',
            ],
            'bug-07' => [
                'source' => 'bug-tracker', 'source_type' => 'daily_report', 'parent' => 'bug-05',
                'title' => '対応：chunk処理への変更とメモリ上限設定',
                'status' => 'processed',
                'occurred_at' => $now->subDays(5),
                'content_markdown' => <<<'MD'
`User::all()`を`User::chunk(500, ...)`に変更し、`memory_limit = 512M`を明示設定。
処理進捗をログに記録するよう改修した。

翌朝のバッチで正常完了を確認。処理時間: 28分→42分（チャンク化により増加）。
MD,
                'summary' => 'chunk(500)への変更とメモリ上限設定で解消。処理時間は28→42分に増加。',
            ],

            // ── 技術調査スパイク ──────────────────────────────────────────────
            'res-01' => [
                'source' => 'tech-notes', 'source_type' => 'research',
                'title' => '調査：全文検索 vs ベクトル検索の比較',
                'slug' => 'research-fulltext-vs-vector-search',
                'status' => 'processed',
                'occurred_at' => $now->subDays(12),
                'content_markdown' => <<<'MD'
ナレッジベースの検索基盤として、全文検索とベクトル検索のどちらを採用するか比較調査。

現状、スクラップ件数が増えてきており「あの仕様どうだったっけ」という曖昧な検索への需要が高まっている。
単純なキーワード検索では「ログイン」と打っても「認証」関連のスクラップが出てこない問題がある。

## 比較軸

- 実装コスト（既存インフラへの追加工数）
- 検索精度（特に日本語の意味検索・表記揺れ対応）
- 運用コスト（APIコスト・ストレージ）
- スケーラビリティ（件数が増えたときの性能）

## 前提条件

- DBはPostgreSQLを使用中
- スクラップ件数: 現在約2,000件、1年後に10,000件想定
- 検索は「自分のスクラップ内での意味検索」がメインユースケース
MD,
                'summary' => 'ナレッジベース検索基盤の選定。全文検索とベクトル検索を実装コスト・精度・運用コストで比較。',
            ],
            'res-02' => [
                'source' => 'tech-notes', 'source_type' => 'research', 'parent' => 'res-01',
                'title' => '調査結果：PostgreSQL全文検索の評価',
                'status' => 'processed',
                'occurred_at' => $now->subDays(12)->addHours(2),
                'content_markdown' => <<<'MD'
**メリット**: 追加インフラ不要、インデックスによる高速検索、実装がシンプル。

**デメリット**: 日本語の意味検索が弱い（「ログイン」で「認証」が引っかからない）。
同義語・表記揺れ対応には辞書整備が必要。

**結論**: キーワード完全一致には十分だが、曖昧な意味検索には不向き。
MD,
                'summary' => 'PostgreSQL FTSは実装が簡単だが日本語意味検索が弱く、曖昧検索には不向き。',
            ],
            'res-03' => [
                'source' => 'tech-notes', 'source_type' => 'research', 'parent' => 'res-01',
                'title' => '調査結果：pgvectorによるベクトル検索の評価',
                'status' => 'processed',
                'occurred_at' => $now->subDays(11),
                'content_markdown' => <<<'MD'
**メリット**: 意味的類似検索が可能（「ログイン」→「認証」「サインイン」も引っかかる）。
PostgreSQL拡張として追加できインフラ変更が最小。

**デメリット**: Embedding生成にAPIコスト発生。保存データ量増加（1536次元で約6KB/レコード）。

**コスト試算**: 10,000スクラップ×平均500トークン = $0.10程度（ほぼ無視できる）
MD,
                'summary' => 'pgvectorは意味検索が可能で追加インフラ不要。埋め込みコストは10,000件で$0.10程度と低い。',
            ],
            'res-04' => [
                'source' => 'tech-notes', 'source_type' => 'research', 'parent' => 'res-01',
                'title' => '採用決定：pgvectorによるベクトル検索を採用',
                'status' => 'processed',
                'occurred_at' => $now->subDays(11)->addHours(4),
                'content_markdown' => <<<'MD'
**決定: pgvectorを採用する。**

意味検索の品質が本システムの価値に直結するため精度を優先した。

実装方針:
- モデル: `text-embedding-3-large`（1536次元）
- 類似度しきい値: コサイン距離 0.2
- Embedding生成: 保存時にQueueジョブで非同期生成

承認: @tanaka 2026-05-19
MD,
                'summary' => 'pgvector採用を決定。精度優先で意味検索を実現。非同期でEmbedding生成。',
            ],

            'res-05' => [
                'source' => 'tech-notes', 'source_type' => 'research',
                'title' => '調査：Redisキャッシュ戦略の検討',
                'slug' => 'research-redis-cache-strategy',
                'status' => 'processed',
                'occurred_at' => $now->subDays(9),
                'content_markdown' => <<<'MD'
APIレスポンスのキャッシュ戦略を検討。現状はキャッシュなし。

先日のN+1クエリ修正で管理画面の速度問題は解消したが、ダッシュボードの集計クエリが
依然重く、ピーク時にDB負荷が高い状態が続いている。キャッシュ導入で根本的に解決したい。

## 対象エンドポイント（優先度順）

1. ダッシュボード統計集計（毎リクエストで重いSELECT）
2. ユーザープロフィール（変更頻度低い）
3. タグ・カテゴリ一覧（ほぼ静的）

## 比較対象

- LaravelのDBキャッシュドライバ（追加インフラ不要）
- Redis（高速・TTL管理が柔軟・pub/subも使える）
MD,
                'summary' => 'APIキャッシュにRedisを採用。ダッシュボード・検索候補等に適用。本番はAzure Cache for Redis予定。',
            ],

            // ── 仕様変更の背景メモ ─────────────────────────────────────────────
            'spec-01' => [
                'source' => 'spec-log', 'source_type' => 'meeting_note',
                'title' => '仕様変更：APIレート制限の追加',
                'slug' => 'spec-api-rate-limiting',
                'status' => 'processed',
                'occurred_at' => $now->subDays(11),
                'content_markdown' => <<<'MD'
認証済みAPIに対してレート制限（1分間60リクエスト）を追加する。

先日の異常アクセス事象を受けて、再発防止のために実装する。
通常の利用では1分間に60リクエストを超えることはないため、一般ユーザーへの影響はない想定。

## 変更内容

- 認証済みユーザー: 60 req/min
- 未認証リクエスト: 10 req/min
- 超過時レスポンス: HTTP 429 Too Many Requests + `Retry-After`ヘッダー

## 考慮事項

外部連携ツール（Webhook受信、バッチ連携）が高頻度でAPIを叩いている場合に
誤って制限に引っかかる可能性がある。既知の連携先を事前に確認しておく必要あり。
MD,
                'summary' => 'APIに1分間60リクエストのレート制限を追加。未認証は10req/min。',
            ],
            'spec-02' => [
                'source' => 'spec-log', 'source_type' => 'meeting_note', 'parent' => 'spec-01',
                'title' => '背景：特定IPからの異常アクセスが急増',
                'status' => 'processed',
                'occurred_at' => $now->subDays(11)->addHours(1),
                'content_markdown' => <<<'MD'
2026-05-18 深夜、特定のIPアドレスから1分間に800リクエストが継続的に発生。
DBの接続数が上限（100）に達し、一般ユーザーへのサービスが断続的に不安定になった。

対象エンドポイント: `/api/scraps/search`（ベクトル検索）

クローラーや総当たり攻撃ではなく、外部連携スクリプトのループバグと推測。
MD,
                'summary' => '深夜に特定IPから800req/minの異常アクセス。DB接続数上限でサービス不安定化。',
            ],
            'spec-03' => [
                'source' => 'spec-log', 'source_type' => 'meeting_note', 'parent' => 'spec-01',
                'title' => '検討：IPブロック vs レート制限の比較',
                'status' => 'processed',
                'occurred_at' => $now->subDays(11)->addHours(2),
                'content_markdown' => <<<'MD'
**IPブロック**: 即効性が高いが、正規ユーザーが同一IPを使う場合に誤ブロックのリスクあり。不採用。

**レート制限（採用）**: ユーザー単位で制限するためIPが変わっても有効。
通常利用（60req/min以下）には影響なし。Laravelの`throttle`ミドルウェアで実装可能。

WAFの導入も検討したがコストと優先度から今期は見送り。
MD,
                'summary' => 'IPブロックは誤ブロックリスクあり不採用。ユーザー単位のレート制限を採用。',
            ],
            'spec-04' => [
                'source' => 'spec-log', 'source_type' => 'meeting_note', 'parent' => 'spec-01',
                'title' => '実装・リリース記録',
                'status' => 'processed',
                'occurred_at' => $now->subDays(10),
                'content_markdown' => <<<'MD'
```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () { ... });
```

429レスポンスに`Retry-After`ヘッダーを付与するよう設定。

2026-05-21 14:00 本番デプロイ完了。以降の異常アクセスは即座に429で弾かれており正常化。

承認: @suzuki (2026-05-20)
MD,
                'summary' => 'throttleミドルウェアで実装。2026-05-21リリース後、異常アクセス問題は解消。',
            ],

            'spec-05' => [
                'source' => 'spec-log', 'source_type' => 'meeting_note',
                'title' => '仕様変更：ユーザー権限モデルをRBACに再設計',
                'slug' => 'spec-rbac-redesign',
                'status' => 'processed',
                'occurred_at' => $now->subDays(14),
                'content_markdown' => <<<'MD'
`is_admin`フラグによる2値権限を廃止し、ロールベースアクセス制御（RBAC）に移行する。

現状の`is_admin: true/false`の2値では、増加する外部委託スタッフや閲覧専用ユーザーへの
対応ができなくなってきた。今後のチーム機能・承認フロー実装に向けても今のうちに整理が必要。

## 新ロール定義

| ロール | 説明 |
|--------|------|
| owner | 組織全体の管理権限（削除・請求含む） |
| admin | ユーザー管理・設定変更可 |
| editor | コンテンツの作成・編集可 |
| viewer | 閲覧のみ |
| guest | 限定公開コンテンツのみ閲覧可 |

## 移行方針

既存の`is_admin = true`ユーザーは`admin`ロールに自動移行。
`is_admin = false`は`editor`（デフォルト）に移行する。
MD,
                'summary' => 'is_adminフラグをRBACに変更。5ロール体制へ移行。',
            ],
            'spec-06' => [
                'source' => 'spec-log', 'source_type' => 'meeting_note', 'parent' => 'spec-05',
                'title' => '背景：is_adminフラグの限界と問題事例',
                'status' => 'processed',
                'occurred_at' => $now->subDays(14)->addHours(1),
                'content_markdown' => <<<'MD'
1. **外部委託スタッフへの権限付与が困難**: 特定コンテンツだけ編集させたいがadmin全体を渡すしかない
2. **監査ログの要求**: 権限ロールと紐付けた変更履歴が必要
3. **将来機能**: チーム機能・承認フローを実装する際に2値では対応不可

`is_admin`がBooleanカラムのため、移行時にデータ変換が必要。
MD,
                'summary' => '外部委託への細粒度権限付与、監査ログ要求、将来機能拡張の3点がis_admin廃止の背景。',
            ],
            'spec-07' => [
                'source' => 'spec-log', 'source_type' => 'meeting_note', 'parent' => 'spec-05',
                'title' => '移行計画とリリーススケジュール',
                'status' => 'raw',
                'occurred_at' => $now->subDays(13),
                'content_markdown' => <<<'MD'
1. `roles`テーブル・`role_user`ピボットテーブルを追加
2. 既存`is_admin = true`ユーザーを`admin`ロールに自動移行
3. PolicyクラスをRBACチェックに更新
4. フロントエンドの権限チェック箇所を更新

スケジュール: 実装〜05-30 / ステージング確認05-31〜06-03 / 本番リリース06-05（予定）
MD,
                'summary' => 'ロールテーブル追加→既存ユーザー移行→Policy更新の3ステップで移行。本番リリースは6/5予定。',
            ],

            // ── インシデント対応ログ ───────────────────────────────────────────
            'inc-01' => [
                'source' => 'incident-log', 'source_type' => 'daily_report',
                'title' => 'インシデント：本番DBのコネクション数が上限に達した',
                'slug' => 'incident-db-connection-exhausted',
                'status' => 'processed',
                'occurred_at' => $now->subDays(4),
                'content_markdown' => <<<'MD'
2026-05-28 02:47 に本番PostgreSQLのコネクション数が上限（100）に達し、
新規リクエストの処理が断続的に失敗した。

深夜帯の月次バッチ実行中に発生。ALBのヘルスチェック失敗でCloudWatchアラートが発報し発覚。
オンコール担当が手動対応して03:21に解消したが、今後の再発防止策が必要。

## 影響範囲

- 発生時間: 02:47〜03:21（約34分）
- 影響ユーザー: 推定120名（深夜帯のためAPIアクセスが主）
- エラーレート: ピーク時 43%
- ユーザーへの通知: なし（深夜帯のため翌朝確認）

## 関連する直近の変更

前日（2026-05-27）に月次レポート生成バッチを新規リリース済み。
このバッチとの関連が疑われる。
MD,
                'summary' => '2026-05-28 02:47〜03:21にDB接続数上限で障害発生。約34分間、推定120名に影響。',
            ],
            'inc-02' => [
                'source' => 'incident-log', 'source_type' => 'daily_report', 'parent' => 'inc-01',
                'title' => '検知・初動対応',
                'status' => 'processed',
                'occurred_at' => $now->subDays(4)->addMinutes(10),
                'content_markdown' => <<<'MD'
02:47 CloudWatchアラート（DB接続数 > 90）が発報。
02:51 オンコール担当 @kato が対応開始。

1. pg_stat_activityでアイドル接続48件（1時間以上）を確認
2. pg_terminate_backend()でアイドル接続を強制切断
3. 03:08 接続数が正常範囲（35前後）に戻り、サービスが回復
MD,
                'summary' => 'CloudWatchアラートで検知。アイドル接続48件を強制切断し03:08に回復。',
            ],
            'inc-03' => [
                'source' => 'incident-log', 'source_type' => 'daily_report', 'parent' => 'inc-01',
                'title' => '根本原因分析',
                'status' => 'processed',
                'occurred_at' => $now->subDays(3),
                'content_markdown' => <<<'MD'
Laravelの接続プール設定（`database.connections.pgsql.pool`）が未設定のままだった。
前日リリースした月次レポート生成ジョブが大量の並行接続を開き、
接続を適切にクローズせずにジョブが終了していた。

接続プールなしでは各ジョブが新規接続を作成し、タイムアウトまで解放されない。
MD,
                'summary' => '月次レポートジョブが接続をクローズせず、接続プール未設定と組み合わさって枯渇。',
            ],
            'inc-04' => [
                'source' => 'incident-log', 'source_type' => 'daily_report', 'parent' => 'inc-01',
                'title' => '恒久対応と再発防止策',
                'status' => 'processed',
                'occurred_at' => $now->subDays(3)->addHours(2),
                'content_markdown' => <<<'MD'
1. ジョブ修正: 処理終了後に`DB::disconnect()`を明示呼び出し
2. 接続プール設定: 最大20接続/ワーカーに設定
3. PostgreSQL設定: `idle_in_transaction_session_timeout = 300000`（5分）
4. 監視強化: DB接続数のアラート閾値を90→70に変更

本番環境チェックリストに「DB接続プール設定確認」を追加。
MD,
                'summary' => 'ジョブ修正・接続プール設定・idle_timeout設定の3点で恒久対応。チェックリストに追加。',
            ],

            'inc-05' => [
                'source' => 'incident-log', 'source_type' => 'daily_report',
                'title' => 'インシデント：外部決済APIの障害で注文が完了しない',
                'slug' => 'incident-payment-api-outage',
                'status' => 'processed',
                'occurred_at' => $now->subDays(2),
                'content_markdown' => <<<'MD'
2026-05-30 13:15〜16:40（約3時間25分）にわたり、Stripe Webhookに障害が発生。
注文完了確認ができない状態が続き、CSへの「注文が完了しているか確認したい」問い合わせが急増した。

Stripeのステータスページで「Webhookの遅延」として報告されており、外部起因の障害。
ただし弊社側の設計（Webhook依存の注文確定）も問題を拡大させた要因の一つ。

## 影響範囲

- 発生時間: 13:15〜16:40（約3時間25分）
- 影響注文数: 47件（決済完了済み: 38件、未決済: 9件）
- CS問い合わせ件数: 23件
- 影響ユーザー: 47名
MD,
                'summary' => '2026-05-30にStripe Webhook障害で47件の注文が完了確認できない状態に。約3時間25分。',
            ],
            'inc-06' => [
                'source' => 'incident-log', 'source_type' => 'daily_report', 'parent' => 'inc-05',
                'title' => '暫定対応：決済状態の手動確認と顧客連絡',
                'status' => 'processed',
                'occurred_at' => $now->subDays(2)->addHours(1),
                'content_markdown' => <<<'MD'
1. Stripeダッシュボードで全注文の決済状態を手動確認
2. 決済完了済み38件: 注文確定メールを手動送信
3. 未決済9件: 状況説明メールを送付し再注文を案内
4. サイトにメンテナンスバナーを表示
MD,
                'summary' => '手動でStripe確認。決済済み38件に注文確定メール送付。未決済9件は案内メール送付。',
            ],
            'inc-07' => [
                'source' => 'incident-log', 'source_type' => 'daily_report', 'parent' => 'inc-05',
                'title' => '恒久対応：サーキットブレーカーと冪等性の実装',
                'status' => 'raw',
                'occurred_at' => $now->subDays(1),
                'content_markdown' => <<<'MD'
1. **Webhook冪等性確保**: Stripe`idempotency_key`を保存し、重複処理を防止
2. **定期的な決済状態同期**: 1時間ごとにStripe APIで状態確認するジョブを追加
3. **サーキットブレーカー**: Stripe APIが連続失敗した場合は注文を「保留」状態にし復旧後に再処理

担当: @nakamura、完了目標: 2026-06-10
MD,
                'summary' => 'Webhook冪等性確保・定期状態同期・サーキットブレーカー実装の3点で再発防止。',
            ],
        ];

        $idMap = [];

        foreach ($scraps as $key => $scrap) {
            $idMap[$key] = DB::table('scraps')->insertGetId([
                'user_id' => $userId,
                'scrap_source_id' => $sourceIds[$scrap['source']],
                'source_type' => $scrap['source_type'],
                'source_reference' => $key,
                'parent_id' => null,
                'title' => $scrap['title'],
                'slug' => $scrap['slug'] ?? null,
                'content' => $scrap['content_markdown'],
                'content_markdown' => $scrap['content_markdown'],
                'summary' => $scrap['summary'],
                'status' => $scrap['status'],
                'language' => 'ja',
                'occurred_at' => $scrap['occurred_at'],
                'last_activity_at' => $scrap['occurred_at'],
                'processed_at' => $scrap['status'] !== 'raw' ? $scrap['occurred_at']->addMinutes(3) : null,
                'extracted_data' => null,
                'meta' => json_encode(['seeded' => true], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $idMap;
    }

    private function wireParents(array $idMap): void
    {
        $parentMap = [
            'inq-02' => 'inq-01', 'inq-03' => 'inq-01', 'inq-04' => 'inq-01',
            'inq-06' => 'inq-05',
            'inq-08' => 'inq-07',
            'bug-02' => 'bug-01', 'bug-03' => 'bug-01', 'bug-04' => 'bug-01',
            'bug-06' => 'bug-05', 'bug-07' => 'bug-05',
            'res-02' => 'res-01', 'res-03' => 'res-01', 'res-04' => 'res-01',
            'spec-02' => 'spec-01', 'spec-03' => 'spec-01', 'spec-04' => 'spec-01',
            'spec-06' => 'spec-05', 'spec-07' => 'spec-05',
            'inc-02' => 'inc-01', 'inc-03' => 'inc-01', 'inc-04' => 'inc-01',
            'inc-06' => 'inc-05', 'inc-07' => 'inc-05',
        ];

        foreach ($parentMap as $child => $parent) {
            DB::table('scraps')
                ->where('id', $idMap[$child])
                ->update(['parent_id' => $idMap[$parent]]);
        }

        // 親の last_activity_at を最新の子の occurred_at に更新
        $parentKeys = array_unique(array_values($parentMap));

        foreach ($parentKeys as $parentKey) {
            $childIds = collect($parentMap)
                ->filter(fn ($p) => $p === $parentKey)
                ->keys()
                ->map(fn ($k) => $idMap[$k])
                ->all();

            $latestChildOccurredAt = DB::table('scraps')
                ->whereIn('id', $childIds)
                ->max('occurred_at');

            if ($latestChildOccurredAt) {
                DB::table('scraps')
                    ->where('id', $idMap[$parentKey])
                    ->update(['last_activity_at' => $latestChildOccurredAt]);
            }
        }
    }

    /**
     * @return array{map: array<string, int>, definitions: array<string, array<string, mixed>>}
     */
    private function seedDocuments(int $userId, CarbonImmutable $now): array
    {
        $documents = [
            'doc-inq' => [
                'title' => 'ログイン障害 対応報告書',
                'document_type' => 'report',
                'status' => 'final',
                'summary' => 'セッションTTL変更に伴うログイン障害の原因・対応・再発防止策をまとめた報告書。',
                'scraps' => ['inq-01', 'inq-02', 'inq-03', 'inq-04'],
                'content_markdown' => "# ログイン障害 対応報告書\n\nセッションTTL変更による既存セッション一括無効化が原因で、約340ユーザーがログインできなくなった障害の対応記録。\n\n影響ユーザー340件にメール送付し、パスワードリセットへ誘導。翌日ほぼ全員が復旧。\n\n再発防止として、セッション設定変更前の影響試算を必須化し、事前通知フローを整備する。",
            ],
            'doc-bug' => [
                'title' => 'パフォーマンス改善報告書：管理画面N+1問題',
                'document_type' => 'report',
                'status' => 'final',
                'summary' => '管理画面ユーザー一覧のN+1クエリ問題の原因調査・修正・効果測定をまとめた報告書。',
                'scraps' => ['bug-01', 'bug-02', 'bug-03', 'bug-04'],
                'content_markdown' => "# パフォーマンス改善報告書：管理画面N+1問題\n\nユーザー一覧表示が5,200件で8.4秒かかっていた問題を解決。Eager loadingの追加でクエリ数を15,600から4に削減し、0.18秒を達成した。",
            ],
            'doc-res' => [
                'title' => '技術選定記録：ナレッジベース検索基盤',
                'document_type' => 'spec',
                'status' => 'final',
                'summary' => '全文検索とベクトル検索の比較調査結果と、pgvector採用決定の背景をまとめた技術選定記録。',
                'scraps' => ['res-01', 'res-02', 'res-03', 'res-04'],
                'content_markdown' => "# 技術選定記録：ナレッジベース検索基盤\n\n意味的類似検索の実現のため全文検索とpgvectorを比較。精度優先でpgvector（text-embedding-3-large、1536次元）を採用した。保存時にQueueジョブで非同期Embedding生成。",
            ],
            'doc-inc' => [
                'title' => 'インシデント報告書：DB接続数枯渇障害',
                'document_type' => 'report',
                'status' => 'final',
                'summary' => '2026-05-28に発生したDB接続数枯渇障害の検知・対応・根本原因分析・再発防止策。',
                'scraps' => ['inc-01', 'inc-02', 'inc-03', 'inc-04'],
                'content_markdown' => "# インシデント報告書：DB接続数枯渇障害\n\n2026-05-28 02:47〜03:21、本番DBの接続数が上限に達し約34分間サービス劣化。月次レポートジョブの接続クローズ漏れと接続プール未設定が原因。ジョブ修正・接続プール設定・idle_timeout設定の3点で恒久対応した。",
            ],
        ];

        $idMap = [];

        foreach ($documents as $key => $doc) {
            $idMap[$key] = DB::table('documents')->insertGetId([
                'user_id' => $userId,
                'title' => $doc['title'],
                'document_type' => $doc['document_type'],
                'status' => $doc['status'],
                'content_markdown' => $doc['content_markdown'],
                'summary' => $doc['summary'],
                'outline' => null,
                'meta' => json_encode(['seeded' => true], JSON_THROW_ON_ERROR),
                'published_at' => $now->subHours(1),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return ['map' => $idMap, 'definitions' => $documents];
    }

    private function seedDocumentScraps(CarbonImmutable $now, array $documentData, array $scrapIdMap): void
    {
        foreach ($documentData['definitions'] as $key => $document) {
            $documentId = $documentData['map'][$key];

            foreach ($document['scraps'] as $position => $scrapKey) {
                DB::table('document_scraps')->insert([
                    'document_id' => $documentId,
                    'scrap_id' => $scrapIdMap[$scrapKey],
                    'position' => $position,
                    'role' => 'source',
                    'excerpt' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
