import Vue from 'vue'

// Imports
import TenantItemAditionalInfoSelector from './views/tenant/components/partials/item_extra_info.vue'
import TenantItemAditionalInfoModal from './views/tenant/components/partials/modal_item_info_attributes.vue'

import TenantDashboardIndex from '../../modules/Dashboard/Resources/assets/js/views/index.vue'
import TenantDashboardSalesByProduct from '../../modules/Dashboard/Resources/assets/js/views/items/SalesByProduct.vue'

import XGraph from './components/graph/src/Graph.vue'
import XGraphLine from './components/graph/src/GraphLine.vue'

import TenantSignaturePseIndex from './views/tenant/companies/signature_pse/index.vue'
import TenantWhatsappApiIndex from './views/tenant/companies/whatsapp_api/index.vue'

import TenantCompaniesForm from './views/tenant/companies/form.vue'
import TenantCompaniesLogo from './views/tenant/companies/logo.vue'
import TenantCertificatesQztray from './views/tenant/companies/certificates_qztray/index.vue'
import TenantSystemEnvironment from './views/tenant/companies/system_environment/index.vue'
import TenantCertificatesIndex from './views/tenant/certificates/index.vue'
import TenantCertificatesForm from './views/tenant/certificates/form.vue'
import TenantConfigurationsForm from './views/tenant/configurations/form.vue'
import TenantConfigurationsFormPurchases from './views/tenant/configurations/partials/purchases.vue'
import TenantConfigurationsVisual from './views/tenant/configurations/visual.vue'
import TenantConfigurationsPdf from './views/tenant/configurations/pdf_templates.vue'
import TenantConfigurationsTicketPdf from './views/tenant/configurations/pdf_ticket_templates.vue'
import TenantConfigurationsSaleNotes from './views/tenant/configurations/sale_notes.vue'
import TenantConfigurationsPdfGuide from './views/tenant/configurations/pdf_guide_templates.vue'
import TenantConfigurationsPreprintedPdf from './views/tenant/configurations/pdf_preprinted_templates.vue'
import TenantDialogHeaderMenu from './views/tenant/configurations/partials/dialog_header_menu.vue'

import TenantBankAccountsIndex from './views/tenant/bank_accounts/index.vue'
import TenantItemsIndex from './views/tenant/items/index.vue'
import TenantPersonsIndex from './views/tenant/persons/index.vue'
import TenantPersonForm from './views/tenant/persons/form.vue'

import TenantUsersForm from './views/tenant/users/form.vue'
import TenantDocumentsIndex from './views/tenant/documents/index.vue'
import TenantDocumentsInvoice from './views/tenant/documents/invoice.vue'
import TenantDocumentsInvoiceGenerate from './views/tenant/documents/invoice_generate.vue'
import TenantDocumentsInvoicetensu from './views/tenant/documents/invoicetensu.vue'
import TenantDocumentsNote from './views/tenant/documents/note.vue'

import TenantPurchaseSettlementsIndex from './views/tenant/purchase-settlements/index.vue'
import TenantPurchaseSettlementsForm from './views/tenant/purchase-settlements/form.vue'

import TenantDocumentsItemsList from './views/tenant/documents/partials/item.vue'
import TenantSummariesIndex from './views/tenant/summaries/index.vue'
import TenantVoidedIndex from './views/tenant/voided/index.vue'
import TenantSearchIndex from './views/tenant/search/index.vue'
import TenantOptionsForm from './views/tenant/options/form.vue'
import TenantOptionsFormItem from './views/tenant/options/form_item.vue'
import TenantUnitTypesIndex from './views/tenant/unit_types/index.vue'
import TenantDetractionTypesIndex from './views/tenant/detraction_types/index.vue'
import TenantUsersIndex from './views/tenant/users/index.vue'
import TenantEstablishmentsIndex from './views/tenant/establishments/index.vue'
import TenantChargeDiscountsIndex from './views/tenant/charge_discounts/index.vue'
import TenantBanksIndex from './views/tenant/banks/index.vue'
import TenantExchangeRatesIndex from './views/tenant/exchange_rates/index.vue'
import TenantCurrencyTypesIndex from './views/tenant/currency_types/index.vue'
import TenantRetentionsIndex from './views/tenant/retentions/index.vue'
import TenantRetentionsForm from './views/tenant/retentions/form.vue'
import TenantPerceptionsIndex from './views/tenant/perceptions/index.vue'
import TenantPerceptionsForm from './views/tenant/perceptions/form.vue'
import TenantDispatchesIndex from './views/tenant/dispatches/index.vue'

import TenantDispatchesForm from './views/tenant/dispatches/form.vue'
import TenantDispatchesCreate from './views/tenant/dispatches/create.vue'
import TenantGuidesModal from './views/tenant/components/guides.vue'
import TenantPurchasesIndex from './views/tenant/purchases/index.vue'
import TenantPurchasesForm from './views/tenant/purchases/form.vue'
import TenantPurchasesEdit from './views/tenant/purchases/form_edit.vue'
import TenantTransferReasonTypesIndex from './views/tenant/transfer_reason_types/index.vue'

import TenantDispatchCarrierIndex from './views/tenant/dispatches/Carrier/Index.vue'
import TenantDispatchCarrierForm from './views/tenant/dispatches/Carrier/Form.vue'

import TenantPurchasesItems from './views/tenant/dispatches/items.vue'
import TenantAttributeTypesIndex from './views/tenant/attribute_types/index.vue'
import TenantCalendar from './views/tenant/components/calendar.vue'
import TenantWarehouses from './views/tenant/components/warehouses.vue'
import TenantCalendarQuotation from './views/tenant/components/calendarquotations.vue'
import TenantProduct from './views/tenant/components/products.vue'

import TenantTasksLists from './views/tenant/tasks/lists.vue'
import TenantTasksForm from './views/tenant/tasks/form.vue'
import TenantReportsConsistencyDocumentsLists from './views/tenant/reports/consistency-documents/lists.vue'
import TenantContingenciesIndex from './views/tenant/contingencies/index.vue'

import TenantQuotationsIndex from './views/tenant/quotations/index.vue'
import TenantQuotationsForm from './views/tenant/quotations/form.vue'
import TenantQuotationsEdit from './views/tenant/quotations/form_edit.vue'
import TenantQuotationsItemForm from './views/tenant/quotations/partials/item.vue'

import TenantSaleNotesIndex from './views/tenant/sale_notes/index.vue'
import TenantSaleNotesForm from './views/tenant/sale_notes/form.vue'
import TenantPosIndex from './views/tenant/pos/index.vue'
import CashIndex from './views/tenant/cash/index.vue'
import TenantCardBrandsIndex from './views/tenant/card_brands/index.vue'
import TenantPosFast from './views/tenant/pos/fast.vue'
import TenantPosGarage from './views/tenant/pos/garage.vue'

