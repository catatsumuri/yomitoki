<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('scraps')
            ->where('slug', 'syntax-highlighting')
            ->update([
                'title' => 'Markdown コードブロックのシンタックスハイライト',
                'summary' => 'このプランでは、`MarkdownPreview` コンポーネントの Markdown コードブロックに `shiki` を使ったシンタックスハイライトを追加します。遅延ロードされる Shiki シングルトン、コードフェンスからの言語抽出、ハイライト済み HTML を描画する `CodeBlock` コンポーネント、ダークモードとライトモードの両方に対応する CSS 変数を実装し、15 以上のプログラミング言語でコードの可読性を改善します。',
                'content' => <<<'MARKDOWN'
# Markdown コードブロックのシンタックスハイライト

## 背景

`MarkdownPreview`（`dashboard.tsx` 内）のコードブロック描画は、言語指定（`` ```ts `` など）を無視しており、トークンの色分けがありません。プランやノートにコードを書いても色が付かないため、可読性が低い状態です。`shiki` を使ってシンタックスハイライトを追加します。

---

## 変更対象ファイル

- `resources/js/pages/dashboard.tsx` - 言語抽出と `CodeBlock` コンポーネントの利用
- `resources/js/lib/highlighter.ts` - `shiki` のシングルトンを新規作成
- `resources/css/app.css` - `shiki` 用のダークモード CSS 変数を追加
- `package.json` - `shiki` を追加（`npm install`）

---

## 実装内容

### 1. `shiki` のインストール

```bash
vendor/bin/sail npm install shiki
```

### 2. `resources/js/lib/highlighter.ts` を新規作成

`shiki` を遅延ロード（dynamic import）するシングルトンです。初回のコードブロック表示時だけ読み込み、その後はキャッシュを使います。

```ts
import type { Highlighter } from 'shiki';

let highlighterPromise: Promise<Highlighter> | null = null;

export function getHighlighter(): Promise<Highlighter> {
    if (!highlighterPromise) {
        highlighterPromise = import('shiki').then(({ createHighlighter }) =>
            createHighlighter({
                themes: ['github-light', 'github-dark'],
                langs: [
                    'typescript', 'tsx', 'javascript', 'jsx',
                    'bash', 'json', 'php', 'yaml', 'css',
                    'markdown', 'sql', 'html',
                ],
            })
        );
    }
    return highlighterPromise;
}
```

### 3. `dashboard.tsx` に `CodeBlock` コンポーネントを追加

`MarkdownPreview` の直上（同ファイル内）に追加します。

```tsx
function CodeBlock({ code, lang }: { code: string; lang: string }) {
    const [html, setHtml] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        getHighlighter().then((h) => {
            if (cancelled) return;
            const supported = h.getLoadedLanguages().includes(lang as never);
            try {
                const result = h.codeToHtml(code, {
                    lang: supported ? lang : 'text',
                    themes: { light: 'github-light', dark: 'github-dark' },
                    defaultColor: false,
                });
                setHtml(result);
            } catch {
                // プレーンテキストで表示する
            }
        });
        return () => {
            cancelled = true;
        };
    }, [code, lang]);

    if (html) {
        return (
            <div
                className="overflow-x-auto rounded-xl bg-muted text-sm [&_pre.shiki]:bg-transparent! [&_pre.shiki]:px-4 [&_pre.shiki]:py-3"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        );
    }

    return (
        <pre className="overflow-x-auto rounded-xl bg-muted px-4 py-3 text-sm">
            <code>{code}</code>
        </pre>
    );
}
```

- `shiki` の初期化中は既存どおりプレーンテキストを表示し、ちらつきを防ぐ
- 読み込み完了後にハイライト済み HTML に差し替える
- `defaultColor: false` と CSS 変数でダークモードとライトモードの両方に対応する
- 未対応の言語は `text` にフォールバックする

### 4. `dashboard.tsx` で言語抽出と `CodeBlock` 利用を追加

**変数追加**（`inCodeBlock` の隣）:
```ts
let currentCodeLang = '';
```

**フェンス検出部分の変更**（line 2042 付近）:
```ts
// 変更前:
if (line.trim().startsWith('```')) {
    if (inCodeBlock) {
        flushCodeBlock();
        inCodeBlock = false;
    } else {
        ...
        inCodeBlock = true;
    }
    continue;
}

