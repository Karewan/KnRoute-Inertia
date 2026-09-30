import { createInertiaApp } from "@inertiajs/vue3";
import Layout from "./Layout.vue";
import "../css/app.css";

// The @inertiajs/vite plugin resolves the components of ./Pages, mounts the application and builds the SSR bundle.
createInertiaApp({
	title: (title) => (title ? `${title} · KnRoute Inertia` : "KnRoute Inertia"),
	layout: (name) => (name === "Error" ? null : Layout),
	progress: {
		color: "#2563eb",
	},
});
