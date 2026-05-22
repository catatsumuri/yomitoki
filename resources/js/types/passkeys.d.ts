declare module '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController' {
    export const destroy: {
        url(id: number): string;
    };
}

declare module '@laravel/passkeys/react' {
    type PasskeyRoutes = {
        options: string;
        submit: string;
    };

    type PasskeyVerifyResponse = {
        redirect?: string;
    };

    export function usePasskeyRegister(options?: { onSuccess?: () => void }): {
        register(name: string): Promise<void>;
        isLoading: boolean;
        error?: string | null;
        isSupported: boolean;
    };

    export function usePasskeyVerify(options?: {
        routes?: PasskeyRoutes;
        onSuccess?: (response: PasskeyVerifyResponse) => void;
    }): {
        verify(): void | Promise<void>;
        isLoading: boolean;
        error?: string | null;
        isSupported: boolean;
    };
}
