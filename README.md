# Yomitoki

AIエージェント（Claude Code など）が生成したプランを収集・閲覧するための個人用ダッシュボード。

エージェントが `/plan` を実行するたびにプランが自動で流れ込み、すべての意思決定の記録が一箇所に集まる。

## コンセプト

AIと作業していると、プランは承認されたあと消えていく。何を考えて、何を決めたかの文脈が残らない。

Yomitoki はその流れを受け止める場所。プランをインボックスに貯め、あとから読み返せるようにする。

## 仕組み

Claude Code の `plan-to-markdown` スキルがプランモード終了後に自動実行され、プランの Markdown をそのままデータベースへ投入する。フロントエンドのインボックスに `source_type: plan` として流れ込み、他のメモや記録と並んで表示される。

```
/plan → ExitPlanMode → save-plan.sh → plans:save → scraps テーブル → ダッシュボード
```

## ロードマップ

### Phase 1 — ローカル収集（現在）

このリポジトリ自身のプランを収集する。Claude Code の `plan-to-markdown` スキルがプランモード終了後に自動実行され、プランの Markdown をそのままデータベースへ投入する。

```
/plan → ExitPlanMode → save-plan.sh → plans:save → scraps テーブル → ダッシュボード
```

### Phase 2 — マルチプロジェクト収集（構想）

API エンドポイントとスキルの組み合わせで、別リポジトリ・別マシンのプランも収集できるようにする。他プロジェクトの `.env` に `YOMITOKI_URL` と `YOMITOKI_TOKEN` を置くだけでスキルが Yomitoki へ POST する構成。

```
別プロジェクトの /plan
  → save-plan.sh
      └─ curl POST /api/plans ──→ Yomitoki
           { title, content,         └─ meta.project で識別
             project, token }
```

## セットアップ

```bash
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan migrate
vendor/bin/sail artisan db:seed
vendor/bin/sail npm run build
```

## Claude Code 連携

### スキル（このプロジェクト用）

`.claude/skills/plan-to-markdown/` にスキルが入っている。プランが承認されたら即座に実行する：

```bash
bash .claude/skills/plan-to-markdown/scripts/save-plan.sh "feature-slug"
```

スキルは Claude が「実行すべき」と判断して初めて動く。確実に自動化したい場合はフックが必要。

### フック（配布・自動化用）

`PostToolUse` フックを `~/.claude/settings.json` に追加すると、`ExitPlanMode` のたびに自動実行される。スキルと違い Claude が忘れることがない。

```json
{
  "hooks": {
    "PostToolUse": [{
      "matcher": "ExitPlanMode",
      "hooks": [{
        "type": "command",
        "command": "bash /path/to/save-plan.sh \"plan\" \"$CLAUDE_PROJECT_DIR\""
      }]
    }]
  }
}
```

Phase 2 の API 実装後は、フック＋スキルをセットにしたプラグインとして配布する想定。
