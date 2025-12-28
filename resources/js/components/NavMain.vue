<script setup lang="ts">
import { ref } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const expandedItems = ref<string[]>([]);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <template v-if="item.children && item.children.length > 0">
                    <SidebarMenuButton
                        @click="expandedItems.includes(item.title) 
                            ? expandedItems = expandedItems.filter(title => title !== item.title)
                            : expandedItems.push(item.title)"
                        :tooltip="item.title"
                        class="justify-between"
                    >
                        <div class="flex items-center">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </div>
                        <ChevronDown 
                            class="w-4 h-4 transition-transform"
                            :class="{ 'rotate-180': expandedItems.includes(item.title) }"
                        />
                    </SidebarMenuButton>
                    <SidebarMenuSub v-if="expandedItems.includes(item.title)">
                        <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                            <SidebarMenuSubButton
                                as-child
                                :is-active="urlIsActive(child.href, page.url)"
                            >
                                <Link :href="child.href">
                                    <component :is="child.icon" />
                                    <span>{{ child.title }}</span>
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    </SidebarMenuSub>
                </template>
                <template v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="urlIsActive(item.href, page.url)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </template>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
