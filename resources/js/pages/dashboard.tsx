import { useLang } from '@erag/lang-sync-inertia/react';
import {
    Head,
    InfiniteScroll,
    Link,
    router,
    setLayoutProps,
    useForm,
    useHttp,
} from '@inertiajs/react';
import {
    Archive,
    ChevronDown,
    CircleMinus,
    CirclePlus,
    CornerDownLeft,
    Download,
    PencilLine,
    RotateCcw,
    Sparkles,
    Undo2,
} from 'lucide-react';
import { Fragment, useEffect, useRef, useState } from 'react';
import BackupController from '@/actions/App/Http/Controllers/BackupController';
import ScrapController from '@/actions/App/Http/Controllers/ScrapController';
import InputError from '@/components/input-error';
import { ScrapCard } from '@/components/scrap-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toneForStatus } from '@/lib/scrap-utils';
import { dashboard } from '@/routes';
import { show as dashboardShow } from '@/routes/dashboard';

type InboxItem = {
    id: number;
    parentId: number | null;
    title: string | null;
    slug: string | null;
    content: string;
    sourceType: string;
    status: string;
    summary: string | null;
    tags: string[];
    occurredAt: string | null;
    latestBackup: {
        createdAt: string;
        description: string | null;
        scrapSlug: string;
    } | null;
    children: {
        id: number;
        parentId: number | null;
        title: string | null;
        sourceType: string;
        status: string;
        summary: string | null;
        occurredAt: string | null;
    }[];
};

type RelatedScrap = {
    id: number;
    title: string | null;
    slug: string | null;
    summary: string | null;
    status: string;
    sourceType: string;
    occurredAt: string | null;
    similarity: number;
};

type DashboardProps = {
    inboxItems: {
        data: InboxItem[];
    };
    selectedScrap: InboxItem | null;
    relatedScraps: RelatedScrap[];
    availableTags: string[];
    activeTag: string | null;
    activeStatus: 'active' | 'archived';
};

type SuggestedMetadata = {
    title: string;
    slug: string;
};

function buildFallbackTitle(content: string, currentTitle: string): string {
    const normalizedTitle = currentTitle.trim().replace(/\s+/g, ' ');

    if (normalizedTitle !== '') {
        return normalizedTitle.slice(0, 72);
    }

    const trimmedContent = content.trim();
    const assetMatch = trimmedContent.match(
        /^!?\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)$/u,
    );

    if (assetMatch) {
        const candidate = (assetMatch[1] || assetMatch[2] || '').trim();

        if (candidate !== '') {
            const withoutQuery =
                candidate.split('?')[0]?.split('#')[0] ?? candidate;
            const lastSegment = withoutQuery.split('/').pop() ?? withoutQuery;
            const withoutExtension = lastSegment.replace(/\.[^.]+$/u, '');
            const cleaned = withoutExtension
                .replace(/[-_]+/gu, ' ')
                .trim()
                .replace(/\s+/g, ' ');

            if (cleaned !== '') {
                return cleaned.slice(0, 72);
            }
        }
    }

    return content.trim().replace(/\s+/g, ' ').slice(0, 72);
}

