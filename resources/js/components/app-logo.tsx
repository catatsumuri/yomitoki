import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/60 text-primary-foreground shadow-lg shadow-primary/20">
                <AppLogoIcon className="size-5" />
            </div>
            <span className="ml-2 hidden font-semibold text-foreground md:block">
                {name}
            </span>
        </>
    );
}
