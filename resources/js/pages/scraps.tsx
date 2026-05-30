import { useLang } from '@erag/lang-sync-inertia/react';
import {
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
} from '@inertiajs/react';
import {
    ArchiveIcon,
    FileTextIcon,
    HardDriveDownloadIcon,
    Trash2Icon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DateDisplay } from '@/components/date-display';
import { MarkdownPreview } from '@/components/markdown-preview';
import { TableOfContents } from '@/components/table-of-contents';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { extractMarkdownHeadings } from '@/lib/markdown-headings';
import { toneForStatus } from '@/lib/scrap-utils';
import { scraps as scrapsRoute } from '@/routes';
import { bulkStream as backupBulkStream } from '@/routes/backup';
import { compose as documentsCompose } from '@/routes/documents';
import { show as scrapsShow } from '@/routes/scraps';
import {
    bulkArchive as scrapsBulkArchive,
    bulkDestroy as scrapsBulkDestroy,
} from '@/routes/scraps';

type Scrap = {
    id: number;
    title: string | null;
    slug: string | null;
    content: string;
    sourceType: string;
    status: string;
    summary: string | null;
    tags: string[];
    occurredAt: string | null;
    children: {
        id: number;
        title: string | null;
        sourceType: string;
        status: string;
        summary: string | null;
        occurredAt: string | null;
    }[];
};

type BackupEntry = {
    filename: string;
    scrapSlug: string | null;
    scrapTitle: string | null;
    createdAt: string | null;
    description: string | null;
    fileSize: number;
};

type BulkBackupItem = {
    id: number;
    title: string;
    status: 'pending' | 'done' | 'failed';
    filename?: string;
};

type BulkBackupState =
    | { phase: 'idle' }
    | { phase: 'running'; items: BulkBackupItem[] }
    | {
          phase: 'done';
          items: BulkBackupItem[];
          succeeded: number;
          failed: number;
      };

type ArticlesProps = {
    view: 'list' | 'backups';
    scraps: { data: Scrap[] };
    selectedScrap: Scrap | null;
    availableTags: string[];
    activeTag: string | null;
    activeStatus: 'active' | 'archived';
    backups: BackupEntry[];
};

