<script setup>
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";

const props = defineProps({
	filters: Object,
	users: Array,
	roles: Array,
});
const page = usePage();
const search = ref(props.filters.search);

// A partial reload: only the users are resolved again by the server.
watch(search, (value) => {
	router.get(
		"/users",
		value
			? {
					search: value,
				}
			: {},
		{
			only: [
				"users",
				"filters",
			],
			preserveState: true,
			replace: true,
		},
	);
});
</script>

<template>
	<Head title="Users" />
	<h1>Users</h1>
	<p>
		<input v-model="search" type="search" placeholder="Search" aria-label="Search">
		<Link href="/users/create" class="button">New user</Link>
		<button type="button" @click="router.reload({ only: ['roles'] })">Load the roles</button>
		<span v-if="roles" id="roles">Roles: {{ roles.join(", ") }}</span>
	</p>
	<table>
		<thead>
			<tr>
				<th>Name</th>
				<th>Email</th>
				<th>Role</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<tr
				v-for="user in users"
				:key="user.id"
				:class="{ highlight: page.flash.highlight === user.id }">
				<td>{{ user.name }}</td>
				<td>{{ user.email }}</td>
				<td>{{ user.role }}</td>
				<td><Link :href="`/users/${user.id}`" method="delete" as="button">Delete</Link></td>
			</tr>
			<tr v-if="!users.length">
				<td colspan="4" class="muted">No user</td>
			</tr>
		</tbody>
	</table>
</template>
