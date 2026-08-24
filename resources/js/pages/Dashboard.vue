<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type ModelStat = {
    model: string;
    message_count: number;
    input_tokens: number;
    output_tokens: number;
    cost: number;
};

defineProps<{
    stats: {
        total_conversations: number;
        total_messages: number;
        total_cost: number;
        by_model: ModelStat[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

function formatCost(cost: number): string {
    return cost < 0.01 ? '< 0,01 $' : `${cost.toFixed(2)} $`;
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card class="border-l-4 border-l-chart-2">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Conversations</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{ stats.total_conversations }}</CardContent>
            </Card>

            <Card class="border-l-4 border-l-chart-4">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Messages</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{ stats.total_messages }}</CardContent>
            </Card>

            <Card class="border-l-4 border-l-chart-1">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Coût estimé</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{ formatCost(stats.total_cost) }}</CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Utilisation par modèle</CardTitle>
            </CardHeader>
            <CardContent>
                <p v-if="stats.by_model.length === 0" class="text-sm text-muted-foreground">
                    Pas encore de réponse générée — les stats apparaîtront après ta première conversation.
                </p>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-muted-foreground">
                            <th class="pb-2">Modèle</th>
                            <th class="pb-2 text-right">Réponses</th>
                            <th class="pb-2 text-right">Tokens entrée</th>
                            <th class="pb-2 text-right">Tokens sortie</th>
                            <th class="pb-2 text-right">Coût</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in stats.by_model" :key="row.model" class="border-b last:border-0">
                            <td class="py-2">{{ row.model }}</td>
                            <td class="py-2 text-right">{{ row.message_count }}</td>
                            <td class="py-2 text-right">{{ row.input_tokens.toLocaleString() }}</td>
                            <td class="py-2 text-right">{{ row.output_tokens.toLocaleString() }}</td>
                            <td class="py-2 text-right">{{ formatCost(row.cost) }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
