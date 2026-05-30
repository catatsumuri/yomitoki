import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ping } from '@/routes/ai';

type Props = {
    provider: string;
};

type LogEntry = { type: 'step' | 'done' | 'error'; message: string };

export default function AiSettings({ provider }: Props) {
    const { __ } = useLang();
    const [prompt, setPrompt] = useState('Ping');
    const [log, setLog] = useState<LogEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [ok, setOk] = useState<boolean | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: __('AI'), href: '/config/ai' }],
    });

    function handlePing() {
        const trimmed = prompt.trim() || 'Ping';
        setLoading(true);
        setLog([]);
        setOk(null);

        const url = ping.url({ query: { prompt: trimmed } });
        const source = new EventSource(url);
        let finished = false;

        function finish() {
            if (finished) {
                return;
            }

            finished = true;
            source.close();
            setLoading(false);
        }

        source.addEventListener('message', (e) => {
            try {
                const data = JSON.parse(e.data as string) as {
                    type: string;
                    message?: string;
                };

                if (data.type === 'step' && data.message) {
                    setLog((prev) => [
                        ...prev,
                        { type: 'step', message: data.message! },
                    ]);
                }
            } catch {
                // skip malformed chunk
            }
        });

        source.addEventListener('done', (e) => {
            try {
                const data = JSON.parse(e.data as string) as {
                    ok: boolean;
                    elapsed_ms?: number;
                    error?: string;
                };

                if (data.ok) {
                    setLog((prev) => [
                        ...prev,
                        {
                            type: 'done',
                            message: `完了 (${data.elapsed_ms ?? '?'} ms)`,
                        },
                    ]);
                    setOk(true);
                } else {
                    setLog((prev) => [
                        ...prev,
                        {
                            type: 'error',
                            message: data.error ?? __('Network error'),
                        },
                    ]);
                    setOk(false);
                }
            } catch {
                // skip
            }

            finish();
        });

        source.onerror = () => {
            if (finished) {
                return;
            }

            setLog((prev) => [
                ...prev,
                { type: 'error', message: __('Network error') },
            ]);
            setOk(false);
            finish();
        };
    }

    return (
        <>
            <Head title={__('AI')} />

            <h1 className="sr-only">{__('AI')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={__('AI provider')}
                    description={__(
                        'Test connectivity to the configured AI provider.',
                    )}
                />

                <div className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        {__('Active provider:')}{' '}
                        <span className="font-mono font-medium text-foreground">
                            {provider}
                        </span>
                    </p>

                    <div className="grid gap-2">
                        <Label htmlFor="prompt">{__('Prompt')}</Label>
                        <div className="flex gap-2">
                            <Input
                                id="prompt"
                                value={prompt}
                                onChange={(e) => setPrompt(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !loading) {
                                        handlePing();
                                    }
                                }}
                                placeholder="Ping"
                                className="font-mono"
                                disabled={loading}
                            />
                            <Button onClick={handlePing} disabled={loading}>
                                {loading ? __('Pinging…') : __('Send')}
                            </Button>
                        </div>
                    </div>

                    {log.length > 0 && (
                        <div
                            className={[
                                'space-y-1 rounded-md border p-3 font-mono text-xs',
                                ok === true
                                    ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                                    : ok === false
                                      ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                      : 'bg-muted/40',
                            ].join(' ')}
                        >
                            {log.map((entry, i) => (
                                <p
                                    key={i}
                                    className={
                                        entry.type === 'error'
                                            ? 'text-red-600 dark:text-red-400'
                                            : entry.type === 'done'
                                              ? 'text-green-600 dark:text-green-400'
                                              : 'text-muted-foreground'
                                    }
                                >
                                    {entry.type === 'done'
                                        ? '✓'
                                        : entry.type === 'error'
                                          ? '✗'
                                          : '·'}{' '}
                                    {entry.message}
                                </p>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
