import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, setLayoutProps } from '@inertiajs/react';
import { SendHorizonal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { MarkdownPreview } from '@/components/markdown-preview';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { search } from '@/routes';
import { query as searchQuery } from '@/routes/search';

type ScrapRef = {
    title: string;
    slug: string;
    summary: string | null;
    url: string;
};

type Message = {
    role: 'user' | 'assistant';
    content: string;
    scraps?: ScrapRef[];
};

type Props = {
    conversationId: string;
};

function getXsrfToken(): string {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export default function Search({ conversationId }: Props) {
    const { __ } = useLang();

    setLayoutProps({
        breadcrumbs: [{ title: __('Search'), href: search() }],
    });

    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        const query = input.trim();

        if (!query || isStreaming) {
            return;
        }

        setInput('');
        setIsStreaming(true);

        const userMessage: Message = { role: 'user', content: query };
        const assistantMessage: Message = { role: 'assistant', content: '' };

        setMessages((prev) => [...prev, userMessage, assistantMessage]);

        try {
            const response = await fetch(searchQuery().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({
                    query,
                    conversation_id: conversationId,
                }),
            });

            if (!response.ok || !response.body) {
                throw new Error('Stream failed');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });

                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                for (const line of lines) {
                    if (line.startsWith('data: ') && line !== 'data: [DONE]') {
                        try {
                            const event = JSON.parse(line.slice(6)) as {
                                type: string;
                                delta?: string;
                                output?: string;
                            };

                            if (event.type === 'text-delta' && event.delta) {
                                setMessages((prev) => {
                                    const next = [...prev];
                                    const last = next[next.length - 1];

                                    if (last?.role === 'assistant') {
                                        next[next.length - 1] = {
                                            ...last,
                                            content: last.content + event.delta,
                                        };
                                    }

                                    return next;
                                });
                            } else if (
                                event.type === 'tool-output-available' &&
                                event.output
                            ) {
                                const jsonMatch =
                                    event.output.match(/\[[\s\S]*\]/);

                                if (jsonMatch) {
                                    const scraps = JSON.parse(
                                        jsonMatch[0],
                                    ) as ScrapRef[];

                                    setMessages((prev) => {
                                        const next = [...prev];
                                        const last = next[next.length - 1];

                                        if (last?.role === 'assistant') {
                                            next[next.length - 1] = {
                                                ...last,
                                                scraps,
                                            };
                                        }

                                        return next;
                                    });
                                }
                            }
                        } catch {
                            // skip malformed chunk
                        }
                    }
                }
            }
        } catch {
            setMessages((prev) => {
                const next = [...prev];
                const last = next[next.length - 1];

                if (last?.role === 'assistant' && last.content === '') {
                    next[next.length - 1] = {
                        ...last,
                        content:
                            'エラーが発生しました。もう一度お試しください。',
                    };
                }

                return next;
            });
        } finally {
            setIsStreaming(false);
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            void handleSubmit(e as unknown as React.FormEvent);
        }
    };

    return (
        <>
            <Head title="検索" />

            <div className="mx-auto flex h-[calc(100dvh-4rem)] max-w-3xl flex-col px-4 md:px-6">
                {messages.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                        <p className="text-2xl font-semibold tracking-tight">
                            スクラップを検索
                        </p>
                        <p className="text-sm text-muted-foreground">
                            計画・メモ・リサーチについて何でも聞いてみましょう。
                        </p>
                    </div>
                ) : (
                    <div className="flex-1 overflow-y-auto py-6">
                        <div className="flex flex-col gap-6">
                            {messages.map((message, index) => (
                                <div
                                    key={index}
                                    className={
                                        message.role === 'user'
                                            ? 'flex justify-end'
                                            : 'flex justify-start'
                                    }
                                >
                                    {message.role === 'user' ? (
                                        <div className="max-w-[80%] rounded-2xl bg-foreground px-4 py-2.5 text-sm text-background">
                                            {message.content}
                                        </div>
                                    ) : (
                                        <div className="max-w-[90%]">
                                            {message.content === '' &&
                                            isStreaming &&
                                            index === messages.length - 1 ? (
                                                <Spinner className="mt-1 h-4 w-4 text-muted-foreground" />
                                            ) : (
                                                <>
                                                    <MarkdownPreview
                                                        content={
                                                            message.content
                                                        }
                                                    />
                                                    {message.scraps &&
                                                        message.scraps.length >
                                                            0 && (
                                                            <div className="mt-3 flex flex-wrap gap-1.5">
                                                                {message.scraps.map(
                                                                    (scrap) => (
                                                                        <a
                                                                            key={
                                                                                scrap.slug
                                                                            }
                                                                            href={
                                                                                scrap.url
                                                                            }
                                                                            className="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium hover:bg-muted"
                                                                        >
                                                                            {
                                                                                scrap.title
                                                                            }
                                                                        </a>
                                                                    ),
                                                                )}
                                                            </div>
                                                        )}
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))}
                            <div ref={messagesEndRef} />
                        </div>
                    </div>
                )}

                <div className="shrink-0 py-4">
                    <form
                        onSubmit={(e) => void handleSubmit(e)}
                        className="flex items-end gap-2 rounded-2xl border bg-card px-4 py-3 shadow-sm"
                    >
                        <textarea
                            ref={inputRef}
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder="スクラップについて聞いてみましょう..."
                            rows={1}
                            disabled={isStreaming}
                            className="max-h-40 flex-1 resize-none bg-transparent text-sm outline-none placeholder:text-muted-foreground disabled:opacity-50"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            variant="ghost"
                            disabled={!input.trim() || isStreaming}
                            className="h-8 w-8 shrink-0"
                        >
                            {isStreaming ? (
                                <Spinner className="h-4 w-4" />
                            ) : (
                                <SendHorizonal className="h-4 w-4" />
                            )}
                        </Button>
                    </form>
                    <p className="mt-2 text-center text-xs text-muted-foreground">
                        Enter で送信・Shift+Enter で改行
                    </p>
                </div>
            </div>
        </>
    );
}
