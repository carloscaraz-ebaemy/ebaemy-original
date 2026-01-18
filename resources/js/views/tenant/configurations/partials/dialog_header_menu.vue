<template>
    <div class="d-none ms-1 d-lg-block topbar-links-container" style="height: inherit;">
        <a
            v-if="
                menu.menu_a != '' &&
                    menu.menu_a !== undefined &&
                    menu.menu_a.route_path !== undefined
            "
            :href="menu.menu_a.route_path"
            :title="menu.menu_a.description"
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
        >
            <span v-html="getIcon(menu.menu_a.icon_id)"></span>
            <span>{{ menu.menu_a.label_menu }}</span>
        </a>
        <a
            v-if="
                menu.menu_b != '' &&
                    menu.menu_b !== undefined &&
                    menu.menu_b.route_path !== undefined
            "
            :href="menu.menu_b.route_path"
            :title="menu.menu_b.description"
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
        >
            <span v-html="getIcon(menu.menu_b.icon_id)"></span>
            <span>{{ menu.menu_b.label_menu }}</span>
        </a>
        <a
            v-if="
                menu.menu_c != '' &&
                    menu.menu_c !== undefined &&
                    menu.menu_c.route_path !== undefined
            "
            :href="menu.menu_c.route_path"
            :title="menu.menu_c.description"
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
        >
            <span v-html="getIcon(menu.menu_c.icon_id)"></span>
            <span>{{ menu.menu_c.label_menu }}</span>
        </a>
        <a
            v-if="
                menu.menu_d != '' &&
                    menu.menu_d !== undefined &&
                    menu.menu_d.route_path !== undefined
            "
            :href="menu.menu_d.route_path"
            :title="menu.menu_d.description"
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
        >
            <span v-html="getIcon(menu.menu_d.icon_id)"></span>
            <span>{{ menu.menu_d.label_menu }}</span>
        </a>

        <a
          v-if="menu.menu_extra_1 && menu.menu_extra_1.link && menu.menu_extra_1.link !== ''"
          :href="menu.menu_extra_1.link"
          :title="getDomain(menu.menu_extra_1.link)"
          class="topbar-links"
          data-placement="bottom"
          data-toggle="tooltip"
          target="_blank"
          rel="noopener noreferrer"
        >
          <span class="favicon-wrap">
            <img
              v-if="faviconOk.extra1 && currentFavicon('extra1')"
              :src="currentFavicon('extra1')"
              width="18" height="18" alt="favicon" referrerpolicy="no-referrer"
              @load="onFaviconLoad('extra1')"
              @error="onFaviconError('extra1')"
              class="mb-1"
            />
            <span v-else v-html="defaultLinkSvg"></span>
          </span>
          <span>{{ menu.menu_extra_1.initials || 'LK1' }}</span>
        </a>

        <a
          v-if="menu.menu_extra_2 && menu.menu_extra_2.link && menu.menu_extra_2.link !== ''"
          :href="menu.menu_extra_2.link"
          :title="getDomain(menu.menu_extra_2.link)"
          class="topbar-links"
          data-placement="bottom"
          data-toggle="tooltip"
          target="_blank"
          rel="noopener noreferrer"
        >
          <span class="favicon-wrap">
            <img
              v-if="faviconOk.extra2 && currentFavicon('extra2')"
              :src="currentFavicon('extra2')"
              width="18" height="18" alt="favicon" referrerpolicy="no-referrer"
              @load="onFaviconLoad('extra2')"
              @error="onFaviconError('extra2')"
              class="mb-1"
            />
            <span v-else v-html="defaultLinkSvg"></span>
          </span>
          <span>{{ menu.menu_extra_2.initials || 'LK2' }}</span>
        </a>
        <a
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
            href="#"
            title="editar accesos directos"
            @click="showDialog = true"
        >
            <span>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="icon icon-tabler icons-tabler-outline icon-tabler-edit mb-1"
                >
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path
                        d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"
                    />
                    <path
                        d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"
                    />
                    <path d="M16 5l3 3" />
                </svg>
            </span>
            <span><i class="fas fa-ellipsis-h"></i></span>
        </a>
        <el-dialog
            :visible="showDialog"
            title="Accesos Directos"
            top="20vh"
            width="40%"
            @close="close"
        >
            <el-form :model="form">
                <el-form-item class="d-flex" :label-width="formLabelWidth" label="Menu 1">
                    <el-select v-model="form.menu_a" placeholder="Acceso no seleccionado">
                        <el-option
                            v-for="option in modules"
                            :key="option.id"
                            :label="
                                routeNames[option.route_path] ||
                                    option.route_path
                            "
                            :value="option.id"
                        ></el-option>
                    </el-select>
                    <el-button v-if="form.menu_a" type="danger" class="ms-2" @click="clearMenu('menu_a')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                    </el-button>
                </el-form-item>
                <el-form-item class="d-flex" :label-width="formLabelWidth" label="Menu 2">
                    <el-select
                        v-model="form.menu_b"
                        placeholder="Acceso no seleccionado"
                        required
                    >
                        <el-option
                            v-for="option in modules"
                            :key="option.id"
                            :label="
                                routeNames[option.route_path] ||
                                    option.route_path
                            "
                            :value="option.id"
                        ></el-option>
                    </el-select>
                    <el-button v-if="form.menu_b" type="danger" class="ms-2" @click="clearMenu('menu_b')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                    </el-button>
                </el-form-item>
                <el-form-item class="d-flex" :label-width="formLabelWidth" label="Menu 3">
                    <el-select
                        v-model="form.menu_c"
                        placeholder="Acceso no seleccionado"
                        required
                    >
                        <el-option
                            v-for="option in modules"
                            :key="option.id"
                            :label="
                                routeNames[option.route_path] ||
                                    option.route_path
                            "
                            :value="option.id"
                        ></el-option>
                    </el-select>
                    <el-button v-if="form.menu_c" type="danger" class="ms-2" @click="clearMenu('menu_c')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                    </el-button>
                </el-form-item>
                <el-form-item class="d-flex mb-4" :label-width="formLabelWidth" label="Menu 4">
                    <el-select
                        v-model="form.menu_d"
                        placeholder="Acceso no seleccionado"
                        required
                    >
                        <el-option
                            v-for="option in modules"
                            :key="option.id"
                            :label="
                                routeNames[option.route_path] ||
                                    option.route_path
                            "
                            :value="option.id"
                        ></el-option>
                    </el-select>
                    <el-button v-if="form.menu_d" type="danger" class="ms-2" @click="clearMenu('menu_d')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                    </el-button>
                </el-form-item>

                <span class="el-dialog__title">Accesos Personalizados</span>

                <p class="text-muted information-text-access mb-3 mt-2">Puede agregar hasta 2 enlaces personalizados que se abriran en una nueva pestaña. Ideal para accesos rápidos a herramientas o páginas frecuentes.</p>

                <el-form-item class="d-flex mt-2" :label-width="formLabelWidth" label="Menu 1">                     
                    <el-input v-model="form.menu_extra_1.link" placeholder="Ej: https://ejemplo.com"></el-input>
                    <el-input 
                        v-model="form.menu_extra_1.initials" 
                        class="ms-2" 
                        style="width: 25%;" 
                        placeholder="Iniciales"
                        maxlength="3"
                        @input="form.menu_extra_1.initials = form.menu_extra_1.initials.toUpperCase()"
                    ></el-input>
                    <el-button v-if="form.menu_extra_1.link" type="danger" class="ms-2" @click="clearMenu('menu_extra_1')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                    </el-button>
                </el-form-item><el-form-item class="d-flex" :label-width="formLabelWidth" label="Menu 2">                    
                    <el-input v-model="form.menu_extra_2.link" placeholder="Ej: https://ejemplo.com"></el-input>
                    <el-input 
                        v-model="form.menu_extra_2.initials" 
                        class="ms-2" 
                        style="width: 25%;" 
                        placeholder="Iniciales"
                        maxlength="3"
                        @input="form.menu_extra_2.initials = form.menu_extra_2.initials.toUpperCase()"
                    ></el-input>
                    <el-button v-if="form.menu_extra_2.link" type="danger" class="ms-2" @click="clearMenu('menu_extra_2')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                    </el-button>
                </el-form-item>
            </el-form>
            <span slot="footer" class="dialog-footer">
                <el-button class="second-buton" @click.prevent="close()"
                    >Cancelar</el-button
                >
                <el-button type="primary" @click.prevent="clickSubmit"
                    >Guardar</el-button
                >
            </span>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            form: {
                menu_a: null,
                menu_b: null,
                menu_c: null,
                menu_d: null,
                menu_extra_1: {
                    link: '',
                    initials: ''
                },
                menu_extra_2: {
                    link: '',
                    initials: ''
                }
            },
            formLabelWidth: "120px",
            modules: [],
            showDialog: false,
            menu: {
                menu_extra_1: {
                    link: '',
                    initials: ''
                },
                menu_extra_2: {
                    link: '',
                    initials: ''
                }
            },
            routeNames: {
                "/documents/create": "Nuevo Comprobante",
                "/sale-notes": "Notas de Venta",
                "/order-notes": "Pedidos",
                "/pos": "Punto de Venta",
                "/items": "Productos",
                "/purchases/create": "Nueva Compra",
                "/inventory": "Inventario",
                "/users": "Usuarios",
                "/establishments": "Sucursales",
                "/companies/create": "Empresa"
            },
            faviconOk: { extra1: true, extra2: true },
            faviconIdx: { extra1: 0, extra2: 0 },
            faviconSources: { extra1: [], extra2: [] },
            faviconCache: {},
            defaultLinkSvg: `
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-external-link mb-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" /><path d="M11 13l9 -9" /><path d="M15 4h5v5" /></svg>
            `
        };
    },
    created() {
        this.getRecords();
        this.loadIcons();
        this.loadFaviconCache();
    },
    watch: {
      'menu.menu_extra_1.link': {
        immediate: true,
        handler(val) {
          this.prepareFavicon('extra1', val);
        }
      },
      'menu.menu_extra_2.link': {
        immediate: true,
        handler(val) {
          this.prepareFavicon('extra2', val);
        }
      }
    },
    methods: {
        loadFaviconCache() {
          try {
            const cached = localStorage.getItem('faviconCache');
            if (cached) {
              this.faviconCache = JSON.parse(cached);
            }
          } catch (error) {
            console.error('Error al cargar cache de favicons:', error);
            this.faviconCache = {};
          }
        },
        saveFaviconCache() {
          try {
            localStorage.setItem('faviconCache', JSON.stringify(this.faviconCache));
          } catch (error) {
            console.error('Error al guardar cache de favicons:', error);
          }
        },
        prepareFavicon(which, url) {
          this.faviconOk[which] = true;
          this.faviconIdx[which] = 0;

          try {
            const u = new URL(url);
            const protoHost = `${u.protocol}//${u.hostname}`;

            // Verificar si ya tenemos el favicon en cache
            if (this.faviconCache[protoHost]) {
              this.faviconSources[which] = [this.faviconCache[protoHost]];
              return;
            }

            // Solo rutas locales comunes
            const candidates = [
              `${protoHost}/favicon.ico`,
              `${protoHost}/favicon.svg`,
              `${protoHost}/apple-touch-icon.png`,
              `${protoHost}/android-chrome-192x192.png`,
              `${protoHost}/favicon-32x32.png`,
              `${protoHost}/favicon-16x16.png`,
            ];

            this.faviconSources[which] = candidates;
          } catch {
            this.faviconSources[which] = [];
            this.faviconOk[which] = false;
          }
        },
        currentFavicon(which) {
          const list = this.faviconSources[which] || [];
          const idx = this.faviconIdx[which] ?? 0;
          return list[idx] || null;
        },
        async loadIcons() {
            try {
                const cachedIcons = localStorage.getItem("icons");
                if (cachedIcons) {
                    this.icons = JSON.parse(cachedIcons);
                    return;
                }
    
                const response = await fetch("/json/icons/icons.json");
                this.icons = await response.json();
                localStorage.setItem("icons", JSON.stringify(this.icons));
            } catch (error) {
                console.error("Error al cargar los íconos:", error);
            }
        },
        getIcon(icon_id) {
            if (!this.icons || !this.icons[icon_id]) {
                return '<span style="color: red;">⚠️ Icono no disponible</span>';
            }
            return this.icons[icon_id];
        },
        getRecords() {
            this.$http.get(`/configurations/visual/get_menu`).then(response => {
                if (response.data !== "") {
                    this.modules = response.data.modules;
                    
                    // Parse menu_extra si viene como string JSON
                    const menuExtra1 = typeof response.data.menu.top_menu_extra_one === 'string' 
                        ? JSON.parse(response.data.menu.top_menu_extra_one || '{"link":"","initials":""}')
                        : (response.data.menu.top_menu_extra_one || {link: '', initials: ''});
                    
                    const menuExtra2 = typeof response.data.menu.top_menu_extra_two === 'string'
                        ? JSON.parse(response.data.menu.top_menu_extra_two || '{"link":"","initials":""}')
                        : (response.data.menu.top_menu_extra_two || {link: '', initials: ''});
                    
                    this.menu = {
                        menu_a: response.data.menu.top_menu_a,
                        menu_b: response.data.menu.top_menu_b,
                        menu_c: response.data.menu.top_menu_c,
                        menu_d: response.data.menu.top_menu_d,
                        menu_extra_1: menuExtra1,
                        menu_extra_2: menuExtra2
                    };
                    this.form = {
                        menu_a: response.data.menu.top_menu_a.id,
                        menu_b: response.data.menu.top_menu_b.id,
                        menu_c: response.data.menu.top_menu_c.id,
                        menu_d: response.data.menu.top_menu_d.id,
                        menu_extra_1: menuExtra1,
                        menu_extra_2: menuExtra2
                    };
                }
            });
        },
        clickSubmit() {
            this.$http
                .post(`/configurations/visual/set_menu`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        
                        // Parse menu_extra si viene como string JSON
                        const menuExtra1 = typeof response.data.menu.top_menu_extra_one === 'string' 
                            ? JSON.parse(response.data.menu.top_menu_extra_one || '{"link":"","initials":""}')
                            : (response.data.menu.top_menu_extra_one || {link: '', initials: ''});
                        
                        const menuExtra2 = typeof response.data.menu.top_menu_extra_two === 'string'
                            ? JSON.parse(response.data.menu.top_menu_extra_two || '{"link":"","initials":""}')
                            : (response.data.menu.top_menu_extra_two || {link: '', initials: ''});
                        
                        this.menu = {
                            menu_a: response.data.menu.top_menu_a,
                            menu_b: response.data.menu.top_menu_b,
                            menu_c: response.data.menu.top_menu_c,
                            menu_d: response.data.menu.top_menu_d,
                            menu_extra_1: menuExtra1,
                            menu_extra_2: menuExtra2
                        };
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    } else {
                        console.log(error);
                    }
                })
                .then(() => {
                    this.loading_submit = false;
                });
            this.close();
        },
        close() {
            this.showDialog = false;
        },
        clearMenu(menuName) {
            if (menuName === 'menu_extra_1' || menuName === 'menu_extra_2') {
                this.form[menuName] = {
                    link: '',
                    initials: ''
                };
            } else {
                this.form[menuName] = null;
            }
        },
        getFavicon(url) {
          try {
            const { hostname } = new URL(url);
            if (!hostname) return null;
            return `https://www.google.com/s2/favicons?domain=${hostname}&sz=64`;
          } catch {
            return null;
          }
        },
        onFaviconError(which) {
          const next = (this.faviconIdx[which] || 0) + 1;
          if (next < (this.faviconSources[which]?.length || 0)) {
            this.faviconIdx[which] = next;
          } else {
            this.faviconOk[which] = false;
          }
        },
        onFaviconLoad(which) {
          // Guardar el favicon exitoso en cache
          const currentUrl = this.currentFavicon(which);
          if (currentUrl) {
            try {
              const u = new URL(currentUrl);
              const protoHost = `${u.protocol}//${u.hostname}`;
              this.faviconCache[protoHost] = currentUrl;
              this.saveFaviconCache();
            } catch (error) {
              console.error('Error al guardar favicon en cache:', error);
            }
          }
        },
        getDomain(url) {
          try { return new URL(url).hostname; } catch { return url; }
        },
    }
};
</script>

<style>
.v-modal {
    display: none !important;
}
.el-dialog {
    z-index: 2002 !important;
}
.sidebar-left {
    z-index: 1020 !important;
}
.el-form-item__label {
    width: auto !important;
    max-width: 100px !important;
    text-align: left;
    text-wrap: nowrap;
}
.el-form-item__label span{
    text-wrap: nowrap;
}
.el-form-item__content {
    margin-left: 70px !important;
    display: flex;
    width: 100%;
}
.information-text-access{
    line-height: 1.4;
    word-break: keep-all;
}
</style>
