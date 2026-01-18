import './bootstrap';

import Vue from 'vue'
import store from './store'
import ElementUI from 'element-ui'

import lang from 'element-ui/lib/locale/lang/es'
import locale from 'element-ui/lib/locale'

// Cargar Bootstrap PRIMERO
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.js'; // Incluye Popper

// Luego Element UI
import '../sass/element-ui.scss';
import 'element-ui/lib/theme-chalk/index.css';

// Personalizar textos del idioma español
lang.el.select.noData = 'No se encontraron resultados'
lang.el.cascader.noData = 'No se encontraron resultados'
lang.el.transfer.noData = 'No se encontraron resultados'
lang.el.table.emptyText = 'No se encontraron resultados'
lang.el.tree.emptyText = 'No se encontraron resultados'
lang.el.empty.description = 'No se encontraron resultados'

locale.use(lang)

ElementUI.Select.computed.readonly = function () {
    const isIE = !this.$isServer && !Number.isNaN(Number(document.documentMode));
    return !(this.filterable || this.multiple || !isIE) && !this.visible;
};

export default ElementUI;

Vue.use(ElementUI, { size: 'small' })
Vue.prototype.$eventHub = new Vue()

// Tenant app: only tenant components here
import './tenant-components'

// Importar scripts migrados desde dom-fixes.js
import { applyThemeAndShowContent, setupHeaderDomEvents, setupEcommerceAuthHandlers, updateTenantPageTitle } from './tenant/dom-fixes';

// Inicializar lógica DOM migrada
if (window && window.vc_visual && window.vc_visual.sidebar_theme) {
    applyThemeAndShowContent(window.vc_visual.sidebar_theme);
}
setupHeaderDomEvents();
setupEcommerceAuthHandlers();
updateTenantPageTitle();

// System reports moved to system.js


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
        getResponseValidations(success = true, message = null)
        {
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
const mainWrapper = document.getElementById('main-wrapper');
if (mainWrapper) {
    new Vue({
        store: store,
        el: '#main-wrapper'
    });
}