import TenantPaymentMethodIndex from './views/tenant/payment_method/index.vue'

// Modules
import InventoryIndex from '../../modules/Inventory/Resources/assets/js/inventory/index.vue'
import InventoryTransfersIndex from '../../modules/Inventory/Resources/assets/js/transfers/index.vue'
import WarehousesIndex from '../../modules/Inventory/Resources/assets/js/warehouses/index.vue'
import TenantReportKardexIndex from '../../modules/Inventory/Resources/assets/js/kardex/index.vue'
import TenantInventoriesForm from '../../modules/Inventory/Resources/assets/js/config/form.vue'
import TenantExpensesIndex from '../../modules/Expense/Resources/assets/js/views/expenses/index.vue'
import TenantExpensesForm from '../../modules/Expense/Resources/assets/js/views/expenses/form.vue'
import TenantAccountExport from '../../modules/Account/Resources/assets/js/views/account/export.vue'
import TenantAccountSummaryReport from '../../modules/Account/Resources/assets/js/views/summary_report/index.vue'
import TenantAccountFormat from '../../modules/Account/Resources/assets/js/views/account/format.vue'
import TenantCompanyAccounts from '../../modules/Account/Resources/assets/js/views/company_accounts/form.vue'
import TenantLedgerAccounts from '../../modules/Account/Resources/assets/js/views/ledger_accounts/form.vue'

import InventoryReviewIndex from '@viewsModuleInventory/inventory-review/index.vue'
import TenantInventoryReport from '../../modules/Inventory/Resources/assets/js/inventory/reports/index.vue'

import TenantInventoryColorIndex from '../../modules/Inventory/Resources/assets/js/extra_info/color/index.vue'
import TenantInventoryItemUnitsPerPackageIndex from '../../modules/Inventory/Resources/assets/js/extra_info/item_units_per_package/index.vue'
import TenantInventoryItemUnitsBusiness from '../../modules/Inventory/Resources/assets/js/extra_info/item_units_business/index.vue'
import TenantInventoryItemPackageMeasurements from '../../modules/Inventory/Resources/assets/js/extra_info/item_package_measurements/index.vue'
import TenantInventoryMoldCavities from '../../modules/Inventory/Resources/assets/js/extra_info/item_mold_cavities/index.vue'
import TenantInventoryMoldProperty from '../../modules/Inventory/Resources/assets/js/extra_info/item_mold_property/index.vue'

import TenantInventorySizeProperty from '../../modules/Inventory/Resources/assets/js/extra_info/item_size/index.vue'
import TenantInventoryItemStatus from '../../modules/Inventory/Resources/assets/js/extra_info/item_status/index.vue'
import TenantInventoryItemProductFamily from '../../modules/Inventory/Resources/assets/js/extra_info/item_product_family/index.vue'
import TenantInventoryExtraInfoList from '../../modules/Inventory/Resources/assets/js/extra_info/index.vue'

import TenantInventoryDevolutionsIndex from '../../modules/Inventory/Resources/assets/js/devolutions/index.vue'
import TenantInventoryDevolutionsForm from '../../modules/Inventory/Resources/assets/js/devolutions/form.vue'

import TenantDocumentsNotSent from '../../modules/Document/Resources/assets/js/views/documents/not_sent.vue'
import TenantReportPurchasesIndex from '../../modules/Report/Resources/assets/js/views/purchases/index.vue'
import TenantReportDocumentsIndex from '../../modules/Report/Resources/assets/js/views/documents/index.vue'
import TenantStateAccountIndex from '../../modules/Report/Resources/assets/js/views/state_account/index.vue'
import TenantReportCustomersIndex from '../../modules/Report/Resources/assets/js/views/customers/index.vue'
import TenantReportItemsIndex from '../../modules/Report/Resources/assets/js/views/items/index.vue'
import TenantReportItemsExtraIndex from '../../modules/Report/Resources/assets/js/views/items/index_extra.vue'

import TenantReportDownloadTrayIndex from '../../modules/Report/Resources/assets/js/views/download_tray/index.vue'

import TenantReportGuideIndex from '../../modules/Report/Resources/assets/js/views/guide/index.vue'
import TenantReportSaleNotesIndex from '../../modules/Report/Resources/assets/js/views/sale_notes/index.vue'
import TenantReportQuotationsIndex from '../../modules/Report/Resources/assets/js/views/quotations/index.vue'
import TenantReportCashIndex from '../../modules/Report/Resources/assets/js/views/cash/index.vue'
import TenantIndexConfiguration from '../../modules/BusinessTurn/Resources/assets/js/views/configurations/index.vue'
import TenantReportDocumentHotelsIndex from '../../modules/Report/Resources/assets/js/views/document_hotels/index.vue'
import TenantReportHotelsIndex from '../../modules/Report/Resources/assets/js/views/report_hotels/index.vue'
import TenantReportCommercialAnalysisIndex from '../../modules/Report/Resources/assets/js/views/commercial_analysis/index.vue'
import TenantOfflineConfigurationsIndex from '../../modules/Offline/Resources/assets/js/views/offline_configurations/index.vue'
import TenantSeriesConfigurationsIndex from '../../modules/Document/Resources/assets/js/views/series_configurations/index.vue'
import TenantValidateDocumentsIndex from '../../modules/Document/Resources/assets/js/views/validate_documents/index.vue'
import TenantReportDocumentDetractionsIndex from '../../modules/Report/Resources/assets/js/views/document-detractions/index.vue'
import TenantReportCommissionsIndex from '../../modules/Report/Resources/assets/js/views/commissions/index.vue'
import TenantReportOrderNotesConsolidatedIndex from '../../modules/Report/Resources/assets/js/views/order_notes_consolidated/index.vue'
import TenantReportGeneralItemsIndex from '../../modules/Report/Resources/assets/js/views/general_items/index.vue'
import TenantReportOrderNotesGeneralIndex from '../../modules/Report/Resources/assets/js/views/order_notes_general/index.vue'
import TenantReportSalesConsolidatedIndex from '../../modules/Report/Resources/assets/js/views/sales_consolidated/index.vue'
import TenantReportUserCommissionsIndex from '../../modules/Report/Resources/assets/js/views/user_commissions/index.vue'
import TenantReportFixedAssetPurchasesIndex from '../../modules/Report/Resources/assets/js/views/fixed-asset-purchases/index.vue'
import TenantReportMassiveDownloadsIndex from '../../modules/Report/Resources/assets/js/views/massive-downloads/index.vue'
import TenantDocumentsRegularizeShipping from '../../modules/Document/Resources/assets/js/views/documents/regularize_shipping.vue'
import TenantReportCommissionsDetailIndex from '../../modules/Report/Resources/assets/js/views/commissions_detail/index.vue'

