import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

import { registerStores } from './stores';
import moneyInput from './components/money-input';
import fileDropzone from './components/file-dropzone';
import typedConfirm from './components/typed-confirm';
import portalPay from './components/portal-pay';
import { registerPwa } from './pwa';
import { guardFormSubmissions } from './forms';

window.Alpine = Alpine;

Alpine.plugin(focus);
registerStores(Alpine);
Alpine.data('moneyInput', moneyInput);
Alpine.data('fileDropzone', fileDropzone);
Alpine.data('typedConfirm', typedConfirm);
Alpine.data('portalPay', portalPay);

guardFormSubmissions();

registerPwa(Alpine);
Alpine.start();
