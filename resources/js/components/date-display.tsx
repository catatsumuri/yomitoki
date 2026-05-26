import { useLang } from '@erag/lang-sync-inertia/react';
import { useEffect, useState } from 'react';

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

export function DateDisplay({ value }: { value: string | null }) {
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