import TenantReportTipsIndex from '../../modules/Report/Resources/assets/js/views/tips/index.vue'

import TenantCategoriesIndex from '../../modules/Item/Resources/assets/js/views/categories/index.vue'
import TenantBrandsIndex from '../../modules/Item/Resources/assets/js/views/brands/index.vue'
import TenantZoneIndex from '../../modules/Item/Resources/assets/js/views/zone/index.vue'
import TenantIncentivesIndex from '../../modules/Item/Resources/assets/js/views/incentives/index.vue'
import TenantItemLotsIndex from '../../modules/Item/Resources/assets/js/views/item-lots/index.vue'

import TenantEcommerceConfigurationInfo from '../../modules/Ecommerce/Resources/assets/js/views/configuration/index.vue'
import TenantEcommerceConfigurationCulqi from '../../modules/Ecommerce/Resources/assets/js/views/configuration_culqi/index.vue'
import TenantEcommerceConfigurationPaypal from '../../modules/Ecommerce/Resources/assets/js/views/configuration_paypal/index.vue'
import TenantEcommerceConfigurationLogo from '../../modules/Ecommerce/Resources/assets/js/views/configuration_logo/index.vue'
import TenantEcommerceConfigurationSocial from '../../modules/Ecommerce/Resources/assets/js/views/configuration_social/index.vue'
import TenantEcommerceConfigurationTag from '../../modules/Ecommerce/Resources/assets/js/views/configuration_tags/index.vue'
import TenantEcommerceItemSetsIndex from '../../modules/Ecommerce/Resources/assets/js/views/item_sets/index.vue'
import TenantEcommerceConfigurationLinks from '../../modules/Ecommerce/Resources/assets/js/views/configuration_links/index.vue'
import TenantEcommerceConfigurationColor from '../../modules/Ecommerce/Resources/assets/js/views/configuration_color/index.vue'

import TenantPurchaseQuotationsIndex from '../../modules/Purchase/Resources/assets/js/views/purchase-quotations/index.vue'
import TenantPurchaseQuotationsForm from '../../modules/Purchase/Resources/assets/js/views/purchase-quotations/form.vue'

import TenantPurchaseOrdersIndex from '../../modules/Purchase/Resources/assets/js/views/purchase-orders/index.vue'
import TenantPurchaseOrdersForm from '../../modules/Purchase/Resources/assets/js/views/purchase-orders/form.vue'
import TenantPurchaseOrdersGenerate from '../../modules/Purchase/Resources/assets/js/views/purchase-orders/generate.vue'

import MovesIndex from '../../modules/Inventory/Resources/assets/js/moves/index.vue'
import InventoryFormMasive from '../../modules/Inventory/Resources/assets/js/transfers/form_masive.vue'

import TenantReportKardexMaster from '../../modules/Inventory/Resources/assets/js/kardex_master/index.vue'
import TenantReportKardexLots from '../../modules/Inventory/Resources/assets/js/kardex/lots.vue'
import TenantReportKardexSeries from '../../modules/Inventory/Resources/assets/js/kardex/series.vue'

import TenantOrderNotesIndex from '../../modules/Order/Resources/assets/js/views/order_notes/index.vue'
import TenantOrderNotesForm from '../../modules/Order/Resources/assets/js/views/order_notes/form.vue'
import TenantOrderNotesEdit from '../../modules/Order/Resources/assets/js/views/order_notes/form_edit.vue'
import TenantReportValuedKardex from '../../modules/Inventory/Resources/assets/js/valued_kardex/index.vue'
import TenantMitiendapeConfig from '../../modules/Order/Resources/assets/js/views/mi_tienda_pe/form.vue'

// Finance
import TenantFinanceGlobalPaymentsIndex from '../../modules/Finance/Resources/assets/js/views/global_payments/index.vue'
import TenantFinanceBalanceIndex from '../../modules/Finance/Resources/assets/js/views/balance/index.vue'
import TenantFinanceModalTransferBetweenAccounts from '../../modules/Finance/Resources/assets/js/views/transfer_between_accounts/options.vue'

import TenantFinancePaymentMethodTypesIndex from '../../modules/Finance/Resources/assets/js/views/payment_method_types/index.vue'
import TenantFinanceUnpaidIndex from '@viewsModuleFinance/unpaid/index.vue'
import TenantFinanceToPayIndex from '@viewsModuleFinance/to_pay/index.vue'
import TenantFinanceIncomeIndex from '@viewsModuleFinance/income/index.vue'
import TenantFinanceIncomeForm from '@viewsModuleFinance/income/form.vue'
import TenantIncomeTypesIndex from '@viewsModuleFinance/income_types/index.vue'
import TenantIncomeReasonsIndex from '@viewsModuleFinance/income_reasons/index.vue'
import TenantFinanceMovementsIndex from '@viewsModuleFinance/movements/index.vue'

// Sale
import TenantSaleOpportunitiesIndex from '@viewsModuleSale/sale_opportunities/index.vue'
import TenantSaleOpportunitiesForm from '@viewsModuleSale/sale_opportunities/form.vue'
import TenantPaymentMethodTypesIndex from '@viewsModuleSale/payment_method_types/index.vue'
import TenantContractsIndex from '@viewsModuleSale/contracts/index.vue'
import TenantContractsForm from '@viewsModuleSale/contracts/form.vue'
import TenantProductionOrdersIndex from '@viewsModuleSale/production_orders/index.vue'
import TenantAgentsIndex from '@viewsModuleSale/agents/index.vue'

// Item
import TenantWebPlatformsIndex from '@viewsModuleItem/web-platforms/index.vue'
import TenantItemDetailIndex from '@viewsModuleItem/items/item-detail.vue'

// Technical Services
import TenantTechnicalServicesIndex from '@viewsModuleSale/technical-services/index.vue'
import TenantUserCommissionsIndex from '@viewsModuleSale/user-commissions/index.vue'
import TenantPendingAccountCommissionsIndex from '@viewsModuleSale/pending-accounts/index.vue'

// Purchase
import TenantFixedAssetItemsIndex from '@viewsModulePurchase/fixed_asset_items/index.vue'
import TenantFixedAssetPurchasesIndex from '@viewsModulePurchase/fixed_asset_purchases/index.vue'
import TenantFixedAssetPurchasesForm from '@viewsModulePurchase/fixed_asset_purchases/form.vue'

