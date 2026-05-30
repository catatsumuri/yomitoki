import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { diffLines } from 'diff';
import type { Change } from 'diff';
import { History } from 'lucide-react';
import { useState } from 'react';
import { DateDisplay } from '@/components/date-display';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { show as dashboardShow } from '@/routes/dashboard';

type Revision = {
    id: number;
    content: string;
    createdAt: string;
    isCurrent: boolean;
};

type Props = {
    scrap: { slug: string; title: string | null };
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

export default function ScrapRevisions({ scrap, revisions }: Props) {
    const { __ } = useLang();
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    setLayoutProps({
        breadcrumbs: [
            { title: __('Dashboard'), href: dashboard() },
            {
                title: scrap.title ?? __('Untitled scrap'),
                href: dashboardShow(scrap.slug),
            },
            { title: __('History') },
        ],
    });

    function toggleRevision(id: number): void {
        setSelectedIds((current) => {
            if (current.includes(id)) {
                return current.filter((sid) => sid !== id);
            }

            if (current.length >= 2) {
                return [current[1]!, id];
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

    const contentChanged =
        olderRevision &&
        newerRevision &&
        olderRevision.content !== newerRevision.content;
    const noDiff = olderRevision && newerRevision && !contentChanged;

    return (
        <>
            <Head
                title={__('History — :title', {
                    title: scrap.title ?? __('Untitled scrap'),
                })}
            />

            <div className="space-y-4 p-4 md:p-6">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <History className="size-5" />
                        <h1 className="text-xl font-semibold">
                            {__('History')}
                        </h1>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={dashboardShow(scrap.slug)}>
                            ← {__('Back to scrap')}
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
                            <div className="text-sm text-muted-foreground">
                                <DateDisplay value={olderRevision.createdAt} />
                                {' → '}
                                <DateDisplay value={newerRevision.createdAt} />
                            </div>

                            {noDiff && (
                                <p className="text-sm text-muted-foreground">
                                    {__(
                                        'No changes between selected versions.',
                                    )}
                                </p>
                            )}

                            {contentChanged && (
                                <div className="rounded-md border">
                                    <DiffView
                                        oldText={olderRevision.content}
                                        newText={newerRevision.content}
                                    />
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
                                            {revision.isCurrent && (
                                                <span className="rounded bg-muted px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">
                                                    {__('Current')}
                                                </span>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                <DateDisplay
                                                    value={revision.createdAt}
                                                />
                                            </p>
                                        </div>
                                        <p className="mt-1 line-clamp-2 font-mono text-xs text-foreground/70">
                                            {revision.content
                                                .trim()
                                                .slice(0, 120)}
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
