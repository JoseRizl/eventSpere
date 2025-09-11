<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import TextInput from '../../Components/TextInput.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false
});

const submit = () => {
    form.post(route("login"), {
        onError: () => form.reset('password', 'remember')
    });
};

defineOptions({ layout: AuthLayout });
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-white p-4">
        <div class="w-full max-w-4xl bg-white rounded-lg flex flex-col md:flex-row overflow-hidden">

            <!-- Left Section (Logo & Branding) -->
            <div class="md:w-1/2 flex flex-col items-center justify-center text-center p-6 bg-white">
                <img src="/resources/images/NCSlogo.png" alt="School Logo" class="w-32 md:w-48 mb-4">
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">EVENT SPHERE</h1>
                <h2 class="text-lg md:text-xl font-semibold text-gray-700">Event Management System</h2>
            </div>

            <!-- Right Section (Login Form) -->
            <div class="md:w-1/2 p-6 bg-white">
                <h1 class="text-xl font-bold mb-4 text-center text-gray-800">Login to Your Account</h1>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email Input -->
                    <TextInput
                        name="Email"
                        type="email"
                        v-model="form.email"
                        :message="form.errors.email"
                    />

                    <!-- Password Input -->
                    <TextInput
                        name="Password"
                        type="password"
                        v-model="form.password"
                        :message="form.errors.password"
                    />

                    <!-- Remember Me & Admin Link -->
                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 text-gray-600">
                            <input id="remember" type="checkbox" v-model="form.remember" class="rounded">
                            Remember Me
                        </label>
                        <a href="/register" class="text-blue-600 hover:underline">Admin</a>
                    </div>

                    <!-- Login Button -->
                    <button
                        type="submit"
                        class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Login
                    </button>

                    <div class="relative flex py-2 items-center">
                        <div class="flex-grow border-t border-gray-300"></div>
                        <span class="flex-shrink mx-4 text-gray-400 text-sm">Or</span>
                        <div class="flex-grow border-t border-gray-300"></div>
                    </div>

                    <Link :href="route('home')" class="w-full block text-center bg-slate-600 text-white py-2 rounded-lg hover:bg-slate-700 transition">
                        Continue as Guest
                    </Link>
                </form>
            </div>

        </div>
    </div>
</template>
