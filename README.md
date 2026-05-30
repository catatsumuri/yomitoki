# Yomitoki

Yomitoki is a Markdown Scrap workspace for AI-assisted development.

It captures plans, execution results, notes, research logs, and other Markdown fragments produced by AI agents or humans, then makes them readable, searchable, and reusable as knowledge artifacts.

The current MVP focuses on a very concrete problem: AI coding agents produce valuable implementation plans, but those plans often disappear into terminal or chat history after approval. Yomitoki stores them as durable Markdown Scraps so the reasoning behind a change can be revisited later.

## Why It Exists

AI agents do not only write code. They also produce plans.

Those plans usually contain the most important parts of the work:

- what will be built
- why the approach was chosen
- what tradeoffs were accepted
- what scope was intentionally left out
- what happened after the plan was executed

In a terminal, long plans are hard to review and easy to lose. Tools such as Claude Code Ultraplan point in the same direction: plans are becoming reviewable artifacts, not temporary text.

Yomitoki takes a tool-agnostic approach. Instead of tightly coupling itself to one agent runtime, it treats Markdown plans and related notes from Claude Code, Codex, GitHub Copilot, or humans as Scraps that can be collected in one place.

## Concept

Yomitoki started as an attempt to build an LLM Wiki-like system with PostgreSQL and a web UI.

The original idea was to collect rough knowledge fragments such as meeting notes, inquiries, research logs, daily reports, and design notes, then use AI to reorganize them into specifications or summary documents.

During development, the most common real source of knowledge turned out to be AI-generated plans and execution results. The product therefore pivoted toward AI-agent development logs, while keeping the broader Scrap model underneath.

In short:

> Yomitoki turns Markdown fragments from AI-assisted work into durable knowledge artifacts.

## Core Model

The central unit is a `Scrap`.

A Scrap can represent:

- an AI-generated implementation plan
- an execution result
- a development note
- a research fragment
- a meeting note
- a daily report
- a Markdown document draft

Scraps can be nested, tagged, summarized, embedded, searched, archived, backed up, and composed into larger documents.

## Features

- Markdown Scrap capture
- AI agent plan storage
- execution results linked as child Scraps
- Markdown preview with syntax highlighting
- AI-generated title and slug suggestions
- AI-generated summaries
- semantic related Scrap lookup with PostgreSQL and pgvector
- conversational search over stored Scraps
- document generation from selected Scraps
- Scrap archive and restore
- zip backup for Scraps and referenced images
- Laravel Fortify authentication and passkeys

## Current Agent Workflow

For the current repository, plans can be saved through the bundled agent skills and Artisan commands.

```text
AI agent plan
  -> Markdown file
  -> plans:save
  -> scraps table
  -> Dashboard
```

Execution results can be attached to an existing plan:

```text
implementation result
  -> Markdown file
  -> plans:result
  -> child Scrap
  -> plan detail view
```

This is intentionally not limited to plans. Plans are just the first high-value Markdown source that became useful in day-to-day development.

## Architecture

```text
AI Agent / Developer
  -> Markdown Scrap
  -> Laravel
  -> PostgreSQL + pgvector
  -> Laravel AI
  -> Azure OpenAI / other supported providers
  -> Inertia + React UI
```

Main technologies:

- Laravel
- Inertia.js
- React
- PostgreSQL
- pgvector
- Laravel AI SDK
- Azure OpenAI
- Tailwind CSS
- Laravel Sail

The application uses Laravel AI SDK so the AI provider can be swapped at the configuration layer. Development has used provider abstraction heavily; the hackathon deployment is intended to run on Azure infrastructure with Azure OpenAI for AI features.

## Screens and Workflows

### Dashboard

Capture and inspect Scraps. This is the primary workspace for rough notes, plans, and execution results.

### Articles

Read Scraps in a more article-like layout, filter by tag/status, select multiple Scraps, archive them, back them up, or compose a document.

### Documents

View AI-generated documents created from selected Scraps.

### Search

Ask questions over stored Scraps. Relevant Scraps are injected as context for the AI assistant.

## Roadmap

### Phase 1: Local Plan Capture

Capture plans and execution results generated while developing Yomitoki itself.

This phase proves that AI-generated plans are useful knowledge artifacts and gives the app real data from its own development process.

### Phase 2: External Scrap Ingest

Add a minimal HTTP ingest API so external projects and agents can send Markdown Scraps to Yomitoki.

Planned shape:

```http
POST /api/scraps
Authorization: Bearer {token}
Content-Type: application/json
```

```json
{
  "title": "External project implementation plan",
  "source_type": "plan",
  "project": "sample-app",
  "content_markdown": "# Plan\n\nMarkdown from another project."
}
```

This turns Yomitoki from a self-contained plan previewer into a cross-project knowledge hub for AI-assisted work.

### Phase 3: LLM Wiki Behavior

Move beyond storing Scraps toward maintaining higher-level knowledge structures:

- concept pages
- project-level indexes
- stale or conflicting Scrap detection
- automatic relationship discovery
- continuously updated generated documents

## Setup

```bash
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan migrate
vendor/bin/sail artisan db:seed
vendor/bin/sail npm run build
```

Run the application locally:

```bash
vendor/bin/sail up -d
vendor/bin/sail npm run dev
```

Run tests:

```bash
vendor/bin/sail artisan test --compact
```

### PDF Export Font Handling

Document PDF export (Dompdf) uses a Japanese font file and runtime-generated font cache files.

- Commit only source font files that are actually required (currently `resources/fonts/ipag.ttf`).
- Do not commit generated files under `storage/fonts` (metrics/cache). They are generated at runtime.
- If you need a different font in your environment, place the TTF file under `resources/fonts` and update the PDF view/controller font settings accordingly.

## Saving a Plan Manually

Save a Markdown plan file as a Scrap:

```bash
vendor/bin/sail artisan plans:save \
  --title="External Scrap Ingest API" \
  --slug="external-scrap-ingest-api" \
  --file="plans/2026-05-28-external-scrap-ingest-api.md" \
  --project="yomitoki"
```

Attach an execution result to a saved plan:

```bash
vendor/bin/sail artisan plans:result \
  --plan="external-scrap-ingest-api" \
  --file="result.md" \
  --title="Execution result"
```

## Hackathon Positioning

Yomitoki is being developed for Microsoft Agent Hackathon powered by Tokyo Electron Device.

The submission angle is:

> Yomitoki collects Markdown-based work fragments produced by AI agents and developers, then uses Azure OpenAI to summarize, search, and compose them into reusable business knowledge.

The first practical use case is AI-agent implementation plans. The broader direction is a tool-agnostic Markdown Scrap knowledge base for agentic work.

## License

MIT
