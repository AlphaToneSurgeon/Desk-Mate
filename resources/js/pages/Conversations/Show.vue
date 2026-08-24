<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Message = {
    id?: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
};

type AiModelOption = {
    id: number;
    slug: string;
    display_name: string;
    provider: string;
};

const props = defineProps<{
    conversation: { id: number; title: string | null };
    thread: Message[];
    models: AiModelOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Conversations', href: '/conversations' },
            { title: 'Conversation', href: '#' },
        ],
    },
});

// État local : la liste affichée démarre avec le fil déjà en base (thread),
// puis on y ajoute les nouveaux messages au fur et à mesure côté client.
const messages = ref([...props.thread] as Message[]);
const input = ref('');
const selectedModelId = ref(props.models[0]?.id as number | undefined);
const isStreaming = ref(false);
const streamingContent = ref(''); // le texte de la réponse EN COURS de génération

function renderMarkdown(content: string): string {
    // marked.parse() transforme le Markdown en HTML.
    // DOMPurify.sanitize() nettoie ce HTML avant de l'injecter dans la page :
    // le texte vient d'un modèle IA, on ne lui fait pas une confiance aveugle
    // (un prompt pourrait en théorie lui faire générer une balise "script" malveillante).
    return DOMPurify.sanitize(marked.parse(content, { async: false }) as string);
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function sendMessage() {
    if (!input.value.trim() || isStreaming.value || !selectedModelId.value) return;

    const content = input.value;
    input.value = '';

    // Affichage optimiste : on montre le message de l'utilisateur tout de
    // suite, sans attendre la réponse du serveur.
    messages.value.push({ role: 'user', content });

    isStreaming.value = true;
    streamingContent.value = '';

    const response = await fetch(`/conversations/${props.conversation.id}/messages/stream`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ content, model_id: selectedModelId.value }),
    });

    // --- Lecture du flux SSE ---
    // C'est EXACTEMENT le même algorithme que côté PHP dans
    // OpenRouterClient::streamMessage() : on lit des morceaux d'octets bruts,
    // on les accumule dans un buffer, et on ne traite que ce qui forme un
    // événement complet (séparé par une ligne vide "\n\n").
    const reader = response.body!.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });

        let separatorIndex;
        while ((separatorIndex = buffer.indexOf('\n\n')) !== -1) {
            const rawEvent = buffer.slice(0, separatorIndex);
            buffer = buffer.slice(separatorIndex + 2);

            if (!rawEvent.startsWith('data: ')) continue;

            const data = JSON.parse(rawEvent.slice('data: '.length));

            if (data.content) {
                // On ajoute le morceau reçu : Vue re-rend automatiquement,
                // c'est ça qui donne l'effet "texte qui s'écrit en direct".
                streamingContent.value += data.content;
            }

            if (data.done) {
                messages.value.push({ id: data.message_id, role: 'assistant', content: streamingContent.value });
                streamingContent.value = '';
                isStreaming.value = false;
            }
        }
    }
}
</script>

<template>
    <Head :title="conversation.title ?? 'Nouvelle conversation'" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex justify-end">
            <Select v-model="selectedModelId">
                <SelectTrigger class="w-64">
                    <SelectValue placeholder="Choisir un modèle" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="model in models" :key="model.id" :value="model.id">
                        {{ model.display_name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="flex flex-1 flex-col gap-3 overflow-y-auto">
            <div
                v-for="(message, index) in messages"
                :key="message.id ?? index"
                class="max-w-2xl rounded-2xl px-4 py-2 shadow-sm"
                :class="
                    message.role === 'user'
                        ? 'bg-brand-gradient ml-auto text-white'
                        : 'mr-auto border border-chart-2/30 bg-chart-2/10'
                "
            >
                <div class="prose prose-sm dark:prose-invert" v-html="renderMarkdown(message.content)" />
            </div>

            <div v-if="isStreaming" class="mr-auto max-w-2xl rounded-2xl border border-chart-2/30 bg-chart-2/10 px-4 py-2 shadow-sm">
                <div class="prose prose-sm dark:prose-invert" v-html="renderMarkdown(streamingContent || '…')" />
            </div>
        </div>

        <form class="flex gap-2" @submit.prevent="sendMessage">
            <textarea
                v-model="input"
                rows="2"
                placeholder="Écris ton message..."
                class="flex-1 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                @keydown.enter.exact.prevent="sendMessage"
            ></textarea>
            <Button type="submit" :disabled="isStreaming" class="bg-brand-gradient text-white hover:opacity-90">Envoyer</Button>
        </form>
    </div>
</template>
