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

// Une couleur différente par carte (cycle), pour un rendu vivant plutôt qu'une liste monochrome.
const cardAccents = ['border-l-chart-1', 'border-l-chart-2', 'border-l-chart-3', 'border-l-chart-4', 'border-l-chart-5'];

function createConversation() {
    router.post('/conversations');
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
            <Link
                v-for="(conversation, index) in conversations"
                :key="conversation.id"
                :href="`/conversations/${conversation.id}`"
            >
                <Card
                    class="border-l-4 transition-colors hover:bg-accent/10"
                    :class="cardAccents[index % cardAccents.length]"
                >
                    <CardContent class="flex items-center justify-between py-3">
                        <span>{{ conversation.title ?? 'Nouvelle conversation' }}</span>
                        <span class="text-xs text-muted-foreground">{{ new Date(conversation.updated_at).toLocaleString() }}</span>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
