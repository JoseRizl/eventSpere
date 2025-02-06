<script setup>
import { useForm } from '@inertiajs/vue3'
import TextInput from '../../Components/TextInput.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const form = useForm({
    name: null,
    email: null,
    password: null,
    password_confirmation: null
})

const submit = () => {
    form.post(route("register"), {
        onError: () => form.reset('password', 'password_confirmation')
    })
}

defineOptions({layout: AuthLayout});
</script>

<template>
    <Home title="| Register"/>

    <h1 class="title">Register a new account</h1>

    <div class="max-w-xl mx-auto">
        <form @submit.prevent="submit">
            <TextInput name="Name" v-model="form.name" :message="form.errors.name"/>
            <TextInput name="Email" type="email" v-model="form.email" :message="form.errors.email"/>
            <TextInput name="Password" type="password" v-model="form.password" :message="form.errors.password"/>
            <TextInput name="Confirm Password" type="password" v-model="form.password_confirmation" :message="form.errors.password_confirmation"/>
            <div>
                <p class="text-slate-600 mb-2">Already a user? <Link :href="route('login')" class="text-link">Login</Link></p>
                <button class="primary-btn" :disabled="form.processing">Register</button>
            </div>
        </form>
    </div>
</template>