// Expense
import TenantExpenseTypesIndex from '@viewsModuleExpense/expense_types/index.vue'
import TenantExpenseReasonsIndex from '@viewsModuleExpense/expense_reasons/index.vue'
import TenantExpenseMethodTypesIndex from '@viewsModuleExpense/expense_method_types/index.vue'

import TenantDriversIndex from './views/tenant/dispatches/drivers/index.vue'
import TenantDispatchersIndex from './views/tenant/dispatches/dispatchers/index.vue'
import TenantTransportsIndex from './views/tenant/dispatches/transports/index.vue'
import TenantOriginAddressesIndex from './views/tenant/dispatches/OriginAddress/Index.vue'
import TenantDispatchAddressesIndex from './views/tenant/dispatches/dispatch-addresses/index.vue'

import TenantOrderFormsIndex from '@viewsModuleOrder/order_forms/index.vue'
import TenantOrderFormsForm from '@viewsModuleOrder/order_forms/form.vue'

import TenantMultiUsersChangeClient from '@viewsModuleMultiUser/tenant/multi-users/change-client.vue'

// Hoteles
import TenantHotelRates from '@viewsModuleHotel/rates/List.vue'
import TenantHotelCategories from '@viewsModuleHotel/categories/List.vue'
import TenantHotelFloors from '@viewsModuleHotel/floors/List.vue'
import TenantHotelRooms from '@viewsModuleHotel/rooms/List.vue'
import TenantHotelReception from '@viewsModuleHotel/rooms/Reception.vue'
import TenantHotelSucursale from '@viewsModuleHotel/rooms/partials/ButtonSucursales.vue'
import TenantHotelRent from '@viewsModuleHotel/rooms/Rent.vue'
import TenantHotelRentAddProduct from '@viewsModuleHotel/rooms/AddProductToRoom.vue'
import TenantHotelRentCheckout from '@viewsModuleHotel/rooms/Checkout.vue'

// Trámite documentario
import TenantDocumentaryOffices from '@viewsModuleDocumentary/offices/Offices.vue'
import TenantDocumentaryStatus from '@viewsModuleDocumentary/status/Status.vue'
import TenantDocumentaryProcesses from '@viewsModuleDocumentary/processes/Processes.vue'
import TenantDocumentaryDocuments from '@viewsModuleDocumentary/documents/Documents.vue'
import TenantDocumentaryActions from '@viewsModuleDocumentary/actions/Actions.vue'
import TenantDocumentaryFiles from '@viewsModuleDocumentary/files/Files.vue'
import TenantDocumentaryRequirements from '@viewsModuleDocumentary/requirements/Requirements.vue'
import TenantDocumentaryStatistic from '@viewsModuleDocumentary/statistic/Index.vue'

// Trámite documentario Simplificado
import TenantDocumentaryFilesSimplify from '@viewsModuleDocumentary/files_simplify/Files.vue'
import TenantDocumentaryFilesSimplifyForm from '@viewsModuleDocumentary/files_simplify/FilesNew.vue'

// apiperu input
import XInputService from '../../modules/ApiPeruDev/Resources/assets/js/components/InputService.vue'

// Ecommerce, tags, promos
import TenantItemsEcommerceIndex from './views/tenant/items_ecommerce/index.vue'
import TenantEcommerceCart from './views/tenant/ecommerce/cart_dropdown.vue'
import TenantTagsIndex from './views/tenant/tags/index.vue'
import TenantPromotionsIndex from './views/tenant/promotions/index.vue'

import TenantItemSetsIndex from './views/tenant/item_sets/index.vue'
import TenantPersonTypesIndex from './views/tenant/person_types/index.vue'
import TenantOrdersIndex from './views/tenant/orders/index.vue'

// Cuenta
import TenantAccountPaymentIndex from './views/tenant/account/payment_index.vue'
import TenantAccountConfigurationIndex from './views/tenant/account/configuration.vue'

// Login
import TenantLoginPage from './views/tenant/login/index.vue'

// Digemid
import TenantDigemidIndex from '../../modules/Digemid/Resources/assets/js/view/index.vue'

// Suscription
import TenantSuscriptionClientIndex from '../../modules/Suscription/Resources/assets/js/clients/index.vue'
import TenantSuscriptionPlansIndex from '../../modules/Suscription/Resources/assets/js/plans/index.vue'
import TenantSuscriptionPaymentsIndex from '../../modules/Suscription/Resources/assets/js/payments/index.vue'
import DataTablePaymentReceipt from '../js/components/DataTablePaymentReceipt.vue'
import TenantIndexPaymentReceipt from '../../modules/Suscription/Resources/assets/js/payment_receipt/index.vue'

// Suscription - extras
import TenantSuscriptionGradesIndex from '@viewsModuleSuscription/grades/index.vue'
import TenantSuscriptionSectionsIndex from '@viewsModuleSuscription/sections/index.vue'

// Full Suscription
import TenantFullSuscriptionClientIndex from '../../modules/FullSuscription/Resources/assets/js/clients/index.vue'
import TenantFullSuscriptionPlansIndex from '../../modules/FullSuscription/Resources/assets/js/plans/index.vue'
import TenantFullSuscriptionPaymentsIndex from '../../modules/FullSuscription/Resources/assets/js/payments/index.vue'
import TenantFullSuscriptionIndexPaymentReceipt from '../../modules/FullSuscription/Resources/assets/js/payment_receipt/index.vue'

// Bank loans
import TenantBankloansIndex from '../../modules/Expense/Resources/assets/js/views/bank_loans/index.vue'
import TenantBankloansForm from '../../modules/Expense/Resources/assets/js/views/bank_loans/form.vue'

// Producción
import TenantMillIndex from '../../modules/Production/Resources/assets/js/view/mill/index.vue'
import TenantMillForm from '../../modules/Production/Resources/assets/js/view/mill/form.vue'
import TenantMachineIndex from '../../modules/Production/Resources/assets/js/view/machine/index.vue'
import TenantMachineTypeIndex from '../../modules/Production/Resources/assets/js/view/machine/index_type.vue'
import TenantMachineForm from '../../modules/Production/Resources/assets/js/view/machine/form.vue'
import TenantMachineTypeForm from '../../modules/Production/Resources/assets/js/view/machine/form_type.vue'
import TenantWorkersIndex from '../../modules/Production/Resources/assets/js/view/workers/index.vue'
import TenantProductionIndex from '../../modules/Production/Resources/assets/js/view/production/index.vue'
import TenantProductionForm from '../../modules/Production/Resources/assets/js/view/production/form.vue'
import TenantPackagingIndex from '../../modules/Production/Resources/assets/js/view/packaging/index.vue'
import TenantPackagingForm from '../../modules/Production/Resources/assets/js/view/packaging/form.vue'

