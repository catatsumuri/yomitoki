import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { DateDisplay } from '@/components/date-display';
import { Badge } from '@/components/ui/badge';
import { toneForStatus } from '@/lib/scrap-utils';
import { cn } from '@/lib/utils';

export type ScrapCardItem = {
    id: number;
    title: string | null;
    slug: string | null;
    summary: string | null;
    status: string;
    sourceType: string;
    tags: string[];
    occurredAt: string | null;
};

type ScrapCardProps = {
    item: ScrapCardItem;
    isSelected?: boolean;
    onClick?: () => void;
    href?: NonNullable<InertiaLinkProps['href']>;
    compact?: boolean;
    statusLabel?: string;
    sourceLabel?: string;
};

export function ScrapCard({
    item,
    isSelected,
    onClick,
    href,
    compact = false,
    statusLabel,
    sourceLabel,
}: ScrapCardProps) {
    const statusVariant = toneForStatus(item.status);

    const inner = (
        <div className="relative">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p
                        className={cn(
                            'truncate font-medium text-foreground',
                            compact ? 'text-sm' : 'text-base',
                        )}
                    >
                        {item.title ?? '無題のスクラップ'}
                    </p>
                    <p
                        className={cn(
                            'mt-1 line-clamp-2 text-muted-foreground',
                            compact ? 'text-xs' : 'text-sm leading-6',
                        )}
                    >
                        {item.summary ?? 'まだサマリーがありません。'}
                    </p>
                </div>
                <Badge
                    variant={statusVariant}
                    className={cn(
                        'shrink-0',
                        statusVariant === 'default' &&
                            'bg-primary/20 text-primary hover:bg-primary/30',
                    )}
                >
                    {statusLabel ?? item.status}
                </Badge>
            </div>

            <div className="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                <span className="inline-flex items-center gap-1">
                    <span className="size-1.5 rounded-full bg-primary/40" />
                    {sourceLabel ?? item.sourceType}
                </span>
                {item.tags.map((tag) => (
                    <Badge
                        key={tag}
                        variant="outline"
                        className="h-4 border-border/50 px-1.5 py-0 text-[10px] text-muted-foreground"
                    >
                        #{tag}
                    </Badge>
                ))}
                {!compact && (
                    <>
                        <span className="text-border">•</span>
                        <span className="tabular-nums">
                            <DateDisplay value={item.occurredAt} />
                        </span>
                    </>
                )}
            </div>
        </div>
    );

    const baseClass = cn(
        'block w-full rounded-xl border border-border/70 p-3 text-left transition-colors',
        isSelected ? 'bg-accent/60' : 'bg-background/80 hover:bg-accent/40',
    );

    if (href) {
        return (
            <Link href={href} className={baseClass}>
                {inner}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} className={baseClass}>
            {inner}
        </button>
    );
}
