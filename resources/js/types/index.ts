import type { Locale } from '@/i18n';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    permissions: string[];
}

/** `title` is a translation key. */
export interface BreadcrumbItem {
    title: string;
    href: string;
}

/** `title` is a translation key; `permission` hides the item from users without it. */
export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
}

export interface School {
    id: number;
    name_en: string;
    name_bn: string;
    slug: string;
    logo_path: string | null;
}

export interface SharedData {
    [key: string]: unknown;
    name: string;
    school: School | null;
    locale: Locale;
    academicYears: AcademicYearOption[];
    academicYear: AcademicYearOption | null;
    flash: { success: string | null; error: string | null };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    avatar?: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

/** Laravel's LengthAwarePaginator as JSON (`->paginate()->withQueryString()`). */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface DataTableColumn {
    key: string;
    /** Translation key. */
    label: string;
    class?: string;
}

export interface AcademicYearOption {
    id: number;
    name: string;
    is_current: boolean;
}
