export function toneForStatus(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'failed') {
        return 'destructive';
    }

    if (status === 'raw' || status === 'queued') {
        return 'secondary';
    }

    if (
        status === 'final' ||
        status === 'completed' ||
        status === 'processed'
    ) {
        return 'default';
    }

    return 'outline';
}
