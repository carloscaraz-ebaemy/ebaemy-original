<template>
    <div class="table-responsive" v-loading="loading">
        <table class="table">
            <thead>
                <slot name="heading"></slot>
            </thead>
            <tbody>
                <slot v-for="(row, index) in records" :row="row" :index="customIndex(index)"></slot>
            </tbody>
        </table>
        <div>
            <el-pagination
                    @size-change="cambiarTamano($event, getRecords)"
                    :page-sizes="opcionesTamano(pagination.per_page)"
                    @current-change="getRecords"
                    layout="total, sizes, prev, pager, next"
                    :total="pagination.total"
                    :current-page.sync="pagination.current_page"
                    :page-size="pagination.per_page">
            </el-pagination>
        </div>
    </div> 
</template>


<script>

import { perPage } from '@mixins/per_page'
    import moment from 'moment'
    import queryString from 'query-string'

    export default {
        mixins: [perPage],
        props: {
            resource: String,
            form: Object,
        },
        data () {
            return { 
                loading:false,
                columns: [],
                records: [],
                pagination: {}
            }
        },
        computed: {
        },
        created() {
            this.$eventHub.$on('reloadSimpleDataTable', () => {
                this.getRecords()                
            }) 
        },
        async mounted () { 
            // console.log(this.form)
            await this.getRecords()

        },
        methods: {
            customIndex(index) {
                return (this.pagination.per_page * (this.pagination.current_page - 1)) + index + 1
            },
            getRecords() {
                this.loading = true
                return this.$http.get(`/${this.resource}/records?${this.getQueryParameters()}`).then((response) => {
                    this.records = response.data.data
                    this.pagination = response.data.meta
                    this.pagination.per_page = parseInt(response.data.meta.per_page)
                }).then(()=>{
                    this.loading = false
                });
            },
            getQueryParameters() {
                return queryString.stringify({
                    per_page: this.filasPorPagina(),
                    page: this.pagination.current_page,
                    form: JSON.stringify(this.form),
                    limit: this.limit
                })
            } 
        }
    }
</script>