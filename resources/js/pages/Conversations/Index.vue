<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Tag as TagIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type TagOption = { id: number; name: string; color: string };
type ConversationItem = {
    id: number;
    title: string | null;
    updated_at: string;
    tags: TagOption[];
};

defineProps<{
    conversations: ConversationItem[];
    availableTags: TagOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Conversations', href: '/conversations' }],
    },
});

// Une couleur différente par carte (cycle), pour un rendu vivant plutôt qu'une liste monochrome.
const cardAccents = ['border-l-chart-1', 'border-l-chart-2', 'border-l-chart-3', 'border-l-chart-4', 'border-l-chart-5'];

function createConversation() {
    router.post('/conversations');
}

function hasTag(conversation: ConversationItem, tagId: number): boolean {
    return conversation.tags.some((t) => t.id === tagId);
}

function toggleTag(conversation: ConversationItem, tag: TagOption) {
    if (hasTag(conversation, tag.id)) {
        router.delete(`/conversations/${conversation.id}/tags/${tag.id}`, { preserveScroll: true });
    } else {
        router.post(`/conversations/${conversation.id}/tags/${tag.id}`, {}, { preserveScroll: true });
    }
}

const newTagName = ref('');
const tagPalette = ['#e75555', '#f5993d', '#f7d64c', '#3fae5c', '#5593f7', '#a366e0'];

function createTag() {
    if (!newTagName.value.trim()) return;
    const color = tagPalette[Math.floor(Math.random() * tagPalette.length)];
    router.post('/tags', { name: newTagName.value, color }, { preserveScroll: true });
    newTagName.value = '';
}
</script>

<template>
    <Head title="Conversations" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Mes conversations</h1>
            <Button @click="createConversation" class="bg-brand-gradient text-white hover:opacity-90">Nouvelle conversation</Button>
        </div>

        <p v-if="conversations.length === 0" class="text-muted-foreground">
            Aucune conversation pour l'instant — clique sur "Nouvelle conversation" pour commencer.
        </p>

        <div class="flex flex-col gap-2">
            <Card v-for="(conversation, index) in conversations" :key="conversation.id" class="border-l-4" :class="cardAccents[index % cardAccents.length]">
                <CardContent class="flex flex-col gap-2 py-3">
                    <Link :href="`/conversations/${conversation.id}`" class="flex items-center justify-between transition-colors hover:text-accent-foreground">
                        <span>{{ conversation.title ?? 'Nouvelle conversation' }}</span>
                        <span class="text-xs text-muted-foreground">{{ new Date(conversation.updated_at).toLocaleString() }}</span>
                    </Link>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            v-for="tag in conversation.tags"
                            :key="tag.id"
                            class="rounded-full px-2 py-0.5 text-xs text-white"
                            :style="{ backgroundColor: tag.color }"
                        >
                            {{ tag.name }}
                        </span>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="flex items-center gap-1 rounded-full border border-dashed border-input px-2 py-0.5 text-xs text-muted-foreground hover:text-foreground">
                                    <TagIcon class="size-3" /> Tags
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuCheckboxItem
                                    v-for="tag in availableTags"
                                    :key="tag.id"
                                    :model-value="hasTag(conversation, tag.id)"
                                    @update:model-value="toggleTag(conversation, tag)"
                                >
                                    <span class="mr-2 inline-block size-2 rounded-full" :style="{ backgroundColor: tag.color }" />
                                    {{ tag.name }}
                                </DropdownMenuCheckboxItem>

                                <DropdownMenuSeparator v-if="availableTags.length > 0" />

                                <div class="flex gap-1 p-1">
                                    <input
                                        v-model="newTagName"
                                        placeholder="Nouveau tag..."
                                        class="w-full rounded border border-input bg-transparent px-2 py-1 text-xs focus-visible:outline-none"
                                        @keydown.enter.prevent="createTag"
                                        @click.stop
                                    />
                                    <Button size="sm" variant="ghost" class="h-auto px-2 py-1 text-xs" @click.stop="createTag">+</Button>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
