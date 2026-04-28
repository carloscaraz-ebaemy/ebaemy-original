import './bootstrap';

import 'bootstrap/dist/js/bootstrap.bundle.js'; // Incluye Popper
import 'bootstrap/dist/css/bootstrap.min.css';

import Vue from 'vue'
import store from './store'
import ElementUI from 'element-ui'

// Exponer Vue como global — necesario para vistas blade que usan
// `<script>new Vue({...})</script>` inline. Con Vite (bundling ES
// modules) Vue NO queda en window por defecto. Sin esto, vistas
// como /admin/marketplace/orders fallaban con "Vue is not defined".
if (typeof window !== 'undefined') {
    window.Vue = Vue;
}

import lang from 'element-ui/lib/locale/lang/es'
import locale from 'element-ui/lib/locale'

import '../sass/element-ui.scss';
import 'element-ui/lib/theme-chalk/index.css';


// ─── SYNCHRONOUS: shared small component ────────────────────────────────────
import InputService from '../../modules/ApiPeruDev/Resources/assets/js/components/InputService.vue'

locale.use(lang)

// Fix for ElementUI Select readonly in IE
ElementUI.Select.computed.readonly = function () {
    const isIE = !this.$isServer && !Number.isNaN(Number(document.documentMode));
    return !(this.filterable || this.multiple || !isIE) && !this.visible;
};

export default ElementUI;

Vue.use(ElementUI, { size: 'small' })
Vue.prototype.$eventHub = new Vue()

// inputservice (sync - small shared component)
Vue.component('x-input-service', InputService);

// ─── LAZY-LOADED: Page-level system components ──────────────────────────────

// System configurations
Vue.component('system-support-configuration', () => import('./views/system/configuration/supportConfiguration.vue'));
Vue.component('system-qrapi-configuration', () => import('./views/system/configuration/qrApiConfiguration.vue'));
Vue.component('system-configuration-culqi', () => import('./views/system/configuration/culqi.vue'));
Vue.component('system-configuration-apk-url', () => import('./views/system/configuration/apk-url.vue'));
Vue.component('system-configuration-token', () => import('./views/system/configuration/token_ruc_dni.vue'));
Vue.component('system-php-configuration', () => import('./views/system/configuration/php_info.vue'));
Vue.component('system-server-status', () => import('./views/system/configuration/server_status.vue'));
Vue.component('system-login-settings', () => import('./views/system/configuration/login.vue'));
Vue.component('system-login-other-configuration', () => import('./views/system/configuration/other_configuration.vue'));
Vue.component('system-seller-onboarding-configuration', () => import('./views/system/configuration/seller_onboarding.vue'));
Vue.component('system-email-configuration', () => import('./views/system/configuration/emailConfiguration.vue'));
Vue.component('system-cron-order-configuration', () => import('./views/system/configuration/cronOrderPayments.vue'));

// System clients
Vue.component('system-clients-index', () => import('./views/system/clients/index.vue'));
Vue.component('system-clients-form', () => import('./views/system/clients/form.vue'));

// System users
Vue.component('system-users-form', () => import('./views/system/users/form.vue'));
Vue.component('system-users-token-user', () => import('./views/system/users/token-user.vue'));

// System certificate & companies
Vue.component('system-certificate-index', () => import('./views/system/certificate/index.vue'));
Vue.component('system-companies-form', () => import('./views/system/companies/form.vue'));

// System modules
Vue.component('system-accounting-index', () => import('@viewsModuleAccount/system/accounting/index.vue'));
Vue.component('system-multi-users-index', () => import('@viewsModuleMultiUser/system/multi-users/index.vue'));
Vue.component('system-massive-invoice-index', () => import('./views/system/massive_invoice/index.vue'));

// Tools
Vue.component('system-update', () => import('./views/system/update/index.vue'));
Vue.component('system-backup', () => import('./views/system/backup/index.vue'));

// Reports
Vue.component('system-report-login-lockout-index', () => import('@viewsModuleReport/system/report_login_lockout/index.vue'));
Vue.component('system-user-not-change-password-index', () => import('@viewsModuleReport/system/user_not_change_password/index.vue'));

// Plans
Vue.component('system-plans-index', () => import('./views/system/plans/index.vue'));
Vue.component('system-plans-form', () => import('./views/system/plans/form.vue'));

// Payments
Vue.component('system-payments-index', () => import('./views/system/payments/index.vue'));

// Analytics
Vue.component('system-analytics-dashboard', () => import('./views/system/analytics/index.vue'));

import VueClipboard from 'vue-clipboard2'
Vue.use(VueClipboard)

import moment from 'moment';

Vue.mixin({
    filters: {
        toDecimals(number, decimal = 2) {
            return Number(number).toFixed(decimal);
        },
        DecimalText: function (number, decimal = 2) {
            return isNaN(parseFloat(number)) ? number : Number(number).toFixed(decimal);
        },
        toDate(date) {
            if (date) {
                return moment(date).format('DD/MM/YYYY');
            }
            return '';
        },
        toTime(time) {
            if (time) {
                if (time.length === 5) {
                    return moment(time + ':00', 'HH:mm:ss').format('HH:mm:ss');
                }
                return moment(time, 'HH:mm:ss').format('HH:mm:ss');
            }
            return '';
        },
        pad(value, fill = '', length = 3) {
            if (value) {
                return String(value).padStart(length, fill);
            }
            return value;
        }
    },
    methods: {
        axiosError(error) {
            const response = error.response;
            const status = response.status;
            if (status === 422) {
                this.errors = response.data
            }
            if (status === 500) {
                this.$message({
                    type: 'info',
                    message: response.data.message
                });
            }
        },
        getResponseValidations(success = true, message = null) {
            return {
                success: success,
                message: message
            }
        },
        generalSleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms))
        }
    }
})
const app = new Vue({
    store: store,
    el: '#main-wrapper'
});
