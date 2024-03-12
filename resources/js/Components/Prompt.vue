<script setup lang="ts">
import Avatar from 'primevue/avatar';
import { Prompt as PromptModel } from '@/Models/Prompt';

defineProps<{
    promptDetails: PromptModel
}>();
</script>

<template>
    <div class="space-y-4">
        <Avatar v-if="promptDetails.type === 'question'" label="Q" size="xlarge" style="background-color:#2196F3; color: #ffffff" shape="circle"  />
        <Avatar v-else label="A" class="bg-red-400" size="xlarge" style="color: #ffffff" shape="circle"  />
        <p class="text-xl">{{ promptDetails.text }}</p>
    </div> 
</template>