import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { diffLines  } from 'diff';
import type {Change} from 'diff';
import { History } from 'lucide-react';
import { useState } from 'react';
import {
    edit as documentsEdit,
    show as documentsShow,
} from '@/actions/App/Http/Controllers/DocumentController';
import { DateDisplay } from '@/components/date-display';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { documents } from '@/routes';

type Revision = {
    id: number;
    title: string;
    contentMarkdown: string;
    createdAt: string;
    isCurrent: boolean;
};

type Props = {
    document: { id: number; title: string };
    revisions: Revision[];
};

function DiffView({ oldText, newText }: { oldText: string; newText: string }) {
    const changes: Change[] = diffLines(oldText, newText);

    return (
        <div className="max-h-[420px] overflow-auto font-mono text-xs leading-relaxed">
            {changes.map((change, i) => {
                const lines = change.value.replace(/\n$/, '').split('\n');
                const colorClass = change.added
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                    : change.removed
                      ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'
                      : 'text-muted-foreground';
                const prefix = change.added ? '+' : change.removed ? '-' : ' ';

                return lines.map((line, j) => (
                    <div
                        key={`${i}-${j}`}
                        className={`grid grid-cols-[1.25rem_minmax(0,1fr)] items-start gap-1 ${colorClass}`}
                    >
                        <span className="text-center select-none">
                            {prefix}
                        </span>
                        <span className="break-words whitespace-pre-wrap">
                            {line || ' '}
                        </span>
                    </div>
                ));
            })}
        </div>
    );
}

export default function DocumentRevisions({ document, revisions }: Props) {
    const { __ } = useLang();
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    setLayoutProps({
        breadcrumbs: [
            { title: __('Documents'), href: documents() },
            { title: document.title, href: documentsShow(document.id).url },
            { title: __('Edit'), href: documentsEdit(document.id).url },
            { title: __('History') },
        ],
    });

    function toggleRevision(id: number): void {
        setSelectedIds((current) => {
            if (current.includes(id)) {
                return current.filter((sid) => sid !== id);
            }

            if (current.length >= 2) {
                return [current[1], id];
            }

            return [...current, id];
        });
    }

    const selectedRevisions = revisions.filter((r) =>
        selectedIds.includes(r.id),
    );
    const [olderRevision, newerRevision] = [...selectedRevisions].sort(
        (a, b) =>
            new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime(),
    );

    const titleChanged =
        olderRevision &&
        newerRevision &&
        olderRevision.title !== newerRevision.title;
    const contentChanged =
        olderRevision &&
        newerRevision &&
        olderRevision.contentMarkdown !== newerRevision.contentMarkdown;
    const noDiff =
        olderRevision && newerRevision && !titleChanged && !contentChanged;

    return (
        <>
            <Head title={__('History — :title', { title: document.title })} />

            <div className="space-y-4 p-4 md:p-6">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <History className="size-5" />
                        <h1 className="text-xl font-semibold">
                            {__('History')}
                        </h1>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={documentsEdit(document.id).url}>
                            ← {__('Back to Edit')}
                        </Link>
                    </Button>
                </div>

                <Card className="space-y-3 p-4">
                    <p className="text-sm text-muted-foreground">
                        {__('Select two versions to compare.')}
                    </p>

                    {selectedRevisions.length !== 2 ? (
                        <div className="rounded-md border border-dashed border-muted-foreground/40 p-4 text-sm text-muted-foreground">
                            {selectedRevisions.length === 0
                                ? __('No versions selected.')
                                : __('Select one more version.')}
                        </div>
                    ) : (
                        <div className="space-y-3 rounded-md border p-4">
                            <div className="flex flex-col gap-1 text-sm">
                                <span className="font-semibold">
                                    {olderRevision.title} →{' '}
                                    {newerRevision.title}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    <DateDisplay
                                        value={olderRevision.createdAt}
                                    />{' '}
                                    →{' '}
                                    <DateDisplay
                                        value={newerRevision.createdAt}
                                    />
                                </span>
                            </div>

                            {noDiff && (
                                <p className="text-sm text-muted-foreground">
                                    {__(
                                        'No changes between selected versions.',
                                    )}
                                </p>
                            )}

                            {titleChanged && (
                                <div>
                                    <p className="mb-1 text-xs font-medium text-muted-foreground">
                                        {__('Title')}
                                    </p>
                                    <div className="rounded-md border font-mono text-xs">
                                        <div className="bg-rose-50 px-3 py-0.5 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                            <span className="mr-3 opacity-40 select-none">
                                                -
                                            </span>
                                            {olderRevision.title}
                                        </div>
                                        <div className="bg-emerald-50 px-3 py-0.5 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                            <span className="mr-3 opacity-40 select-none">
                                                +
                                            </span>
                                            {newerRevision.title}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {contentChanged && (
                                <div>
                                    <p className="mb-1 text-xs font-medium text-muted-foreground">
                                        {__('Content')}
                                    </p>
                                    <div className="rounded-md border">
                                        <DiffView
                                            oldText={
                                                olderRevision.contentMarkdown
                                            }
                                            newText={
                                                newerRevision.contentMarkdown
                                            }
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </Card>

                <Card className="divide-y">
                    {revisions.length === 0 ? (
                        <div className="p-4 text-sm text-muted-foreground">
                            {__('No revision history yet.')}
                        </div>
                    ) : (
                        revisions.map((revision) => (
                            <div key={revision.id} className="p-4">
                                <label className="flex cursor-pointer items-center gap-3">
                                    <input
                                        type="checkbox"
                                        checked={selectedIds.includes(
                                            revision.id,
                                        )}
                                        onChange={() =>
                                            toggleRevision(revision.id)
                                        }
                                        className="size-4 accent-foreground"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate text-sm font-medium">
                                                {revision.title}
                                            </span>
                                            {revision.isCurrent && (
                                                <span className="rounded bg-muted px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">
                                                    {__('Current')}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            <DateDisplay
                                                value={revision.createdAt}
                                            />
                                        </p>
                                    </div>
                                </label>
                            </div>
                        ))
                    )}
                </Card>
            </div>
        </>
    );
}
