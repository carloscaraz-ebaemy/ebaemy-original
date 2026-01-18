import './bootstrap';

import 'bootstrap/dist/js/bootstrap.bundle.js'; // Incluye Popper
import 'bootstrap/dist/css/bootstrap.min.css';

import Vue from 'vue'
import store from './store'
import ElementUI from 'element-ui'

import lang from 'element-ui/lib/locale/lang/es'
import locale from 'element-ui/lib/locale'

import '../sass/element-ui.scss';
import 'element-ui/lib/theme-chalk/index.css';


// components
import SystemSupportConfiguration from './views/system/configuration/supportConfiguration.vue';
import SystemConfigurationQrApi from './views/system/configuration/qrApiConfiguration.vue'
import SystemClientsIndex from './views/system/clients/index.vue';
import SystemClientsForm from './views/system/clients/form.vue';
import SystemUsersform from './views/system/users/form.vue';
import SystemUsersTokenUser from './views/system/users/token-user.vue';
import SystemCertificateIndex from './views/system/certificate/index.vue';
import SystemCompaniesForm from './views/system/companies/form.vue';
import SystemAccountingIndex from '@viewsModuleAccount/system/accounting/index.vue';
import SystemMultiUsersIndex from '@viewsModuleMultiUser/system/multi-users/index.vue';
import SystemMassiveInvoiceIndex from './views/system/massive_invoice/index.vue';
import SystemUpdateIndex from './views/system/update/index.vue';
import SystemBackupIndex from './views/system/backup/index.vue';
import SystemConfigurationCulqui from './views/system/configuration/culqi.vue';
import SystemConfigurationApkUrl from './views/system/configuration/apk-url.vue';
import SystemConfigurationTokenRucDni from './views/system/configuration/token_ruc_dni.vue';
import SystemConfigurationPhpInfo from './views/system/configuration/php_info.vue';
import SystemConfigurationServerStatus from './views/system/configuration/server_status.vue';
import SystemConfigurationLogin from './views/system/configuration/login.vue';
import SystemConfigurationOtherConfiguration from './views/system/configuration/other_configuration.vue';
import SystemConfigurationEmail from './views/system/configuration/emailConfiguration.vue';
import SystemReportLoginLockout from '@viewsModuleReport/system/report_login_lockout/index.vue';
import SystemUserNotChangePassword from '@viewsModuleReport/system/user_not_change_password/index.vue';
import SystemPlansIndex from './views/system/plans/index.vue';
import SystemPlansForm from './views/system/plans/form.vue';
import SystemConfigurationCronOrderPayments from './views/system/configuration/cronOrderPayments.vue';
import SystemPaymentsIndex from './views/system/payments/index.vue';

import InputService from '../../modules/ApiPeruDev/Resources/assets/js/components/InputService.vue'// apiperu - porque cambiar el input si tiene el mismo contenido?


locale.use(lang)

// Fix for ElementUI Select readonly in IE
ElementUI.Select.computed.readonly = function () {
    const isIE = !this.$isServer && !Number.isNaN(Number(document.documentMode));
    return !(this.filterable || this.multiple || !isIE) && !this.visible;
};

export default ElementUI;

Vue.use(ElementUI, { size: 'small' })
Vue.prototype.$eventHub = new Vue()

// System components only
Vue.component('system-support-configuration', SystemSupportConfiguration);

Vue.component('system-clients-index', SystemClientsIndex);
Vue.component('system-qrapi-configuration', SystemConfigurationQrApi);
Vue.component('system-clients-form', SystemClientsForm);
Vue.component('system-users-form', SystemUsersform);
Vue.component('system-users-token-user', SystemUsersTokenUser);

Vue.component('system-certificate-index', SystemCertificateIndex);
Vue.component('system-companies-form', SystemCompaniesForm);

Vue.component('system-accounting-index', SystemAccountingIndex);

Vue.component('system-multi-users-index', SystemMultiUsersIndex);
Vue.component('system-massive-invoice-index', SystemMassiveInvoiceIndex);

// Tools & config in System
Vue.component('system-update', SystemUpdateIndex);
Vue.component('system-backup', SystemBackupIndex);
Vue.component('system-configuration-culqi', SystemConfigurationCulqui);
Vue.component('system-configuration-apk-url', SystemConfigurationApkUrl);
Vue.component('system-configuration-token', SystemConfigurationTokenRucDni);
Vue.component('system-php-configuration', SystemConfigurationPhpInfo);
Vue.component('system-server-status', SystemConfigurationServerStatus);

// Login/Access settings
Vue.component('system-login-settings', SystemConfigurationLogin);
Vue.component('system-login-other-configuration', SystemConfigurationOtherConfiguration);
Vue.component('system-email-configuration', SystemConfigurationEmail);

// Reports in system
Vue.component('system-report-login-lockout-index', SystemReportLoginLockout);
Vue.component('system-user-not-change-password-index', SystemUserNotChangePassword);

// System plans
Vue.component('system-plans-index', SystemPlansIndex);
Vue.component('system-plans-form', SystemPlansForm);

// inputservice
Vue.component('x-input-service', InputService);

//system payments
Vue.component('system-payments-index', SystemPaymentsIndex);
Vue.component('system-cron-order-configuration', SystemConfigurationCronOrderPayments);

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
