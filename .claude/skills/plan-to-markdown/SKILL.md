---
name: plan-to-markdown
description: This skill should be used when the user invokes plan mode, uses /plan, asks to "create a plan", "plan this feature", "plan this", or when ExitPlanMode has just been called and approved. Automatically saves the approved plan to the database as a Scrap, and queues Embedding generation and AI summarization.
---

# Plan to Markdown

## Purpose

プランモード（`/plan`）で承認されたプランを DB（scraps テーブル）へ保存し、以下を自動キューに積む：

- **Embedding 生成**（pgvector による類似検索に使用）
- **AI 要約生成**（インボックスの summary フィールドに反映）

プロジェクト名はスクリプトが `PROJECT_DIR` の `basename` から自動取得する。

## Workflow

**ExitPlanMode が承認された直後**、以下を実行すること：

```bash
bash <skill-dir>/scripts/save-plan.sh "{plan-title-slug}"
```

- `{plan-title-slug}` はプランの内容を表す英数字・ハイフンのみの短いスラッグ（例: `add-user-auth`, `embed-posts`, `sync-mode`）
- `<skill-dir>` は実際のスキルディレクトリのパス（例: `/opt/home-admin/yomitoki/.claude/skills/plan-to-markdown`）に置き換えること

**実行タイミングの注意：**
- プランモード中（ExitPlanMode 呼び出し前）は書き込み禁止のため、スクリプトは必ず承認後に実行すること
- プランを承認したターンの最初の行動として実行すること

## Output

```
✓ Plan saved: scrap #42 "add-user-auth"
```

保存後、キューワーカーが Embedding と summary を非同期で生成する。

## Bundled Script

- **`scripts/save-plan.sh`** — 最新プランを DB へ保存し、プロジェクト情報（`meta.project`）を自動付与する CLI
