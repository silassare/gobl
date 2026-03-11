<template>
	<div class="schema-editor-wrap">
		<div class="schema-editor-toolbar">
			<span class="schema-editor-label">Gobl Schema Editor</span>
			<span class="schema-editor-hint"
				>JSON • validated against
				<a :href="schemaUrl" target="_blank">schema.json</a></span
			>
			<div class="schema-editor-buttons">
				<button class="btn" @click="formatCode">Format</button>
				<button class="btn" @click="resetCode">Reset</button>
				<button class="btn btn-copy" @click="copyCode">
					{{ copyLabel }}
				</button>
			</div>
		</div>
		<div ref="editorEl" class="schema-editor-container"></div>
		<div v-if="errors.length" class="schema-editor-errors">
			<div v-for="(e, i) in errors" :key="i" class="schema-error-item">
				<span class="schema-error-loc"
					>Line {{ e.startLineNumber }}:{{ e.startColumn }}</span
				>
				{{ e.message }}
			</div>
		</div>
		<div v-else-if="monacoReady" class="schema-editor-ok">
			✓ Schema looks valid
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from "vue";

const editorEl = ref<HTMLElement | null>(null);
const errors = ref<any[]>([]);
const monacoReady = ref(false);
const copyLabel = ref("Copy");

// The schema URL depends on the base URL of the deployed site.
const schemaUrl =
	typeof window !== "undefined"
		? `${window.location.origin}/schema.json`
		: "/schema.json";

const DEFAULT_SCHEMA = JSON.stringify(
	{
		users: {
			plural_name: "users",
			singular_name: "user",
			column_prefix: "user",
			columns: {
				id: {
					type: "bigint",
					unsigned: true,
					auto_increment: true,
				},
				name: {
					type: "string",
					min: 1,
					max: 100,
				},
				email: {
					type: "string",
					min: 5,
					max: 255,
				},
				status: {
					type: "string",
					one_of: ["active", "inactive", "banned"],
					default: "active",
				},
				created_at: {
					type: "date",
					format: "timestamp",
					auto: true,
				},
			},
			constraints: [
				{ type: "primary_key", columns: ["id"] },
				{ type: "unique_key", columns: ["email"] },
			],
		},
	},
	null,
	2,
);

let editor: any = null;
let monaco: any = null;

function loadMonacoFromCDN(): Promise<any> {
	return new Promise((resolve) => {
		const CDN = "https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs";
		if ((window as any).monaco) {
			resolve((window as any).monaco);
			return;
		}
		// loader config
		const loaderScript = document.createElement("script");
		loaderScript.src = CDN + "/loader.js";
		loaderScript.onload = () => {
			const require = (window as any).require;
			require.config({ paths: { vs: CDN } });
			require(["vs/editor/editor.main"], (m: any) => resolve(m));
		};
		document.head.appendChild(loaderScript);
	});
}

async function initEditor() {
	if (!editorEl.value) return;
	monaco = await loadMonacoFromCDN();

	// register Gobl schema for JSON validation
	monaco.languages.json.jsonDefaults.setDiagnosticsOptions({
		validate: true,
		schemas: [
			{
				uri: schemaUrl,
				fileMatch: ["gobl-schema-editor-*"],
				schema: await fetch("/schema.json").then((r) => r.json()),
			},
		],
	});

	const modelUri = monaco.Uri.parse("gobl-schema-editor-1.json");
	const model = monaco.editor.createModel(DEFAULT_SCHEMA, "json", modelUri);

	editor = monaco.editor.create(editorEl.value, {
		model,
		theme: document.documentElement.classList.contains("dark")
			? "vs-dark"
			: "vs",
		fontSize: 13,
		lineNumbers: "on",
		minimap: { enabled: false },
		scrollBeyondLastLine: false,
		automaticLayout: true,
		tabSize: 2,
		wordWrap: "on",
	});

	// watch theme changes
	const observer = new MutationObserver(() => {
		monaco.editor.setTheme(
			document.documentElement.classList.contains("dark")
				? "vs-dark"
				: "vs",
		);
	});
	observer.observe(document.documentElement, {
		attributes: true,
		attributeFilter: ["class"],
	});

	// collect markers (validation errors) live
	monaco.editor.onDidChangeMarkers(([resource]: any[]) => {
		if (resource?.toString() === modelUri.toString()) {
			errors.value = monaco.editor.getModelMarkers({ resource });
		}
	});

	monacoReady.value = true;
}

function formatCode() {
	editor?.getAction("editor.action.formatDocument")?.run();
}

function resetCode() {
	editor?.getModel()?.setValue(DEFAULT_SCHEMA);
	errors.value = [];
}

async function copyCode() {
	const code = editor?.getModel()?.getValue() ?? "";
	await navigator.clipboard.writeText(code);
	copyLabel.value = "Copied!";
	setTimeout(() => {
		copyLabel.value = "Copy";
	}, 1500);
}

onMounted(() => {
	// guard: only run in browser
	if (typeof window !== "undefined") {
		initEditor();
	}
});

onBeforeUnmount(() => {
	editor?.dispose();
});
</script>

<style scoped>
.schema-editor-wrap {
	border: 1px solid var(--vp-c-divider);
	border-radius: 8px;
	overflow: hidden;
	margin: 1.5rem 0;
}

.schema-editor-toolbar {
	display: flex;
	align-items: center;
	gap: 0.75rem;
	padding: 0.5rem 0.75rem;
	background: var(--vp-c-bg-soft);
	border-bottom: 1px solid var(--vp-c-divider);
	flex-wrap: wrap;
}

.schema-editor-label {
	font-weight: 600;
	font-size: 0.85rem;
	color: var(--vp-c-text-1);
}

.schema-editor-hint {
	font-size: 0.78rem;
	color: var(--vp-c-text-3);
	flex: 1;
}

.schema-editor-hint a {
	color: var(--vp-c-brand-1);
	text-decoration: underline;
}

.schema-editor-buttons {
	display: flex;
	gap: 0.4rem;
}

.btn {
	padding: 0.2rem 0.65rem;
	border-radius: 4px;
	border: 1px solid var(--vp-c-divider);
	background: var(--vp-c-bg);
	color: var(--vp-c-text-1);
	font-size: 0.78rem;
	cursor: pointer;
	transition: background 0.15s;
}

.btn:hover {
	background: var(--vp-c-bg-mute);
}

.btn-copy {
	min-width: 52px;
}

.schema-editor-container {
	height: 460px;
	width: 100%;
}

.schema-editor-errors {
	padding: 0.5rem 0.75rem;
	background: #fff1f0;
	border-top: 1px solid #ffa39e;
}

.dark .schema-editor-errors {
	background: #2a1215;
	border-top-color: #58181c;
}

.schema-error-item {
	font-size: 0.8rem;
	color: #cf1322;
	margin: 0.15rem 0;
}

.dark .schema-error-item {
	color: #ff7875;
}

.schema-error-loc {
	font-weight: 600;
	margin-right: 0.4rem;
}

.schema-editor-ok {
	padding: 0.4rem 0.75rem;
	background: #f6ffed;
	border-top: 1px solid #b7eb8f;
	font-size: 0.8rem;
	color: #389e0d;
}

.dark .schema-editor-ok {
	background: #162312;
	border-top-color: #274916;
	color: #73d13d;
}
</style>
