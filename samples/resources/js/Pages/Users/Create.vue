<script setup>
import { Head, useForm } from "@inertiajs/vue3";

// Precognition: each field is validated by the action that stores the user, without storing it.
const form = useForm({
	name: "",
	email: "",
}).withPrecognition("post", "/users");
</script>

<template>
	<Head title="New user" />
	<h1>New user</h1>
	<form @submit.prevent="form.submit()">
		<label>
			Name
			<input v-model="form.name" name="name" @change="form.validate('name')">
		</label>
		<p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
		<label>
			Email
			<input v-model="form.email" name="email" @change="form.validate('email')">
		</label>
		<p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
		<button type="submit" :disabled="form.processing">Create</button>
		<span v-if="form.validating" class="muted">Validating…</span>
	</form>
</template>
