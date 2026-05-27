import { SendHorizonal, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { MarkdownPreview } from '@/components/markdown-preview';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { init as searchInit, query as searchQuery } from '@/routes/search';

type Message = {
    role: 'user' | 'assistant';
    content: string;
};

function getXsrfToken(): string {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export function SearchDialog() {
    const [isOpen, setIsOpen] = useState(false);
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [isInitializing, setIsInitializing] = useState(false);
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    // Cmd+K / Ctrl+K でダイアログを開く
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setIsOpen((prev) => !prev);
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    const handleOpenChange = async (open: boolean) => {
        setIsOpen(open);

        if (open && !conversationId) {
            setIsInitializing(true);

            try {
                const response = await fetch(searchInit().url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                });
                const data = (await response.json()) as {
                    conversation_id: string;
                };
                setConversationId(data.conversation_id);
            } finally {
                setIsInitializing(false);
                setTimeout(() => inputRef.current?.focus(), 0);
            }
        }

        if (!open) {
            // Sheet を閉じたら会話をリセット（次回開いたとき新規会話）
            setMessages([]);
            setInput('');
            setConversationId(null);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        const queryText = input.trim();

        if (!queryText || isStreaming || !conversationId) {
            return;
        }

        setInput('');
        setIsStreaming(true);

        const userMessage: Message = { role: 'user', content: queryText };
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
                    query: queryText,
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
        <Sheet
            open={isOpen}
            onOpenChange={(open) => void handleOpenChange(open)}
        >
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="group h-9 w-9 cursor-pointer"
                    aria-label="検索を開く (⌘K)"
                >
                    <Search className="!size-5 opacity-80 group-hover:opacity-100" />
                </Button>
            </SheetTrigger>

            <SheetContent
                side="right"
                className="flex w-full flex-col p-0 sm:max-w-xl"
            >
                <SheetHeader className="shrink-0 border-b px-6 py-4">
                    <SheetTitle className="text-base">
                        スクラップを検索
                    </SheetTitle>
                </SheetHeader>

                {isInitializing ? (
                    <div className="flex flex-1 items-center justify-center">
                        <Spinner className="h-5 w-5 text-muted-foreground" />
                    </div>
                ) : (
                    <>
                        <div className="flex-1 overflow-y-auto px-6">
                            {messages.length === 0 ? (
                                <div className="flex h-full flex-col items-center justify-center gap-2 text-center">
                                    <p className="text-sm font-medium">
                                        何でも聞いてみましょう
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        計画・メモ・リサーチについて質問できます
                                    </p>
                                </div>
                            ) : (
                                <div className="flex flex-col gap-6 py-6">
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
                                                    index ===
                                                        messages.length - 1 ? (
                                                        <Spinner className="mt-1 h-4 w-4 text-muted-foreground" />
                                                    ) : (
                                                        <MarkdownPreview
                                                            content={
                                                                message.content
                                                            }
                                                        />
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                    <div ref={messagesEndRef} />
                                </div>
                            )}
                        </div>

                        <div className="shrink-0 border-t px-6 py-4">
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
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
