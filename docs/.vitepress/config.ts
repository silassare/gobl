import { defineConfig } from "vitepress";

// ─── Shared sidebar used by multiple packages in the framework ──────────────
// To reuse: import { goblSidebar } from '../../gobl/docs/.vitepress/config'
export const sidebar = [
	{
		text: "Introduction",
		items: [
			{ text: "What is Gobl?", link: "/guide/" },
			{ text: "Installation", link: "/guide/installation" },
			{ text: "Quick Start", link: "/guide/quick-start" },
		],
	},
	{
		text: "DBAL",
		items: [
			{ text: "Connecting", link: "/guide/connecting" },
			{ text: "Schema Definition", link: "/guide/schema" },
			{ text: "Schema Editor", link: "/guide/schema-editor" },
			{ text: "Column Types", link: "/guide/column-types" },
			{ text: "Query Builder", link: "/guide/query-builder" },
			{ text: "Filters", link: "/guide/filters" },
			{ text: "Relations", link: "/guide/relations" },
			{ text: "Migrations", link: "/guide/migrations" },
		],
	},
	{
		text: "ORM",
		items: [
			{ text: "Overview", link: "/guide/orm" },
			{ text: "Code Generation", link: "/guide/code-generation" },
			{ text: "Controllers", link: "/guide/controllers" },
			{ text: "CRUD Events", link: "/guide/crud-events" },
		],
	},
	{
		text: "Drivers",
		items: [
			{ text: "MySQL / MariaDB", link: "/guide/driver-mysql" },
			{ text: "PostgreSQL", link: "/guide/driver-postgresql" },
			{ text: "SQLite", link: "/guide/driver-sqlite" },
		],
	},
	{
		text: "API Reference",
		items: [{ text: "Auto-generated", link: "/api/" }],
	},
];

// ─── VitePress config ────────────────────────────────────────────────────────
export default defineConfig({
	lang: "en-US",
	title: "Gobl",
	description: "PHP Database Abstraction Layer & ORM",

	// put generated site in docs/.vitepress/dist so CI can deploy from there
	outDir: ".vitepress/dist",

	head: [
		["link", { rel: "icon", type: "image/svg+xml", href: "/logo.svg" }],
		["meta", { name: "theme-color", content: "#3d6bce" }],
	],

	themeConfig: {
		logo: "/logo.svg",
		siteTitle: "Gobl",

		// ── Top nav ──────────────────────────────────────────────────────────────
		nav: [
			{ text: "Guide", link: "/guide/", activeMatch: "/guide/" },
			{ text: "API", link: "/api/", activeMatch: "/api/" },
			{ text: "Changelog", link: "/changelog" },
			{
				text: "v2.0.0",
				items: [
					{
						text: "Release notes",
						link: "https://github.com/silassare/gobl/releases",
					},
					{
						text: "Contributing",
						link: "https://github.com/silassare/gobl/blob/main/CONTRIBUTING.md",
					},
				],
			},
		],

		// ── Sidebar ───────────────────────────────────────────────────────────
		sidebar,

		// ── Editing ───────────────────────────────────────────────────────────
		editLink: {
			pattern: "https://github.com/silassare/gobl/edit/main/docs/:path",
			text: "Edit this page on GitHub",
		},

		// ── Footer ────────────────────────────────────────────────────────────
		footer: {
			message:
				'Released under the <a href="https://github.com/silassare/gobl/blob/main/LICENSE">MIT License</a>.',
			copyright:
				'Copyright © 2021–present <a href="https://github.com/silassare">Emile Silas Sare</a>',
		},

		// ── Social ────────────────────────────────────────────────────────────
		socialLinks: [
			{ icon: "github", link: "https://github.com/silassare/gobl" },
		],

		// ── Search (built-in, no Algolia key needed) ──────────────────────────
		search: {
			provider: "local",
		},

		// ── Outline ───────────────────────────────────────────────────────────
		outline: { level: [2, 3], label: "On this page" },
	},

	markdown: {
		lineNumbers: true,
	},
});
