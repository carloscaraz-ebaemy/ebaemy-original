<template>
  <div>
    <div class="table-responsive">

              <table class="table">
                <thead>
                  <slot name="heading"></slot>
                </thead>
                <tbody>
                  <slot
                    v-for="(row, index) in records"
                    :row="row"
                    :index="customIndex(index)"
                  ></slot>
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
                  :page-size="pagination.per_page"
                >
                </el-pagination>
              </div>
    </div>
  </div>
</template>


<script>
import { perPage } from '@mixins/per_page'
import queryString from "query-string";

export default {
  mixins: [perPage],
  props: {
    resource: String,
  },
  data() {
    return {
      columns: [],
      records: [],
      pagination: {},
      form: {},
    };
  },
  computed: {},
  created() {
    this.initForm();

    this.$eventHub.$on("reloadSimpleDataTable", (establishment_id) => {
      this.form.establishment_id = establishment_id;
      this.getRecords();
    });

    this.$eventHub.$on("reloadSimpleDate", (date_start,date_end) => {
      this.form.date_start= date_start;
      this.form.date_end= date_end;
      this.getRecords();
    });
    
  },
  async mounted() {
    await this.getRecords();
  },
  methods: {
    initForm() {
      this.form = {
        establishment_id: null,
        date_start: null,
        date_end: null,
      };
    },
    customIndex(index) {
      return (
        this.pagination.per_page * (this.pagination.current_page - 1) +
        index +
        1
      );
    },
    getRecords() {
      if (this.form.establishment_id) {
        return this.$http
          .get(`/${this.resource}/records?${this.getQueryParameters()}`)
          .then((response) => {
            this.records = response.data.data;
            this.pagination = response.data.meta;
            this.pagination.per_page = parseInt(response.data.meta.per_page);
            this.$eventHub.$emit("recordsSkeletonLoader", false);
          });
      }
    },
    getQueryParameters() {
      return queryString.stringify({
        per_page: this.filasPorPagina(),
        page: this.pagination.current_page,
        limit: this.limit,
        ...this.form,
      });
    },
    changeDates() {
      this.$eventHub.$emit("changeDates", this.form.date_start, this.form.date_end);
    },
  },
};
</script>