function formatFileSize(bytes: number): string {
    if (bytes === 0) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Articles({
    view,
    scraps,
    selectedScrap,
    availableTags,
    activeTag,
    activeStatus,
    backups,
}: ArticlesProps) {
    const { __ } = useLang();
    const headerRef = useRef<HTMLDivElement>(null);
    const [checkedIds, setCheckedIds] = useState<Set<number>>(new Set());
    const [isArchiving, setIsArchiving] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);
    const [bulkBackup, setBulkBackup] = useState<BulkBackupState>({
        phase: 'idle',
    });
    const sseRef = useRef<EventSource | null>(null);

    const isArchivedView = activeStatus === 'archived';
    const isBackupsView = view === 'backups';
    const listIds = scraps.data.map((s) => s.id);
    const allChecked =
        listIds.length > 0 && listIds.every((id) => checkedIds.has(id));
    const someChecked = !allChecked && listIds.some((id) => checkedIds.has(id));

    setLayoutProps({
        breadcrumbs: [{ title: __('Scraps'), href: scrapsRoute() }],
    });

    // Reset checked state and close any open SSE when filters change
    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setCheckedIds(new Set());

        setBulkBackup({ phase: 'idle' });
        sseRef.current?.close();
    }, [activeStatus, activeTag]);

    // Cleanup SSE on unmount
    useEffect(() => {
        return () => {
            sseRef.current?.close();
        };
    }, []);

    // Scroll to top of content when detail view opens/closes
    useEffect(() => {
        headerRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }, [selectedScrap?.id]);

    const sourceLabels: Record<string, string> = {
        daily_report: __('Daily report'),
        execution: __('Execution result'),
        inquiry: __('Inquiry'),
        meeting_note: __('Meeting note'),
        plan: __('Plan'),
        research: __('Research'),
    };

    const statusLabels: Record<string, string> = {
        raw: __('Raw'),
        queued: __('Queued'),
        final: __('Final'),
        completed: __('Completed'),
        processed: __('Processed'),
        failed: __('Failed'),
        archived: __('Archived'),
    };

    function routeQuery(
        tag: string | null,
        status: 'active' | 'archived' = activeStatus,
    ): { tag?: string; status?: 'archived' } | undefined {
        const query: { tag?: string; status?: 'archived' } = {};

        if (tag) {
            query.tag = tag;
        }

        if (status === 'archived') {
            query.status = 'archived';
        }

        return Object.keys(query).length > 0 ? query : undefined;
    }

    function visitTag(tag: string | null): void {
        router.visit(scrapsRoute({ query: routeQuery(tag) }));
    }

    function visitStatus(status: 'active' | 'archived'): void {
        router.visit(scrapsRoute({ query: routeQuery(activeTag, status) }));
    }

    function toggleItem(id: number): void {
        setCheckedIds((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    }

    function toggleAll(): void {
        if (allChecked) {
            setCheckedIds(new Set());
        } else {
            setCheckedIds(new Set(listIds));
        }
    }

    function bulkArchive(): void {
        const ids = [...checkedIds];

        if (ids.length === 0 || isArchiving) {
            return;
        }

        setIsArchiving(true);
        router.post(
            scrapsBulkArchive().url,
            { ids },
            { onFinish: () => setIsArchiving(false) },
        );
    }

    function bulkDestroy(): void {
        const ids = [...checkedIds];

        if (ids.length === 0 || isDeleting) {
            return;
        }

        setIsDeleting(true);
        router.delete(scrapsBulkDestroy().url, {
            data: { ids },
            onFinish: () => setIsDeleting(false),
        });
    }

    function generateDocument(): void {
        const ids = [...checkedIds];

        if (ids.length === 0 || isGenerating) {
            return;
        }

        setIsGenerating(true);
        router.post(
            documentsCompose().url,
            { ids, document_type: 'spec' },
            { onFinish: () => setIsGenerating(false) },
        );
    }

    function startBulkBackup(): void {
        const ids = [...checkedIds];

        if (ids.length === 0) {
            return;
        }

        // Build initial items list from current scraps data
        const items: BulkBackupItem[] = ids.map((id) => {
            const scrap = scraps.data.find((s) => s.id === id);

            return {
                id,
                title: scrap?.title ?? scrap?.slug ?? String(id),
                status: 'pending',
            };
        });

        setBulkBackup({ phase: 'running', items });

        // Build SSE URL with ids[] query params
        const params = ids.map((id) => `ids[]=${id}`).join('&');
        const url = `${backupBulkStream.url()}?${params}`;

        const source = new EventSource(url);
        sseRef.current = source;

        source.addEventListener('progress', (e: MessageEvent) => {
            const data = JSON.parse(e.data) as {
                scrapId: number;
                title: string;
                status: 'done' | 'failed';
                filename?: string;
            };
            setBulkBackup((prev) => {
                if (prev.phase !== 'running') {
                    return prev;
                }

                return {
                    ...prev,
                    items: prev.items.map((item) =>
                        item.id === data.scrapId
                            ? {
                                  ...item,
                                  status: data.status,
                                  filename: data.filename,
                              }
                            : item,
                    ),
                };
            });
        });

        source.addEventListener('complete', (e: MessageEvent) => {
            const data = JSON.parse(e.data) as {
                total: number;
                succeeded: number;
                failed: number;
            };
            source.close();
            sseRef.current = null;
            setBulkBackup((prev) => {
                if (prev.phase !== 'running') {
                    return prev;
                }

                return {
                    phase: 'done',
                    items: prev.items,
                    succeeded: data.succeeded,
                    failed: data.failed,
                };
            });
        });

        source.onerror = () => {
            source.close();
            sseRef.current = null;
            setBulkBackup((prev) =>
                prev.phase === 'running'
                    ? {
                          phase: 'done',
                          items: prev.items,
                          succeeded: 0,
                          failed: prev.items.length,
                      }
                    : prev,
            );
        };
    }

    function closeBulkBackup(): void {
        sseRef.current?.close();
        sseRef.current = null;
        setBulkBackup({ phase: 'idle' });
        setCheckedIds(new Set());
    }

    // ── Detail view ────────────────────────────────────────────────────────
    // eslint-disable-next-line react-hooks/rules-of-hooks
    const scrapHeadingPrefix = selectedScrap ? `scrap-${selectedScrap.id}` : '';
    // eslint-disable-next-line react-hooks/rules-of-hooks
    const scrapHeadings = useMemo(
        () =>
            selectedScrap?.content
                ? extractMarkdownHeadings(
                      selectedScrap.content,
                      scrapHeadingPrefix,
                  )
                : [],
        [selectedScrap?.id, selectedScrap?.content],
    );

    if (selectedScrap) {
        return (
            <>
                <Head title={selectedScrap.title ?? __('Untitled scrap')} />
                <div
                    ref={headerRef}
                    className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                >
                    <div>
                        <Link
                            href={scrapsRoute({ query: routeQuery(activeTag) })}
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            ← {__('Scraps')}
                        </Link>
                    </div>

                    <div className="mx-auto w-full max-w-5xl">
                        <div
                            className={
                                scrapHeadings.length > 0
                                    ? 'lg:grid lg:grid-cols-[1fr_220px] lg:gap-8'
                                    : ''
                            }
                        >
                            <div className="space-y-6">
                                <div className="space-y-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={toneForStatus(
                                                selectedScrap.status,
                                            )}
                                        >
                                            {statusLabels[
                                                selectedScrap.status
                                            ] ?? selectedScrap.status}
                                        </Badge>
                                        <Badge variant="outline">
                                            {sourceLabels[
                                                selectedScrap.sourceType
                                            ] ?? selectedScrap.sourceType}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            <DateDisplay
                                                value={selectedScrap.occurredAt}
                                            />
                                        </span>
                                    </div>

                                    {selectedScrap.tags.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {selectedScrap.tags.map((tag) => (
                                                <Badge
                                                    key={tag}
                                                    variant="outline"
                                                >
                                                    #{tag}
                                                </Badge>
                                            ))}
                                        </div>
                                    )}

                                    <h1 className="text-2xl font-bold text-foreground">
                                        {selectedScrap.title ??
                                            __('Untitled scrap')}
                                    </h1>
                                </div>

                                <div className="rounded-2xl border border-border/70 bg-background/70 p-6">
                                    <MarkdownPreview
                                        content={selectedScrap.content}
                                        headingPrefix={scrapHeadingPrefix}
                                    />
                                </div>

                                {selectedScrap.children.length > 0 && (
                                    <div className="space-y-3">
                                        <h2 className="text-sm font-medium text-foreground">
                                            {__('Child scraps')}
                                            <span className="ml-2 text-muted-foreground">
                                                {__(':count linked', {
                                                    count: selectedScrap
                                                        .children.length,
                                                })}
                                            </span>
                                        </h2>
                                        <div className="space-y-3">
                                            {selectedScrap.children.map(
                                                (child) => (
                                                    <div
                                                        key={child.id}
                                                        className="rounded-2xl border border-border/70 bg-background/60 p-4"
                                                    >
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div>
                                                                <p className="font-medium text-foreground">
                                                                    {child.title ??
                                                                        __(
                                                                            'Untitled scrap',
                                                                        )}
                                                                </p>
                                                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                                    {child.summary ??
                                                                        __(
                                                                            'No summary yet.',
                                                                        )}
                                                                </p>
                                                            </div>
                                                            <Badge
                                                                variant={toneForStatus(
                                                                    child.status,
                                                                )}
                                                            >
                                                                {statusLabels[
                                                                    child.status
                                                                ] ??
                                                                    child.status}
                                                            </Badge>
                                                        </div>
                                                        <div className="mt-3 flex gap-2 text-xs text-muted-foreground">
                                                            <span>
                                                                {sourceLabels[
                                                                    child
                                                                        .sourceType
                                                                ] ??
                                                                    child.sourceType}
                                                            </span>
                                                            <span>•</span>
                                                            <span>
                                                                <DateDisplay
                                                                    value={
                                                                        child.occurredAt
                                                                    }
                                                                />
                                                            </span>
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {scrapHeadings.length > 0 && (
                                <aside className="hidden self-start lg:sticky lg:top-24 lg:block">
                                    <TableOfContents
                                        headings={scrapHeadings}
                                        sticky
                                    />
                                </aside>
                            )}
                        </div>
                    </div>
                </div>
            </>
        );
    }

    // ── Backup history view ────────────────────────────────────────────────
    if (isBackupsView) {
        return (
            <>
                <Head title={__('Backups')} />
                <div
                    ref={headerRef}
                    className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6"
                >
                    {/* Toolbar */}
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => router.visit(scrapsRoute())}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            ← {__('Scraps')}
                        </Button>
                        <span className="text-sm font-medium text-foreground">
                            {__('Backups')}
                        </span>
                    </div>

                    {/* Backup list */}
                    <div className="space-y-2">
                        {backups.length === 0 && (
                            <p className="py-12 text-center text-sm text-muted-foreground">
                                {__('No backups yet.')}
                            </p>
                        )}
                        {backups.map((entry) => (
                            <div
                                key={entry.filename}
                                className="rounded-2xl border border-border/70 bg-background/80 p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium text-foreground">
                                            {entry.scrapTitle ??
                                                entry.scrapSlug ??
                                                entry.filename}
                                        </p>
                                        {entry.description && (
                                            <p className="mt-1 line-clamp-2 text-sm leading-6 text-muted-foreground">
                                                {entry.description}
                                            </p>
                                        )}
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className="shrink-0 font-mono text-[11px]"
                                    >
                                        {formatFileSize(entry.fileSize)}
                                    </Badge>
                                </div>
                                <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    {entry.scrapSlug && entry.scrapTitle && (
                                        <>
                                            <span className="font-mono">
                                                {entry.scrapSlug}
                                            </span>
                                            <span>•</span>
                                        </>
                                    )}
                                    <DateDisplay value={entry.createdAt} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </>
        );
    }

    // ── List view ──────────────────────────────────────────────────────────
    return (
        <>
            <Head title={__('Scraps')} />
            <div
                ref={headerRef}
                className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6"
            >
                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <Tabs
                        value={activeStatus}
                        onValueChange={(v) =>
                            visitStatus(v as 'active' | 'archived')
                        }
                        className="w-auto"
                    >
                        <TabsList className="h-10 rounded-xl bg-muted/50 p-1">
                            <TabsTrigger
                                value="active"
                                className="gap-2 rounded-lg px-4 data-[state=active]:bg-background data-[state=active]:shadow-sm"
                            >
                                <FileTextIcon className="size-4" />
                                {__('Active')}
                            </TabsTrigger>
                            <TabsTrigger
                                value="archived"
                                className="gap-2 rounded-lg px-4 data-[state=active]:bg-background data-[state=active]:shadow-sm"
                            >
                                <ArchiveIcon className="size-4" />
                                {__('Archived')}
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    {availableTags.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant={
                                    activeTag === null ? 'default' : 'outline'
                                }
                                onClick={() => visitTag(null)}
                            >
                                {__('All tags')}
                            </Button>
                            {availableTags.map((tag) => (
                                <Button
                                    key={tag}
                                    size="sm"
                                    variant={
                                        activeTag === tag
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => visitTag(tag)}
                                >
                                    #{tag}
                                </Button>
                            ))}
                        </div>
                    )}

                    <Button
                        size="sm"
                        variant="ghost"
                        className="ml-auto text-muted-foreground hover:text-foreground"
                        onClick={() =>
                            router.visit(
                                scrapsRoute({ query: { view: 'backups' } }),
                            )
                        }
                    >
                        {__('Backups')}
                    </Button>
                </div>

                {/* Select all row */}
                {scraps.data.length > 0 && (
                    <div className="flex items-center gap-3 px-1">
                        <input
                            type="checkbox"
                            id="select-all"
                            checked={allChecked}
                            ref={(el) => {
                                if (el) {
                                    el.indeterminate = someChecked;
                                }
                            }}
                            onChange={toggleAll}
                            className="size-4 cursor-pointer rounded border-border accent-foreground"
                        />
                        <label
                            htmlFor="select-all"
                            className="cursor-pointer text-sm text-muted-foreground select-none"
                        >
                            {checkedIds.size > 0
                                ? __(':count selected', {
                                      count: checkedIds.size,
                                  })
                                : __('Select all')}
                        </label>
                    </div>
                )}

                {/* List */}
                <InfiniteScroll
                    data="scraps"
                    next={({ loading }) =>
                        loading ? (
                            <div className="pt-4 text-center text-sm text-muted-foreground">
                                {__('Loading...')}
                            </div>
                        ) : null
                    }
                >
                    <div className="space-y-2">
                        {scraps.data.length === 0 && (
                            <p className="py-12 text-center text-sm text-muted-foreground">
                                {isArchivedView
                                    ? __('No archived scraps.')
                                    : __('No scraps yet.')}
                            </p>
                        )}
                        {scraps.data.map((item) => (
                            <div
                                key={item.id}
                                className={`flex items-start gap-3 rounded-2xl border bg-background/80 p-4 transition-all duration-200 hover:border-primary/30 hover:bg-card hover:shadow-lg hover:shadow-black/5 ${
                                    checkedIds.has(item.id)
                                        ? 'border-primary/50 ring-2 ring-primary/20'
                                        : 'border-border/50'
                                }`}
                            >
                                <div className="flex shrink-0 items-start pt-0.5">
                                    <input
                                        type="checkbox"
                                        checked={checkedIds.has(item.id)}
                                        onChange={() => toggleItem(item.id)}
                                        className="size-4 cursor-pointer rounded border-border accent-primary"
                                        aria-label={
                                            item.title ?? __('Untitled scrap')
                                        }
                                    />
                                </div>
                                <Link
                                    href={
                                        item.slug
                                            ? scrapsShow(item.slug, {
                                                  query: routeQuery(activeTag),
                                              })
                                            : scrapsRoute({
                                                  query: routeQuery(activeTag),
                                              })
                                    }
                                    prefetch
                                    className="min-w-0 flex-1"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium text-foreground">
                                                {item.title ??
                                                    __('Untitled scrap')}
                                            </p>
                                            {item.summary && (
                                                <p className="mt-1 line-clamp-2 text-sm leading-6 text-muted-foreground">
                                                    {item.summary}
                                                </p>
                                            )}
                                        </div>
                                        <Badge
                                            variant={toneForStatus(item.status)}
                                            className="shrink-0"
                                        >
                                            {statusLabels[item.status] ??
                                                item.status}
                                        </Badge>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span className="inline-flex items-center gap-1">
                                            <span className="size-1.5 rounded-full bg-primary/40" />
                                            {sourceLabels[item.sourceType] ??
                                                item.sourceType}
                                        </span>
                                        {item.tags.map((tag) => (
                                            <Badge
                                                key={tag}
                                                variant="outline"
                                                className="text-[11px]"
                                            >
                                                #{tag}
                                            </Badge>
                                        ))}
                                        <span>•</span>
                                        <span>
                                            <DateDisplay
                                                value={item.occurredAt}
                                            />
                                        </span>
                                    </div>
                                </Link>
                            </div>
                        ))}
                    </div>
                </InfiniteScroll>

                {/* Action bar — shown when items are checked */}
                {checkedIds.size > 0 && bulkBackup.phase === 'idle' && (
                    <div className="sticky bottom-4 flex items-center justify-between rounded-2xl border border-border bg-background/95 px-4 py-3 shadow-lg backdrop-blur-sm">
                        <span className="text-sm font-medium">
                            {__(':count selected', { count: checkedIds.size })}
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setCheckedIds(new Set())}
                                disabled={
                                    isArchiving || isDeleting || isGenerating
                                }
                            >
                                {__('Clear')}
                            </Button>
                            {isArchivedView ? (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={bulkDestroy}
                                    disabled={isDeleting || isGenerating}
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                >
                                    <Trash2Icon className="size-3.5" />
                                    {isDeleting
                                        ? __('Deleting…')
                                        : __('Delete :count', {
                                              count: checkedIds.size,
                                          })}
                                </Button>
                            ) : (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={bulkArchive}
                                    disabled={isArchiving || isGenerating}
                                >
                                    <ArchiveIcon className="size-3.5" />
                                    {isArchiving
                                        ? __('Archiving…')
                                        : __('Archive :count', {
                                              count: checkedIds.size,
                                          })}
                                </Button>
                            )}
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={generateDocument}
                                disabled={
                                    isArchiving || isDeleting || isGenerating
                                }
                            >
                                <FileTextIcon className="size-3.5" />
                                {isGenerating
                                    ? __('Composing…')
                                    : __('Compose :count', {
                                          count: checkedIds.size,
                                      })}
                            </Button>
                            <Button
                                size="sm"
                                onClick={startBulkBackup}
                                disabled={
                                    isArchiving || isDeleting || isGenerating
                                }
                            >
                                <HardDriveDownloadIcon className="size-3.5" />
                                {__('Backup :count', {
                                    count: checkedIds.size,
                                })}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Bulk backup progress panel */}
                {bulkBackup.phase !== 'idle' && (
                    <div className="sticky bottom-4 rounded-2xl border border-border bg-background/95 shadow-lg backdrop-blur-sm">
                        {/* Header */}
                        <div className="flex items-center justify-between border-b border-border/50 px-4 py-3">
                            <span className="text-sm font-medium">
                                {bulkBackup.phase === 'done'
                                    ? bulkBackup.failed > 0
                                        ? __(
                                              'Backup complete (:succeeded done, :failed failed)',
                                              {
                                                  succeeded:
                                                      bulkBackup.succeeded,
                                                  failed: bulkBackup.failed,
                                              },
                                          )
                                        : __('Backup complete — :count saved', {
                                              count: bulkBackup.succeeded,
                                          })
                                    : __('Backing up… :done / :total', {
                                          done: bulkBackup.items.filter(
                                              (i) => i.status !== 'pending',
                                          ).length,
                                          total: bulkBackup.items.length,
                                      })}
                            </span>
                            {bulkBackup.phase === 'done' && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={closeBulkBackup}
                                >
                                    {__('Close')}
                                </Button>
                            )}
                        </div>

                        {/* Per-item list */}
                        <div className="max-h-48 overflow-y-auto px-4 py-2">
                            {bulkBackup.items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center gap-3 py-1.5"
                                >
                                    <span
                                        className={`shrink-0 text-sm ${
                                            item.status === 'done'
                                                ? 'text-green-500'
                                                : item.status === 'failed'
                                                  ? 'text-destructive'
                                                  : 'text-muted-foreground'
                                        }`}
                                    >
                                        {item.status === 'done'
                                            ? '✓'
                                            : item.status === 'failed'
                                              ? '✗'
                                              : '·'}
                                    </span>
                                    <span
                                        className={`min-w-0 flex-1 truncate text-sm ${
                                            item.status === 'pending'
                                                ? 'text-muted-foreground'
                                                : 'text-foreground'
                                        }`}
                                    >
                                        {item.title}
                                    </span>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {item.status === 'done'
                                            ? __('Done')
                                            : item.status === 'failed'
                                              ? __('Failed')
                                              : __('Pending')}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
