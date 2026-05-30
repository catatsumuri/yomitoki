import {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import ReactMarkdown from 'react-markdown';
import type { Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { getHighlighter } from '@/lib/highlighter';
import { extractRenderedHeadingText } from '@/lib/markdown-heading-text';
import { slugify } from '@/lib/slugify';

// ── Heading ID context (for TOC scroll-spy) ──────────────────────────────────

type IdDispenser = (baseId: string, self: object) => string;

const HeadingIdContext = createContext<IdDispenser | null>(null);

function createIdDispenser(): IdDispenser {
    const counts = new Map<string, number>();
    const cache = new WeakMap<object, string>();

    return (baseId, self) => {
        const cached = cache.get(self);

        if (cached) {
            return cached;
        }

        const n = (counts.get(baseId) ?? 0) + 1;
        const id = n === 1 ? baseId : `${baseId}-${n}`;

        counts.set(baseId, n);
        cache.set(self, id);

        return id;
    };
}

function makeHeadingComponent(level: 1 | 2 | 3, prefix: string) {
    const Tag = `h${level}` as const;
    const sizeClass =
        level === 1 ? 'text-2xl' : level === 2 ? 'text-xl' : 'text-lg';

    return function Heading({ children }: { children?: ReactNode }) {
        const dispenser = useContext(HeadingIdContext);
        const self = useRef<object>({});

        if (!dispenser) {
            return (
                <Tag className={`${sizeClass} font-semibold`}>{children}</Tag>
            );
        }

        const text = extractRenderedHeadingText(children);
        const id = dispenser(`${prefix}-${slugify(text)}`, self.current);

        return (
            <Tag id={id} className={`${sizeClass} scroll-mt-24 font-semibold`}>
                {children}
            </Tag>
        );
    };
}

// ── CodeBlock ─────────────────────────────────────────────────────────────────

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

// ── MarkdownPreview ───────────────────────────────────────────────────────────

export function MarkdownPreview({
    content,
    headingPrefix,
}: {
    content: string;
    headingPrefix?: string;
}) {
    // Reset the dispenser whenever content or prefix changes so IDs stay consistent.

    const dispenser = useMemo(
        () => (headingPrefix ? createIdDispenser() : null),
        [headingPrefix, content],
    );

    const headingComponents = useMemo((): Pick<
        Components,
        'h1' | 'h2' | 'h3'
    > => {
        if (!headingPrefix) {
            return {};
        }

        return {
            h1: makeHeadingComponent(1, headingPrefix),
            h2: makeHeadingComponent(2, headingPrefix),
            h3: makeHeadingComponent(3, headingPrefix),
        };
    }, [headingPrefix]);

    return (
        <HeadingIdContext.Provider value={dispenser}>
            <div className="space-y-3 text-sm text-foreground">
                <ReactMarkdown
                    remarkPlugins={[remarkGfm]}
                    components={{
                        h1:
                            headingComponents.h1 ??
                            (({ children }) => (
                                <h1 className="text-2xl font-semibold">
                                    {children}
                                </h1>
                            )),
                        h2:
                            headingComponents.h2 ??
                            (({ children }) => (
                                <h2 className="text-xl font-semibold">
                                    {children}
                                </h2>
                            )),
                        h3:
                            headingComponents.h3 ??
                            (({ children }) => (
                                <h3 className="text-lg font-semibold">
                                    {children}
                                </h3>
                            )),
                        h4: ({ children }) => (
                            <h4 className="text-base font-semibold">
                                {children}
                            </h4>
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
                                /language-(\w+)/.exec(className ?? '')?.[1] ??
                                '';
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
        </HeadingIdContext.Provider>
    );
}
