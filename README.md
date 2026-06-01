# Yomitoki

Yomitoki is a Markdown Scrap workspace for AI-assisted development.

It captures plans, execution results, notes, research logs, and other Markdown fragments produced by AI agents or humans, then makes them readable, searchable, and reusable as knowledge artifacts.

## Why It Exists

AI agents produce plans. Those plans usually contain the most important parts of the work — what will be built, why the approach was chosen, what tradeoffs were accepted, what scope was left out. In a terminal, long plans are hard to review and easy to lose.

Yomitoki takes a tool-agnostic approach. Instead of tightly coupling itself to one agent runtime, it treats Markdown plans and related notes from Claude Code, Codex, GitHub Copilot, or humans as Scraps that can be collected in one place.

## Features

- Markdown Scrap capture with AI-suggested title, slug, and summary
- AI-generated tags (auto-dispatched on save)
- pgvector semantic embedding and related Scrap lookup
- RAG conversational search with tool-based retrieval and source link chips
- Markdown refinement (plain text → structured Markdown via AI)
- Document generation from selected Scraps
- Scrap archive, restore, zip backup
- External API with Sanctum PAT authentication
- Agent skill auto-install (`/api/skills/install`)
- Cross-linking between Dashboard workspace and Articles view
- Laravel Fortify authentication + passkeys

## Architecture

```
AI Agent / Developer
  → Markdown Scrap (web UI or API)
  → Laravel 13 + PostgreSQL 17 + pgvector
  → Laravel AI SDK (Azure OpenAI / other providers)
  → Queue workers: EmbeddingJob, SummaryJob, TagsJob
  → Inertia v3 + React UI
```

## Setup

```bash
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan migrate:fresh --seed
vendor/bin/sail npm run build
```

Local development:

```bash
vendor/bin/sail up -d
vendor/bin/sail npm run dev
vendor/bin/sail artisan queue:work   # process embedding/summary/tags jobs
```

Tests:

```bash
vendor/bin/sail artisan test --compact
```

## Demo Data

Two seeders are available:

| Seeder | User | Description |
|--------|------|-------------|
| `DashboardDemoSeeder` | test@example.com / password | Spec-generation workflow demo |
| `KnowledgeDemoSeeder` | demo@example.com / password | 34 realistic scraps across 5 scenarios (inquiries, bugs, research, spec changes, incidents) |

Run after `migrate:fresh`:

```bash
vendor/bin/sail artisan db:seed --class KnowledgeDemoSeeder
vendor/bin/sail artisan scraps:embed --user=demo@example.com --tags
```

## Artisan Commands

### Plan / Execution result

```bash
# Save a Markdown plan file as a Scrap
vendor/bin/sail artisan plans:save \
  --title="My Plan" \
  --slug="my-plan" \
  --file="plan.md" \
  --project="my-project"

# Attach an execution result as a child Scrap to an existing plan
vendor/bin/sail artisan plans:result \
  --plan="my-plan" \
  --file="result.md" \
  --title="Execution result"
```

### Scrap management

```bash
# List scraps
vendor/bin/sail artisan scraps:list
vendor/bin/sail artisan scraps:list --source-type=plan --status=processed

# Add a scrap
vendor/bin/sail artisan scraps:add --title="Note" --file="note.md"

# Update a scrap by slug
vendor/bin/sail artisan scraps:update my-slug --title="New Title"

# Delete a scrap and its children
vendor/bin/sail artisan scraps:delete my-slug

# Backup scraps to zip
vendor/bin/sail artisan scraps:backup
vendor/bin/sail artisan scraps:backup --slug=my-slug
```

### Embedding and tags

```bash
# Dispatch embedding jobs for all scraps missing embeddings
vendor/bin/sail artisan scraps:embed

# Scope to a specific user (ID or email)
vendor/bin/sail artisan scraps:embed --user=demo@example.com

# Also dispatch tag generation jobs
vendor/bin/sail artisan scraps:embed --user=demo@example.com --tags

# Force re-dispatch even if embedding already exists
vendor/bin/sail artisan scraps:embed --force --tags
```

## API Reference

All endpoints require `Authorization: Bearer {token}` with the `ingest` ability.
Tokens can be generated from **Settings → API Tokens**.

### Agent skill install

```bash
curl -H "Authorization: Bearer {token}" \
  "https://your-yomitoki/api/skills/install?profile=full" | bash
```

| `profile` | Skills | Use case |
|-----------|--------|----------|
| `basic` | `scrap-utils` | Push any Markdown (CI logs, notes, docs) |
| `plan` | `plan-to-markdown`, `execution-result` | AI agent development log |
| `full` | All of the above | Everything |

### POST /api/scraps

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | required |
| `content_markdown` | string | required |
| `source_type` | string | `plan` (default), `note`, `result`, `research`, `meeting`, `execution` |
| `project` | string | Added as a tag |
| `slug` | string | Auto-generated if omitted |
| `parent_slug` | string | Creates a child relationship |
| `description` | string | Saved directly as summary, skips AI summarization |

Response `201`: `{ id, slug, url }`

### GET /api/scraps

List scraps. Query params: `source_type`, `status`, `limit` (max 100).

### GET /api/scraps/{slug}

Get a single Scrap with children.

### PATCH /api/scraps/{slug}

Update title, slug, content_markdown, status, source_type, or summary.

### DELETE /api/scraps/{slug}

Delete a Scrap and all descendants. Response `204`.

### GET /api/scraps/{slug}/related

Semantically similar Scraps via pgvector. Query param: `limit` (max 20).

### GET /api/search

Semantic vector search. Query params: `q` (required), `limit`, `threshold` (default 0.2), `include=content`.

## License

MIT
