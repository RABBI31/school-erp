import type { Page } from '@inertiajs/core';
import type { SharedData } from './index';

declare global {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
    }
}

// Props shared by HandleInertiaRequests are available on every page.
declare module '@inertiajs/core' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- declaration merging
    interface PageProps extends SharedData {}
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $page: Page<SharedData>;
    }
}
