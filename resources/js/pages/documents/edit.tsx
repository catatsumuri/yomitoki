import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { XIcon } from 'lucide-react';
import { useState } from 'react';
import {
    revisions as documentsRevisions,
    show as documentsShow,
    update as documentsUpdate,
} from '@/actions/App/Http/Controllers/DocumentController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { documents } from '@/routes';

type DocumentEditProps = {
    document: {
        id: number;
        title: string;
        contentMarkdown: string;
        tags: string[];
    };
};

export default function DocumentEdit({ document }: DocumentEditProps) {
    const { __ } = useLang();
    const [tagInput, setTagInput] = useState('');

    const { data, setData, patch, processing, errors } = useForm({
        title: document.title,
        content_markdown: document.contentMarkdown,
        tags: document.tags,
    });

    setLayoutProps({
        breadcrumbs: [
            { title: __('Documents'), href: documents() },
            { title: document.title, href: documentsShow(document.id).url },
            { title: __('Edit') },
        ],
    });

    function addTag(raw: string): void {
        const tag = raw.trim().toLowerCase().replace(/\s+/g, '-');

        if (!tag || data.tags.includes(tag)) {
            setTagInput('');

            return;
        }

        setData('tags', [...data.tags, tag]);
        setTagInput('');
    }

    function removeTag(tag: string): void {
        setData(
            'tags',
            data.tags.filter((t) => t !== tag),
        );
    }

    function submit(e: React.FormEvent): void {
        e.preventDefault();
        patch(documentsUpdate(document.id).url);
    }

    return (
        <>
            <Head title={__('Edit — :title', { title: document.title })} />

            <div className="w-full space-y-8 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <Link
                        href={documentsShow(document.id).url}
                        className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        ← {document.title}
                    </Link>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={documentsRevisions(document.id).url}>
                            {__('View History')}
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="title">{__('Title')}</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            required
                        />
                        {errors.title && (
                            <p className="text-sm text-destructive">
                                {errors.title}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="content_markdown">
                            {__('Content')}
                        </Label>
                        <textarea
                            id="content_markdown"
                            value={data.content_markdown}
                            onChange={(e) =>
                                setData('content_markdown', e.target.value)
                            }
                            rows={24}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            required
                        />
                        {errors.content_markdown && (
                            <p className="text-sm text-destructive">
                                {errors.content_markdown}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label>{__('Tags')}</Label>
                        <div className="flex flex-wrap gap-1.5">
                            {data.tags.map((tag) => (
                                <Badge
                                    key={tag}
                                    variant="secondary"
                                    className="gap-1 pr-1"
                                >
                                    #{tag}
                                    <button
                                        type="button"
                                        onClick={() => removeTag(tag)}
                                        className="ml-0.5 rounded-sm opacity-60 hover:opacity-100"
                                    >
                                        <XIcon className="size-3" />
                                    </button>
                                </Badge>
                            ))}
                        </div>
                        <Input
                            value={tagInput}
                            onChange={(e) => setTagInput(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ',') {
                                    e.preventDefault();
                                    addTag(tagInput);
                                }
                            }}
                            onBlur={() => tagInput.trim() && addTag(tagInput)}
                            placeholder={__('Add tag…')}
                            className="w-48"
                        />
                    </div>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {__('Save')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                (window.location.href = documentsShow(
                                    document.id,
                                ).url)
                            }
                        >
                            {__('Cancel')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
