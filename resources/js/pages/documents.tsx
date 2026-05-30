import { useLang } from '@erag/lang-sync-inertia/react';
import {
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
} from '@inertiajs/react';
import { FileDownIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import { useMemo, useRef } from 'react';
import {
    destroy as documentsDestroy,
    edit as documentsEdit,
    pdf as documentsPdf,
    show as documentsShow,
} from '@/actions/App/Http/Controllers/DocumentController';
import { DateDisplay } from '@/components/date-display';
import { MarkdownPreview } from '@/components/markdown-preview';
import { TableOfContents } from '@/components/table-of-contents';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { extractMarkdownHeadings } from '@/lib/markdown-headings';
import { documents } from '@/routes';

type Scrap = {
    id: number;
    title: string | null;
    slug: string | null;
    summary: string | null;
    sourceType: string;
};

type Document = {
    id: number;
    title: string;
    documentType: string;
    tags: string[];
    summary: string | null;
    contentMarkdown: string | null;
    createdAt: string;
    scraps?: Scrap[];
};

type DocumentsProps = {
    documents: { data: Document[] };
    selectedDocument: Document | null;
    availableTags: string[];
    activeTag: string | null;
};

export default function Documents({
    documents: documentsProp,
    selectedDocument,
    availableTags,
    activeTag,
}: DocumentsProps) {
    const { __ } = useLang();
    const headerRef = useRef<HTMLDivElement>(null);

    setLayoutProps({
        breadcrumbs: [{ title: __('Documents'), href: documents() }],
    });

    const headingPrefix = selectedDocument ? `doc-${selectedDocument.id}` : '';
    const headings = useMemo(
        () =>
            selectedDocument?.contentMarkdown
                ? extractMarkdownHeadings(
                      selectedDocument.contentMarkdown,
                      headingPrefix,
                  )
                : [],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [selectedDocument?.id, selectedDocument?.contentMarkdown],
    );

    function visitTag(tag: string | null): void {
        router.visit(tag ? documents({ tag }) : documents());
    }

    // ── Detail view ────────────────────────────────────────────────────────
    if (selectedDocument) {
        return (
            <>
                <Head title={selectedDocument.title} />
                <div
                    ref={headerRef}
                    className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                >
                    <div className="flex items-center justify-between">
                        <Link
                            href={documents()}
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            ← {__('Documents')}
                        </Link>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground hover:text-foreground"
                                asChild
                            >
                                <a
                                    href={documentsPdf(selectedDocument.id).url}
                                    download
                                >
                                    <FileDownIcon className="size-4" />
                                </a>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground hover:text-foreground"
                                onClick={() =>
                                    router.visit(
                                        documentsEdit(selectedDocument.id).url,
                                    )
                                }
                            >
                                <PencilIcon className="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground hover:text-destructive"
                                onClick={() => {
                                    if (!confirm(__('Delete this document?'))) {
                                        return;
                                    }

                                    router.delete(
                                        documentsDestroy(selectedDocument.id)
                                            .url,
                                    );
                                }}
                            >
                                <Trash2Icon className="size-4" />
                            </Button>
                        </div>
                    </div>

                    <div
                        className={
                            headings.length > 0
                                ? 'lg:grid lg:grid-cols-[1fr_220px] lg:gap-8'
                                : undefined
                        }
                    >
                        <div className="min-w-0 space-y-6">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    {selectedDocument.tags.map((tag) => (
                                        <Badge key={tag} variant="secondary">
                                            #{tag}
                                        </Badge>
                                    ))}
                                    <span className="text-xs text-muted-foreground">
                                        <DateDisplay
                                            value={selectedDocument.createdAt}
                                        />
                                    </span>
                                </div>

                                <h1 className="text-2xl font-bold text-foreground">
                                    {selectedDocument.title}
                                </h1>

                                {selectedDocument.summary && (
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        {selectedDocument.summary}
                                    </p>
                                )}
                            </div>

                            {selectedDocument.contentMarkdown && (
                                <div className="rounded-2xl border border-border/70 bg-background/70 p-6">
                                    <MarkdownPreview
                                        content={
                                            selectedDocument.contentMarkdown
                                        }
                                        headingPrefix={headingPrefix}
                                    />
                                </div>
                            )}

                            {selectedDocument.scraps &&
                                selectedDocument.scraps.length > 0 && (
                                    <div className="space-y-3">
                                        <h2 className="text-sm font-medium text-foreground">
                                            {__('Source scraps')}
                                            <span className="ml-2 text-muted-foreground">
                                                {__(':count linked', {
                                                    count: selectedDocument
                                                        .scraps.length,
                                                })}
                                            </span>
                                        </h2>
                                        <div className="space-y-2">
                                            {selectedDocument.scraps.map(
                                                (scrap) => (
                                                    <div
                                                        key={scrap.id}
                                                        className="rounded-2xl border border-border/70 bg-background/60 p-4"
                                                    >
                                                        <p className="font-medium text-foreground">
                                                            {scrap.title ??
                                                                __(
                                                                    'Untitled scrap',
                                                                )}
                                                        </p>
                                                        {scrap.summary && (
                                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                                {scrap.summary}
                                                            </p>
                                                        )}
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                        </div>

                        {headings.length > 0 && (
                            <aside className="hidden self-start lg:sticky lg:top-24 lg:block">
                                <TableOfContents headings={headings} sticky />
                            </aside>
                        )}
                    </div>
                </div>
            </>
        );
    }

    // ── List view ──────────────────────────────────────────────────────────
    return (
        <>
            <Head title={__('Documents')} />
            <div
                ref={headerRef}
                className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6"
            >
                {availableTags.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant={activeTag === null ? 'default' : 'outline'}
                            onClick={() => visitTag(null)}
                        >
                            {__('All')}
                        </Button>
                        {availableTags.map((tag) => (
                            <Button
                                key={tag}
                                size="sm"
                                variant={
                                    activeTag === tag ? 'default' : 'outline'
                                }
                                onClick={() => visitTag(tag)}
                            >
                                #{tag}
                            </Button>
                        ))}
                    </div>
                )}

                <InfiniteScroll
                    data="documents"
                    manual
                    next={({ loading, fetch, hasMore }) =>
                        hasMore ? (
                            <div className="pt-4 text-center">
                                <Button
                                    variant="outline"
                                    disabled={loading}
                                    onClick={fetch}
                                >
                                    {loading
                                        ? __('Loading...')
                                        : __('Load more')}
                                </Button>
                            </div>
                        ) : null
                    }
                >
                    <div className="space-y-2">
                        {documentsProp.data.length === 0 && (
                            <p className="py-12 text-center text-sm text-muted-foreground">
                                {__('No documents yet.')}
                            </p>
                        )}
                        {documentsProp.data.map((item) => (
                            <Link
                                key={item.id}
                                href={documentsShow(item.id)}
                                prefetch
                                className="block rounded-2xl border border-border/50 bg-background/80 p-4 transition-all duration-200 hover:border-primary/20 hover:bg-card hover:shadow-lg hover:shadow-black/5"
                            >
                                <p className="truncate font-medium text-foreground">
                                    {item.title}
                                </p>
                                {item.summary && (
                                    <p className="mt-1 line-clamp-2 text-sm leading-6 text-muted-foreground">
                                        {item.summary}
                                    </p>
                                )}
                                <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                    {item.tags.map((tag) => (
                                        <Badge
                                            key={tag}
                                            variant="secondary"
                                            className="h-4 px-1.5 py-0 text-[10px]"
                                        >
                                            #{tag}
                                        </Badge>
                                    ))}
                                    <span className="text-xs text-muted-foreground">
                                        <DateDisplay value={item.createdAt} />
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </InfiniteScroll>
            </div>
        </>
    );
}
