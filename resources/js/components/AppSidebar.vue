<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Shield, ShoppingBag, Headset, Download, Users } from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';
import { dashboard } from '@/routes';
import { index as supportIndex } from '@/routes/support';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard as resellerDashboard } from '@/routes/reseller';

import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = page.props.auth.user;

let mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: user.role === 'reseller' ? resellerDashboard().url : dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Downloads',
        href: '/downloads',
        icon: Download,
    },
];

if (user.role === 'admin') {
    mainNavItems.push(
        {
            title: 'My Purchases',
            href: '/purchases',
            icon: ShoppingBag,
        },
        {
            title: 'Support Center',
            href: supportIndex().url,
            icon: Headset,
        },
        {
            title: 'Admin Panel',
            href: adminDashboard().url,
            icon: Shield,
        }
    );
} else if (user.role === 'reseller') {
    mainNavItems.push(
        {
            title: 'Licenses',
            href: '/licenses',
            icon: ShoppingBag,
        },
        {
            title: 'Team Workspace',
            href: '/reseller/workspace',
            icon: Users,
        }
    );
} else {
    // Normal User
    mainNavItems.push(
        {
            title: 'My Purchases',
            href: '/purchases',
            icon: ShoppingBag,
        },
        {
            title: 'Support Center',
            href: supportIndex().url,
            icon: Headset,
        }
    );
}

const footerNavItems: NavItem[] = [
    {
        title: 'Platform Rules',
        href: '/rules',
        icon: Shield,
    },
];

// Documentation only for resellers and admins
const hasDocAccess = user.role === 'admin' || (user.role === 'reseller' && page.props.auth.workspace_permissions?.access_api);

if (hasDocAccess) {
    footerNavItems.push({
        title: 'Documentation',
        href: 'https://docs.rlbmods.com',
        icon: BookOpen,
    });
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
