---
name: scrap-utils
description: Use this skill for basic Scrap CRUD operations from the CLI or scripts. Trigger when you need to list scraps/slugs, add a new scrap, update an existing scrap, or delete a scrap by slug. Especially useful for checking slug availability before creating plans or other scraps.
---

# Scrap Utils

## Purpose

Scrap の汎用 CRUD を CLI から行うユーティリティ。  
`plan-to-markdown` などのスキルがスラッグ重複を事前確認するためにも利用できる。

## Scripts

すべてのスクリプトは `<skill-dir>` を実際のパス（例: `/opt/home-admin/yomitoki/.claude/skills/scrap-utils`）に置き換えて実行すること。

### Slug 一覧を取得する

```bash
bash <skill-dir>/scripts/scrap-list.sh [source-type] [project-dir]
```

- 引数なしで全 slug を出力
- `source-type` を指定すると絞り込み（例: `plan`, `note`, `execution`）
- 出力: 1 行 1 slug（スクリプトからパイプしやすい形式）

**スラッグ重複チェックの例:**
```bash
bash <skill-dir>/scripts/scrap-list.sh | grep -c "^my-plan$"
# 0 なら未使用、1 以上なら重複
```

### Scrap を追加する

```bash
bash <skill-dir>/scripts/scrap-add.sh "{slug}" "{file}" [project-dir] [description]
```

- `{slug}`: 希望するスラッグ（重複時は自動でサフィックス付与）
- `{file}`: コンテンツの Markdown ファイルパス
- `[description]`: 指定すると AI 要約をスキップして summary に直接保存

### Scrap を更新する

```bash
bash <skill-dir>/scripts/scrap-update.sh "{slug}" [project-dir] [--title="..."] [--file="..."] [--status="..."] [--new-slug="..."]
```

- `{slug}`: 更新対象のスラッグ（必須）

### Scrap を削除する

```bash
bash <skill-dir>/scripts/scrap-delete.sh "{slug}" [project-dir]
```

- `{slug}`: 削除対象のスラッグ（必須）
- 子 Scrap も再帰的に削除される
- 確認プロンプトなし（`--force` 固定）

## Artisan コマンド（直接実行）

スクリプトを使わず Artisan コマンドを直接実行することもできる：

```bash
vendor/bin/sail artisan scraps:list [--source-type=] [--status=] [--json] [--slugs-only] [--limit=20]
vendor/bin/sail artisan scraps:add --title="..." --content="..." [--slug=] [--source-type=note] [--parent=] [--description=]
vendor/bin/sail artisan scraps:update --slug="..." [--title=] [--new-slug=] [--content=] [--file=] [--status=]
vendor/bin/sail artisan scraps:delete --slug="..." [--force]
```

## Bundled Scripts

- **`scripts/scrap-list.sh`** — slug 一覧を 1 行ずつ出力
- **`scripts/scrap-add.sh`** — ファイルから Scrap を作成
- **`scripts/scrap-update.sh`** — slug を指定して Scrap を更新
- **`scripts/scrap-delete.sh`** — slug を指定して Scrap を削除（子も含む）