// 変更後:
const fenceMatch = line.trim().match(/^```(\w+)?/);
if (fenceMatch) {
    if (inCodeBlock) {
        flushCodeBlock();
        inCodeBlock = false;
        currentCodeLang = '';
    } else {
        flushList();
        flushOrderedList();
        flushQuote();
        flushTable();
        inCodeBlock = true;
        currentCodeLang = fenceMatch[1] ?? '';
    }
    continue;
}
```

**`flushCodeBlock` の変更**（line 1964 付近）:
```ts
const flushCodeBlock = (): void => {
    if (codeLines.length === 0) return;

    nodes.push(
        <CodeBlock
            key={`code-${nodes.length}`}
            code={codeLines.join('\n')}
            lang={currentCodeLang}
        />,
    );

    codeLines = [];
};
```

### 5. `resources/css/app.css` に `shiki` 用 CSS を追加

```css
/* Shiki のデュアルテーマをダークモードで切り替える */
.shiki,
.shiki span {
    color: var(--shiki-light);
    background-color: var(--shiki-light-bg);
}

.dark .shiki,
.dark .shiki span {
    color: var(--shiki-dark);
    background-color: var(--shiki-dark-bg);
}
```

### 6. `dashboard.tsx` の先頭にインポートを追加

```ts
import { getHighlighter } from '@/lib/highlighter';
```

---

## 対応言語

`ts` / `tsx` / `js` / `jsx` / `bash` / `sh` / `json` / `php` / `yaml` / `yml` / `css` / `md` / `markdown` / `sql` / `html`

それ以外はプレーンテキストにフォールバックします。

---

## 確認手順

```bash
# インストール確認
vendor/bin/sail npm install shiki

# ビルド確認
vendor/bin/sail npm run build

# テスト（既存テストへの影響確認）
vendor/bin/sail artisan test --compact
```

`playwright-cli` で `http://localhost:8000/dashboard/welcome-page-redesign` を開き、コードブロックにトークンの色分けが適用されていることを確認します。ダークモードとライトモードの切り替えで色が変わることも確認します。
MARKDOWN,
                'content_markdown' => <<<'MARKDOWN'
# Markdown コードブロックのシンタックスハイライト

## 背景

`MarkdownPreview`（`dashboard.tsx` 内）のコードブロック描画は、言語指定（`` ```ts `` など）を無視しており、トークンの色分けがありません。プランやノートにコードを書いても色が付かないため、可読性が低い状態です。`shiki` を使ってシンタックスハイライトを追加します。

---

## 変更対象ファイル

- `resources/js/pages/dashboard.tsx` - 言語抽出と `CodeBlock` コンポーネントの利用
- `resources/js/lib/highlighter.ts` - `shiki` のシングルトンを新規作成
- `resources/css/app.css` - `shiki` 用のダークモード CSS 変数を追加
- `package.json` - `shiki` を追加（`npm install`）

---

## 実装内容

### 1. `shiki` のインストール

```bash
vendor/bin/sail npm install shiki
```

### 2. `resources/js/lib/highlighter.ts` を新規作成

`shiki` を遅延ロード（dynamic import）するシングルトンです。初回のコードブロック表示時だけ読み込み、その後はキャッシュを使います。

```ts
import type { Highlighter } from 'shiki';

let highlighterPromise: Promise<Highlighter> | null = null;

export function getHighlighter(): Promise<Highlighter> {
    if (!highlighterPromise) {
        highlighterPromise = import('shiki').then(({ createHighlighter }) =>
            createHighlighter({
                themes: ['github-light', 'github-dark'],
                langs: [
                    'typescript', 'tsx', 'javascript', 'jsx',
                    'bash', 'json', 'php', 'yaml', 'css',
                    'markdown', 'sql', 'html',
                ],
            })
        );
    }
    return highlighterPromise;
}
```

### 3. `dashboard.tsx` に `CodeBlock` コンポーネントを追加

`MarkdownPreview` の直上（同ファイル内）に追加します。

```tsx
function CodeBlock({ code, lang }: { code: string; lang: string }) {
    const [html, setHtml] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        getHighlighter().then((h) => {
            if (cancelled) return;
            const supported = h.getLoadedLanguages().includes(lang as never);
            try {
                const result = h.codeToHtml(code, {
                    lang: supported ? lang : 'text',
                    themes: { light: 'github-light', dark: 'github-dark' },
                    defaultColor: false,
                });
                setHtml(result);
            } catch {
                // プレーンテキストで表示する
            }
        });
        return () => {
            cancelled = true;
        };
    }, [code, lang]);

    if (html) {
        return (
            <div
                className="overflow-x-auto rounded-xl bg-muted text-sm [&_pre.shiki]:bg-transparent! [&_pre.shiki]:px-4 [&_pre.shiki]:py-3"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        );
    }

    return (
        <pre className="overflow-x-auto rounded-xl bg-muted px-4 py-3 text-sm">
            <code>{code}</code>
        </pre>
    );
}
```

- `shiki` の初期化中は既存どおりプレーンテキストを表示し、ちらつきを防ぐ
- 読み込み完了後にハイライト済み HTML に差し替える
- `defaultColor: false` と CSS 変数でダークモードとライトモードの両方に対応する
- 未対応の言語は `text` にフォールバックする

### 4. `dashboard.tsx` で言語抽出と `CodeBlock` 利用を追加

**変数追加**（`inCodeBlock` の隣）:
```ts
let currentCodeLang = '';
```

**フェンス検出部分の変更**（line 2042 付近）:
```ts
// 変更前:
if (line.trim().startsWith('```')) {
    if (inCodeBlock) {
        flushCodeBlock();
        inCodeBlock = false;
    } else {
        ...
        inCodeBlock = true;
    }
    continue;
}

