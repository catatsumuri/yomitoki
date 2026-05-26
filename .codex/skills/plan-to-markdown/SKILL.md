---
name: plan-to-markdown
description: Use this skill when Codex plan mode has produced an approved implementation plan and the plan should be saved into the app as a scrap. Write the plan markdown to a project file, then save it with the bundled script.
---

# Plan To Markdown

Save an approved Codex plan into the application's `scraps` table.

## Workflow

1. Write the approved plan markdown to `storage/app/plans-tmp/plan-{slug}.md`.
   The first `# ...` heading becomes the saved scrap title.
2. Run:

```bash
bash .codex/skills/plan-to-markdown/scripts/save-plan.sh "{slug}"
```

- `{slug}` should be a short kebab-case identifier such as `codex-plan-skills`.
- The visible title comes from the markdown H1, not from the slug.
- The script reads `storage/app/plans-tmp/plan-{slug}.md` by default.
- If the markdown lives elsewhere, pass it as the second argument.

## Output

```text
✓ Plan saved: scrap #42 (slug: codex-plan-skills) "Codex向けプラン保存フロー移植"
```