// Restaurante
import TenantRestaurantListItems from '../../modules/Restaurant/Resources/assets/js/views/items/index.vue'
import TenantRestaurantPromotionsIndex from '../../modules/Restaurant/Resources/assets/js/views/promotions/index.vue'
import TenantRestaurantOrdersIndex from '../../modules/Restaurant/Resources/assets/js/views/orders/index.vue'
import TenantRestaurantCashIndex from '../../modules/Restaurant/Resources/assets/js/views/cash/index.vue'
import TenantRestaurantCashFilterPos from '../../modules/Restaurant/Resources/assets/js/views/cash/filter-pos.vue'
import TenantRestaurantConfiguration from '../../modules/Restaurant/Resources/assets/js/views/configuration/index.vue'
import TenantRestaurantSuppliesIndex from '../../modules/Restaurant/Resources/assets/js/views/supplies/index.vue'
import TenantRestaurantModifierGroupsIndex from '../../modules/Restaurant/Resources/assets/js/views/modifier-groups/index.vue'
import TenantRestaurantModifierGroupsForm from '../../modules/Restaurant/Resources/assets/js/views/modifier-groups/form.vue'

// Pagos
import TenantPaymentConfigurationsIndex from '@viewsModulePayment/payment_configurations/index.vue'
import TenantPublicPaymentLinksIndex from '@viewsModulePayment/payment_links/public/index.vue'
import TenantPaymentLinksIndex from '@viewsModulePayment/payment_links/index.vue'

// Mobile App
import TenantMobileAppConfiguration from '@viewsModuleMobileApp/configuration/index.vue'
import TenantMobileAppPermissions from '@viewsModuleMobileApp/permissions/index.vue'

// LevelAccess
import TenantSystemActivityLogsGeneralsIndex from '@viewsModuleLevelAccess/system_activity_logs/generals/index.vue'
import TenantSystemActivityLogsTransactionsIndex from '@viewsModuleLevelAccess/system_activity_logs/transactions/index.vue'
import TenantRememberChangePassword from './views/tenant/users/partials/remember_change_password.vue'

import TenantSireIndex from './views/tenant/sire/index.vue'
import TenantQrChat from '@viewsModuleQrChatBuho/Configuration.vue'
import TenantQrApi from '@viewsModuleQrApi/ConfigurationQrApi.vue'
import TenantReportPendingAccountCommissionsIndex from '@viewsModuleReport/pending-account-commissions/index.vue'
import TenantReportSaleByBrand from '../../modules/Report/Resources/assets/js/views/sales_by_brand/index.vue'
import TenatnEditorTag from '../../modules/Item/Resources/assets/js/views/editor-tag/index.vue'
import EmptyState from './components/EmptyState.vue'
import TenantItemAffectations from './views/tenant/item_affectations/index.vue';

//componente agregado para issue #93 añadir icono cuando no hay datos
Vue.component('empty-state', EmptyState);   
// Sire
Vue.component('tenant-sire-index', TenantSireIndex);

// Sire
Vue.component('tenant-qr-chat', TenantQrChat);

// QR Api
Vue.component('tenant-qr-api', TenantQrApi);


// Registrations
Vue.component('tenant-item-aditional-info-selector', TenantItemAditionalInfoSelector)
Vue.component('tenant-item-aditional-info-modal', TenantItemAditionalInfoModal)

Vue.component('tenant-dashboard-index', TenantDashboardIndex)
Vue.component('tenant-dashboard-sales-by-product', TenantDashboardSalesByProduct)

Vue.component('x-graph', XGraph)
Vue.component('x-graph-line', XGraphLine)

Vue.component('tenant-signature-pse-index', TenantSignaturePseIndex)
Vue.component('tenant-whatsapp-api-index', TenantWhatsappApiIndex)

Vue.component('tenant-companies-form', TenantCompaniesForm)
Vue.component('tenant-companies-logo', TenantCompaniesLogo)
Vue.component('tenant-certificates-qztray', TenantCertificatesQztray)
Vue.component('tenant-certificates-index', TenantCertificatesIndex)
Vue.component('tenant-system-environment', TenantSystemEnvironment)
Vue.component('tenant-certificates-form', TenantCertificatesForm)
Vue.component('tenant-configurations-form', TenantConfigurationsForm)
Vue.component('tenant-configurations-form-purchases', TenantConfigurationsFormPurchases)
Vue.component('tenant-configurations-visual', TenantConfigurationsVisual)
Vue.component('tenant-configurations-pdf', TenantConfigurationsPdf)
Vue.component('tenant-configurations-ticket-pdf', TenantConfigurationsTicketPdf)
Vue.component('tenant-configurations-sale-notes', TenantConfigurationsSaleNotes)
Vue.component('tenant-configurations-pdf-guide', TenantConfigurationsPdfGuide)
Vue.component('tenant-configurations-preprinted-pdf', TenantConfigurationsPreprintedPdf)
Vue.component('tenant-dialog-header-menu', TenantDialogHeaderMenu)

Vue.component('tenant-bank_accounts-index', TenantBankAccountsIndex)
Vue.component('tenant-items-index', TenantItemsIndex)
Vue.component('tenant-persons-index', TenantPersonsIndex)
Vue.component('tenant-person-form', TenantPersonForm)

Vue.component('tenant-users-form', TenantUsersForm)
Vue.component('tenant-documents-index', TenantDocumentsIndex)
Vue.component('tenant-documents-invoice', TenantDocumentsInvoice)
Vue.component('tenant-documents-invoice-generate', TenantDocumentsInvoiceGenerate)
Vue.component('tenant-documents-invoicetensu', TenantDocumentsInvoicetensu)
Vue.component('tenant-documents-note', TenantDocumentsNote)

Vue.component('tenant-purchase-settlements-index', TenantPurchaseSettlementsIndex)
Vue.component('tenant-purchase-settlements-form', TenantPurchaseSettlementsForm)

Vue.component('tenant-documents-items-list', TenantDocumentsItemsList)
Vue.component('tenant-summaries-index', TenantSummariesIndex)
Vue.component('tenant-voided-index', TenantVoidedIndex)
Vue.component('tenant-search-index', TenantSearchIndex)
Vue.component('tenant-options-form', TenantOptionsForm)
Vue.component('tenant-options-form-item', TenantOptionsFormItem)
Vue.component('tenant-unit_types-index', TenantUnitTypesIndex)
Vue.component('tenant-detraction_types-index', TenantDetractionTypesIndex)
Vue.component('tenant-users-index', TenantUsersIndex)
Vue.component('tenant-establishments-index', TenantEstablishmentsIndex)
Vue.component('tenant-charge_discounts-index', TenantChargeDiscountsIndex)
Vue.component('tenant-banks-index', TenantBanksIndex)
Vue.component('tenant-exchange_rates-index', TenantExchangeRatesIndex)
Vue.component('tenant-currency-types-index', TenantCurrencyTypesIndex)
Vue.component('tenant-retentions-index', TenantRetentionsIndex)
Vue.component('tenant-retentions-form', TenantRetentionsForm)
Vue.component('tenant-perceptions-index', TenantPerceptionsIndex)
Vue.component('tenant-perceptions-form', TenantPerceptionsForm)
Vue.component('tenant-dispatches-index', TenantDispatchesIndex)

