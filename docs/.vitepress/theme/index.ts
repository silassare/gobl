// .vitepress/theme/index.ts
// Extend the default VitePress theme with Gobl brand overrides.
//
// To reuse in other packages:
//   1. Copy this file (adjust the CSS import path if needed).
//   2. Register any additional global Vue components here.

import DefaultTheme from "vitepress/theme";
import type { Theme } from "vitepress";
import "./custom.css";
import SchemaEditor from "../components/SchemaEditor.vue";

export default {
	extends: DefaultTheme,
	enhanceApp({ app }) {
		app.component("SchemaEditor", SchemaEditor);
	},
} satisfies Theme;
