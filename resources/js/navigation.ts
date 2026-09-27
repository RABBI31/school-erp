import type { NavItem } from '@/types';
import { ClipboardCheck, FileSpreadsheet, GraduationCap, LayoutGrid, Megaphone, MessageSquare, School, Users, Wallet } from 'lucide-vue-next';

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
        title: 'nav.attendance',
        href: '/attendance',
        icon: ClipboardCheck,
        permission: 'attendance.take',
    },
    {
        title: 'nav.exams',
        href: '/exams',
        icon: FileSpreadsheet,
        permission: 'results.view',
    },
    {
        title: 'nav.fees',
        href: '/fees',
        icon: Wallet,
        permission: 'fees.view',
    },
    {
        title: 'nav.notices',
        href: '/notices',
        icon: Megaphone,
        permission: 'notices.manage',
    },
    {
        title: 'nav.sms',
        href: '/sms/log',
        icon: MessageSquare,
        permission: 'sms.send',
    },
    {
        title: 'nav.staff',
        href: '/staff',
        icon: Users,
        permission: 'staff.view',
    },
    {
        title: 'nav.setup',
        href: '/setup',
        icon: School,
        permission: 'setup.manage',
    },
];
