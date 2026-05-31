import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
    token: Token;
};

export default function ApiTokenShow({ token }: Props) {
    const { __ } = useLang();
    const page = usePage<{ flash?: { newToken?: string } }>();
    const [copiedText, copy] = useClipboard();
    const newToken =
        typeof page.flash?.newToken === 'string' ? page.flash.newToken : null;

    setLayoutProps({
        breadcrumbs: [
            { title: __('API Tokens'), href: edit() },
            { title: token.name, href: '#' },
        ],
    });

    return (
        <>
            <Head title={token.name} />

            <h1 className="sr-only">{token.name}</h1>

            <div className="space-y-6">
                <Heading variant="small" title={token.name} />

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

                <dl className="divide-y text-sm">
                    <div className="flex justify-between py-3">
                        <dt className="text-muted-foreground">
                            {__('Last used')}
                        </dt>
                        <dd>
                            {token.last_used_at
                                ? new Date(
                                      token.last_used_at,
                                  ).toLocaleDateString()
                                : __('Never used')}
                        </dd>
                    </div>
                    <div className="flex justify-between py-3">
                        <dt className="text-muted-foreground">
                            {__('Expiry')}
                        </dt>
                        <dd>
                            {token.expires_at
                                ? new Date(
                                      token.expires_at,
                                  ).toLocaleDateString()
                                : __('No expiry')}
                        </dd>
                    </div>
                    <div className="flex justify-between py-3">
                        <dt className="text-muted-foreground">
                            {__('Created')}
                        </dt>
                        <dd>
                            {new Date(token.created_at).toLocaleDateString()}
                        </dd>
                    </div>
                </dl>

                <div className="flex gap-3">
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.post(
                                ApiTokenController.regenerate.url(token.id),
                            )
                        }
                    >
                        {__('Regenerate')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            router.delete(
                                ApiTokenController.destroy.url(token.id),
                            )
                        }
                    >
                        {__('Revoke')}
                    </Button>
                </div>

                <div>
                    <Link
                        href={edit()}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        ← {__('Back to tokens')}
                    </Link>
                </div>
            </div>
        </>
    );
}
