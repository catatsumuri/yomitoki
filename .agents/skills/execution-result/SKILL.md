---
name: execution-result
description: Use this skill after a plan has been fully implemented to record the execution result. Attach a summary of what was done as a child scrap to the original plan. Invoke when the user says "結果を記録", "実行結果を保存", "attach result", or after completing implementation that was preceded by a /plan.
---

# Execution Result

## Purpose

実装完了後、プランに対して実行結果を子スクラップとして紐付ける。

- プランが「何をするか」を記録するのに対し、実行結果は「何をしたか」を記録する
- 子スクラップとして紐付けることで、ダッシュボードでプランと結果を一緒に閲覧できる
- Embedding・AI 要約も非同期で自動生成される

## Workflow

実装が完了したら、以下の手順を実行すること：

### 1. 実行結果マークダウンを書く

`storage/app/plans-tmp/result-{plan-slug}.md` に実行結果を書き込む：

```
# 実行結果: {plan-title}

## 実施内容

- 変更・作成したファイルと概要
- 主な実装の決定事項

## 変更ファイル

（git diff --stat や直接列挙）

## 備考

（想定外の発見、今後の課題など）
```

### 2. スクリプトを実行する

```bash
bash <skill-dir>/scripts/attach-result.sh "{plan-slug}"
```

- `{plan-slug}` は対応するプランのスラッグ（`plans:save` 実行時に出力された `slug: xxx` の値）
- `<skill-dir>` は実際のスキルディレクトリのパス（例: `/opt/home-admin/yomitoki/.agents/skills/execution-result`）に置き換えること

## Output

```
✓ Result attached: scrap #43 → plan "add-user-auth"
```

## Bundled Script

- **`scripts/attach-result.sh`** — `storage/app/plans-tmp/result-{slug}.md` を読み取り、`plans:result` artisan コマンドで DB に保存する CLI
