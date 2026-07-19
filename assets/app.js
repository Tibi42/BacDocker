import '@hotwired/turbo';
import './stimulus_bootstrap.js';
// Charge les listeners CSRF (double-submit) même hors éléments data-controller
import './controllers/csrf_protection_controller.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './mobile_menu.js';
import './reveal.js';
import './theme_toggle.js';
import './join_panel.js';
import './modal.js';
import './calendar_turbo.js';
import './cookie_consent.js';
import './admin_url_mask.js';
import './ui_actions.js';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