Vue.component('tenant-dispatches-form', TenantDispatchesForm)
Vue.component('tenant-dispatches-create', TenantDispatchesCreate)
Vue.component('tenant-guides-modal', TenantGuidesModal)
Vue.component('tenant-purchases-index', TenantPurchasesIndex)
Vue.component('tenant-purchases-form', TenantPurchasesForm)
Vue.component('tenant-purchases-edit', TenantPurchasesEdit)
Vue.component('tenant-transfer-reason-types-index', TenantTransferReasonTypesIndex)

Vue.component('tenant-dispatch_carrier-index', TenantDispatchCarrierIndex)
Vue.component('tenant-dispatch_carrier-form', TenantDispatchCarrierForm)

Vue.component('tenant-purchases-items', TenantPurchasesItems)
Vue.component('tenant-attribute_types-index', TenantAttributeTypesIndex)
Vue.component('tenant-item-affectations', TenantItemAffectations)
Vue.component('tenant-calendar', TenantCalendar)
Vue.component('tenant-warehouses', TenantWarehouses)
Vue.component('tenant-calendar-quotation', TenantCalendarQuotation)
Vue.component('tenant-product', TenantProduct)

Vue.component('tenant-tasks-lists', TenantTasksLists)
Vue.component('tenant-tasks-form', TenantTasksForm)
Vue.component('tenant-reports-consistency-documents-lists', TenantReportsConsistencyDocumentsLists)
Vue.component('tenant-contingencies-index', TenantContingenciesIndex)

Vue.component('tenant-quotations-index', TenantQuotationsIndex)
Vue.component('tenant-quotations-form', TenantQuotationsForm)
Vue.component('tenant-quotations-edit', TenantQuotationsEdit)
Vue.component('tenant-quotations-item-form', TenantQuotationsItemForm)

Vue.component('tenant-sale-notes-index', TenantSaleNotesIndex)
Vue.component('tenant-sale-notes-form', TenantSaleNotesForm)
Vue.component('tenant-pos-index', TenantPosIndex)
Vue.component('cash-index', CashIndex)
Vue.component('tenant-card-brands-index', TenantCardBrandsIndex)
Vue.component('tenant-pos-fast', TenantPosFast)
Vue.component('tenant-pos-garage', TenantPosGarage)

Vue.component('tenant-payment-method-index', TenantPaymentMethodIndex)

Vue.component('inventory-index', InventoryIndex)
Vue.component('inventory-transfers-index', InventoryTransfersIndex)
Vue.component('warehouses-index', WarehousesIndex)
Vue.component('tenant-report-kardex-index', TenantReportKardexIndex)
Vue.component('tenant-inventories-form', TenantInventoriesForm)
Vue.component('tenant-expenses-index', TenantExpensesIndex)
Vue.component('tenant-expenses-form', TenantExpensesForm)
Vue.component('tenant-account-export', TenantAccountExport)
Vue.component('tenant-account-summary-report', TenantAccountSummaryReport)
Vue.component('tenant-account-format', TenantAccountFormat)
Vue.component('tenant-company-accounts', TenantCompanyAccounts)
Vue.component('tenant-ledger-accounts', TenantLedgerAccounts)

Vue.component('inventory-review-index', InventoryReviewIndex)
Vue.component('tenant-inventory-report', TenantInventoryReport)

Vue.component('tenant-inventory-color-index', TenantInventoryColorIndex)
Vue.component('tenant-inventory-item-units-per-package-index', TenantInventoryItemUnitsPerPackageIndex)
Vue.component('tenant-inventory-item-units-business', TenantInventoryItemUnitsBusiness)
Vue.component('tenant-inventory-item-package-measurements', TenantInventoryItemPackageMeasurements)
Vue.component('tenant-inventory-mold-cavities', TenantInventoryMoldCavities)
Vue.component('tenant-inventory-mold-property', TenantInventoryMoldProperty)

Vue.component('tenant-inventory-size-property', TenantInventorySizeProperty)
Vue.component('tenant-inventory-item-status', TenantInventoryItemStatus)
Vue.component('tenant-inventory-item-product-family', TenantInventoryItemProductFamily)
Vue.component('tenant-inventory-extra-info-list', TenantInventoryExtraInfoList)

Vue.component('tenant-inventory-devolutions-index', TenantInventoryDevolutionsIndex)
Vue.component('tenant-inventory-devolutions-form', TenantInventoryDevolutionsForm)

Vue.component('tenant-documents-not-sent', TenantDocumentsNotSent)
Vue.component('tenant-report-purchases-index', TenantReportPurchasesIndex)
Vue.component('tenant-report-documents-index', TenantReportDocumentsIndex)
Vue.component('tenant-state-account-index', TenantStateAccountIndex)
Vue.component('tenant-report-customers-index', TenantReportCustomersIndex)
Vue.component('tenant-report-items-index', TenantReportItemsIndex)
Vue.component('tenant-report-items-extra-index', TenantReportItemsExtraIndex)

Vue.component('tenant-report-download-tray-index', TenantReportDownloadTrayIndex)

