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

    <h1 class="title">Login to Your account</h1>

    <div class="max-w-xl mx-auto">
        <form @submit.prevent="submit">
            <TextInput name="Email" type="email" v-model="form.email" :message="form.errors.email"/>
            <TextInput name="Password" type="password" v-model="form.password" :message="form.errors.password"/>

            <div class="flex items-center mb-2">
                <div class="flex items-center gap-2">
                    <input id="remember" type="checkbox" v-model="form.remember">
                    <label for="remember" class="ml-[-12px] mr-12">Remember Me</label>
                </div>

                <p class="text-slate-600">Don't have an Account?
                    <Link :href="route('register')" class="text-link">Register</Link>
                </p>
            </div>


            <div class="space-y-3">
                <button class="primary-btn" :disabled="form.processing">Login</button>
            </div>
        </form>
    </div>

</template>
