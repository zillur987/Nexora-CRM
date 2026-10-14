import { createApp } from 'vue';
import App from './App.vue';
import { reveal } from './directives/reveal.js';
import { parallax } from './directives/parallax.js';
import { magnetic } from './directives/magnetic.js';

// Vendor: Bootstrap CSS + Icons (fonts are bundled by Vite), and only the JS plugins the theme uses.
// Importing a plugin registers Bootstrap's data-API (data-bs-toggle="offcanvas" | "collapse" | "dropdown").
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import 'bootstrap/js/dist/offcanvas';
import 'bootstrap/js/dist/collapse';
import 'bootstrap/js/dist/dropdown';

// Theme (order matters: tokens -> base -> components)
import './styles/tokens.css';
import './styles/base.css';
import './styles/header.css';
import './styles/mega-menu.css';
import './styles/hero.css';
import './styles/search-bar.css';
import './styles/intro.css';
import './styles/showcase.css';
import './styles/stories.css';
import './styles/sections.css';
import './styles/motion.css';
import './styles/footer.css';

createApp(App)
  .directive('reveal', reveal)
  .directive('parallax', parallax)
  .directive('magnetic', magnetic)
  .mount('#app');
