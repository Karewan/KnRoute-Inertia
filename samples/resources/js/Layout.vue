<script setup>
import { Link, router, usePage } from "@inertiajs/vue3";
import { onUnmounted, ref } from "vue";

const page = usePage();
const toast = ref(null);

// Flash data is sent once and never kept in the history.
const removeListener = router.on("flash", (event) => {
	if (event.detail.flash.success) {
		toast.value = event.detail.flash.success;
	}
});
onUnmounted(removeListener);
</script>

<template>
	<header>
		<strong>{{ page.props.appName }}</strong>
		<nav>
			<Link href="/" :class="{ active: page.component === 'Home' }">Home</Link>
			<Link href="/users" :class="{ active: page.component.startsWith('Users/') }"
				>Users</Link
			>
			<Link href="/feed" :class="{ active: page.component === 'Feed' }">Feed</Link>
			<Link href="/account" :class="{ active: page.component === 'Account' }">Account</Link>
			<Link href="/missing">Missing page</Link>
			<Link href="/docs">Docs (external)</Link>
		</nav>
		<span>{{ page.props.auth.user.name }}</span>
	</header>
	<p v-if="toast" class="toast" role="status">
		{{ toast }}
		<button type="button" aria-label="Dismiss" @click="toast = null">×</button>
	</p>
	<main><slot /></main>
</template>
