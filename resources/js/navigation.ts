import type { NavItem } from '@/types';
import { GraduationCap, LayoutGrid, School } from 'lucide-vue-next';

/**
 * Sidebar items. Each module adds its entries here with the permission that
 * guards its routes; items the user lacks permission for are hidden.
 */
export const mainNavItems: NavItem[] = [
    {
        title: 'nav.dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'nav.students',
        href: '/students',
        icon: GraduationCap,
        permission: 'students.view',
    },
    {
        title: 'nav.setup',
        href: '/setup',
        icon: School,
        permission: 'setup.manage',
    },
];
