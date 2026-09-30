<script setup>
import { Head, InfiniteScroll } from "@inertiajs/vue3";

defineProps({
	posts: Object,
});
</script>

<template>
	<Head title="Feed" />
	<h1>Feed</h1>
	<InfiniteScroll data="posts" class="feed">
		<article v-for="post in posts.data" :key="post.id">{{ post.title }}</article>
	</InfiniteScroll>
</template>
