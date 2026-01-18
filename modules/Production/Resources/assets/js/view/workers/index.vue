<template>
    <div>
        <div class="page-header pe-0">
            <h2>
                <a href="/workers">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        style="margin-top: -5px;"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-building-factory-2"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M3 21h18" />
                        <path d="M5 21v-12l5 4v-4l5 4h4" />
                        <path
                            d="M19 21v-8l-1.436 -9.574a.5 .5 0 0 0 -.495 -.426h-1.145a.5 .5 0 0 0 -.494 .418l-1.43 8.582"
                        />
                        <path d="M9 17h1" />
                        <path d="M14 17h1" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>{{ title }}</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <button
                    class="btn btn-custom btn-sm  mt-2 me-2"
                    type="button"
                    @click.prevent="clickCreate()"
                >
                    <i class="fa fa-plus-circle"></i> Nuevo
                </button>
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Listado de {{ title }}</h3>
            </div> -->
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th>Nombre</th>
                        <th class="text-start">Tipo de documento</th>
                        <th class="text-end">Número</th>
                        <th class="text-start">Fecha admisión</th>
                        <th>Cargo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <!-- <td>{{ index }}</td> -->
                        <td>{{ row.name }}</td>
                        <td class="text-start">{{ row.document_type }}</td>
                        <td class="text-end">{{ row.number }}</td>
                        <td class="text-start">
                            {{ formatDate(row.admission_date) }}
                        </td>
                        <td>{{ row.occupation }}</td>
                        <td class="text-end">
                            <el-dropdown
                                trigger="click"
                                @command="handleDropdownCommand($event, row)"
                            >
                                <el-button
                                    class="btn btn-default btn-sm btn-dropdown-toggle"
                                    size="mini"
                                    native-type="button"
                                >
                                    <i class="fas fa-ellipsis-v"></i>
                                    <i class="fas fa-ellipsis-h" style="display: none;"></i>
                                </el-button>
                                <el-dropdown-menu slot="dropdown">
                                    <el-dropdown-item command="edit">
                                        Editar
                                    </el-dropdown-item>
                                    <el-dropdown-item command="delete">
                                        Eliminar
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </el-dropdown>
                        </td>
                    </tr>
                </data-table>
            </div>

            <worker-form
                :recordId="recordId"
                :showDialog.sync="showDialog"
            ></worker-form>
        </div>
    </div>
</template>
<style>
@media only screen and (max-width: 485px) {
    .filter-container {
        margin-top: 0px;
        & .btn-filter-content,
        .btn-container-mobile {
            display: flex;
            align-items: center;
            justify-content: start;
        }
    }
}
</style>
<script>
import WorkerForm from "./form.vue";
import DataTable from "@components/DataTable.vue";
import { deletable } from "@mixins/deletable";

export default {
    mixins: [deletable],
    components: { WorkerForm, DataTable },
    data() {
        return {
            title: null,
            showDialog: false,
            resource: "workers",
            recordId: null
        };
    },
    created() {
        this.title = "Empleados";
    },
    methods: {
        formatDate(date) {
            if (!date) return null;
            const parsedDate = moment(date);
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY")
                : null;
        },
        handleDropdownCommand(command, row) {
            switch (command) {
                case "edit":
                    this.clickCreate(row.id);
                    break;
                case "delete":
                    this.clickDelete(row.id);
                    break;
                default:
                    break;
            }
        },
        clickCreate(recordId = null) {
            this.recordId = recordId;
            this.showDialog = true;
        },
        clickDelete(id) {
            this.destroy(`/${this.resource}/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        }
    }
};
</script>
