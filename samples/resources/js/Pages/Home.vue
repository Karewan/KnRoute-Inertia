<script setup>
import { Deferred, Head, router } from "@inertiajs/vue3";

defineProps({
	time: String,
	stats: Object,
	countries: Array,
});
</script>

<template>
	<Head title="Home" />
	<h1>Home</h1>
	<p>
		Server time: <time>{{ time }}</time>
		<button type="button" @click="router.reload({ only: ['time'] })">
			Reload the time only
		</button>
	</p>
	<Deferred data="stats">
		<template #fallback><p class="muted">Loading the statistics…</p></template>
		<p id="stats">{{ stats.users }} users, PHP {{ stats.php }}</p>
	</Deferred>
	<p>Countries (once prop): {{ countries.join(", ") }}</p>
</template>
