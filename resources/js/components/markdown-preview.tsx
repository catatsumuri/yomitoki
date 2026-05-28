import { Fragment, useEffect, useState } from 'react';
import { getHighlighter } from '@/lib/highlighter';

function CodeBlock({ code, lang }: { code: string; lang: string }) {
    const [html, setHtml] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        getHighlighter().then((h) => {
            if (cancelled) {
                return;
            }

            const supported = h
                .getLoadedLanguages()
                .includes(lang as Parameters<typeof h.codeToHtml>[1]['lang']);

            try {
                const result = h.codeToHtml(code, {
                    lang: supported ? lang : 'text',
                    themes: { light: 'github-light', dark: 'github-dark' },
                    defaultColor: false,
                });
                setHtml(result);
            } catch {
                // fallback to plain text on unexpected error
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

function renderInlineMarkdown(text: string): React.ReactNode {
    const fragments: React.ReactNode[] = [];
    const pattern = /(`[^`]+`|\[[^\]]+\]\([^)]+\)|\*\*[^*]+\*\*)/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    match = pattern.exec(text);

    while (match !== null) {
        if (match.index > lastIndex) {
            fragments.push(text.slice(lastIndex, match.index));
        }

        const token = match[0];

        if (token.startsWith('`') && token.endsWith('`')) {
            fragments.push(
                <code
                    key={`code-${match.index}`}
                    className="rounded bg-muted px-1 py-0.5 text-[0.9em]"
                >
                    {token.slice(1, -1)}
                </code>,
            );
        } else if (token.startsWith('**') && token.endsWith('**')) {
            fragments.push(
                <strong key={`strong-${match.index}`}>
                    {renderInlineMarkdown(token.slice(2, -2))}
                </strong>,
            );
        } else {
            const linkMatch = /^\[([^\]]+)\]\(([^)]+)\)$/.exec(token);

            if (linkMatch) {
                fragments.push(
                    <a
                        key={`link-${match.index}`}
                        href={linkMatch[2]}
                        target="_blank"
                        rel="noreferrer"
                        className="underline underline-offset-4"
                    >
                        {linkMatch[1]}
                    </a>,
                );
            } else {
                fragments.push(token);
            }
        }

        lastIndex = match.index + token.length;
        match = pattern.exec(text);
    }

    if (lastIndex < text.length) {
        fragments.push(text.slice(lastIndex));
    }

    return fragments.map((fragment, index) => (
        <Fragment key={index}>{fragment}</Fragment>
    ));
}

export function MarkdownPreview({ content }: { content: string }) {
    const lines = content.split('\n');
    const nodes: React.ReactNode[] = [];
    let listItems: string[] = [];
    let orderedListItems: string[] = [];
    let quoteLines: string[] = [];
    let codeLines: string[] = [];
    let tableRows: string[][] = [];
    let inCodeBlock = false;
    let currentCodeLang = '';

    const flushList = (): void => {
        if (listItems.length === 0) {
            return;
        }

        nodes.push(
            <ul
                key={`list-${nodes.length}`}
                className="list-disc space-y-1 pl-5"
            >
                {listItems.map((item, index) => (
                    <li key={index}>{renderInlineMarkdown(item)}</li>
                ))}
            </ul>,
        );

        listItems = [];
    };

    const flushQuote = (): void => {
        if (quoteLines.length === 0) {
            return;
        }

        nodes.push(
            <blockquote
                key={`quote-${nodes.length}`}
                className="border-l-2 border-border pl-4 text-muted-foreground"
            >
                {quoteLines.map((line, index) => (
                    <p key={index}>{renderInlineMarkdown(line)}</p>
                ))}
            </blockquote>,
        );

        quoteLines = [];
    };

    const flushOrderedList = (): void => {
        if (orderedListItems.length === 0) {
            return;
        }

        nodes.push(
            <ol
                key={`ordered-list-${nodes.length}`}
                className="list-decimal space-y-1 pl-5"
            >
                {orderedListItems.map((item, index) => (
                    <li key={index}>{renderInlineMarkdown(item)}</li>
                ))}
            </ol>,
        );

        orderedListItems = [];
    };

    const flushCodeBlock = (): void => {
        if (codeLines.length === 0) {
            return;
        }

        nodes.push(
            <CodeBlock
                key={`code-${nodes.length}`}
                code={codeLines.join('\n')}
                lang={currentCodeLang}
            />,
        );

        codeLines = [];
    };

    const flushTable = (): void => {
        if (tableRows.length === 0) {
            return;
        }

        const isSeparator = (row: string[]): boolean =>
            row.every((cell) => /^:?-+:?$/.test(cell.trim()));

        const sepIdx = tableRows.findIndex(isSeparator);
        const headerRows = sepIdx > 0 ? tableRows.slice(0, sepIdx) : [];
        const bodyRows = sepIdx >= 0 ? tableRows.slice(sepIdx + 1) : tableRows;

        nodes.push(
            <div
                key={`table-${nodes.length}`}
                className="overflow-x-auto rounded-xl border border-border/70"
            >
                <table className="w-full border-collapse text-sm">
                    {headerRows.length > 0 && (
                        <thead>
                            {headerRows.map((row, i) => (
                                <tr
                                    key={i}
                                    className="border-b border-border bg-muted/50"
                                >
                                    {row.map((cell, j) => (
                                        <th
                                            key={j}
                                            className="px-4 py-2 text-left font-medium"
                                        >
                                            {renderInlineMarkdown(cell.trim())}
                                        </th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                    )}
                    <tbody>
                        {bodyRows.map((row, i) => (
                            <tr
                                key={i}
                                className="border-b border-border/50 last:border-0"
                            >
                                {row.map((cell, j) => (
                                    <td key={j} className="px-4 py-2">
                                        {renderInlineMarkdown(cell.trim())}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>,
        );

        tableRows = [];
    };

    for (const line of lines) {
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

        if (inCodeBlock) {
            codeLines.push(line);
            continue;
        }

        if (line.trim().startsWith('|') && line.trim().endsWith('|')) {
            flushList();
            flushOrderedList();
            flushQuote();
            const cells = line.trim().slice(1, -1).split('|');
            tableRows.push(cells);
            continue;
        }

        if (line.startsWith('- ')) {
            flushQuote();
            flushOrderedList();
            flushTable();
            listItems.push(line.slice(2));
            continue;
        }

        if (/^\d+\.\s/.test(line)) {
            flushList();
            flushQuote();
            flushTable();
            orderedListItems.push(line.replace(/^\d+\.\s/, ''));
            continue;
        }

        if (line.startsWith('> ')) {
            flushList();
            flushOrderedList();
            flushTable();
            quoteLines.push(line.slice(2));
            continue;
        }

        flushList();
        flushOrderedList();
        flushQuote();
        flushTable();

        if (line.trim() === '') {
            nodes.push(<div key={`space-${nodes.length}`} className="h-2" />);
            continue;
        }

        if (/^(-{3,}|\*{3,}|_{3,})$/.test(line.trim())) {
            nodes.push(
                <hr key={`hr-${nodes.length}`} className="border-border/70" />,
            );
            continue;
        }

        if (line.startsWith('#### ')) {
            nodes.push(
                <h4
                    key={`h4-${nodes.length}`}
                    className="text-base font-semibold"
                >
                    {renderInlineMarkdown(line.slice(5))}
                </h4>,
            );
            continue;
        }

        if (line.startsWith('### ')) {
            nodes.push(
                <h3
                    key={`h3-${nodes.length}`}
                    className="text-lg font-semibold"
                >
                    {renderInlineMarkdown(line.slice(4))}
                </h3>,
            );
            continue;
        }

        if (line.startsWith('## ')) {
            nodes.push(
                <h2
                    key={`h2-${nodes.length}`}
                    className="text-xl font-semibold"
                >
                    {renderInlineMarkdown(line.slice(3))}
                </h2>,
            );
            continue;
        }

        if (line.startsWith('# ')) {
            nodes.push(
                <h1
                    key={`h1-${nodes.length}`}
                    className="text-2xl font-semibold"
                >
                    {renderInlineMarkdown(line.slice(2))}
                </h1>,
            );
            continue;
        }

        nodes.push(
            <p key={`p-${nodes.length}`} className="leading-7">
                {renderInlineMarkdown(line)}
            </p>,
        );
    }

    flushList();
    flushOrderedList();
    flushQuote();
    flushTable();
    flushCodeBlock();

    return <div className="space-y-3 text-sm text-foreground">{nodes}</div>;
}
