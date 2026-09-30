<script setup>
import { Head, Link } from "@inertiajs/vue3";

defineProps({
	status: Number,
	title: String,
	detail: String,
});
</script>

<template>
	<Head :title="`${status} ${title}`" />
	<main class="error-page">
		<h1>{{ status }} · {{ title }}</h1>
		<p>{{ detail }}</p>
		<Link href="/">Back home</Link>
	</main>
</template>
