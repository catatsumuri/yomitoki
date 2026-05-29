import { useEffect, useRef, useState } from 'react';
import type { MarkdownHeading } from '@/lib/markdown-headings';
import { cn } from '@/lib/utils';

const ACTIVE_HEADING_OFFSET = 160;

export function TableOfContents({
    headings,
    sticky = false,
}: {
    headings: MarkdownHeading[];
    sticky?: boolean;
}) {
    const scrollContainerRef = useRef<HTMLElement | null>(null);
    const itemRefs = useRef<Map<string, HTMLAnchorElement>>(new Map());
    const [activeId, setActiveId] = useState<string | null>(
        headings[0]?.id ?? null,
    );

    useEffect(() => {
        if (headings.length === 0) {
            return;
        }

        const updateActiveId = () => {
            let nextActiveId = headings[0]?.id ?? null;

            for (const heading of headings) {
                const el = document.getElementById(heading.id);

                if (!el) {
                    continue;
                }

                if (el.getBoundingClientRect().top <= ACTIVE_HEADING_OFFSET) {
                    nextActiveId = heading.id;
                } else {
                    break;
                }
            }

            setActiveId((cur) => (cur === nextActiveId ? cur : nextActiveId));
        };

        updateActiveId();
        window.addEventListener('scroll', updateActiveId, { passive: true });
        window.addEventListener('resize', updateActiveId);
        window.addEventListener('hashchange', updateActiveId);

        return () => {
            window.removeEventListener('scroll', updateActiveId);
            window.removeEventListener('resize', updateActiveId);
            window.removeEventListener('hashchange', updateActiveId);
        };
    }, [headings]);

    useEffect(() => {
        if (!activeId) {
            return;
        }

        const container = scrollContainerRef.current;
        const item = itemRefs.current.get(activeId);

        if (
            !container ||
            !item ||
            container.scrollHeight <= container.clientHeight
        ) {
            return;
        }

        const containerTop = container.getBoundingClientRect().top;
        const itemTop = item.getBoundingClientRect().top;
        const offset = itemTop - containerTop;
        const scrollTop = container.scrollTop + offset;

        container.scrollTop = Math.max(
            0,
            scrollTop - container.clientHeight / 2 + item.offsetHeight / 2,
        );
    }, [activeId]);

    function registerRef(id: string) {
        return (el: HTMLAnchorElement | null) => {
            if (el) {
                itemRefs.current.set(id, el);
            } else {
                itemRefs.current.delete(id);
            }
        };
    }

    function handleClick(id: string) {
        return (e: React.MouseEvent<HTMLAnchorElement>) => {
            e.preventDefault();
            const target = document.getElementById(id);

            if (target) {
                target.scrollIntoView({ block: 'start' });
            }

            window.history.pushState(null, '', `#${encodeURIComponent(id)}`);
            setActiveId(id);
        };
    }

    if (headings.length === 0) {
        return null;
    }

    return (
        <nav
            ref={scrollContainerRef}
            className={cn(
                'text-sm',
                sticky && 'max-h-[calc(100vh-8rem)] overflow-y-auto pr-1',
            )}
        >
            <p className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                Contents
            </p>
            <ul className="space-y-1 border-l border-border">
                {headings.map((h) => (
                    <li
                        key={h.id}
                        style={{ paddingLeft: `${(h.level - 1) * 12 + 8}px` }}
                    >
                        <a
                            ref={registerRef(h.id)}
                            href={`#${encodeURIComponent(h.id)}`}
                            onClick={handleClick(h.id)}
                            aria-current={
                                activeId === h.id ? 'location' : undefined
                            }
                            className={cn(
                                'block rounded-md px-2 py-1 leading-snug transition-colors',
                                activeId === h.id
                                    ? 'bg-accent font-medium text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {h.text}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
