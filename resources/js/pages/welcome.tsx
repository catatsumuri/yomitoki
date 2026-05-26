import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    const { __ } = useLang();

    return (
        <>
            <Head title={__('Welcome')} />
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
                <header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
                    <nav className="flex items-center justify-end gap-4">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                            >
                                {__('Dashboard')}
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                                >
                                    {__('Log in')}
                                </Link>
                            </>
                        )}
                    </nav>
                </header>
                <div className="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
                    <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
                        <div className="min-w-0 flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                            <div className="mb-6 flex items-center gap-2.5">
                                <AppLogoIcon className="h-8 w-8 fill-[#1b1b18] dark:fill-[#EDEDEC]" />
                                <div>
                                    <span className="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">
                                        Yomitoki
                                    </span>
                                    <span className="ml-2 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                        {__('読み解く')}
                                    </span>
                                </div>
                            </div>

                            <h1 className="mb-3 text-lg leading-snug font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                                {__('AIが生成したプランを記録し、読み解く。')}
                            </h1>

                            <p className="mb-6 text-[#706f6c] dark:text-[#A1A09A]">
                                {__(
                                    'プランは承認されたあと消えていく。Yomitokiはその流れを受け止める場所。',
                                )}
                            </p>

                            <ul className="mb-8 flex flex-col gap-3">
                                {[
                                    __(
                                        'プランが自動で流れ込む（Claude Code 連携）',
                                    ),
                                    __('類似プランを即座に検索'),
                                    __('承認後も文脈が残る'),
                                ].map((text, i) => (
                                    <li
                                        key={i}
                                        className="flex items-start gap-3"
                                    >
                                        <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-[#e3e3e0] bg-[#FDFDFC] dark:border-[#3E3E3A] dark:bg-[#161615]">
                                            <span className="h-1.5 w-1.5 rounded-full bg-[#1b1b18] dark:bg-[#EDEDEC]" />
                                        </span>
                                        <span className="text-[13px] text-[#1b1b18] dark:text-[#EDEDEC]">
                                            {text}
                                        </span>
                                    </li>
                                ))}
                            </ul>

                            <div>
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="inline-block rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:border-black hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:border-white dark:hover:bg-white"
                                    >
                                        {__('Dashboard')}
                                    </Link>
                                ) : (
                                    <Link
                                        href={login()}
                                        className="inline-block rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:border-black hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:border-white dark:hover:bg-white"
                                    >
                                        {__('Log in')}
                                    </Link>
                                )}
                            </div>
                        </div>
                        <div className="relative -mb-px aspect-[335/364] w-full shrink-0 overflow-hidden rounded-t-lg bg-[#0f0f0e] lg:mb-0 lg:-ml-px lg:aspect-auto lg:w-[438px] lg:rounded-t-none lg:rounded-r-lg">
                            {/* ターミナルウィンドウ */}
                            <div className="absolute inset-6 flex flex-col rounded-lg border border-[#2a2a28] bg-[#161615] shadow-xl">
                                {/* タイトルバー */}
                                <div className="flex shrink-0 items-center gap-1.5 border-b border-[#2a2a28] px-4 py-3">
                                    <span className="h-2.5 w-2.5 rounded-full bg-[#ff5f57]" />
                                    <span className="h-2.5 w-2.5 rounded-full bg-[#febc2e]" />
                                    <span className="h-2.5 w-2.5 rounded-full bg-[#28c840]" />
                                    <span className="ml-2 font-mono text-xs text-[#706f6c]">
                                        yomitoki — bash
                                    </span>
                                </div>
                                {/* ターミナル本文 */}
                                <div className="flex-1 overflow-hidden px-5 py-4 font-mono text-sm leading-7">
                                    <p>
                                        <span className="text-[#28c840]">
                                            $
                                        </span>
                                        <span className="ml-2 text-[#EDEDEC]">
                                            /plan
                                        </span>
                                    </p>
                                    <p className="ml-4 text-[#706f6c]">
                                        # ExitPlanMode 承認済
                                    </p>
                                    <p>
                                        <span className="text-[#28c840]">
                                            $
                                        </span>
                                        <span className="ml-2 text-[#EDEDEC]">
                                            save-plan.sh
                                        </span>
                                    </p>
                                    <p className="ml-4 text-[#28c840]">
                                        ✓ Saved to scraps (plan)
                                    </p>

                                    <div className="my-4 border-t border-[#2a2a28]" />

                                    <p className="mb-2 text-xs text-[#706f6c]">
                                        # inbox
                                    </p>

                                    <div className="mb-2 rounded border border-[#3E3E3A] bg-[#1f1f1e] px-3 py-2">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="truncate text-[#EDEDEC]">
                                                welcome-page-redesign
                                            </span>
                                            <span className="shrink-0 rounded bg-[#28c840]/20 px-1.5 py-0.5 text-[10px] text-[#28c840]">
                                                plan
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-[10px] text-[#706f6c]">
                                            just now
                                        </p>
                                    </div>

                                    <div className="mb-2 rounded border border-[#2a2a28] px-3 py-2">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="truncate text-[#A1A09A]">
                                                embedding-vector-strategy
                                            </span>
                                            <span className="shrink-0 rounded bg-[#A1A09A]/20 px-1.5 py-0.5 text-[10px] text-[#A1A09A]">
                                                plan
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-[10px] text-[#706f6c]">
                                            2 days ago
                                        </p>
                                    </div>

                                    <div className="rounded border border-[#2a2a28] px-3 py-2 opacity-40">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="truncate text-[#A1A09A]">
                                                plan-to-markdown-skill
                                            </span>
                                            <span className="shrink-0 rounded bg-[#A1A09A]/20 px-1.5 py-0.5 text-[10px] text-[#A1A09A]">
                                                plan
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-[10px] text-[#706f6c]">
                                            5 days ago
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="absolute inset-0 rounded-t-lg shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-t-none lg:rounded-r-lg dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"></div>
                        </div>
                    </main>
                </div>
                <div className="hidden h-14.5 lg:block"></div>
            </div>
        </>
    );
}
