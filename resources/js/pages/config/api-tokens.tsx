import { useLang } from '@erag/lang-sync-inertia/react';
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit, show } from '@/routes/api-tokens';

type Token = {
    id: number;
    name: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
};

type Props = {
    tokens: Token[];
};

export default function ApiTokens({ tokens }: Props) {
    const { __ } = useLang();
    const [expiryDays, setExpiryDays] = useState<string | undefined>(undefined);
    const [selected, setSelected] = useState<number[]>([]);

    setLayoutProps({
        breadcrumbs: [{ title: __('API Tokens'), href: edit() }],
    });

    function toggleSelect(id: number): void {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function toggleAll(): void {
        setSelected((prev) =>
            prev.length === tokens.length ? [] : tokens.map((t) => t.id),
        );
    }

    return (
        <>
            <Head title={__('API Tokens')} />

            <h1 className="sr-only">{__('API Tokens')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={__('Create token')}
                    description={__(
                        'Tokens allow external projects to ingest Markdown scraps into Yomitoki.',
                    )}
                />

                <Form
                    {...ApiTokenController.store.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">{__('Token name')}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder={__('e.g. my-project')}
                                    required
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="expires_in_days">
                                    {__('Expiry')}
                                </Label>
                                <Select
                                    value={expiryDays}
                                    onValueChange={setExpiryDays}
                                >
                                    <SelectTrigger id="expires_in_days">
                                        <SelectValue
                                            placeholder={__('No expiry')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="30">
                                            {__('30 days')}
                                        </SelectItem>
                                        <SelectItem value="90">
                                            {__('90 days')}
                                        </SelectItem>
                                        <SelectItem value="365">
                                            {__('1 year')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {expiryDays && (
                                    <input
                                        type="hidden"
                                        name="expires_in_days"
                                        value={expiryDays}
                                    />
                                )}
                            </div>

                            <Button disabled={processing}>
                                {__('Generate token')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>

            {tokens.length > 0 && (
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <Heading variant="small" title={__('Active tokens')} />

                        {selected.length > 0 && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive" size="sm">
                                        {__('Revoke :count', {
                                            count: selected.length,
                                        })}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        {__('Revoke selected tokens?')}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {__(
                                            ':count token(s) will be permanently revoked. This cannot be undone.',
                                            { count: selected.length },
                                        )}
                                    </DialogDescription>
                                    <DialogFooter>
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                {__('Cancel')}
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            onClick={() => {
                                                router.delete(
                                                    ApiTokenController.bulkDestroy.url(),
                                                    {
                                                        data: {
                                                            token_ids: selected,
                                                        },
                                                        onSuccess: () =>
                                                            setSelected([]),
                                                    },
                                                );
                                            }}
                                        >
                                            {__('Revoke')}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>

                    <ul className="divide-y">
                        <li className="flex items-center gap-3 py-2">
                            <Checkbox
                                id="select-all"
                                checked={selected.length === tokens.length}
                                onCheckedChange={toggleAll}
                            />
                            <Label
                                htmlFor="select-all"
                                className="cursor-pointer text-xs font-normal text-muted-foreground"
                            >
                                {__('Select all')}
                            </Label>
                        </li>
                        {tokens.map((token) => (
                            <li
                                key={token.id}
                                className="flex items-center gap-3 py-3"
                            >
                                <Checkbox
                                    checked={selected.includes(token.id)}
                                    onCheckedChange={() =>
                                        toggleSelect(token.id)
                                    }
                                    aria-label={token.name}
                                />
                                <Link
                                    href={show(token.id)}
                                    className="flex flex-1 items-center justify-between hover:opacity-70"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {token.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {token.last_used_at
                                                ? __('Last used :date', {
                                                      date: new Date(
                                                          token.last_used_at,
                                                      ).toLocaleDateString(),
                                                  })
                                                : __('Never used')}
                                            {token.expires_at &&
                                                ' · ' +
                                                    __('Expires :date', {
                                                        date: new Date(
                                                            token.expires_at,
                                                        ).toLocaleDateString(),
                                                    })}
                                        </p>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        →
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}
