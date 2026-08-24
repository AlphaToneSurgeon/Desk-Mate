<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AssistantController from '@/actions/App/Http/Controllers/Settings/AssistantController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/assistant';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Instructions personnalisées',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Instructions personnalisées" />

    <h1 class="sr-only">Instructions personnalisées</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Instructions personnalisées"
            description="Configure le ton et le comportement par défaut de l'assistant, pour toutes tes conversations"
        />

        <Form v-bind="AssistantController.update.form()" class="space-y-6" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <textarea
                    id="custom_instructions"
                    name="custom_instructions"
                    rows="6"
                    class="mt-1 block w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    placeholder="Ex : explique-moi toujours étape par étape, avec des exemples tirés de la vie courante..."
                >{{ user.custom_instructions }}</textarea>
                <InputError class="mt-2" :message="errors.custom_instructions" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-assistant-button">Save</Button>
            </div>
        </Form>
    </div>
</template>
