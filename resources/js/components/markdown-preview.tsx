import { useEffect, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
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

export function MarkdownPreview({ content }: { content: string }) {
    return (
        <div className="space-y-3 text-sm text-foreground">
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                    h1: ({ children }) => (
                        <h1 className="text-2xl font-semibold">{children}</h1>
                    ),
                    h2: ({ children }) => (
                        <h2 className="text-xl font-semibold">{children}</h2>
                    ),
                    h3: ({ children }) => (
                        <h3 className="text-lg font-semibold">{children}</h3>
                    ),
                    h4: ({ children }) => (
                        <h4 className="text-base font-semibold">{children}</h4>
                    ),
                    p: ({ children }) => (
                        <p className="leading-7">{children}</p>
                    ),
                    ul: ({ children }) => (
                        <ul className="list-disc space-y-1 pl-5 marker:text-primary/50">
                            {children}
                        </ul>
                    ),
                    ol: ({ children }) => (
                        <ol className="list-decimal space-y-1 pl-5 marker:text-primary/50">
                            {children}
                        </ol>
                    ),
                    blockquote: ({ children }) => (
                        <blockquote className="border-l-2 border-primary/30 pl-4 text-muted-foreground italic">
                            {children}
                        </blockquote>
                    ),
                    pre: ({ children }) => <>{children}</>,
                    code({ children, className }) {
                        const lang =
                            /language-(\w+)/.exec(className ?? '')?.[1] ?? '';
                        const code = String(children).replace(/\n$/, '');

                        if (className || code.includes('\n')) {
                            return <CodeBlock code={code} lang={lang} />;
                        }

                        return (
                            <code className="rounded-lg bg-primary/10 px-1.5 py-0.5 font-mono text-[0.9em] text-primary">
                                {children}
                            </code>
                        );
                    },
                    table: ({ children }) => (
                        <div className="overflow-x-auto rounded-xl border border-border/70">
                            <table className="w-full border-collapse text-sm">
                                {children}
                            </table>
                        </div>
                    ),
                    th: ({ children }) => (
                        <th className="border-b border-border bg-muted/50 px-4 py-2 text-left font-medium">
                            {children}
                        </th>
                    ),
                    td: ({ children }) => (
                        <td className="px-4 py-2">{children}</td>
                    ),
                    tr: ({ children }) => (
                        <tr className="border-b border-border/50 last:border-0">
                            {children}
                        </tr>
                    ),
                    a: ({ href, children }) => (
                        <a
                            href={href}
                            target="_blank"
                            rel="noreferrer"
                            className="text-primary underline underline-offset-4 transition-colors hover:text-primary/80"
                        >
                            {children}
                        </a>
                    ),
                    hr: () => <hr className="border-border/70" />,
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}
