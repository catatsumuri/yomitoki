import { useLang } from '@erag/lang-sync-inertia/react';
import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { edit } from '@/routes/api-tokens';

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
    const [newToken, setNewToken] = useState<string | null>(null);
    const [expiryDays, setExpiryDays] = useState<string | undefined>(undefined);
    const [copiedText, copy] = useClipboard();

    setLayoutProps({
        breadcrumbs: [{ title: __('API Tokens'), href: edit() }],
    });

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
                    options={{
                        preserveScroll: true,
                        onFlash: (flash: Record<string, unknown>) => {
                            const token = flash.newToken;

                            if (typeof token === 'string') {
                                setNewToken(token);
                            }
                        },
                    }}
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

                {newToken && (
                    <div className="rounded-md border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950">
                        <p className="mb-2 text-sm font-medium text-yellow-800 dark:text-yellow-200">
                            {__(
                                'Copy your token now — it will not be shown again.',
                            )}
                        </p>
                        <div className="flex items-center gap-2">
                            <code className="flex-1 rounded bg-white px-2 py-1 font-mono text-xs break-all dark:bg-black">
                                {newToken}
                            </code>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => copy(newToken)}
                            >
                                {copiedText === newToken
                                    ? __('Copied!')
                                    : __('Copy')}
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {tokens.length > 0 && (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title={__('Active tokens')}
                        description={__('Revoke tokens you no longer need.')}
                    />

                    <ul className="divide-y">
                        {tokens.map((token) => (
                            <li
                                key={token.id}
                                className="flex items-center justify-between py-3"
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
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() =>
                                        router.delete(
                                            ApiTokenController.destroy.url(
                                                token.id,
                                            ),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {__('Revoke')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}
