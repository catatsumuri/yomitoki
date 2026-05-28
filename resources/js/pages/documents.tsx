import { useLang } from '@erag/lang-sync-inertia/react';
import {
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
} from '@inertiajs/react';
import { Trash2Icon } from 'lucide-react';
import { useRef } from 'react';
import { DateDisplay } from '@/components/date-display';
import { MarkdownPreview } from '@/components/markdown-preview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { documents } from '@/routes';
import {
    destroy as documentsDestroy,
    show as documentsShow,
} from '@/routes/documents';

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
    status: string;
    summary: string | null;
    outline: string[] | null;
    contentMarkdown: string | null;
    createdAt: string;
    scraps?: Scrap[];
};

type DocumentsProps = {
    documents: { data: Document[] };
    selectedDocument: Document | null;
};

function toneForStatus(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'failed') {
        return 'destructive';
    }

    if (status === 'draft' || status === 'queued') {
        return 'secondary';
    }

    if (status === 'completed' || status === 'published') {
        return 'default';
    }

    return 'outline';
}

export default function Documents({
    documents: documentsProp,
    selectedDocument,
}: DocumentsProps) {
    const { __ } = useLang();
    const headerRef = useRef<HTMLDivElement>(null);

    setLayoutProps({
        breadcrumbs: [{ title: __('Documents'), href: documents() }],
    });

    const documentTypeLabels: Record<string, string> = {
        spec: __('Spec'),
        summary: __('Summary'),
        report: __('Report'),
    };

    const statusLabels: Record<string, string> = {
        draft: __('Draft'),
        completed: __('Completed'),
        published: __('Published'),
        failed: __('Failed'),
    };

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
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground hover:text-destructive"
                            onClick={() => {
                                if (!confirm(__('Delete this document?'))) {
                                    return;
                                }

                                router.delete(
                                    documentsDestroy(selectedDocument.id).url,
                                );
                            }}
                        >
                            <Trash2Icon className="size-4" />
                        </Button>
                    </div>

                    <div className="mx-auto w-full max-w-3xl space-y-6">
                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge
                                    variant={toneForStatus(
                                        selectedDocument.status,
                                    )}
                                >
                                    {statusLabels[selectedDocument.status] ??
                                        selectedDocument.status}
                                </Badge>
                                <Badge variant="outline">
                                    {documentTypeLabels[
                                        selectedDocument.documentType
                                    ] ?? selectedDocument.documentType}
                                </Badge>
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

                        {selectedDocument.outline &&
                            selectedDocument.outline.length > 0 && (
                                <div className="rounded-2xl border border-border/70 bg-muted/30 p-5">
                                    <h2 className="mb-3 text-sm font-medium text-foreground">
                                        {__('Outline')}
                                    </h2>
                                    <ol className="space-y-1">
                                        {selectedDocument.outline.map(
                                            (item, i) => (
                                                <li
                                                    key={i}
                                                    className="text-sm text-muted-foreground"
                                                >
                                                    {i + 1}. {item}
                                                </li>
                                            ),
                                        )}
                                    </ol>
                                </div>
                            )}

                        {selectedDocument.contentMarkdown && (
                            <div className="rounded-2xl border border-border/70 bg-background/70 p-6">
                                <MarkdownPreview
                                    content={selectedDocument.contentMarkdown}
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
                                                count: selectedDocument.scraps
                                                    .length,
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
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium text-foreground">
                                            {item.title}
                                        </p>
                                        {item.summary && (
                                            <p className="mt-1 line-clamp-2 text-sm leading-6 text-muted-foreground">
                                                {item.summary}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-1.5">
                                        <Badge
                                            variant={toneForStatus(item.status)}
                                        >
                                            {statusLabels[item.status] ??
                                                item.status}
                                        </Badge>
                                        <Badge variant="outline">
                                            {documentTypeLabels[
                                                item.documentType
                                            ] ?? item.documentType}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="mt-3 text-xs text-muted-foreground">
                                    <DateDisplay value={item.createdAt} />
                                </div>
                            </Link>
                        ))}
                    </div>
                </InfiniteScroll>
            </div>
        </>
    );
}
