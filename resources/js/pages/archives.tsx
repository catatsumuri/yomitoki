import { Form, Head, InfiniteScroll, useForm } from '@inertiajs/react';
import { Archive, CornerDownLeft, RotateCcw, Trash2 } from 'lucide-react';
import ScrapController from '@/actions/App/Http/Controllers/ScrapController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { archives, dashboard } from '@/routes';

type ArchivedItem = {
    id: number;
    title: string | null;
    slug: string | null;
    content: string;
    summary: string | null;
    sourceType: string;
    isNested: boolean;
    parent: {
        id: number;
        title: string | null;
        slug: string | null;
    } | null;
    occurredAt: string | null;
    archivedAt: string | null;
};

type ArchivesProps = {
    summary: {
        archivedCount: number;
        topLevelCount: number;
        nestedCount: number;
    };
    archivedItems: {
        data: ArchivedItem[];
    };
};

const sourceLabels: Record<string, string> = {
    daily_report: 'Daily report',
    inquiry: 'Inquiry',
    meeting_note: 'Meeting note',
    note: 'Note',
    research: 'Research',
};

function formatUtcDate(value: string | null): string {
    if (!value) {
        return 'No timestamp';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Invalid timestamp';
    }

    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');
    const hour = String(date.getUTCHours()).padStart(2, '0');
    const minute = String(date.getUTCMinutes()).padStart(2, '0');

    return `${year}-${month}-${day} ${hour}:${minute} UTC`;
}

function excerpt(content: string): string {
    return content.trim().replace(/\s+/g, ' ').slice(0, 180);
}

export default function Archives({ summary, archivedItems }: ArchivesProps) {
    const restoreForm = useForm({});

    return (
        <>
            <Head title="Archives" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 md:px-6">
                <section className="rounded-2xl border border-sidebar-border/70 bg-muted/40 px-4 py-3 dark:border-sidebar-border dark:bg-muted/20">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Archives</p>
                            <h1 className="text-xl font-semibold tracking-tight">
                                Review what has already been set aside
                            </h1>
                        </div>
                        <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                            <span>{summary.archivedCount} archived</span>
                            <span>•</span>
                            <span>{summary.topLevelCount} top-level</span>
                            <span>•</span>
                            <span>{summary.nestedCount} nested</span>
                        </div>
                    </div>
                </section>

                <Card className="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Archived scraps</CardTitle>
                        <CardDescription>
                            These scraps are no longer in the active capture flow. Top-level and nested scraps are listed together.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <InfiniteScroll
                            data="archivedItems"
                            manual
                            next={({ fetch, hasMore, loading }) =>
                                hasMore ? (
                                    <div className="pt-3">
                                        <button
                                            type="button"
                                            onClick={fetch}
                                            disabled={loading}
                                            className="rounded-xl border border-border/70 px-4 py-2 text-sm font-medium transition-colors hover:bg-accent/40 disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {loading ? 'Loading…' : 'Load more'}
                                        </button>
                                    </div>
                                ) : null
                            }
                        >
                            <div className="space-y-3">
                                {archivedItems.data.map((item) => (
                                    <article
                                        key={item.id}
                                        className="rounded-2xl border border-border/70 bg-background/80 p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium text-foreground">
                                                        {item.title ?? 'Untitled scrap'}
                                                    </p>
                                                    <Badge variant="outline">archived</Badge>
                                                    {item.isNested ? (
                                                        <Badge variant="secondary">nested</Badge>
                                                    ) : (
                                                        <Badge variant="secondary">top-level</Badge>
                                                    )}
                                                </div>
                                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                                    {item.summary ?? excerpt(item.content)}
                                                </p>
                                            </div>
                                            <Archive className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        </div>

                                        <div className="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                            <span>{sourceLabels[item.sourceType] ?? item.sourceType}</span>
                                            <span>•</span>
                                            <span>Captured {formatUtcDate(item.occurredAt)}</span>
                                            <span>•</span>
                                            <span>Archived {formatUtcDate(item.archivedAt)}</span>
                                        </div>

                                        {item.parent ? (
                                            <div className="mt-4 inline-flex items-center gap-2 rounded-full border border-border/70 px-3 py-1.5 text-xs text-muted-foreground">
                                                <CornerDownLeft className="size-3.5" />
                                                <span>
                                                    Child of {item.parent.title ?? item.parent.slug ?? 'untitled parent'}
                                                </span>
                                            </div>
                                        ) : null}

                                        <div className="mt-4 flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                disabled={restoreForm.processing}
                                                onClick={() =>
                                                    restoreForm.submit(
                                                        ScrapController.restore(item.id),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <RotateCcw className="size-4" />
                                                Restore
                                            </Button>

                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                    >
                                                        <Trash2 className="size-4" />
                                                        Delete permanently
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogTitle>
                                                        Permanently delete this scrap?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        This action cannot be undone. The selected archived scrap
                                                        {item.isNested ? '' : ' and any archived child scraps'}
                                                        {' '}will be removed permanently.
                                                    </DialogDescription>

                                                    <Form
                                                        {...ScrapController.destroy.form(item.id)}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                        className="space-y-6"
                                                    >
                                                        {({ processing }) => (
                                                            <DialogFooter className="gap-2">
                                                                <DialogClose asChild>
                                                                    <Button variant="secondary">
                                                                        Cancel
                                                                    </Button>
                                                                </DialogClose>

                                                                <Button
                                                                    variant="destructive"
                                                                    disabled={processing}
                                                                    asChild
                                                                >
                                                                    <button type="submit">
                                                                        Delete permanently
                                                                    </button>
                                                                </Button>
                                                            </DialogFooter>
                                                        )}
                                                    </Form>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </InfiniteScroll>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Archives.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Archives',
            href: archives(),
        },
    ],
};
