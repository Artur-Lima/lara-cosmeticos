import './bootstrap';

import Alpine from 'alpinejs';
import variantEditor from './admin/variant-editor';

window.Alpine = Alpine;

Alpine.data('variantEditor', variantEditor);
Alpine.start();
