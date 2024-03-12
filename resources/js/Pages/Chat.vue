<script setup lang="ts">
import {ref, onMounted, reactive} from 'vue';
import {Head, useForm} from '@inertiajs/vue3';
import Prompt from '@/Components/Prompt.vue';
import { Prompt as PromptModel } from '@/Models/Prompt';
import ScrollTop from 'primevue/scrolltop';
import Button from 'primevue/button';
import InputGroup from 'primevue/inputgroup';
import InputText from 'primevue/inputtext';


const props = defineProps({ session_id: String })

const texts = ref<object[]>([]);
const prompts = ref<PromptModel[]>([]);

const form = useForm({
    question: null,
})

function submit()
{
    form.post(route('chat.ask'), {
        preserveScroll: true,
        onSuccess: () => {
            prompts.value.push({type: 'question', text: form.question ?? ''});
            form.reset('question');
        },
    });
}

const listenForTextStream = (session_id? : string) => {
    console.log('listening');
    window.Echo.channel(session_id + '_text-stream').listen('StreamTextChunk', (data: any) => {
        console.log(data);
        prompts.value.push({type: 'answer', text: data.textChunk});
    });
};

onMounted(() => {
    listenForTextStream(props.session_id);
});
</script>

<template>
    <Head title="Chat" />

    <div
        class="container flex flex-col h-screen dark:bg-gray-800 dark:text-white"
    >
        <div class="dark:bg-gray-800 grow p-4 space-y-4">
            <ScrollTop class="right-5 bg-slate-300 dark:bg-gray-900 icon-color"/>
            <Prompt v-for="(prompt, index) in prompts" :key="index" :promptDetails="prompt"></Prompt>
            <p v-for="(text, index) in texts" :key="index">{{ text }}</p>
        </div>

        <div class="sticky bottom-0 bg-white dark:bg-gray-800">
            <div class="max-w-4xl mx-auto">
                <form @submit.prevent="submit">
                    <div class="flex items-center m-1 py-1 px-4 rounded bg-slate-200 dark:bg-gray-900">
                        <InputGroup class="bg-slate-200 dark:bg-gray-900">
                            <InputText class="bg-slate-200 dark:bg-gray-900 border-none outline-none focus:[box-shadow:none]" placeholder="Type your message.." v-model="form.question"/>
                            <Button class="border-none hover:bg-slate-300 dark:hover:bg-gray-800 icon-color" icon="pi pi-caret-right" type="submit" :disabled="form.processing || !form.question" style="justify-content:flex-start;" />
                        </InputGroup>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style>
.bg-dots-darker {
    background-image: url("data:image/svg+xml,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z' fill='rgba(0,0,0,0.07)'/%3E%3C/svg%3E");
}
.icon-color {
    color:black;
}
@media (prefers-color-scheme: dark) {
    .dark\:bg-dots-lighter {
        background-image: url("data:image/svg+xml,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z' fill='rgba(255,255,255,0.07)'/%3E%3C/svg%3E");
    }
    .icon-color {
        color:white;
    }
}
</style>