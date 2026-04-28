<template>
  <div class="col-lg-6 col-md-12">
    <div class="card card-config">
      <div class="card-header" style="background:#009ee3;color:#fff">
        <h3 class="my-0">
          <i class="fas fa-credit-card mr-1"></i>
          MercadoPago
        </h3>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:13px">
          Cuando actives MercadoPago, los pagos del marketplace que correspondan a productos de tu tienda
          se cobrarán <strong>directamente a tu cuenta MP</strong>. Si el carrito tiene productos de varias
          tiendas, el pago va a la cuenta de ebaemy y luego se distribuye manualmente.
        </p>

        <form autocomplete="off" @submit.prevent="submit">
          <div class="form-body">
            <div class="row">
              <div class="col-md-12 mb-3">
                <div class="form-group">
                  <el-switch
                    v-model="form.mp_enabled"
                    active-text="Activar cobro con MercadoPago"
                    :active-value="true"
                    :inactive-value="false">
                  </el-switch>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group" :class="{'has-danger': errors.mp_public_key}">
                  <label class="control-label">
                    Public Key
                    <el-tooltip placement="right-start">
                      <div slot="content">
                        Llave pública de tu app MP. Empieza con APP_USR-* (producción) o TEST-* (sandbox).
                        <a href="#" @click.prevent="openMpPanel">Ir al panel de MP</a>
                      </div>
                      <i class="fa fa-info-circle"></i>
                    </el-tooltip>
                  </label>
                  <el-input v-model="form.mp_public_key" placeholder="APP_USR-..." :disabled="!form.mp_enabled"></el-input>
                  <small class="form-control-feedback" v-if="errors.mp_public_key" v-text="errors.mp_public_key[0]"></small>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-group" :class="{'has-danger': errors.mp_access_token}">
                  <label class="control-label">
                    Access Token
                    <el-tooltip placement="right-start">
                      <div slot="content">
                        Token privado. NO lo compartas. Se guarda cifrado en la BD.
                      </div>
                      <i class="fa fa-info-circle"></i>
                    </el-tooltip>
                  </label>
                  <el-input
                    v-model="form.mp_access_token"
                    type="password"
                    show-password
                    placeholder="APP_USR-... (deja '__SECRET__' si no quieres cambiar el guardado)"
                    :disabled="!form.mp_enabled">
                  </el-input>
                  <small class="form-control-feedback" v-if="errors.mp_access_token" v-text="errors.mp_access_token[0]"></small>
                  <small class="text-muted" v-if="hasStoredSecret">
                    ✓ Ya hay un access token guardado. Solo escribe uno nuevo si lo quieres reemplazar.
                  </small>
                </div>
              </div>

              <div class="col-md-12 mt-2">
                <div class="form-group">
                  <el-switch
                    v-model="form.mp_sandbox"
                    active-text="Modo sandbox (TEST tokens)"
                    :active-value="true"
                    :inactive-value="false"
                    :disabled="!form.mp_enabled">
                  </el-switch>
                </div>
              </div>
            </div>
          </div>

          <div class="form-actions text-end float-end pt-2">
            <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
          </div>
        </form>

        <div class="clearfix"></div>

        <div v-if="form.mp_enabled" class="alert alert-info mt-3" style="font-size:12px">
          <strong>Webhook URL:</strong>
          <code style="user-select:all">{{ webhookUrl }}</code>
          <br>
          Configura esta URL en
          <a href="https://www.mercadopago.com.pe/developers/panel/app" target="_blank">tu panel MP</a>
          → Webhooks → Eventos: <strong>Payments</strong>.
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const SECRET_PLACEHOLDER = "__SECRET__";

export default {
  data() {
    return {
      loading_submit: false,
      resource: "ecommerce",
      errors: {},
      form: {
        id: null,
        mp_enabled: false,
        mp_sandbox: false,
        mp_public_key: "",
        mp_access_token: "",
      },
      hasStoredSecret: false,
    };
  },
  computed: {
    webhookUrl() {
      const base = window.location.protocol + '//' + window.location.host;
      return base.replace(/^https?:\/\/[^.]+\./, 'https://') + '/marketplace/payment/webhook';
    },
  },
  async created() {
    await this.initForm();
    await this.$http.get(`/${this.resource}/record`).then((response) => {
      if (response.data !== "") {
        const data = response.data.data;
        this.form.id = data.id;
        this.form.mp_enabled = !!data.mp_enabled;
        this.form.mp_sandbox = !!data.mp_sandbox;
        this.form.mp_public_key = data.mp_public_key || "";
        // El access_token NO se devuelve en claro; el backend manda
        // el sentinel '__SECRET__' si hay valor guardado.
        if (data.mp_access_token === SECRET_PLACEHOLDER) {
          this.hasStoredSecret = true;
          this.form.mp_access_token = SECRET_PLACEHOLDER;
        } else {
          this.hasStoredSecret = false;
          this.form.mp_access_token = "";
        }
      }
    });
  },
  methods: {
    openMpPanel() {
      window.open("https://www.mercadopago.com.pe/developers/panel/app", "_blank");
    },
    initForm() {
      this.errors = {};
      this.form = {
        id: null,
        mp_enabled: false,
        mp_sandbox: false,
        mp_public_key: "",
        mp_access_token: "",
      };
    },
    submit() {
      this.loading_submit = true;
      this.$http
        .post(`/${this.resource}/configuration_mercadopago`, this.form)
        .then((response) => {
          if (response.data.success) {
            this.$message.success(response.data.message);
            // Si pegó un nuevo secreto, ahora hay uno guardado
            if (
              this.form.mp_access_token &&
              this.form.mp_access_token !== SECRET_PLACEHOLDER
            ) {
              this.hasStoredSecret = true;
              this.form.mp_access_token = SECRET_PLACEHOLDER;
            }
          } else {
            this.$message.error(response.data.message || "No se pudo guardar");
          }
        })
        .catch((error) => {
          if (error.response && error.response.status === 422) {
            this.errors = error.response.data;
          } else {
            this.$message.error("Error de servidor al guardar");
          }
        })
        .then(() => {
          this.loading_submit = false;
        });
    },
  },
};
</script>
