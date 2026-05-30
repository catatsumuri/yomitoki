---
name: plan-to-markdown
description: This skill should be used when the user invokes plan mode, asks to "create a plan", "plan this feature", "plan this", or after a plan has been approved. Automatically saves the approved plan to the database as a Scrap, and queues Embedding generation and AI summarization.
---

# Plan to Markdown

## Purpose

承認されたプランを DB（scraps テーブル）へ保存し、以下を自動キューに積む：

- **Embedding 生成**（pgvector による類似検索に使用）
- **AI 要約生成**（インボックスの summary フィールドに反映）

プロジェクト名はスクリプトが `PROJECT_DIR` の `basename` から自動取得する。

## Workflow

プランが承認されたら、まずプランの内容をファイルに保存してから以下を実行すること：

```bash
bash <skill-dir>/scripts/save-plan.sh "{plan-file}" "{plan-title-slug}" "{project-dir}" "{description}"
```

- `{plan-file}` はプランの Markdown ファイルの絶対パス（必須）
- `{plan-title-slug}` はプランの内容を表す英数字・ハイフンのみの短いスラッグ（例: `add-user-auth`, `embed-posts`）
- `{project-dir}` はプロジェクトルートの絶対パス（省略時は `pwd`）
- `{description}` はプランの短い説明文（省略可。指定すると AI 要約をスキップして summary に直接保存される）
- `<skill-dir>` は実際のスキルディレクトリのパス（例: `/opt/home-admin/yomitoki/.agents/skills/plan-to-markdown`）に置き換えること

## Output

```
✓ Plan saved: scrap #42 "add-user-auth"
```

description を渡した場合は summary に直接保存され AI 要約をスキップする。省略した場合はキューワーカーが Embedding と summary を非同期で生成する。

## Bundled Script

- **`scripts/save-plan.sh`** — 指定したプランファイルを DB へ保存し、プロジェクト情報（`meta.project`）を自動付与する CLI
