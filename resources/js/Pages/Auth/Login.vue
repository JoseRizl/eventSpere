<script setup>
import { useForm } from '@inertiajs/vue3';
import TextInput from '../../Components/TextInput.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';


const form = useForm({
    email: null,
    password: null,
    remember: null
})

const submit = () => {
    form.post(route("login"), {
        onError: () => form.reset('password', 'remember')
    })
}

defineOptions({layout: AuthLayout});
</script>

<template>
    <Home title="| Login"/>

    <h1 class="title">Login you account</h1>

    <div class="w-2/4 mx-auto">
        <form @submit.prevent="submit">
            <TextInput name="Email" type="email" v-model="form.email" :message="form.errors.email"/>
            <TextInput name="Password" type="password" v-model="form.password" :message="form.errors.password"/>

            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <input id="remember" type="checkbox" v-model="form.remember">
                    <label for="remember">Remember me</label>
                </div>

                <p class="text-slate-600">Need an Account? <Link :href="route('register')" class="text-link">Login</Link></p>
            </div>

            <div>
                <button class="primary-btn" :disabled="form.processing">Login</button>
            </div>
        </form>
    </div>

</template>