Vue.component('tenant-report-guide-index', TenantReportGuideIndex)
Vue.component('tenant-report-sale_notes-index', TenantReportSaleNotesIndex)
Vue.component('tenant-report-quotations-index', TenantReportQuotationsIndex)
Vue.component('tenant-report-cash-index', TenantReportCashIndex)
Vue.component('tenant-index-configuration', TenantIndexConfiguration)
Vue.component('tenant-report-document_hotels-index', TenantReportDocumentHotelsIndex)
Vue.component('tenant-report_hotels-index', TenantReportHotelsIndex)
Vue.component('tenant-report-commercial_analysis-index', TenantReportCommercialAnalysisIndex)
Vue.component('tenant-offline-configurations-index', TenantOfflineConfigurationsIndex)
Vue.component('tenant-series-configurations-index', TenantSeriesConfigurationsIndex)
Vue.component('tenant-validate-documents-index', TenantValidateDocumentsIndex)
Vue.component('tenant-report-document-detractions-index', TenantReportDocumentDetractionsIndex)
Vue.component('tenant-report-commissions-index', TenantReportCommissionsIndex)
Vue.component('tenant-report-order-notes-consolidated-index', TenantReportOrderNotesConsolidatedIndex)
Vue.component('tenant-report-general-items-index', TenantReportGeneralItemsIndex)
Vue.component('tenant-report-order-notes-general-index', TenantReportOrderNotesGeneralIndex)
Vue.component('tenant-report-sales-consolidated-index', TenantReportSalesConsolidatedIndex)
Vue.component('tenant-report-user-commissions-index', TenantReportUserCommissionsIndex)
Vue.component('tenant-report-fixed-asset-purchases-index', TenantReportFixedAssetPurchasesIndex)
Vue.component('tenant-report-massive-downloads-index', TenantReportMassiveDownloadsIndex)
Vue.component('tenant-documents-regularize-shipping', TenantDocumentsRegularizeShipping)
Vue.component('tenant-report-commissions-detail-index', TenantReportCommissionsDetailIndex)

Vue.component('tenant-report-tips-index', TenantReportTipsIndex)

Vue.component('tenant-categories-index', TenantCategoriesIndex)
Vue.component('tenant-brands-index', TenantBrandsIndex)
Vue.component('tenant-zone-index', TenantZoneIndex)
Vue.component('tenant-incentives-index', TenantIncentivesIndex)
Vue.component('tenant-item-lots-index', TenantItemLotsIndex)

Vue.component('tenant-ecommerce-configuration-info', TenantEcommerceConfigurationInfo)
Vue.component('tenant-ecommerce-configuration-culqi', TenantEcommerceConfigurationCulqi)
Vue.component('tenant-ecommerce-configuration-paypal', TenantEcommerceConfigurationPaypal)
Vue.component('tenant-ecommerce-configuration-logo', TenantEcommerceConfigurationLogo)
Vue.component('tenant-ecommerce-configuration-social', TenantEcommerceConfigurationSocial)
Vue.component('tenant-ecommerce-configuration-tag', TenantEcommerceConfigurationTag)
Vue.component('tenant-ecommerce-item-sets-index', TenantEcommerceItemSetsIndex)
Vue.component('tenant-ecommerce-configuration-links', TenantEcommerceConfigurationLinks)
Vue.component('tenant-ecommerce-configuration-color', TenantEcommerceConfigurationColor)

Vue.component('tenant-purchase-quotations-index', TenantPurchaseQuotationsIndex)
Vue.component('tenant-purchase-quotations-form', TenantPurchaseQuotationsForm)

Vue.component('tenant-purchase-orders-index', TenantPurchaseOrdersIndex)
Vue.component('tenant-purchase-orders-form', TenantPurchaseOrdersForm)
Vue.component('tenant-purchase-orders-generate', TenantPurchaseOrdersGenerate)

Vue.component('moves-index', MovesIndex)
Vue.component('inventory-form-masive', InventoryFormMasive)

Vue.component('tenant-report-kardex-master', TenantReportKardexMaster)
Vue.component('tenant-report-kardex-lots', TenantReportKardexLots)
Vue.component('tenant-report-kardex-series', TenantReportKardexSeries)

Vue.component('tenant-order-notes-index', TenantOrderNotesIndex)
Vue.component('tenant-order-notes-form', TenantOrderNotesForm)
Vue.component('tenant-order-notes-edit', TenantOrderNotesEdit)
Vue.component('tenant-report-valued-kardex', TenantReportValuedKardex)
Vue.component('tenant-mitiendape-config', TenantMitiendapeConfig)

// Finance
Vue.component('tenant-finance-global-payments-index', TenantFinanceGlobalPaymentsIndex)
Vue.component('tenant-finance-balance-index', TenantFinanceBalanceIndex)
Vue.component('tenant-finance-modal-transfer-between-accounts', TenantFinanceModalTransferBetweenAccounts)

Vue.component('tenant-finance-payment-method-types-index', TenantFinancePaymentMethodTypesIndex)
Vue.component('tenant-finance-unpaid-index', TenantFinanceUnpaidIndex)
Vue.component('tenant-finance-to-pay-index', TenantFinanceToPayIndex)
Vue.component('tenant-finance-income-index', TenantFinanceIncomeIndex)
Vue.component('tenant-finance-income-form', TenantFinanceIncomeForm)
Vue.component('tenant-income-types-index', TenantIncomeTypesIndex)
Vue.component('tenant-income-reasons-index', TenantIncomeReasonsIndex)
Vue.component('tenant-finance-movements-index', TenantFinanceMovementsIndex)

// Sale
Vue.component('tenant-sale-opportunities-index', TenantSaleOpportunitiesIndex)
Vue.component('tenant-sale-opportunities-form', TenantSaleOpportunitiesForm)
Vue.component('tenant-payment-method-types-index', TenantPaymentMethodTypesIndex)
Vue.component('tenant-contracts-index', TenantContractsIndex)
Vue.component('tenant-contracts-form', TenantContractsForm)
Vue.component('tenant-production-orders-index', TenantProductionOrdersIndex)
Vue.component('tenant-agents-index', TenantAgentsIndex)

// Item
Vue.component('tenant-web-platforms-index', TenantWebPlatformsIndex)
Vue.component('tenant-item-detail-index', TenantItemDetailIndex)

// Technical Services
Vue.component('tenant-technical-services-index', TenantTechnicalServicesIndex)
Vue.component('tenant-user-commissions-index', TenantUserCommissionsIndex)
Vue.component('tenant-pending-account-commissions-index', TenantPendingAccountCommissionsIndex)

// Purchase
Vue.component('tenant-fixed-asset-items-index', TenantFixedAssetItemsIndex)
Vue.component('tenant-fixed-asset-purchases-index', TenantFixedAssetPurchasesIndex)
Vue.component('tenant-fixed-asset-purchases-form', TenantFixedAssetPurchasesForm)

// Expense
Vue.component('tenant-expense-types-index', TenantExpenseTypesIndex)
Vue.component('tenant-expense-reasons-index', TenantExpenseReasonsIndex)
Vue.component('tenant-expense-method-types-index', TenantExpenseMethodTypesIndex)

Vue.component('tenant-drivers-index', TenantDriversIndex)
Vue.component('tenant-dispatchers-index', TenantDispatchersIndex)
Vue.component('tenant-transports-index', TenantTransportsIndex)
Vue.component('tenant-origin_addresses-index', TenantOriginAddressesIndex)
Vue.component('tenant-dispatch-addresses-index', TenantDispatchAddressesIndex)

