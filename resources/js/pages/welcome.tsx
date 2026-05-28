import { useLang } from '@erag/lang-sync-inertia/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    canResetPassword: boolean;
    status?: string;
};

export default function Welcome({ canResetPassword, status }: Props) {
    const { auth } = usePage().props;
    const { __ } = useLang();

    return (
        <>
            <Head title={__('Welcome')} />
            <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
                {/* Floating accent blobs */}
                <div className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div
                        className="absolute h-[600px] w-[600px] animate-pulse rounded-full opacity-15"
                        style={{
                            background:
                                'radial-gradient(circle, hsl(var(--accent)) 0%, transparent 70%)',
                            top: '-200px',
                            right: '-200px',
                        }}
                    />
                    <div
                        className="absolute h-[400px] w-[400px] animate-pulse rounded-full opacity-[0.08]"
                        style={{
                            background:
                                'radial-gradient(circle, hsl(var(--primary)) 0%, transparent 70%)',
                            bottom: '-100px',
                            left: '-100px',
                        }}
                    />
                </div>

                <div className="relative z-10 flex min-h-screen flex-col-reverse lg:flex-row">
                    {/* Left: Hero */}
                    <div className="flex flex-1 flex-col justify-center p-8 lg:p-16 xl:p-24">
                        {/* Logo + brand */}
                        <div className="relative mb-12">
                            <div className="absolute -inset-8 rounded-full bg-accent/20 blur-3xl" />
                            <div className="relative flex items-center gap-6">
                                <div className="relative">
                                    <AppLogoIcon className="h-24 w-auto transition-transform duration-300 hover:scale-105 lg:h-32 xl:h-40" />
                                    <div className="absolute -right-2 -bottom-2 h-8 w-8 rounded-lg bg-accent" />
                                </div>
                                <div>
                                    <h2 className="text-4xl font-black tracking-tight lg:text-5xl xl:text-6xl">
                                        YOMITOKI
                                    </h2>
                                    <p className="mt-1 text-lg font-medium tracking-widest text-accent lg:text-xl">
                                        {__('Read deeply')}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Headline */}
                        <div className="max-w-2xl space-y-6">
                            <h1 className="text-5xl leading-[0.9] font-black tracking-tight lg:text-6xl xl:text-7xl">
                                <span className="block">{__('AI plans,')}</span>
                                <span className="block text-accent">
                                    {__('record,')}
                                </span>
                                <span className="block">
                                    {__('read deeply.')}
                                </span>
                            </h1>
                            <p className="max-w-lg text-lg leading-relaxed text-muted-foreground lg:text-xl">
                                {__('Plans fade after approval.')}
                                <br />
                                {__('Yomitoki catches that flow.')}
                            </p>
                        </div>

                        {/* Feature pills */}
                        <div className="mt-10 flex flex-wrap gap-3">
                            {[
                                __('Capture the moment'),
                                __('Analyze patterns'),
                                __('Track decisions'),
                            ].map((feature) => (
                                <span
                                    key={feature}
                                    className="cursor-default rounded-full border border-border bg-secondary px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                >
                                    {feature}
                                </span>
                            ))}
                        </div>
                    </div>

                    {/* Right: Login panel */}
                    <div className="flex w-full items-center justify-center border-b border-border bg-card p-8 lg:w-[480px] lg:border-b-0 lg:border-l lg:p-12 xl:w-[540px]">
                        <div className="w-full max-w-sm">
                            {auth.user ? (
                                <>
                                    <h3 className="mb-2 text-2xl font-bold">
                                        {__('Welcome back')}
                                    </h3>
                                    <p className="mb-8 text-sm text-muted-foreground">
                                        {__('Sign in to access your account')}
                                    </p>
                                    <Link
                                        href={dashboard()}
                                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-primary py-3 font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                                    >
                                        {__('Dashboard')}
                                        <ArrowRight size={18} />
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <div className="mb-8">
                                        <h3 className="mb-2 text-2xl font-bold">
                                            {__('Log in')}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {__(
                                                'Sign in to access your account',
                                            )}
                                        </p>
                                    </div>

                                    {status && (
                                        <p className="mb-4 text-sm font-medium text-green-600">
                                            {status}
                                        </p>
                                    )}

                                    <Form
                                        {...store.form()}
                                        resetOnSuccess={['password']}
                                        className="flex flex-col gap-5"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="email">
                                                        {__('Email address')}
                                                    </Label>
                                                    <Input
                                                        id="email"
                                                        type="email"
                                                        name="email"
                                                        required
                                                        autoFocus
                                                        tabIndex={1}
                                                        autoComplete="email"
                                                        placeholder="your@email.com"
                                                    />
                                                    <InputError
                                                        message={errors.email}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <div className="flex items-center">
                                                        <Label htmlFor="password">
                                                            {__('Password')}
                                                        </Label>
                                                        {canResetPassword && (
                                                            <Link
                                                                href={request()}
                                                                className="ml-auto text-xs text-accent underline-offset-4 hover:underline"
                                                                tabIndex={5}
                                                            >
                                                                {__(
                                                                    'Forgot your password?',
                                                                )}
                                                            </Link>
                                                        )}
                                                    </div>
                                                    <PasswordInput
                                                        id="password"
                                                        name="password"
                                                        required
                                                        tabIndex={2}
                                                        autoComplete="current-password"
                                                        placeholder="••••••••"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.password
                                                        }
                                                    />
                                                </div>

                                                <div className="flex items-center space-x-3">
                                                    <Checkbox
                                                        id="remember"
                                                        name="remember"
                                                        tabIndex={3}
                                                    />
                                                    <Label htmlFor="remember">
                                                        {__('Remember me')}
                                                    </Label>
                                                </div>

                                                <Button
                                                    type="submit"
                                                    className="group flex w-full items-center justify-center gap-2"
                                                    tabIndex={4}
                                                    disabled={processing}
                                                >
                                                    {processing ? (
                                                        <>
                                                            <Spinner />
                                                            {__(
                                                                'Logging in...',
                                                            )}
                                                        </>
                                                    ) : (
                                                        <>
                                                            {__('Log in')}
                                                            <ArrowRight
                                                                size={18}
                                                                className="transition-transform group-hover:translate-x-1"
                                                            />
                                                        </>
                                                    )}
                                                </Button>

                                                <PasskeyVerify
                                                    separatorPosition="top"
                                                    separator={__(
                                                        'Or sign in with a passkey',
                                                    )}
                                                    showSeparator
                                                />
                                            </>
                                        )}
                                    </Form>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <footer className="absolute bottom-4 left-8 text-xs text-muted-foreground">
                    © 2025 YOMITOKI
                </footer>
            </div>
        </>
    );
}
