<script setup>
import { Head, Link } from "@inertiajs/vue3";

defineProps({
	iban: String,
});
</script>

<template>
	<Head title="Account" />
	<h1>Account</h1>
	<p>
		The history of this page is encrypted: after the logout, the back button cannot show it
		again.
	</p>
	<p>IBAN: <code>{{ iban }}</code></p>
	<Link href="/logout" method="post" as="button">Log out</Link>
</template>