// 変更後:
const fenceMatch = line.trim().match(/^```(\w+)?/);
if (fenceMatch) {
    if (inCodeBlock) {
        flushCodeBlock();
        inCodeBlock = false;
        currentCodeLang = '';
    } else {
        flushList();
        flushOrderedList();
        flushQuote();
        flushTable();
        inCodeBlock = true;
        currentCodeLang = fenceMatch[1] ?? '';
    }
    continue;
}
```

**`flushCodeBlock` の変更**（line 1964 付近）:
```ts
const flushCodeBlock = (): void => {
    if (codeLines.length === 0) return;

    nodes.push(
        <CodeBlock
            key={`code-${nodes.length}`}
            code={codeLines.join('\n')}
            lang={currentCodeLang}
        />,
    );

    codeLines = [];
};
```

### 5. `resources/css/app.css` に `shiki` 用 CSS を追加

```css
/* Shiki のデュアルテーマをダークモードで切り替える */
.shiki,
.shiki span {
    color: var(--shiki-light);
    background-color: var(--shiki-light-bg);
}

.dark .shiki,
.dark .shiki span {
    color: var(--shiki-dark);
    background-color: var(--shiki-dark-bg);
}
```

### 6. `dashboard.tsx` の先頭にインポートを追加

```ts
import { getHighlighter } from '@/lib/highlighter';
```

---

## 対応言語

`ts` / `tsx` / `js` / `jsx` / `bash` / `sh` / `json` / `php` / `yaml` / `yml` / `css` / `md` / `markdown` / `sql` / `html`

それ以外はプレーンテキストにフォールバックします。

---

## 確認手順

```bash
# インストール確認
vendor/bin/sail npm install shiki

# ビルド確認
vendor/bin/sail npm run build

# テスト（既存テストへの影響確認）
vendor/bin/sail artisan test --compact
```

`playwright-cli` で `http://localhost:8000/dashboard/welcome-page-redesign` を開き、コードブロックにトークンの色分けが適用されていることを確認します。ダークモードとライトモードの切り替えで色が変わることも確認します。
MARKDOWN,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('scraps')
            ->where('slug', 'syntax-highlighting')
            ->update([
                'title' => 'syntax-highlighting',
                'summary' => 'This plan adds syntax highlighting to Markdown code blocks in the MarkdownPreview component using the `shiki` library. The implementation includes creating a lazy-loaded shiki singleton, extracting language identifiers from code fences, building a CodeBlock component that renders highlighted HTML, and adding CSS variables for dark/light mode support to improve code readability across 15+ programming languages.',
                'content' => <<<'MARKDOWN'
# Syntax Highlighting for Markdown Code Blocks

## Context

`MarkdownPreview`（`dashboard.tsx` 内）のコードブロックレンダリングは言語指定（`` ```ts `` 等）を無視しており、トークン着色が一切ない。プランやノートにコードを書いても色が付かないため、可読性が低い。`shiki` を使ってsyntax highlightingを追加する。

---

## 変更ファイル

- `resources/js/pages/dashboard.tsx` — 言語抽出 + `CodeBlock` コンポーネント使用
- `resources/js/lib/highlighter.ts` — shiki シングルトン（新規作成）
- `resources/css/app.css` — shiki dark mode CSS 変数の追加
- `package.json` — `shiki` 追加（npm install）

---

## 実装内容

### 1. `shiki` のインストール

```bash
vendor/bin/sail npm install shiki
```

### 2. `resources/js/lib/highlighter.ts`（新規作成）

shiki を遅延ロード（dynamic import）するシングルトン。初回コードブロック表示時にのみロードされ、以降はキャッシュされる。

```ts
import type { Highlighter } from 'shiki';

let highlighterPromise: Promise<Highlighter> | null = null;

export function getHighlighter(): Promise<Highlighter> {
    if (!highlighterPromise) {
        highlighterPromise = import('shiki').then(({ createHighlighter }) =>
            createHighlighter({
                themes: ['github-light', 'github-dark'],
                langs: [
                    'typescript', 'tsx', 'javascript', 'jsx',
                    'bash', 'json', 'php', 'yaml', 'css',
                    'markdown', 'sql', 'html',
                ],
            })
        );
    }
    return highlighterPromise;
}
```

### 3. `dashboard.tsx` — `CodeBlock` コンポーネント追加

`MarkdownPreview` の直上（同ファイル内）に追加：

```tsx
function CodeBlock({ code, lang }: { code: string; lang: string }) {
    const [html, setHtml] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        getHighlighter().then((h) => {
            if (cancelled) return;
            const supported = h.getLoadedLanguages().includes(lang as never);
            try {
                const result = h.codeToHtml(code, {
                    lang: supported ? lang : 'text',
                    themes: { light: 'github-light', dark: 'github-dark' },
                    defaultColor: false,
                });
                setHtml(result);
            } catch {
                // fallback to plain text
            }
        });
        return () => {
            cancelled = true;
        };
    }, [code, lang]);

    if (html) {
        return (
            <div
                className="overflow-x-auto rounded-xl bg-muted text-sm [&_pre.shiki]:bg-transparent! [&_pre.shiki]:px-4 [&_pre.shiki]:py-3"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        );
    }

    return (
        <pre className="overflow-x-auto rounded-xl bg-muted px-4 py-3 text-sm">
            <code>{code}</code>
        </pre>
    );
}
```

- shiki 初期化中は既存の plain text を表示（フリッカーなし）
- ロード完了後に highlighted HTML に差し替え
- `defaultColor: false` + CSS 変数でダーク/ライトモード対応
- サポート外の言語は `text` にフォールバック

### 4. `dashboard.tsx` — `MarkdownPreview` の言語抽出 + `CodeBlock` 使用

**変数追加**（`inCodeBlock` の隣）:
```ts
let currentCodeLang = '';
```

**フェンス検出部分の変更**（line 2042付近）:
```ts
// Before:
if (line.trim().startsWith('```')) {
    if (inCodeBlock) {
        flushCodeBlock();
        inCodeBlock = false;
    } else {
        ...
        inCodeBlock = true;
    }
    continue;
}

// After:
const fenceMatch = line.trim().match(/^```(\w+)?/);
if (fenceMatch) {
    if (inCodeBlock) {
        flushCodeBlock();
        inCodeBlock = false;
        currentCodeLang = '';
    } else {
        flushList();
        flushOrderedList();
        flushQuote();
        flushTable();
        inCodeBlock = true;
        currentCodeLang = fenceMatch[1] ?? '';
    }
    continue;
}
```

**`flushCodeBlock` の変更**（line 1964付近）:
```ts
const flushCodeBlock = (): void => {
    if (codeLines.length === 0) return;

    nodes.push(
        <CodeBlock
            key={`code-${nodes.length}`}
            code={codeLines.join('\n')}
            lang={currentCodeLang}
        />,
    );

    codeLines = [];
};
```

### 5. `resources/css/app.css` — shiki dark mode CSS

```css
/* Shiki dual-theme dark mode support */
.shiki,
.shiki span {
    color: var(--shiki-light);
    background-color: var(--shiki-light-bg);
}

.dark .shiki,
.dark .shiki span {
    color: var(--shiki-dark);
    background-color: var(--shiki-dark-bg);
}
```

### 6. インポート追加（dashboard.tsx 先頭）

```ts
import { getHighlighter } from '@/lib/highlighter';
```

---

## 対応言語

`ts` / `tsx` / `js` / `jsx` / `bash` / `sh` / `json` / `php` / `yaml` / `yml` / `css` / `md` / `markdown` / `sql` / `html`

それ以外は plain text フォールバック。

---

## Verification

```bash
# インストール確認
vendor/bin/sail npm install shiki

# ビルド確認
vendor/bin/sail npm run build

# テスト（既存テストへの影響確認）
vendor/bin/sail artisan test --compact
```

playwright-cli で `http://localhost:8000/dashboard/welcome-page-redesign` を開き、コードブロックにトークン着色が適用されていることを確認。ダーク/ライトモード切り替えで色が変わることも確認する。
MARKDOWN,
                'content_markdown' => <<<'MARKDOWN'
# Syntax Highlighting for Markdown Code Blocks

## Context

`MarkdownPreview`（`dashboard.tsx` 内）のコードブロックレンダリングは言語指定（`` ```ts `` 等）を無視しており、トークン着色が一切ない。プランやノートにコードを書いても色が付かないため、可読性が低い。`shiki` を使ってsyntax highlightingを追加する。

---

## 変更ファイル

- `resources/js/pages/dashboard.tsx` — 言語抽出 + `CodeBlock` コンポーネント使用
- `resources/js/lib/highlighter.ts` — shiki シングルトン（新規作成）
- `resources/css/app.css` — shiki dark mode CSS 変数の追加
- `package.json` — `shiki` 追加（npm install）

---

## 実装内容

### 1. `shiki` のインストール

```bash
vendor/bin/sail npm install shiki
```

### 2. `resources/js/lib/highlighter.ts`（新規作成）

shiki を遅延ロード（dynamic import）するシングルトン。初回コードブロック表示時にのみロードされ、以降はキャッシュされる。

```ts
import type { Highlighter } from 'shiki';

let highlighterPromise: Promise<Highlighter> | null = null;

export function getHighlighter(): Promise<Highlighter> {
    if (!highlighterPromise) {
        highlighterPromise = import('shiki').then(({ createHighlighter }) =>
            createHighlighter({
                themes: ['github-light', 'github-dark'],
                langs: [
                    'typescript', 'tsx', 'javascript', 'jsx',
                    'bash', 'json', 'php', 'yaml', 'css',
                    'markdown', 'sql', 'html',
                ],
            })
        );
    }
    return highlighterPromise;
}
```

### 3. `dashboard.tsx` — `CodeBlock` コンポーネント追加

`MarkdownPreview` の直上（同ファイル内）に追加：

```tsx
function CodeBlock({ code, lang }: { code: string; lang: string }) {
    const [html, setHtml] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        getHighlighter().then((h) => {
            if (cancelled) return;
            const supported = h.getLoadedLanguages().includes(lang as never);
            try {
                const result = h.codeToHtml(code, {
                    lang: supported ? lang : 'text',
                    themes: { light: 'github-light', dark: 'github-dark' },
                    defaultColor: false,
                });
                setHtml(result);
            } catch {
                // fallback to plain text
            }
        });
        return () => {
            cancelled = true;
        };
    }, [code, lang]);

    if (html) {
        return (
            <div
                className="overflow-x-auto rounded-xl bg-muted text-sm [&_pre.shiki]:bg-transparent! [&_pre.shiki]:px-4 [&_pre.shiki]:py-3"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        );
    }

    return (
        <pre className="overflow-x-auto rounded-xl bg-muted px-4 py-3 text-sm">
            <code>{code}</code>
        </pre>
    );
}
```

- shiki 初期化中は既存の plain text を表示（フリッカーなし）
- ロード完了後に highlighted HTML に差し替え
- `defaultColor: false` + CSS 変数でダーク/ライトモード対応
- サポート外の言語は `text` にフォールバック

### 4. `dashboard.tsx` — `MarkdownPreview` の言語抽出 + `CodeBlock` 使用

**変数追加**（`inCodeBlock` の隣）:
```ts
let currentCodeLang = '';
```

**フェンス検出部分の変更**（line 2042付近）:
```ts
// Before:
if (line.trim().startsWith('```')) {
    if (inCodeBlock) {
        flushCodeBlock();
        inCodeBlock = false;
    } else {
        ...
        inCodeBlock = true;
    }
    continue;
}

