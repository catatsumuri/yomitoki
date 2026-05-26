---
name: execution-result
description: Use this skill after a saved Codex plan has been implemented and the execution result should be attached back to that plan as a child scrap.
---

# Execution Result

Attach implementation results to an existing saved plan.

## Workflow

1. Write the result markdown to `storage/app/plans-tmp/result-{plan-slug}.md`.
   The first `# ...` heading becomes the saved child scrap title.
2. Run:

```bash
bash .codex/skills/execution-result/scripts/attach-result.sh "{plan-slug}"
```

- `{plan-slug}` is the saved plan slug from `plans:save`.
- If the result markdown lives elsewhere, pass it as the second argument.
- The visible title comes from the markdown H1, not from the slug.

## Output

```text
✓ Result attached: scrap #43 → plan "codex-plan-skills"
```
