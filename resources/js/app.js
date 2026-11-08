import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

import { registerStores } from './stores';
import moneyInput from './components/money-input';
import fileDropzone from './components/file-dropzone';
import typedConfirm from './components/typed-confirm';
import { guardFormSubmissions } from './forms';

window.Alpine = Alpine;

Alpine.plugin(focus);
registerStores(Alpine);
Alpine.data('moneyInput', moneyInput);
Alpine.data('fileDropzone', fileDropzone);
Alpine.data('typedConfirm', typedConfirm);

guardFormSubmissions();

Alpine.start();
