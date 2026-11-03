import '@fontsource-variable/figtree';
import '@fontsource/bricolage-grotesque/700.css';
import '@fontsource/bricolage-grotesque/800.css';
import './shared/styles/tokens.css';
import './shared/styles/base.css';
import './shared/styles/components.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

/*
 * Cada página vive en resources/js/modules/<módulo>/pages/<Página>.vue y el
 * servidor la nombra "<módulo>/pages/<Página>" (Inertia::render en Laravel).
 */
const pages = import.meta.glob<{ default: DefineComponent }>('./modules/**/pages/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} · ISA Track` : 'ISA Track'),
    resolve: async (name) => {
        const page = pages[`./modules/${name}.vue`];
        if (!page) throw new Error(`No existe la página ${name}`);
        return (await page()).default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#00C896' },
});