function buildFallbackSlug(title: string, currentSlug: string): string {
    const source = currentSlug.trim() !== '' ? currentSlug : title;

    return source
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

function isSupportedBodyFile(file: File): boolean {
    if (file.type.startsWith('image/')) {
        return true;
    }

    const lowerCaseName = file.name.toLowerCase();

    return ['.pdf', '.doc', '.docx', '.md', '.txt'].some((extension) =>
        lowerCaseName.endsWith(extension),
    );
}

export default function Dashboard({
    inboxItems,
    selectedScrap: initialSelectedScrap,
    relatedScraps,
    availableTags,
    activeTag,
    activeStatus,
}: DashboardProps) {
    const { __ } = useLang();
    const [selectedScrap, setSelectedScrap] = useState<InboxItem | null>(
        initialSelectedScrap,
    );
    const [isEditingSelected, setIsEditingSelected] = useState(false);
    const [editorMode, setEditorMode] = useState<'write' | 'preview'>('write');
    const [showMainMetaFields, setShowMainMetaFields] = useState(false);
    const [showChildMetaFields, setShowChildMetaFields] = useState(false);
    const [isSuggestionDialogOpen, setIsSuggestionDialogOpen] = useState(false);
    const [isBackupDialogOpen, setIsBackupDialogOpen] = useState(false);
    const [backupDescription, setBackupDescription] = useState('');
    const [rightPanelTab, setRightPanelTab] = useState<'recent' | 'similar'>(
        'recent',
    );
    const [showMobileRecent, setShowMobileRecent] = useState(false);
    const [pendingOrganize, setPendingOrganize] = useState<boolean | null>(
        null,
    );
    const [suggestedMetadata, setSuggestedMetadata] =
        useState<SuggestedMetadata>({
            title: '',
            slug: '',
        });
    const recentScraps = inboxItems.data;
    const isArchivedView = activeStatus === 'archived';
    const selectedScrapIsArchived = selectedScrap?.status === 'archived';

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

    function workspaceIndex(query?: {
        tag?: string;
        status?: 'archived';
    }): ReturnType<typeof dashboard> {
        return dashboard({ query });
    }

    function workspaceShow(
        slug: string,
        query?: { tag?: string; status?: 'archived' },
    ): ReturnType<typeof dashboardShow> {
        return dashboardShow(slug, { query });
    }

    function visitTag(tag: string | null): void {
        router.visit(
            selectedScrap?.slug
                ? workspaceShow(selectedScrap.slug, routeQuery(tag))
                : workspaceIndex(routeQuery(tag)),
        );
    }

    function visitStatus(status: 'active' | 'archived'): void {
        router.visit(
            selectedScrap?.slug
                ? workspaceShow(
                      selectedScrap.slug,
                      routeQuery(activeTag, status),
                  )
                : workspaceIndex(routeQuery(activeTag, status)),
        );
    }

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

    setLayoutProps({
        breadcrumbs: [
            {
                title: __('Dashboard'),
                href: dashboard(),
            },
        ],
    });

    const form = useForm({
        title: '',
        slug: '',
        content: '',
    });
    const archiveForm = useForm({});
    const nextUploadKeyRef = useRef(1);
    const pendingUploadRef = useRef<{
        key: string;
        fileName: string;
        isImage: boolean;
        selectionStart: number;
        selectionEnd: number;
    } | null>(null);
    const mainTextareaRef = useRef<HTMLTextAreaElement | null>(null);
    const childTextareaRef = useRef<HTMLTextAreaElement | null>(null);
    const suggestionRequest = useHttp<
        {
            title: string;
            slug: string;
            content: string;
            parent_id: number | null;
            scrap_id: number | null;
        },
        { title: string | null; slug: string | null }
    >(() => ({
        title: form.data.title,
        slug: form.data.slug,
        content: form.data.content,
        parent_id:
            selectedScrap && !isEditingSelected ? selectedScrap.id : null,
        scrap_id: selectedScrap && isEditingSelected ? selectedScrap.id : null,
    }));

    // Inertia reuses this page component between visits, so the editor needs to
    // resync its local UI state from the latest server payload after navigation.
    /* eslint-disable react-hooks/set-state-in-effect, react-hooks/exhaustive-deps */
    useEffect(() => {
        setSelectedScrap(initialSelectedScrap);
        setIsEditingSelected(false);
        setEditorMode('write');
        setShowMainMetaFields(false);
        setShowChildMetaFields(false);
        setIsSuggestionDialogOpen(false);
        setPendingOrganize(null);
        setRightPanelTab('recent');
        form.resetAndClearErrors();
    }, [initialSelectedScrap]);

    useEffect(() => {
        if (!selectedScrap) {
            return;
        }

        const refreshedSelection = recentScraps.find(
            (item) => item.id === selectedScrap.id,
        );

        if (!refreshedSelection) {
            beginNewScrap();

            return;
        }

        setSelectedScrap((prev) => ({
            ...refreshedSelection,
            latestBackup: prev?.latestBackup ?? null,
        }));

        if (isEditingSelected) {
            form.setData({
                title: refreshedSelection.title ?? '',
                slug: refreshedSelection.slug ?? '',
                content: refreshedSelection.content,
            });
        }
    }, [recentScraps, selectedScrap?.id, isEditingSelected]);
    /* eslint-enable react-hooks/set-state-in-effect, react-hooks/exhaustive-deps */

    function beginNewScrap(): void {
        if (isArchivedView) {
            router.visit(workspaceIndex(routeQuery(activeTag, 'active')));

            return;
        }

        if (initialSelectedScrap !== null) {
            router.visit(workspaceIndex(routeQuery(activeTag)));

            return;
        }

        setSelectedScrap(null);
        setIsEditingSelected(false);
        setEditorMode('write');
        setShowMainMetaFields(false);
        setShowChildMetaFields(false);
        setIsSuggestionDialogOpen(false);
        setPendingOrganize(null);
        form.reset();
        form.clearErrors();
    }

    function openScrap(scrap: InboxItem): void {
        setSelectedScrap(scrap);
        setIsEditingSelected(false);
        setEditorMode('write');
        setShowMainMetaFields(false);
        setShowChildMetaFields(false);
        setIsSuggestionDialogOpen(false);
        setPendingOrganize(null);
        form.resetAndClearErrors();
    }

    function beginEditSelectedScrap(): void {
        if (!selectedScrap) {
            return;
        }

        setIsEditingSelected(true);
        setEditorMode('write');
        setShowMainMetaFields(
            Boolean(selectedScrap.title || selectedScrap.slug),
        );
        form.setData({
            title: selectedScrap.title ?? '',
            slug: selectedScrap.slug ?? '',
            content: selectedScrap.content,
        });
        form.clearErrors();
    }

    function cancelEditSelectedScrap(): void {
        if (!selectedScrap) {
            beginNewScrap();

            return;
        }

        setIsEditingSelected(false);
        setEditorMode('write');
        setShowMainMetaFields(false);
        setIsSuggestionDialogOpen(false);
        setPendingOrganize(null);
        form.resetAndClearErrors();
    }

    function archiveSelectedScrap(): void {
        if (!selectedScrap) {
            return;
        }

        archiveForm.submit(ScrapController.archive(selectedScrap.id), {
            preserveScroll: true,
            onSuccess: () => {
                beginNewScrap();
            },
        });
    }

    function restoreSelectedScrap(): void {
        if (!selectedScrap) {
            return;
        }

        archiveForm.submit(ScrapController.restore(selectedScrap.id), {
            preserveScroll: true,
        });
    }

    function currentTextarea(): HTMLTextAreaElement | null {
        return selectedScrap && !isEditingSelected
            ? childTextareaRef.current
            : mainTextareaRef.current;
    }

    function applyUploadedAsset(upload: { key: string; url: string }): void {
        const pendingUpload = pendingUploadRef.current;

        if (!pendingUpload || upload.key !== pendingUpload.key) {
            return;
        }

        const markdown = pendingUpload.isImage
            ? `![${pendingUpload.fileName}](${upload.url})`
            : `[${pendingUpload.fileName}](${upload.url})`;

        form.setData((data) => ({
            ...data,
            content:
                data.content.slice(0, pendingUpload.selectionStart) +
                markdown +
                data.content.slice(pendingUpload.selectionEnd),
        }));

        pendingUploadRef.current = null;

        setTimeout(() => {
            const textarea = currentTextarea();

            if (!textarea) {
                return;
            }

            const position = pendingUpload.selectionStart + markdown.length;
            textarea.setSelectionRange(position, position);
            textarea.focus();
        }, 0);
    }

    function uploadBodyAsset(file: File | null): void {
        if (!file) {
            return;
        }

        const textarea = currentTextarea();
        const uploadKey = `scrap-image-${nextUploadKeyRef.current++}`;

        pendingUploadRef.current = {
            key: uploadKey,
            fileName: file.name,
            isImage: file.type.startsWith('image/'),
            selectionStart: textarea?.selectionStart ?? 0,
            selectionEnd: textarea?.selectionEnd ?? 0,
        };

        router.post(
            ScrapController.uploadImage(),
            { image: file, upload_key: uploadKey },
            {
                forceFormData: true,
                preserveState: true,
                preserveScroll: true,
                onFlash: (flash) => {
                    const upload = flash.scrapImageUpload;

                    if (
                        upload &&
                        typeof upload === 'object' &&
                        'key' in upload &&
                        'url' in upload &&
                        typeof upload.key === 'string' &&
                        typeof upload.url === 'string'
                    ) {
                        applyUploadedAsset({
                            key: upload.key,
                            url: upload.url,
                        });
                    }
                },
                onError: (errors) => {
                    pendingUploadRef.current = null;
                    alert(errors.image ?? __('File upload failed.'));
                },
            },
        );
    }

    function handleBodyDrop(event: React.DragEvent<HTMLTextAreaElement>): void {
        event.preventDefault();

        const imageFile = Array.from(event.dataTransfer.files).find((file) =>
            isSupportedBodyFile(file),
        );

        if (imageFile) {
            uploadBodyAsset(imageFile);
        }
    }

    function handleBodyPaste(
        event: React.ClipboardEvent<HTMLTextAreaElement>,
    ): void {
        const imageItem = Array.from(event.clipboardData.items).find((item) =>
            item.type.startsWith('image/'),
        );

        if (!imageItem) {
            return;
        }

        event.preventDefault();

        const file = imageItem.getAsFile();

        if (file) {
            uploadBodyAsset(file);
        }
    }

    function persistScrap(
        organize: boolean,
        overrides?: Partial<typeof form.data>,
    ): void {
        const isEditingExistingScrap =
            isEditingSelected && selectedScrap !== null;
        const parentScrap = !isEditingSelected ? selectedScrap : null;
        const nextData = {
            ...form.data,
            ...overrides,
        };

        form.transform(() => ({
            ...nextData,
            organize,
            parent_id: isEditingExistingScrap
                ? null
                : (parentScrap?.id ?? null),
        }));

        form.submit(
            isEditingExistingScrap && selectedScrap
                ? ScrapController.update(selectedScrap.id)
                : ScrapController.store(),
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (!isEditingExistingScrap) {
                        if (parentScrap) {
                            setEditorMode('write');
                            setShowChildMetaFields(false);
                            setIsSuggestionDialogOpen(false);
                            setPendingOrganize(null);
                            form.resetAndClearErrors();

                            return;
                        }

                        beginNewScrap();

                        return;
                    }

                    setIsEditingSelected(false);
                    setEditorMode('write');
                    setIsSuggestionDialogOpen(false);
                    setPendingOrganize(null);
                    form.clearErrors();
                },
            },
        );
    }

    const canEditSlug =
        selectedScrap === null || selectedScrap.parentId === null;
    const shouldSuggestSlugForCurrentSave =
        selectedScrap === null
            ? true
            : isEditingSelected
              ? selectedScrap.parentId === null
              : false;

    async function handleSave(organize: boolean): Promise<void> {
        const missingTitle = form.data.title.trim() === '';
        const missingSlug =
            shouldSuggestSlugForCurrentSave && form.data.slug.trim() === '';

        if (!missingTitle && !missingSlug) {
            persistScrap(organize);

            return;
        }

        try {
            const response = await suggestionRequest.submit(
                ScrapController.suggestMetadata(),
            );

            setSuggestedMetadata({
                title: response.title ?? '',
                slug: response.slug ?? '',
            });
            setPendingOrganize(organize);
            setIsSuggestionDialogOpen(true);
        } catch {
            const fallbackTitle = buildFallbackTitle(
                form.data.content,
                form.data.title,
            );

            setSuggestedMetadata({
                title: fallbackTitle,
                slug: shouldSuggestSlugForCurrentSave
                    ? buildFallbackSlug(fallbackTitle, form.data.slug)
                    : '',
            });
            setPendingOrganize(organize);
            setIsSuggestionDialogOpen(true);
        }
    }

    function applySuggestionsAndSave(): void {
        if (pendingOrganize === null) {
            return;
        }

        const overrides: Partial<typeof form.data> = {
            title: suggestedMetadata.title,
        };

        if (shouldSuggestSlugForCurrentSave) {
            overrides.slug = suggestedMetadata.slug;
        }

        form.setData((data) => ({
            ...data,
            ...overrides,
        }));
        setIsSuggestionDialogOpen(false);
        persistScrap(pendingOrganize, overrides);
    }

    return (
        <>
            <Head title={__('Dashboard')} />
            <Dialog
                open={isSuggestionDialogOpen}
                onOpenChange={(open) => {
                    if (!suggestionRequest.processing) {
                        setIsSuggestionDialogOpen(open);

                        if (!open) {
                            setPendingOrganize(null);
                        }
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {__('AI metadata suggestion')}
                        </DialogTitle>
                        <DialogDescription>
                            {shouldSuggestSlugForCurrentSave
                                ? __(
                                      'Title and slug were incomplete, so the AI prepared a polished title and a unique slug before save.',
                                  )
                                : __(
                                      'Title and slug were incomplete, so the AI prepared a polished title before save.',
                                  )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="suggested-title">
                                {__('Suggested title')}
                            </Label>
                            <Input
                                id="suggested-title"
                                value={suggestedMetadata.title}
                                onChange={(event) =>
                                    setSuggestedMetadata((current) => ({
                                        ...current,
                                        title: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        {shouldSuggestSlugForCurrentSave && (
                            <div className="space-y-2">
                                <Label htmlFor="suggested-slug">
                                    {__('Suggested slug')}
                                </Label>
                                <Input
                                    id="suggested-slug"
                                    value={suggestedMetadata.slug}
                                    onChange={(event) =>
                                        setSuggestedMetadata((current) => ({
                                            ...current,
                                            slug: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            variant="secondary"
                            onClick={() => {
                                setIsSuggestionDialogOpen(false);
                                setPendingOrganize(null);
                            }}
                        >
                            {__('Cancel')}
                        </Button>
                        <Button onClick={applySuggestionsAndSave}>
                            {__('Save with these values')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog
                open={isBackupDialogOpen}
                onOpenChange={setIsBackupDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{__('Create backup')}</DialogTitle>
                        <DialogDescription>
                            {__(
                                'Optionally add a description. It will be saved inside the zip.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="backup-description">
                            {__('Description')}
                            <span className="ml-1 text-xs text-muted-foreground">
                                ({__('optional')})
                            </span>
                        </Label>
                        <textarea
                            id="backup-description"
                            className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder={__('e.g. Before refactoring the spec')}
                            value={backupDescription}
                            onChange={(e) =>
                                setBackupDescription(e.target.value)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="secondary">{__('Cancel')}</Button>
                        </DialogClose>
                        <Button
                            onClick={() => {
                                if (!selectedScrap) {
                                    return;
                                }

                                router.post(
                                    BackupController.backupScrap.url(
                                        selectedScrap.id,
                                    ),
                                    { description: backupDescription },
                                );

                                setIsBackupDialogOpen(false);
                            }}
                        >
                            <Download className="size-4" />
                            {__('Save backup')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 md:p-6">
                <Sheet
                    open={showMobileRecent}
                    onOpenChange={setShowMobileRecent}
                >
                    <button
                        type="button"
                        onClick={() => setShowMobileRecent(true)}
                        className="flex items-center justify-between rounded-2xl border border-border/50 bg-muted/40 px-4 py-3 text-sm text-muted-foreground transition-colors hover:text-foreground lg:hidden"
                    >
                        <span>{__('Recent scraps')}</span>
                        <ChevronDown className="size-4 -rotate-90" />
                    </button>
                    <SheetContent
                        side="right"
                        className="w-80 overflow-y-auto sm:w-96"
                    >
                        <SheetHeader>
                            <SheetTitle>{__('Recent scraps')}</SheetTitle>
                        </SheetHeader>
                        <div className="mt-4 space-y-3">
                            <InfiniteScroll
                                data="inboxItems"
                                manual
                                next={({ loading, fetch, hasMore }) =>
                                    hasMore ? (
                                        <div className="pt-2">
                                            <Button
                                                variant="outline"
                                                className="w-full"
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
                                    {recentScraps.map((item) => (
                                        <ScrapCard
                                            key={item.id}
                                            item={item}
                                            isSelected={
                                                selectedScrap?.id === item.id
                                            }
                                            href={
                                                item.slug
                                                    ? workspaceShow(
                                                          item.slug,
                                                          routeQuery(activeTag),
                                                      )
                                                    : undefined
                                            }
                                            onClick={
                                                !item.slug
                                                    ? () => {
                                                          openScrap(item);
                                                          setShowMobileRecent(
                                                              false,
                                                          );
                                                      }
                                                    : undefined
                                            }
                                            compact
                                            statusLabel={
                                                statusLabels[item.status]
                                            }
                                            sourceLabel={
                                                sourceLabels[item.sourceType]
                                            }
                                        />
                                    ))}
                                </div>
                            </InfiniteScroll>
                        </div>
                    </SheetContent>
                </Sheet>

                {/* Filter toolbar */}
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
                                className="rounded-lg px-4 data-[state=active]:bg-background data-[state=active]:shadow-sm"
                            >
                                {__('Active')}
                            </TabsTrigger>
                            <TabsTrigger
                                value="archived"
                                className="rounded-lg px-4 data-[state=active]:bg-background data-[state=active]:shadow-sm"
                            >
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
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,0.72fr)]">
                    <Card className="border-border/50 shadow-sm">
                        <CardHeader className="gap-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle className="text-2xl">
                                        {selectedScrap
                                            ? isEditingSelected
                                                ? __('Edit scrap')
                                                : __('Selected scrap')
                                            : __('New scrap')}
                                    </CardTitle>
                                    <CardDescription className="mt-2 max-w-2xl leading-6">
                                        {selectedScrap
                                            ? isEditingSelected
                                                ? __(
                                                      'You are editing a saved scrap in the same capture workspace.',
                                                  )
                                                : null
                                            : __(
                                                  'Keep it rough. A fragment, a customer quote, a meeting point, a reminder, a link with a note. The goal here is capture, not cleanup. AI can infer the shape after the fact.',
                                              )}
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {(!selectedScrap || isEditingSelected) && (
                                        <div className="flex shrink-0 rounded-xl border border-border/70 bg-background p-1">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setEditorMode('write')
                                                }
                                                className={`rounded-lg px-3 py-1.5 text-sm transition-colors ${
                                                    editorMode === 'write'
                                                        ? 'bg-foreground text-background'
                                                        : 'text-muted-foreground'
                                                }`}
                                            >
                                                {__('Write')}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setEditorMode('preview')
                                                }
                                                className={`rounded-lg px-3 py-1.5 text-sm transition-colors ${
                                                    editorMode === 'preview'
                                                        ? 'bg-foreground text-background'
                                                        : 'text-muted-foreground'
                                                }`}
                                            >
                                                {__('Preview')}
                                            </button>
                                        </div>
                                    )}
                                    {isEditingSelected && (
                                        <Button
                                            variant="outline"
                                            disabled={
                                                form.processing ||
                                                archiveForm.processing
                                            }
                                            onClick={cancelEditSelectedScrap}
                                        >
                                            <Undo2 className="size-4" />
                                            {__('Cancel')}
                                        </Button>
                                    )}
                                    {selectedScrap && !isEditingSelected && (
                                        <Button
                                            variant="outline"
                                            onClick={beginNewScrap}
                                        >
                                            <PencilLine className="size-4" />
                                            {__('New scrap')}
                                        </Button>
                                    )}
                                    {selectedScrap && !isEditingSelected && (
                                        <Button
                                            onClick={beginEditSelectedScrap}
                                        >
                                            <PencilLine className="size-4" />
                                            {__('Edit this scrap')}
                                        </Button>
                                    )}
                                    {selectedScrap && !isEditingSelected && (
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setBackupDescription('');
                                                setIsBackupDialogOpen(true);
                                            }}
                                        >
                                            <Download className="size-4" />
                                            {__('Create backup')}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {selectedScrap && !isEditingSelected && (
                                <div className="space-y-6">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
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
                                                    value={
                                                        selectedScrap.occurredAt
                                                    }
                                                />
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <h2 className="text-xl font-semibold text-foreground">
                                            {selectedScrap.title ??
                                                __('Untitled scrap')}
                                        </h2>
                                        {selectedScrap.latestBackup && (
                                            <p className="mt-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <Download className="size-3 shrink-0" />
                                                <span>
                                                    {__('Last backup:')}{' '}
                                                    <DateDisplay
                                                        value={
                                                            selectedScrap
                                                                .latestBackup
                                                                .createdAt
                                                        }
                                                    />
                                                    {selectedScrap.latestBackup
                                                        .description && (
                                                        <span className="ml-1 text-foreground/60">
                                                            —{' '}
                                                            {
                                                                selectedScrap
                                                                    .latestBackup
                                                                    .description
                                                            }
                                                        </span>
                                                    )}
                                                </span>
                                            </p>
                                        )}
                                    </div>

                                    <div className="rounded-2xl border border-border/70 bg-background/70 p-4">
                                        <MarkdownPreview
                                            content={selectedScrap.content}
                                        />
                                    </div>

                                    <div className="space-y-4 border-t border-border/70 pt-4">
                                        <div>
                                            <h3 className="text-sm font-medium text-foreground">
                                                {__('Add child scrap')}
                                            </h3>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {__(
                                                    'Continue this thought directly under the current article.',
                                                )}
                                            </p>
                                        </div>

                                        <div className="space-y-4 rounded-2xl border border-border/70 bg-background/60 p-4">
                                            <div className="flex items-center justify-end gap-3">
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setShowChildMetaFields(
                                                                (current) =>
                                                                    !current,
                                                            )
                                                        }
                                                        className="text-muted-foreground transition-colors hover:text-foreground"
                                                        aria-label={
                                                            showChildMetaFields
                                                                ? __(
                                                                      'Hide optional child title',
                                                                  )
                                                                : __(
                                                                      'Show optional child title',
                                                                  )
                                                        }
                                                        title={
                                                            showChildMetaFields
                                                                ? __(
                                                                      'Hide optional child title',
                                                                  )
                                                                : __(
                                                                      'Show optional child title',
                                                                  )
                                                        }
                                                    >
                                                        {showChildMetaFields ? (
                                                            <CircleMinus className="size-4" />
                                                        ) : (
                                                            <CirclePlus className="size-4" />
                                                        )}
                                                    </button>
                                                    <div className="flex shrink-0 rounded-xl border border-border/70 bg-background p-1">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setEditorMode(
                                                                    'write',
                                                                )
                                                            }
                                                            className={`rounded-lg px-3 py-1.5 text-sm transition-colors ${
                                                                editorMode ===
                                                                'write'
                                                                    ? 'bg-foreground text-background'
                                                                    : 'text-muted-foreground'
                                                            }`}
                                                        >
                                                            {__('Write')}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setEditorMode(
                                                                    'preview',
                                                                )
                                                            }
                                                            className={`rounded-lg px-3 py-1.5 text-sm transition-colors ${
                                                                editorMode ===
                                                                'preview'
                                                                    ? 'bg-foreground text-background'
                                                                    : 'text-muted-foreground'
                                                            }`}
                                                        >
                                                            {__('Preview')}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            {showChildMetaFields && (
                                                <div className="space-y-2">
                                                    <Label htmlFor="child-scrap-title">
                                                        {__('Optional title')}
                                                    </Label>
                                                    <Input
                                                        id="child-scrap-title"
                                                        value={form.data.title}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'title',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder={__(
                                                            'Leave blank if the body says enough.',
                                                        )}
                                                    />
                                                    <InputError
                                                        message={
                                                            form.errors.title
                                                        }
                                                    />
                                                </div>
                                            )}

                                            <div className="space-y-2">
                                                <Label htmlFor="child-scrap-body">
                                                    {__('Body')}
                                                </Label>
                                                {editorMode === 'write' ? (
                                                    <textarea
                                                        ref={childTextareaRef}
                                                        id="child-scrap-body"
                                                        value={
                                                            form.data.content
                                                        }
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'content',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        onDrop={handleBodyDrop}
                                                        onDragOver={(event) =>
                                                            event.preventDefault()
                                                        }
                                                        onPaste={
                                                            handleBodyPaste
                                                        }
                                                        placeholder={__(
                                                            'Write in Markdown. Drag & drop images or files to attach.',
                                                        )}
                                                        className="min-h-48 w-full rounded-2xl border border-input bg-transparent px-4 py-4 font-mono text-sm leading-6 shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                                    />
                                                ) : (
                                                    <div className="min-h-48 rounded-2xl border border-input bg-background/60 px-4 py-4">
                                                        {form.data.content.trim() ? (
                                                            <MarkdownPreview
                                                                content={
                                                                    form.data
                                                                        .content
                                                                }
                                                            />
                                                        ) : (
                                                            <p className="text-sm text-muted-foreground">
                                                                {__(
                                                                    'Nothing to preview yet.',
                                                                )}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                                <InputError
                                                    message={
                                                        form.errors.content
                                                    }
                                                />
                                            </div>

                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    disabled={form.processing}
                                                    onClick={() =>
                                                        handleSave(false)
                                                    }
                                                >
                                                    <CornerDownLeft className="size-4" />
                                                    {__('Save child scrap')}
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    disabled={form.processing}
                                                    onClick={() =>
                                                        handleSave(true)
                                                    }
                                                >
                                                    <Sparkles className="size-4" />
                                                    {__('Save and organize')}
                                                </Button>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <h3 className="text-sm font-medium text-foreground">
                                                    {__('Child scraps')}
                                                </h3>
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                {__(':count linked', {
                                                    count: selectedScrap
                                                        .children.length,
                                                })}
                                            </span>
                                        </div>
                                    </div>

                                    {selectedScrap.children.length > 0 && (
                                        <div className="space-y-3">
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
                                                                        child
                                                                            .status
                                                                    ] ??
                                                                        child.status}
                                                                </Badge>
                                                            </div>
                                                            <div className="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground">
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

                                    <div className="flex items-center justify-end gap-4 border-t border-border/70 pt-4">
                                        {selectedScrapIsArchived ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    archiveForm.processing
                                                }
                                                onClick={restoreSelectedScrap}
                                            >
                                                <RotateCcw className="size-4" />
                                                {__('Restore')}
                                            </Button>
                                        ) : (
                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={
                                                            archiveForm.processing
                                                        }
                                                        className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    >
                                                        <Archive className="size-4" />
                                                        {__('Send to archive')}
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogHeader>
                                                        <DialogTitle>
                                                            {__(
                                                                'Send to archive?',
                                                            )}
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            {__(
                                                                'This scrap will be moved to the archive and removed from the inbox.',
                                                            )}
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <DialogFooter>
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">
                                                                {__('Cancel')}
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            variant="destructive"
                                                            disabled={
                                                                archiveForm.processing
                                                            }
                                                            onClick={
                                                                archiveSelectedScrap
                                                            }
                                                        >
                                                            <Archive className="size-4" />
                                                            {__(
                                                                'Send to archive',
                                                            )}
                                                        </Button>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        )}
                                    </div>
                                </div>
                            )}

                            {(selectedScrap === null || isEditingSelected) && (
                                <>
                                    {selectedScrap && (
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
                                                    value={
                                                        selectedScrap.occurredAt
                                                    }
                                                />
                                            </span>
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between gap-3 rounded-2xl border border-border/70 bg-background/60 px-4 py-3">
                                            <div>
                                                <p className="text-sm font-medium text-foreground">
                                                    {__('Optional metadata')}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {canEditSlug
                                                        ? __(
                                                              'Add a title and slug only when they help.',
                                                          )
                                                        : __(
                                                              'Add a title only when it helps.',
                                                          )}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setShowMainMetaFields(
                                                        (current) => !current,
                                                    )
                                                }
                                                className="text-muted-foreground transition-colors hover:text-foreground"
                                                aria-label={
                                                    showMainMetaFields
                                                        ? __(
                                                              'Hide optional metadata',
                                                          )
                                                        : __(
                                                              'Show optional metadata',
                                                          )
                                                }
                                                title={
                                                    showMainMetaFields
                                                        ? __(
                                                              'Hide optional metadata',
                                                          )
                                                        : __(
                                                              'Show optional metadata',
                                                          )
                                                }
                                            >
                                                {showMainMetaFields ? (
                                                    <CircleMinus className="size-4" />
                                                ) : (
                                                    <CirclePlus className="size-4" />
                                                )}
                                            </button>
                                        </div>
                                        {showMainMetaFields && (
                                            <div className="space-y-4 rounded-2xl border border-border/70 bg-background/60 p-4">
                                                <div className="space-y-2">
                                                    <Label htmlFor="scrap-title">
                                                        {__('Optional title')}
                                                    </Label>
                                                    <Input
                                                        id="scrap-title"
                                                        value={form.data.title}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'title',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder={__(
                                                            'Leave blank if the body says enough.',
                                                        )}
                                                    />
                                                    <InputError
                                                        message={
                                                            form.errors.title
                                                        }
                                                    />
                                                </div>

                                                {canEditSlug && (
                                                    <div className="space-y-2">
                                                        <Label htmlFor="scrap-slug">
                                                            {__(
                                                                'Optional slug',
                                                            )}
                                                        </Label>
                                                        <Input
                                                            id="scrap-slug"
                                                            value={
                                                                form.data.slug
                                                            }
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'slug',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="dashboard-direction"
                                                        />
                                                        <InputError
                                                            message={
                                                                form.errors.slug
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="scrap-body">
                                            {__('Body')}
                                        </Label>
                                        {editorMode === 'write' ? (
                                            <textarea
                                                ref={mainTextareaRef}
                                                id="scrap-body"
                                                value={form.data.content}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'content',
                                                        event.target.value,
                                                    )
                                                }
                                                onDrop={handleBodyDrop}
                                                onDragOver={(event) =>
                                                    event.preventDefault()
                                                }
                                                onPaste={handleBodyPaste}
                                                placeholder={__(
                                                    'Write in Markdown. Drag & drop images or files to attach.',
                                                )}
                                                className="min-h-72 w-full rounded-2xl border border-input bg-transparent px-4 py-4 font-mono text-sm leading-6 shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                            />
                                        ) : (
                                            <div className="min-h-72 rounded-2xl border border-input bg-background/60 px-4 py-4">
                                                {form.data.content.trim() ? (
                                                    <MarkdownPreview
                                                        content={
                                                            form.data.content
                                                        }
                                                    />
                                                ) : (
                                                    <p className="text-sm text-muted-foreground">
                                                        {__(
                                                            'Nothing to preview yet.',
                                                        )}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                        <InputError
                                            message={form.errors.content}
                                        />
                                    </div>

                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="lg"
                                                className="min-w-36"
                                                disabled={
                                                    form.processing ||
                                                    archiveForm.processing
                                                }
                                                onClick={() =>
                                                    handleSave(false)
                                                }
                                            >
                                                <CornerDownLeft className="size-4" />
                                                {selectedScrap
                                                    ? __('Save changes')
                                                    : __('Save scrap')}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="lg"
                                                className="min-w-40"
                                                disabled={
                                                    form.processing ||
                                                    archiveForm.processing
                                                }
                                                onClick={() => handleSave(true)}
                                            >
                                                <Sparkles className="size-4" />
                                                {selectedScrap
                                                    ? __('Save and organize')
                                                    : __('AI organize on save')}
                                            </Button>
                                        </div>
                                        <p className="text-xs leading-5 text-muted-foreground">
                                            {__(
                                                'Type, tags, priority, and summary can be inferred later through structured output. Paste images or drop and upload files to insert Markdown automatically.',
                                            )}
                                        </p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <div className="hidden min-w-0 lg:flex lg:flex-col">
                        <Card className="border-border/50 shadow-sm">
                            <CardHeader className="pb-0">
                                <Tabs
                                    value={rightPanelTab}
                                    onValueChange={(v) =>
                                        setRightPanelTab(
                                            v as 'recent' | 'similar',
                                        )
                                    }
                                >
                                    <TabsList className="h-10 w-full rounded-xl bg-muted/50 p-1">
                                        <TabsTrigger
                                            value="recent"
                                            className="flex-1 gap-2 rounded-lg data-[state=active]:bg-background data-[state=active]:shadow-sm"
                                        >
                                            {__('Recent')}
                                            <Badge
                                                variant="secondary"
                                                className="ml-1 h-5 text-[10px]"
                                            >
                                                {recentScraps.length}
                                            </Badge>
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="similar"
                                            disabled={
                                                relatedScraps.length === 0
                                            }
                                            className="flex-1 rounded-lg data-[state=active]:bg-background data-[state=active]:shadow-sm"
                                        >
                                            {__('Similar')}
                                        </TabsTrigger>
                                    </TabsList>
                                </Tabs>
                            </CardHeader>
                            <CardContent className="overflow-hidden pt-3">
                                {rightPanelTab === 'similar' && (
                                    <div className="space-y-2">
                                        {relatedScraps.map((related) => (
                                            <Link
                                                key={related.id}
                                                href={
                                                    related.slug
                                                        ? workspaceShow(
                                                              related.slug,
                                                              routeQuery(
                                                                  activeTag,
                                                              ),
                                                          )
                                                        : workspaceIndex(
                                                              routeQuery(
                                                                  activeTag,
                                                              ),
                                                          )
                                                }
                                                prefetch
                                                className="block rounded-xl border border-border/50 bg-background/60 px-4 py-3 transition-all hover:border-primary/30 hover:shadow-md"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium text-foreground">
                                                            {related.title ??
                                                                __(
                                                                    'Untitled scrap',
                                                                )}
                                                        </p>
                                                        {related.summary && (
                                                            <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                                                {
                                                                    related.summary
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        className="shrink-0 rounded-lg text-[10px]"
                                                    >
                                                        {Math.round(
                                                            related.similarity *
                                                                100,
                                                        )}
                                                        %
                                                    </Badge>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                )}
                                {rightPanelTab === 'recent' && (
                                    <InfiniteScroll
                                        data="inboxItems"
                                        manual
                                        next={({ loading, fetch, hasMore }) =>
                                            hasMore ? (
                                                <div className="pt-2">
                                                    <Button
                                                        variant="outline"
                                                        className="w-full"
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
                                            {recentScraps.map((item) => (
                                                <ScrapCard
                                                    key={item.id}
                                                    item={item}
                                                    isSelected={
                                                        selectedScrap?.id ===
                                                        item.id
                                                    }
                                                    href={
                                                        item.slug
                                                            ? workspaceShow(
                                                                  item.slug,
                                                                  routeQuery(
                                                                      activeTag,
                                                                  ),
                                                              )
                                                            : undefined
                                                    }
                                                    onClick={
                                                        !item.slug
                                                            ? () =>
                                                                  openScrap(
                                                                      item,
                                                                  )
                                                            : undefined
                                                    }
                                                    compact
                                                    statusLabel={
                                                        statusLabels[
                                                            item.status
                                                        ]
                                                    }
                                                    sourceLabel={
                                                        sourceLabels[
                                                            item.sourceType
                                                        ]
                                                    }
                                                />
                                            ))}
                                        </div>
                                    </InfiniteScroll>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
            <div className="flex justify-end px-1">
                <a
                    href={BackupController.download.url()}
                    className="flex items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                >
                    <Download className="size-3" />
                    {__('Download backup')}
                </a>
            </div>
        </>
    );
}

function toJst(value: string): string {
    const date = new Date(value);
    const jst = new Date(date.getTime() + 9 * 60 * 60 * 1000);
    const y = jst.getUTCFullYear();
    const mo = String(jst.getUTCMonth() + 1).padStart(2, '0');
    const d = String(jst.getUTCDate()).padStart(2, '0');
    const h = String(jst.getUTCHours()).padStart(2, '0');
    const mi = String(jst.getUTCMinutes()).padStart(2, '0');

    return `${y}/${mo}/${d} ${h}:${mi}`;
}

function relativeLabel(value: string): string {
    const diff = Math.floor((Date.now() - new Date(value).getTime()) / 1000);

    if (diff < 60) {
        return `${diff}秒前`;
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}分前`;
    }

    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}時間前`;
    }

    if (diff < 86400 * 30) {
        return `${Math.floor(diff / 86400)}日前`;
    }

    if (diff < 86400 * 365) {
        return `${Math.floor(diff / (86400 * 30))}ヶ月前`;
    }

    return `${Math.floor(diff / (86400 * 365))}年前`;
}

function DateDisplay({ value }: { value: string | null }) {
    const { __ } = useLang();
    const [refreshTick, setRefreshTick] = useState(0);

    useEffect(() => {
        if (!value) {
            return;
        }

        const id = setInterval(
            () => setRefreshTick((tick) => tick + 1),
            60_000,
        );

        return () => clearInterval(id);
    }, [value]);

    if (!value) {
        return <>{__('No timestamp')}</>;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return <>{__('Invalid timestamp')}</>;
    }

    void refreshTick;

    return (
        <>
            {toJst(value)}
            {` (${relativeLabel(value)})`}
        </>
    );
}

function MarkdownPreview({ content }: { content: string }) {
    const lines = content.split('\n');
    const nodes: React.ReactNode[] = [];
    let listItems: string[] = [];
    let orderedListItems: string[] = [];
    let quoteLines: string[] = [];
    let codeLines: string[] = [];
    let inCodeBlock = false;

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
            <pre
                key={`code-${nodes.length}`}
                className="overflow-x-auto rounded-xl bg-muted px-4 py-3 text-sm"
            >
                <code>{codeLines.join('\n')}</code>
            </pre>,
        );

        codeLines = [];
    };

    for (const line of lines) {
        if (line.trim().startsWith('```')) {
            if (inCodeBlock) {
                flushCodeBlock();
                inCodeBlock = false;
            } else {
                flushList();
                flushOrderedList();
                flushQuote();
                inCodeBlock = true;
            }

            continue;
        }

        if (inCodeBlock) {
            codeLines.push(line);
            continue;
        }

        if (line.startsWith('- ')) {
            flushQuote();
            flushOrderedList();
            listItems.push(line.slice(2));
            continue;
        }

        if (/^\d+\.\s/.test(line)) {
            flushList();
            flushQuote();
            orderedListItems.push(line.replace(/^\d+\.\s/, ''));
            continue;
        }

        if (line.startsWith('> ')) {
            flushList();
            flushOrderedList();
            quoteLines.push(line.slice(2));
            continue;
        }

        flushList();
        flushOrderedList();
        flushQuote();

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
    flushCodeBlock();

    return <div className="space-y-3 text-sm text-foreground">{nodes}</div>;
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
                    {token.slice(2, -2)}
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
