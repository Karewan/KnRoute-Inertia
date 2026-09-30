import inertia from "@inertiajs/vite";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

export default defineConfig(({ isSsrBuild }) => ({
	plugins: [
		vue(),
		inertia(),
	],
	// public/ is the web root of PHP, not a folder of static files to copy.
	publicDir: false,
	build: isSsrBuild
		? {
				outDir: "ssr",
				emptyOutDir: true,
			}
		: {
				outDir: "public/build",
				emptyOutDir: true,
				manifest: true,
				rolldownOptions: {
					input: "resources/js/app.js",
				},
			},
	server: {
		port: 5173,
		strictPort: true,
	},
}));
