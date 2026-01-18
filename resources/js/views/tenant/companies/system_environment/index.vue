<template>
	<div class="card card-config">
		<div class="card-header bg-info">
			<h3 class="my-0">Entorno del sistema</h3>
		</div>
		<div class="card-body">
			<form autocomplete="off" @submit.prevent="submit">
				<div class="form-body">
					<div class="row">
						<div class="col-md-6">
							<div :class="{'has-danger': errors.soap_type_id}"
								 class="form-group">
								<label class="control-label">SOAP Tipo</label>
								<el-select v-model="form.soap_type_id"
										   :disabled="!form.config_system_env"
										   @change="verifyDocumentsInDemo">
									<el-option v-for="option in soap_types"
											   :key="option.id"
											   :label="option.description"
											   :value="option.id"></el-option>
								</el-select>
								<small v-if="errors.soap_type_id"
									   class="form-control-feedback"
									   v-text="errors.soap_type_id[0]"></small>
							</div>
						</div>
						<div v-if="form.soap_type_id != '03'"
							 class="col-md-6">
							<div :class="{'has-danger': errors.soap_send_id}"
								 class="form-group">
								<label class="control-label">SOAP Envio</label>
								<el-select v-model="form.soap_send_id"
										   :disabled="!form.config_system_env">
									<el-option v-for="(option, index) in soap_sends"
											   :key="index"
											   :label="option"
											   :value="index"></el-option>
								</el-select>
								<small v-if="errors.soap_send_id"
									   class="form-control-feedback"
									   v-text="errors.soap_send_id[0]"></small>
							</div>
						</div>
					</div>

					<template v-if="form.soap_type_id == '02' || form.soap_send_id == '02'">						
						<div class="row mt-4">
                            <h4 class="col-12 m-0 fw-medium">Usuario Secundario Sunat/OSE</h4>
							<div class="col-md-6">
								<div :class="{'has-danger': errors.soap_username}"
									 class="form-group">
									<label class="control-label">SOAP Usuario <span
										class="text-danger">*</span></label>
									<el-input v-model="form.soap_username"
											  :disabled="!form.config_system_env"></el-input>
									<div class="sub-title text-muted"><small>RUC + Usuario. Ejemplo:
										01234567890ELUSUARIO</small></div>
									<small v-if="errors.soap_username"
										   class="form-control-feedback"
										   v-text="errors.soap_username[0]"></small>
								</div>
							</div>
							<div class="col-md-6">
								<div :class="{'has-danger': errors.soap_password}"
									 class="form-group">
									<label class="control-label">SOAP Password
										<span class="text-danger">*</span></label>
									<el-input v-model="form.soap_password"
											  :disabled="!form.config_system_env"></el-input>
									<small v-if="errors.soap_password"
										   class="form-control-feedback"
										   v-text="errors.soap_password[0]"></small>
								</div>
							</div>
						</div>
					</template>

					<div v-if="form.soap_send_id == '02'" class="row">
						<div class="col-md-12">
							<div :class="{'has-danger': errors.soap_url}"
								 class="form-group">
								<label class="control-label">SOAP Url</label>
								<el-input v-model="form.soap_url"></el-input>
								<small v-if="errors.soap_url"
									   class="form-control-feedback"
									   v-text="errors.soap_url[0]"></small>
							</div>
						</div>
					</div>
				</div>

				<div class="form-actions text-end pt-2">
					<el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
				</div>
			</form>
		</div>
	</div>
</template>

<script>
export default {
	name: 'SystemEnvironment',
	data() {
		return {
			resource: 'companies',
			loading_submit: false,
			errors: {},
			form: {},
			soap_sends: [],
			soap_types: [],
			verifyDemo: null,
		}
	},
	async created() {
		await this.initForm()
		await this.getTables()
		await this.getRecord()
	},
	methods: {
		async getTables() {
			return this.$http.get(`/${this.resource}/tables`)
				.then(response => {
					this.soap_sends = response.data.soap_sends
					this.soap_types = response.data.soap_types
					this.verifyDemo = response.data.verifyDocumentsInDemo
				})
		},
		async getRecord() {
			return this.$http.get(`/${this.resource}/record`)
				.then(response => {
					if (response.data !== '') {
						this.form = response.data.data
					}
				})
		},
		verifyDocumentsInDemo() {
			if (this.verifyDemo && this.verifyDemo.success) {
				this.form.soap_type_id = '01'
				this.$message.warning(this.verifyDemo.message)
			}
		},
		initForm() {
			this.errors = {}
			this.form = {
				id: null,
				identity_document_type_id: '06000006',
				number: null,
				name: null,
				trade_name: null,
				soap_send_id: '01',
				soap_type_id: '01',
				soap_username: null,
				soap_password: null,
				soap_url: null,
				certificate: null,
				certificate_due: null,
				logo: null,
				logo_dark: null,
				logo_store: null,
				detraction_account: null,
				operation_amazonia: false,
				toggle: false,
				config_system_env: false,
				img_firm: null,
				is_pharmacy: false,
				cod_digemid: null,
				integrated_query_client_id: null,
				integrated_query_client_secret: null,
				app_logo: null,
				soap_sunat_username: null,
				soap_sunat_password: null,
				api_sunat_id: null,
				api_sunat_secret: null,
				title_web: null
			}
		},
		submit() {
			this.loading_submit = true
			this.$http.post(`/${this.resource}`, this.form)
				.then(response => {
					if (response.data.success) {
						this.$message.success(response.data.message)
					} else {
						this.$message.error(response.data.message)
					}
				})
				.catch(error => {
					if (error.response && error.response.status === 422) {
						this.errors = error.response.data
					} else {
						console.log(error)
					}
				})
				.then(() => {
					this.loading_submit = false
				})
		}
	}
}
</script>