Vue.component('tenant-order-forms-index', TenantOrderFormsIndex)
Vue.component('tenant-order-forms-form', TenantOrderFormsForm)

Vue.component('tenant-report-sales-by-brand-index', TenantReportSaleByBrand);
Vue.component('tenant-multi-users-change-client', TenantMultiUsersChangeClient)

// Hoteles
Vue.component('tenant-hotel-rates', TenantHotelRates)
Vue.component('tenant-hotel-categories', TenantHotelCategories)
Vue.component('tenant-hotel-floors', TenantHotelFloors)
Vue.component('tenant-hotel-rooms', TenantHotelRooms)
Vue.component('tenant-hotel-reception', TenantHotelReception)
Vue.component('tenant-hotel-sucursale', TenantHotelSucursale)
Vue.component('tenant-hotel-rent', TenantHotelRent)
Vue.component('tenant-hotel-rent-add-product', TenantHotelRentAddProduct)
Vue.component('tenant-hotel-rent-checkout', TenantHotelRentCheckout)

// Trámite documentario
Vue.component('tenant-documentary-offices', TenantDocumentaryOffices)
Vue.component('tenant-documentary-status', TenantDocumentaryStatus)
Vue.component('tenant-documentary-processes', TenantDocumentaryProcesses)
Vue.component('tenant-documentary-documents', TenantDocumentaryDocuments)
Vue.component('tenant-documentary-actions', TenantDocumentaryActions)
Vue.component('tenant-documentary-files', TenantDocumentaryFiles)
Vue.component('tenant-documentary-requirements', TenantDocumentaryRequirements)
Vue.component('tenant-documentary-statistic', TenantDocumentaryStatistic)

// Trámite documentario Simplificado
Vue.component('tenant-documentary-files-simplify', TenantDocumentaryFilesSimplify)
Vue.component('tenant-documentary-files-simplify-form', TenantDocumentaryFilesSimplifyForm)

// apiperu input
Vue.component('x-input-service', XInputService)

Vue.component('tenant-items-ecommerce-index', TenantItemsEcommerceIndex)
Vue.component('tenant-ecommerce-cart', TenantEcommerceCart)
Vue.component('tenant-tags-index', TenantTagsIndex)
Vue.component('tenant-promotions-index', TenantPromotionsIndex)

Vue.component('tenant-item-sets-index', TenantItemSetsIndex)
Vue.component('tenant-person-types-index', TenantPersonTypesIndex)

Vue.component('tenant-orders-index', TenantOrdersIndex)

// Cuenta
Vue.component('tenant-account-payment-index', TenantAccountPaymentIndex)
Vue.component('tenant-account-configuration-index', TenantAccountConfigurationIndex)

// Login
Vue.component('tenant-login-page', TenantLoginPage)

// Digemid
Vue.component('tenant-digemid-index', TenantDigemidIndex)

// Suscription
Vue.component('tenant-suscription-client-index', TenantSuscriptionClientIndex)
Vue.component('tenant-suscription-plans-index', TenantSuscriptionPlansIndex)
Vue.component('tenant-suscription-payments-index', TenantSuscriptionPaymentsIndex)
Vue.component('data-table-payment-receipt', DataTablePaymentReceipt)
Vue.component('tenant-index-payment-receipt', TenantIndexPaymentReceipt)

// Suscription extras
Vue.component('tenant-suscription-grades-index', TenantSuscriptionGradesIndex)
Vue.component('tenant-suscription-sections-index', TenantSuscriptionSectionsIndex)

// Full Suscription
Vue.component('tenant-full-suscription-client-index', TenantFullSuscriptionClientIndex)
Vue.component('tenant-full-suscription-plans-index', TenantFullSuscriptionPlansIndex)
Vue.component('tenant-full-suscription-payments-index', TenantFullSuscriptionPaymentsIndex)
Vue.component('tenant-full-suscription-index-payment-receipt', TenantFullSuscriptionIndexPaymentReceipt)

// Bank loans
Vue.component('tenant-bankloans-index', TenantBankloansIndex)
Vue.component('tenant-bankloans-form', TenantBankloansForm)

// Producción
Vue.component('tenant-mill-index', TenantMillIndex)
Vue.component('tenant-mill-form', TenantMillForm)
Vue.component('tenant-machine-index', TenantMachineIndex)
Vue.component('tenant-machine-type-index', TenantMachineTypeIndex)
Vue.component('tenant-machine-form', TenantMachineForm)
Vue.component('tenant-machine-type-form', TenantMachineTypeForm)
Vue.component('tenant-workers-index', TenantWorkersIndex)
Vue.component('tenant-production-index', TenantProductionIndex)
Vue.component('tenant-production-form', TenantProductionForm)
Vue.component('tenant-packaging-index', TenantPackagingIndex)
Vue.component('tenant-packaging-form', TenantPackagingForm)

// Restaurante
Vue.component('tenant-restaurant-list-items', TenantRestaurantListItems)
Vue.component('tenant-restaurant-promotions-index', TenantRestaurantPromotionsIndex)
Vue.component('tenant-restaurant-orders-index', TenantRestaurantOrdersIndex)
Vue.component('tenant-restaurant-cash-index', TenantRestaurantCashIndex)
Vue.component('tenant-restaurant-cash-filter-pos', TenantRestaurantCashFilterPos)
Vue.component('tenant-restaurant-configuration', TenantRestaurantConfiguration)
Vue.component('tenant-restaurant-supplies-index', TenantRestaurantSuppliesIndex)
Vue.component('tenant-restaurant-modifier-groups-index', TenantRestaurantModifierGroupsIndex)
Vue.component('tenant-restaurant-modifier-groups-form', TenantRestaurantModifierGroupsForm)

// Pagos
Vue.component('tenant-payment-configurations-index', TenantPaymentConfigurationsIndex)
Vue.component('tenant-public-payment-links-index', TenantPublicPaymentLinksIndex)
Vue.component('tenant-payment-links-index', TenantPaymentLinksIndex)

// Mobile App
Vue.component('tenant-mobile-app-configuration', TenantMobileAppConfiguration)
Vue.component('tenant-mobile-app-permissions', TenantMobileAppPermissions)

// LevelAccess
Vue.component('tenant-system-activity-logs-generals-index', TenantSystemActivityLogsGeneralsIndex)
Vue.component('tenant-system-activity-logs-transactions-index', TenantSystemActivityLogsTransactionsIndex)
Vue.component('tenant-remember-change-password', TenantRememberChangePassword)
Vue.component('tenant-report-pending-account-commissions-index', TenantReportPendingAccountCommissionsIndex)
Vue.component('tenant-item-editor-tag', TenatnEditorTag)