// After:
const fenceMatch = line.trim().match(/^```(\w+)?/);
if (fenceMatch) {
    if (inCodeBlock) {
        flushCodeBlock();
        inCodeBlock = false;
        currentCodeLang = '';
    } else {
        flushList();
        flushOrderedList();
        flushQuote();
        flushTable();
        inCodeBlock = true;
        currentCodeLang = fenceMatch[1] ?? '';
    }
    continue;
}
```

**`flushCodeBlock` の変更**（line 1964付近）:
```ts
const flushCodeBlock = (): void => {
    if (codeLines.length === 0) return;

    nodes.push(
        <CodeBlock
            key={`code-${nodes.length}`}
            code={codeLines.join('\n')}
            lang={currentCodeLang}
        />,
    );

    codeLines = [];
};
```

### 5. `resources/css/app.css` — shiki dark mode CSS

```css
/* Shiki dual-theme dark mode support */
.shiki,
.shiki span {
    color: var(--shiki-light);
    background-color: var(--shiki-light-bg);
}

.dark .shiki,
.dark .shiki span {
    color: var(--shiki-dark);
    background-color: var(--shiki-dark-bg);
}
```

### 6. インポート追加（dashboard.tsx 先頭）

```ts
import { getHighlighter } from '@/lib/highlighter';
```

---

## 対応言語

`ts` / `tsx` / `js` / `jsx` / `bash` / `sh` / `json` / `php` / `yaml` / `yml` / `css` / `md` / `markdown` / `sql` / `html`

それ以外は plain text フォールバック。

---

## Verification

```bash
# インストール確認
vendor/bin/sail npm install shiki

# ビルド確認
vendor/bin/sail npm run build

# テスト（既存テストへの影響確認）
vendor/bin/sail artisan test --compact
```

playwright-cli で `http://localhost:8000/dashboard/welcome-page-redesign` を開き、コードブロックにトークン着色が適用されていることを確認。ダーク/ライトモード切り替えで色が変わることも確認する。
MARKDOWN,
            ]);
    }
};
