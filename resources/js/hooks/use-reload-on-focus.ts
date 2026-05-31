import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export function useReloadOnFocus(only?: string[]): void {
    useEffect(() => {
        function handleVisibilityChange(): void {
            if (document.visibilityState === 'visible') {
                router.reload({ only });
            }
        }

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, []);
}
