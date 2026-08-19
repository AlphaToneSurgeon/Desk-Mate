<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Conversations', href: '/conversations' }],
    },
});

defineProps<{
    conversations: Array<{ id: number; title: string | null; updated_at: string }>;
}>();

function createConversation() {
    router.post('/conversations');
}
</script>

<template>
    <Head title="Conversations" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Mes conversations</h1>
            <Button @click="createConversation">Nouvelle conversation</Button>
        </div>

        <p v-if="conversations.length === 0" class="text-muted-foreground">
            Aucune conversation pour l'instant — clique sur "Nouvelle conversation" pour commencer.
        </p>

        <div class="flex flex-col gap-2">
            <Link v-for="conversation in conversations" :key="conversation.id" :href="`/conversations/${conversation.id}`">
                <Card class="transition-colors hover:bg-accent">
                    <CardContent class="flex items-center justify-between py-3">
                        <span>{{ conversation.title ?? 'Nouvelle conversation' }}</span>
                        <span class="text-xs text-muted-foreground">{{ new Date(conversation.updated_at).toLocaleString() }}</span>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
