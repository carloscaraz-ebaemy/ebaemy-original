/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `abandoned_carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `abandoned_carts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `items` json NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `item_count` smallint unsigned NOT NULL DEFAULT '0',
  `customer_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recovered_at` timestamp NULL DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `abandoned_carts_session_token_recovered_at_index` (`session_token`,`recovered_at`),
  KEY `abandoned_carts_expires_at_index` (`expires_at`),
  KEY `abandoned_carts_session_token_index` (`session_token`),
  KEY `abandoned_carts_customer_email_index` (`customer_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_modules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_menu` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_modules_value_unique` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_id` int unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_accounts_bank_id_foreign` (`bank_id`),
  KEY `bank_accounts_currency_type_id_foreign` (`currency_type_id`),
  CONSTRAINT `bank_accounts_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`),
  CONSTRAINT `bank_accounts_currency_type_id_foreign` FOREIGN KEY (`currency_type_id`) REFERENCES `cat_currency_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `recommended_theme_id` bigint unsigned DEFAULT NULL,
  `suggested_categories` json DEFAULT NULL,
  `required_fields` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `card_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `card_brands` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_affectation_igv_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_affectation_igv_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `exportation` tinyint(1) DEFAULT NULL,
  `free` tinyint(1) DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_affectation_igv_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_attribute_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_attribute_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_attribute_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_charge_discount_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_charge_discount_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `base` tinyint(1) NOT NULL,
  `level` enum('item','global') COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('discount','charge') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_charge_discount_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_currency_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_currency_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `symbol` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_currency_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_document_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `short` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_document_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_identity_document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_identity_document_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_identity_document_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_legend_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_legend_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_legend_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_note_credit_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_note_credit_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_note_credit_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_note_debit_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_note_debit_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_note_debit_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_operation_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_operation_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `exportation` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_operation_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_other_tax_concept_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_other_tax_concept_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_other_tax_concept_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_payment_method_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_payment_method_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_payment_method_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_perception_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_perception_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `percentage` decimal(10,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_perception_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_price_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_price_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_price_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_related_documents_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_related_documents_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_related_documents_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_related_tax_document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_related_tax_document_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_related_tax_document_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_retention_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_retention_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `percentage` decimal(10,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_retention_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_summary_status_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_summary_status_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_summary_status_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_system_isc_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_system_isc_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_system_isc_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_transfer_reason_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_transfer_reason_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_transfer_reason_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_transport_mode_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_transport_mode_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_transport_mode_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cat_unit_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cat_unit_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `symbol` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_unit_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned NOT NULL,
  `date_of_payment` date NOT NULL,
  `payment_method_type_id` int unsigned NOT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  `card_brand_id` int unsigned DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment` decimal(12,2) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_payments_client_id_foreign` (`client_id`),
  KEY `client_payments_card_brand_id_foreign` (`card_brand_id`),
  KEY `client_payments_payment_method_type_id_foreign` (`payment_method_type_id`),
  CONSTRAINT `client_payments_card_brand_id_foreign` FOREIGN KEY (`card_brand_id`) REFERENCES `card_brands` (`id`),
  CONSTRAINT `client_payments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_payments_payment_method_type_id_foreign` FOREIGN KEY (`payment_method_type_id`) REFERENCES `payment_method_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `hostname_id` bigint unsigned DEFAULT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_ws` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `locked_users` tinyint(1) NOT NULL DEFAULT '0',
  `locked_tenant` tinyint(1) NOT NULL DEFAULT '0',
  `locked_emission` tinyint(1) NOT NULL DEFAULT '0',
  `restrict_sales_limit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'habilitar restricción de límite de ventas mensual',
  `locked_create_establishments` tinyint(1) NOT NULL DEFAULT '0',
  `plan_id` int unsigned NOT NULL,
  `plan_period_id` int unsigned NOT NULL DEFAULT '1',
  `price` double(8,2) DEFAULT NULL,
  `from_guest_register` tinyint(1) NOT NULL DEFAULT '0',
  `enable_list_product` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'habilitar listado de precios de productos',
  `smtp_encryption` text COLLATE utf8mb4_unicode_ci COMMENT 'Tipo de cifrado de correo',
  `smtp_password` text COLLATE utf8mb4_unicode_ci COMMENT 'contraseña de usuario para el envio de correo',
  `smtp_user` text COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de usuario para el envio de correo',
  `smtp_port` int unsigned NOT NULL DEFAULT '0' COMMENT 'Puerto de correo del cliente',
  `smtp_host` text COLLATE utf8mb4_unicode_ci COMMENT 'Host de correo del cliente',
  `start_billing_cycle` date DEFAULT NULL,
  `ending_billing_cycle` date DEFAULT NULL,
  `restore_dbname_bkdemo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `restore_type_bkdemo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled_cron_restore_bkdemo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_hostname_id_foreign` (`hostname_id`),
  KEY `clients_plan_id_foreign` (`plan_id`),
  KEY `clients_plan_period_id_foreign` (`plan_period_id`),
  CONSTRAINT `clients_hostname_id_foreign` FOREIGN KEY (`hostname_id`) REFERENCES `hostnames` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clients_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`),
  CONSTRAINT `clients_plan_period_id_foreign` FOREIGN KEY (`plan_period_id`) REFERENCES `plan_periods` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configurations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `locked_admin` tinyint(1) NOT NULL DEFAULT '0',
  `certificate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soap_send_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT '01',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT '01',
  `soap_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soap_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soap_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_public_culqui` text COLLATE utf8mb4_unicode_ci,
  `token_private_culqui` text COLLATE utf8mb4_unicode_ci,
  `url_apiruc` text COLLATE utf8mb4_unicode_ci,
  `token_apiruc` text COLLATE utf8mb4_unicode_ci,
  `use_login_global` tinyint(1) NOT NULL DEFAULT '0',
  `enable_guest_register` tinyint(1) NOT NULL DEFAULT '1',
  `login` text COLLATE utf8mb4_unicode_ci,
  `login_bg_color` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rgb(248, 248, 248)' COMMENT 'Color de fondo del panel de login',
  `visual` json DEFAULT NULL,
  `apk_url` text COLLATE utf8mb4_unicode_ci,
  `enable_whatsapp` tinyint(1) DEFAULT '1',
  `regex_password_client` tinyint(1) NOT NULL DEFAULT '0',
  `tenant_show_ads` tinyint(1) NOT NULL DEFAULT '0',
  `tenant_image_ads` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_encryption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_msg` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Hola @variable_nombre \nEste es un recordatorio para informar que tiene un pago pendiente por sus servicios \nPlan *@variable_plan*\n- Monto: *@variable_precios*\n- Vence: *@variable_fecha_vencimiento*',
  `active_cron` tinyint(1) NOT NULL DEFAULT '0',
  `send_notification_cron` tinyint(1) NOT NULL DEFAULT '0',
  `day_before_due` int NOT NULL DEFAULT '3',
  `hour_generate_payment_order` time NOT NULL DEFAULT '09:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `domain_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hostname_id` bigint unsigned NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` enum('dns_txt','dns_cname','file') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dns_cname',
  `verification_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','verified','failed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `attempts` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `domain_verifications_domain_index` (`domain`),
  KEY `domain_verifications_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ecommerce_modes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_modes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `default_features` json DEFAULT NULL,
  `default_settings` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ecommerce_modes_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_rates` (
  `date` date NOT NULL,
  `buy` decimal(13,3) NOT NULL,
  `sell` decimal(13,3) NOT NULL,
  `date_original` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `features` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'module',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `features_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `flash_sale_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `flash_price` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flash_sale_items_flash_sale_id_item_id_unique` (`flash_sale_id`,`item_id`),
  CONSTRAINT `flash_sale_items_flash_sale_id_foreign` FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `groups_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_resources` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cpu_percent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memory_total` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memory_free` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memory_used` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hostnames`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hostnames` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fqdn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `redirect_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `force_https` tinyint(1) NOT NULL DEFAULT '0',
  `under_maintenance_since` timestamp NULL DEFAULT NULL,
  `website_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostnames_fqdn_unique` (`fqdn`),
  KEY `hostnames_website_id_foreign` (`website_id`),
  CONSTRAINT `hostnames_website_id_foreign` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `item_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_types` (
  `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `item_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batching_trays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batching_trays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_batch_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generated_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_batching_trays_job_batch_id_foreign` (`job_batch_id`),
  CONSTRAINT `job_batching_trays_job_batch_id_foreign` FOREIGN KEY (`job_batch_id`) REFERENCES `job_batches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `massive_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `massive_invoices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `ruc_emisor` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_comprobante` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serie_comprobante` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruc` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `correo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moneda` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `forma_pago` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `orden_compra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `incluye_igv` tinyint(1) NOT NULL DEFAULT '1',
  `incluye_detraccion` tinyint(1) NOT NULL DEFAULT '0',
  `porcentaje_detraccion` decimal(10,2) DEFAULT NULL,
  `servicio_detraccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion_producto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `unidad_medida` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_afectacion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `nota` text COLLATE utf8mb4_unicode_ci,
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_link` text COLLATE utf8mb4_unicode_ci,
  `xml_link` text COLLATE utf8mb4_unicode_ci,
  `cdr_link` text COLLATE utf8mb4_unicode_ci,
  `estado_sunat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensaje_sunat` text COLLATE utf8mb4_unicode_ci,
  `total_gravado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_igv` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_venta` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_level_client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_level_client` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned NOT NULL,
  `module_id` int unsigned NOT NULL,
  `module_level_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `module_level_client_client_id_foreign` (`client_id`),
  KEY `module_level_client_module_id_foreign` (`module_id`),
  KEY `module_level_client_module_level_id_foreign` (`module_level_id`),
  CONSTRAINT `module_level_client_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_level_client_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_level_client_module_level_id_foreign` FOREIGN KEY (`module_level_id`) REFERENCES `module_levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_levels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_levels_module_id_foreign` (`module_id`),
  CONSTRAINT `module_levels_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `multi_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `multi_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `origin_client_id` int unsigned NOT NULL,
  `destination_client_id` int unsigned NOT NULL,
  `origin_user_id` int unsigned NOT NULL,
  `destination_user_id` int unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `multi_users_origin_client_id_index` (`origin_client_id`),
  KEY `multi_users_destination_client_id_index` (`destination_client_id`),
  KEY `multi_users_origin_user_id_index` (`origin_user_id`),
  KEY `multi_users_destination_user_id_index` (`destination_user_id`),
  KEY `multi_users_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_method_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_method_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_order_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_order_states` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_due` date NOT NULL,
  `notifications` int NOT NULL DEFAULT '0',
  `amount` double(8,2) NOT NULL,
  `date_of_payment` timestamp NULL DEFAULT NULL,
  `date_of_notification` timestamp NULL DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_state_id` int unsigned NOT NULL,
  `client_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_orders_order_state_id_foreign` (`order_state_id`),
  KEY `payment_orders_client_id_foreign` (`client_id`),
  CONSTRAINT `payment_orders_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_orders_order_state_id_foreign` FOREIGN KEY (`order_state_id`) REFERENCES `payment_order_states` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_features` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int unsigned NOT NULL,
  `feature_id` int unsigned NOT NULL,
  `limit` bigint unsigned DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_features_plan_id_feature_id_unique` (`plan_id`,`feature_id`),
  KEY `plan_features_feature_id_foreign` (`feature_id`),
  CONSTRAINT `plan_features_feature_id_foreign` FOREIGN KEY (`feature_id`) REFERENCES `features` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plan_features_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_periods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `months` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing` double NOT NULL,
  `limit_users` bigint NOT NULL,
  `limit_documents` bigint NOT NULL,
  `include_sale_notes_limit_documents` tinyint(1) NOT NULL DEFAULT '0',
  `plan_documents` json NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `include_sale_notes_sales_limit` tinyint(1) NOT NULL DEFAULT '0',
  `sales_limit` decimal(22,2) NOT NULL DEFAULT '0.00',
  `sales_unlimited` tinyint(1) NOT NULL DEFAULT '1',
  `establishments_limit` bigint NOT NULL DEFAULT '0',
  `establishments_unlimited` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurant_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurant_partners` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gitlab_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restaurant_partners_email_unique` (`email`),
  UNIQUE KEY `restaurant_partners_gitlab_user_unique` (`gitlab_user`),
  UNIQUE KEY `restaurant_partners_domain_unique` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `soap_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `soap_types` (
  `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `soap_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `state_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `state_types` (
  `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `state_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `theme_installations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `theme_installations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `theme_id` bigint unsigned NOT NULL,
  `hostname_id` bigint unsigned NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0.0',
  `status` enum('active','inactive','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `custom_settings` json DEFAULT NULL,
  `license_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `theme_installations_theme_id_hostname_id_unique` (`theme_id`,`hostname_id`),
  KEY `theme_installations_hostname_id_index` (`hostname_id`),
  KEY `theme_installations_status_index` (`status`),
  CONSTRAINT `theme_installations_theme_id_foreign` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `themes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `themes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `css_template` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `preview_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_premium` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0.0',
  `author` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(8,2) NOT NULL DEFAULT '0.00',
  `default_settings` json DEFAULT NULL,
  `supported_modes` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `themes_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `track_api_peru_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `track_api_peru_services` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service` int unsigned DEFAULT '0' COMMENT 'Tipo de servicio 1 => sunat/dni, 2 => validacion_multiple_cpe, 3 => CPE, 4 => tipo_de_cambio, 5 => printer_ticket',
  `ruc` text COLLATE utf8mb4_unicode_ci COMMENT 'Ruc de la empresa que consulta',
  `client_id` int unsigned DEFAULT '0',
  `date_of_issue` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `api_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `introduction` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_api_token_unique` (`api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `websites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `websites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `managed_by_database_connection` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'References the database connection key in your database.php',
  PRIMARY KEY (`id`),
  UNIQUE KEY `websites_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `websockets_statistics_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `websockets_statistics_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `peak_connections_count` int NOT NULL,
  `websocket_messages_count` int NOT NULL,
  `api_messages_count` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0000_00_00_000000_create_websockets_statistics_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0000_00_00_000000_rename_statistics_counters',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2017_01_01_000003_tenancy_websites',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2017_01_01_000005_tenancy_hostnames',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2018_04_06_000001_tenancy_websites_needs_db_host',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2019_01_28_092812_create_plans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2019_01_29_094116_create_plan_documents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2019_01_29_170027_create_clients_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2019_02_27_165906_change_data_to_plans',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2019_07_03_094112_create_card_brands_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2019_07_03_094441_create_payment_method_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2019_07_03_100132_create_client_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2019_07_19_163317_add_locked_emission_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2019_10_09_100840_add_locked_tenant_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2019_10_09_141307_create_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2019_10_11_153451_add_locked_users_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2019_11_07_155742_create_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2019_11_14_211509_add_start_billing_cycle_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2020_02_01_131218_add_certificate_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2020_02_01_182806_add_soap_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2020_03_10_165827_add_data_module_for_finance',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2020_03_31_151819_add_phone_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2020_07_03_232125_add_culqi_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2020_07_27_184250_add_apiruc_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2020_09_07_110230_add_data_module_for_establishments_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2021_03_08_154204_add_login_settings_column_to_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2021_03_10_160908_add_extra_modules_to_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2021_03_10_170439_create_module_levels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2021_03_19_112500_create_module_level_client_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2021_03_19_201634_add_sort_column_to_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2021_03_20_110950_change_order_item_to_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2021_04_01_090115_add_levels_to_module_levels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2021_05_03_131833_add_mail_configuration',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2021_06_18_141136_add_modules_digemid',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2021_06_18_141137_add_documentary_requirements',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2021_08_20_161555_add_extra_data_item_menu',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2021_09_16_144202_add_app_to_modules',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2021_09_16_160109_add_url_apk_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2021_10_05_171912_add_configuration_module_to_admin',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2021_10_14_163406_create_history_resources_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2021_10_18_154601_add_data_purchase_settlements_to_module_levels',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2021_10_22_130040_create_app_suscription',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2021_12_10_130040_create_app_production',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2022_01_25_152340_create_app_restaurant',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2022_01_30_104230_update_token_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2022_01_30_105446_create_restaurant_partners_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2022_02_23_001946_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2022_02_25_001413_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2022_03_11_120508_add_trace_to_api_peru_service',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2022_03_11_125431_add_attr_to_restaurant_partners',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2022_03_31_132605_create_app_pos_garage',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2022_04_19_101832_add_default_to_client_id_track',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2022_04_30_124731_addwhatsapp_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2022_05_07_165152_update_user_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2022_05_08_130040_create_app_full_suscription',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2022_05_09_212031_register_app_generate_link_to_modules',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2022_06_15_091833_add_force_unlocked_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2022_07_07_110727_add_module_app_2_generator_to_modules',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2022_07_07_160621_create_app_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2022_07_08_102447_add_data_configuration_to_app_modules',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2022_07_11_175937_add_data_quotation_to_app_modules',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2022_09_01_095559_add_regex_password_client_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2022_10_04_150900_change_description_value_to_module_levels',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2022_11_25_132556_add_tenant_show_ads_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2022_12_08_225820_add_default_api_token_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2022_12_23_100215_add_establishments_limit_to_plans',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2022_12_23_114520_add_locked_create_establishments_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2022_12_23_164914_add_sales_limit_to_plans',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2022_12_23_172757_add_restrict_sales_limit_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2022_12_26_150024_add_include_sale_notes_sales_limit_to_plans',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2023_03_15_100000_add_include_sale_notes_limit_documents_to_plans',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2023_09_01_143124_create_multi_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2024_04_16_104436_add_permission_list_product_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2024_12_16_141449_update_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2024_12_16_141507_update_modules_levels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_01_03_124701_add_enabled_cron_restore_bkdemo_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_06_10_092834_create_massive_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_06_16_094905_add_ruc_emisor_to_massive_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_06_16_202738_fix_table_massive_invoice',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_08_04_104053_add_from_guest_register_to_clients_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_08_11_133547_add_enable_guest_register_to_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_08_14_164321_add_columns_emails_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_09_02_172748_add_login_bg_color_to_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_09_10_155019_add_whatsapp_number_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2025_09_10_155732_add_introduction_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2025_09_10_165618_add_address_contact_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2025_09_24_131935_create_plan_periods_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2025_09_24_132658_add_column_price_and_periods_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_09_24_171503_add_column_qr_api_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_09_25_155914_create_payment_order_state_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2025_09_25_155954_create_payment_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2025_09_25_173457_add_column_cron_to_configurations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2025_10_06_162220_add_visual_to_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2025_10_13_174858_update_login_json_in_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2025_10_14_103615_add_padding_in_form_to_login_in_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_10_16_181617_add_column_client_name_and_phonews_to_clients',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_11_11_151513_create_job_batches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_11_11_171612_create_jobs_batching_tray',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_03_15_000001_add_logistic_module_levels',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_03_16_000003_create_flash_sales_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2018_00_00_000000_tenant_catalogs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2018_01_00_000000_tenant_system_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2018_01_01_000000_tenant_users_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2018_01_01_000001_tenant_password_resets_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2018_01_01_000004_tenant_modules_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2018_01_01_000006_tenant_module_user_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2018_01_01_000013_tenant_location_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2018_05_16_000800_tenant_companies_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2018_05_16_000810_tenant_establishments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2018_05_16_000900_tenant_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2018_05_17_000002_tenant_series_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2018_05_17_000101_tenant_persons_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2018_06_17_000001_tenant_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2018_06_17_000002_tenant_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2018_06_17_000005_tenant_document_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2018_06_19_000020_tenant_invoices_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2018_06_19_000021_tenant_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2018_06_21_000002_tenant_summaries_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2018_06_21_000003_tenant_summary_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2018_06_21_000004_tenant_voided_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2018_06_21_000005_tenant_voided_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2018_06_22_000022_tenant_retentions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2018_06_22_000023_tenant_retention_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2018_06_22_000024_tenant_perceptions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2018_06_22_000026_tenant_perception_details_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2018_07_22_000024_tenant_dispatches_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2018_07_22_000030_tenant_dispatch_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2019_02_12_000002_tenant_purchases_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2019_02_12_000005_tenant_purchase_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2019_02_12_000007_tenant_kardex_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2019_02_13_150334_tenant_add_cront_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2019_02_13_175903_tenant_change_type_column_quantity_to_document_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2019_02_13_190940_tenant_add_information_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2019_02_14_100645_tenant_add_establishment_id_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2019_02_19_150123_tenant_change_columns_to_exchange_rates',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2019_02_25_074400_tenant_add_data_json_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2019_02_26_084922_tenant_change_columns_offline_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2019_02_27_093803_tenant_add_send_online_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2019_02_27_150015_create_tasks_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2019_02_28_100503_tenant_add_calculate_quantity_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2019_02_28_154355_tenant_delete_unique_class_to_tasks',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2019_02_28_215128_tenant_change_decimal_column_quantity_to_document_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2019_03_01_100028_tenant_change_decimal_column_stock_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2019_03_01_100550_tenant_change_type_column_quantity_to_purchase_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2019_03_01_100938_tenant_change_type_column_quantity_to_kardex',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2019_03_01_163938_tenant_add_locked_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2019_03_16_095539_tenant_quotations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2019_03_16_095620_tenant_quotation_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2019_03_19_155345_tenant_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2019_03_19_155546_tenant_sale_note_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2019_03_20_152101_tenant_add_sale_note_id_to_kardex',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2019_03_22_095723_tenant_change_nullable_colum_type_to_kardex',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2019_03_23_114011_tenant_warehouses_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2019_03_23_134515_add_warehouse_id_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2019_03_23_154011_tenant_item_warehouse_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2019_03_25_120709_tenant_inventories_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2019_03_26_120709_tenant_inventory_kardex_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2019_03_27_104823_tenant_add_record_to_warehouses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2019_03_28_102106_tenant_add_quotation_id_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2019_03_28_112106_tenant_add_foreign_establishment_id_to_warehouses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2019_03_29_100403_tenant_add_foreign_to_item_warehouse',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2019_03_29_100413_tenant_add_foreign_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2019_03_29_100433_tenant_add_foreign_to_inventory_kardex',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2019_03_29_100503_tenant_add_has_igv_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2019_04_24_151702_tenant_add_stock_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2019_04_26_105302_tenant_add_contingency_to_series',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2019_04_29_111659_tenant_inventory_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2019_04_29_164935_tenant_add_record_to_inventory_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2019_04_30_140509_tenant_add_type_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2019_05_06_174801_tenant_change_column_type_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2019_05_07_160954_tenant_item_unit_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2019_05_10_172128_tenant_add_price_default_to_item_unit_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2019_05_13_145524_tenant_fix_error_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2019_05_14_091046_tenant_description_to_item_unit_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2019_05_27_185903_tenant_change_type_column_qr_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2019_05_28_172128_tenant_add_percentage_of_profit_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2019_06_12_000005_tenant_document_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2019_06_12_000015_tenant_sale_note_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2019_06_12_172128_tenant_add_total_canceled_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2019_06_13_100503_tenant_change_unit_price_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2019_06_14_100503_tenant_person_address_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2019_06_24_122116_tenant_add_changed_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2019_07_09_141248_tenant_expense_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2019_07_09_141408_tenant_expenses_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2019_07_09_141508_tenant_expense_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2019_07_09_172826_tenant_add_perception_agent_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2019_07_10_092347_tenant_add_percentage_perception_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2019_07_10_103811_tenant_add_columns_perceptions_to_purchases',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2019_07_10_120610_tenant_add_columns_purchases_to_payment_method_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2019_07_10_123325_tenant_purchase_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2019_07_10_140636_tenant_add_date_of_due_to_purchases',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2019_07_10_151332_tenant_add_warehouse_id_to_purchase_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2019_07_12_181618_tenant_add_columns_aditional_to_establishments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2019_07_19_163617_tenant_add_columns_system_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2019_07_22_094601_tenant_cash_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2019_07_22_094658_tenant_cash_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2019_07_22_094725_tenant_add_image_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2019_07_22_094803_tenant_modify_document_id_to_cash_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2019_07_22_102243_tenant_accounts_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2019_07_22_103459_tenant_add_account_id_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2019_07_23_175808_tenant_modify_decimals_to_item_unit_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2019_07_24_162847_tenant_add_data_module_for_pos',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2019_07_25_144505_tenant_add_ose_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2019_07_27_181623_tenant_add_name_name2_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2019_07_31_165537_tenant_add_subtotal_account_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2019_08_01_002801_tenant_add_status_to_item',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2019_08_01_005553_tenant_add_status_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2019_08_01_011908_tenant_add_description_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2019_08_01_095140_tenant_add_status_to_bank_accounts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2019_08_01_101234_tenant_add_active_to_banks',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2019_08_01_102419_tenant_add_active_to_card_brands',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2019_08_01_105836_tenant_delete_subtotal_account_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2019_08_01_110045_tenant_company_accounts_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2019_08_03_162431_tenant_add_data_modules_for_dashboard',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2019_08_05_130830_tenant_add_index_external_id_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2019_08_12_125016_tenant_up_unit_price_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2019_08_13_082230_tenant_add_column_limit_user_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2019_08_16_153648_tenant_add_more_decimal_column_stock_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2019_08_16_161756_tenant_add_total_plastic_bag_taxes_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2019_08_16_161824_tenant_add_total_plastic_bag_taxes_to_document_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2019_08_16_161854_tenant_add_amount_plastic_bag_taxes_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2019_08_19_112540_tenant_add_quotation_id_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2019_08_19_115344_tenant_add_sale_note_id_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2019_08_19_124610_tenant_add_state_condition_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2019_08_20_121326_tenant_add_indexes_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2019_08_20_144511_tenant_add_indexes_to_summaries',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2019_08_20_151037_tenant_add_indexes_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2019_08_21_145954_tenant_modify_name_name2_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2019_08_23_115358_tenant_add_data_accounnting_inventory_to_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2019_08_23_160411_tenant_modify_description_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2019_09_03_153427_tenant_change_nullable_column_affected_document_id_to_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2019_09_03_153656_tenant_add_data_affected_document_to_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2019_09_09_153206_tenant_add_sale_note_id_to_cash_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2019_09_09_174848_tenant_modify_columns_to_perceptions',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2019_09_09_174916_tenant_modify_columns_to_perception_details',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2019_09_10_102854_tenant_modify_series_id_to_perceptions',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2019_09_11_131559_tenant_add_apply_store_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2019_09_11_154949_tenant_expense_method_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2019_09_11_155535_tenant_expense_payments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2019_09_11_174858_tenant_expense_reasons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2019_09_11_174929_tenant_add_expense_reason_id_to_expenses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2019_09_13_112026_tenant_add_column_image_medium_and_image_small_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2019_09_15_233528_tenant_create_table_tag',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2019_09_15_233537_tenant_create_table_item_tag',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2019_09_16_121938_tenant_add_date_of_due_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2019_09_16_133219_tenant_add_data_ecommerce_to_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2019_09_16_160453_tenant_add_timestamps_to_tag',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2019_09_16_161726_tenant_add_status_to_tags',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2019_09_17_131050_tenant_add_type_client_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2019_09_17_202003_tenant_promotions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2019_09_18_152416_tenant_add_percentage_perception_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2019_09_18_160838_tenant_add_has_perception_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2019_09_30_151349_tenant_add_has_prepayment_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2019_09_30_160541_tenant_inventory_transactions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2019_09_30_160919_tenant_change_columns_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2019_10_04_092658_tenant_business_turns_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2019_10_04_094841_tenant_document_hotels_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2019_10_09_101229_tenant_add_locked_tenant_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2019_10_10_155554_tenant_add_quantity_documents_date_time_start_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2019_10_11_095050_tenant_billing_cycles_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2019_10_11_153948_tenant_add_locked_users_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2019_10_14_101501_tenant_add_column_set_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2019_10_14_102317_tenant_item_sets_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2019_10_14_235308_tenant_orders_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2019_10_15_122633_tenant_person_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2019_10_15_123201_tenant_add_person_types_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2019_10_16_001052_tenant_add_identity_and_number_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2019_10_17_100307_tenant_add_data_to_document_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2019_10_18_150004_tenant_categories_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2019_10_18_150414_tenant_brands_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2019_10_18_150604_tenant_brand_category_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2019_10_18_194622_tenant_add_soft_delete_to_purchases',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2019_10_20_190039_tenant_add_plan_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2019_10_20_195730_tenant_add_cuenta_to_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2019_10_20_200958_tenant_account_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2019_10_22_173407_tenant_add_was_deducted_prepayment_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2019_10_24_103947_tenant_add_sunat_alternate_server_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2019_10_24_183250_tenant_add_document_id_to_orders',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2019_10_24_210806_tenant_items_rating_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2019_10_26_213130_tenant_add_logo_store_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2019_10_28_202116_tenant_add_reference_number_to_cash',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2019_11_05_113236_create_padrones_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2019_11_05_113320_create_charge_padrones_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2019_11_06_095251_tenant_add_success_shipping_status_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2019_11_06_102422_tenant_add_success_sunat_shipping_status_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (288,'2019_11_06_110606_tenant_add_success_query_status_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2019_11_06_113035_tenant_offline_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2019_11_11_102124_tenant_series_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2019_11_12_223340_tenant_add_reference_document_id_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2019_11_13_124821_tenant_add_document_type_id_to_series_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2019_11_18_154307_tenant_create_congiguration_ecommerce_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2019_11_19_113132_tenant_cat_detraction_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2019_11_20_175549_tenant_add_inventory_kardex_id_to_sale_note_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2019_11_20_221547_tenant_add_address_to_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2019_11_25_213648_tenant_add_social_to_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2019_11_29_093342_tenant_add_detraction_account_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2019_12_02_105910_tenant_add_purchase_expense_to_cash_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2019_12_02_111743_tenant_change_expense_reason_id_to_expenses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (301,'2019_12_02_111837_tenant_add_soap_type_id_to_expenses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2019_12_02_152128_tenant_add_date_of_due_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2019_12_02_161856_tenant_add_data_to_payment_method_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2019_12_02_163726_tenant_add_payment_method_type_id_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2019_12_05_104120_tenant_fixed_columns_to_cash_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2019_12_06_114132_tenant_add_shipping_address_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2019_12_06_120917_tenant_add_commission_amount_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2019_12_11_111224_tenant_purchase_quotations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2019_12_11_112209_tenant_purchase_quotation_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2019_12_11_122830_tenant_add_reference_quotation_id_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2019_12_11_174726_tenant_purchase_orders_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2019_12_11_175353_tenant_purchase_order_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2019_12_16_103759_tenant_add_decimal_quantity_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2019_12_16_181022_tenant_add_prefix_to_purchase_orders',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2019_12_17_101130_tenant_add_purchase_order_id_to_purchases',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2019_12_19_102946_tenant_item_lots_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2019_12_19_105644_tenant_add_lot_code_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2019_12_19_141604_tenant_add_operation_amazonia_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2019_12_20_123931_tenant_module_levels_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2019_12_20_123945_tenant_module_level_user_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2019_12_23_144236_tenant_add_apply_concurrency_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2019_12_23_171335_tenant_add_columns_periods_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2019_12_24_114350_tenant_add_columns_lots_to_item_lots',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2019_12_24_123601_tenant_add_lot_code_to_purchase_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2019_12_27_111848_tenant_add_lot_code_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2019_12_30_095201_tenant_add_lots_enabled_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2020_01_03_111747_tenant_add_amount_plastic_bag_taxes_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2020_01_08_102051_tenant_add_total_canceled_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2020_01_09_094728_tenant_change_decimal_column_quantity_to_dispatch_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2020_01_09_102017_tenant_change_type_decimal_column_quantity_to_dispatch_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2020_01_09_153023_tenant_add_compact_sidebar_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2020_01_10_095143_tenant_add_cci_to_bank_accounts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2020_01_10_121518_tenant_add_warehouse_id_to_document_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2020_01_15_095621_tenant_add_change_decimal_exchange_rate_sale_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (335,'2020_01_15_100032_tenant_add_data_to_cat_document_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2020_01_15_144606_tenant_add_series_number_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2020_01_15_172447_tenant_add_paid_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2020_01_15_181229_tenant_add_plate_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2020_01_16_101424_tenant_add_detail_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2020_01_16_103741_tenant_change_decimals_unit_price_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2020_01_16_121313_tenant_add_state_to_item_lots',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2020_01_17_095233_tenant_document_transports_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2020_01_17_115328_tenant_add_plate_number_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2020_01_17_175217_tenant_add_state_type_to_expenses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2020_01_21_153921_tenant_inventories_transfer_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2020_01_21_155245_tenant_add_inventories_transfer_id_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (347,'2020_01_23_120700_tenant_drop_compact_sidebar_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (348,'2020_01_23_120830_tenant_re_create_compact_sidebar_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (349,'2020_01_23_123235_tenant_add_columns_to_document_hotels',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (350,'2020_01_27_121553_tenant_add_commission_type_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (351,'2020_01_27_150915_tenant_change_column_number_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (352,'2020_01_28_135422_tenant_add_tag_to_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (353,'2020_01_31_122444_tenant_add_config_system_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (354,'2020_02_05_124542_tenant_add_guests_to_document_hotels',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (355,'2020_02_07_164026_tenant_create_person_addresses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (356,'2020_02_10_111943_tenant_add_affectation_type_prepayment_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (357,'2020_02_11_210535_tenant_add_active_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (358,'2020_02_12_203736_tenant_add_contact_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (359,'2020_02_17_115050_tenant_add_delivery_date_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (360,'2020_02_17_141658_tenant_create_format_templates_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (361,'2020_02_17_194731_tenant_add_formats_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (362,'2020_02_17_202910_tenant_item_images_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (363,'2020_02_19_102018_tenant_order_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (364,'2020_02_19_121619_tenant_order_note_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (365,'2020_02_19_150814_tenant_add_order_note_id_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (366,'2020_02_19_150828_tenant_add_order_note_id_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (367,'2020_02_19_160926_tenant_add_reference_order_note_id_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (368,'2020_02_20_224501_tenant_add_colums_grid_items_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (369,'2020_02_21_172411_tenant_add_soap_shipping_response_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (370,'2020_02_24_213558_tenant_date_of_due_column_to_purchase_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (371,'2020_02_25_103837_tenant_add_options_pos_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (372,'2020_02_25_154338_tenant_add_change_to_document_payments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (373,'2020_02_26_201604_tenant_item_lots_group_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (374,'2020_02_26_203030_tenant_add_series_enabled_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (375,'2020_02_27_113111_tenant_add_change_to_sale_note_payments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (376,'2020_03_03_172821_tenant_add_additional_information_to_document_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (377,'2020_03_06_101730_tenant_global_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (378,'2020_03_10_165850_tenant_add_data_module_for_finance',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (379,'2020_03_11_151338_tenant_add_edit_name_product_purchase',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (380,'2020_03_13_110238_add_column_name_product_pdf',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (381,'2020_03_13_134951_add_column_name_product_pdf_update',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (382,'2020_03_13_134955_add_column_name_product_pdf_change',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (383,'2020_03_16_152939_tenant_add_indexes_to_payments_tables_for_finances',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (384,'2020_03_16_162652_tenant_add_enabled_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (385,'2020_03_20_030559_add_columna_restrict_receipt_date',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (386,'2020_03_20_174637_tenant_add_affectation_igv_type_id_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (387,'2020_03_25_173128_tenant_sale_opportunities_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (388,'2020_03_25_173442_tenant_sale_opportunity_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (389,'2020_03_26_121642_tenant_add_sale_opportunity_id_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (390,'2020_03_26_170415_tenant_sale_opportunity_files_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (391,'2020_03_27_111133_tenant_quotation_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (392,'2020_03_27_123343_tenant_add_changed_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (393,'2020_03_27_141008_tenant_add_column_visual_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (394,'2020_03_27_143825_tenant_add_account_number_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (395,'2020_03_27_150024_tenant_add_terms_condition_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (396,'2020_03_30_112859_tenant_payment_files_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (397,'2020_03_31_111522_tenant_add_customer_id_to_purchases',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (398,'2020_03_31_122445_tenant_fixed_asset_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (399,'2020_03_31_141008_tenant_add_column_show_ws_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (400,'2020_03_31_151057_tenant_fixed_asset_purchases_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (401,'2020_03_31_151323_tenant_fixed_asset_purchase_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (402,'2020_04_01_150413_tenant_add_terms_condition_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (403,'2020_04_02_124023_tenant_add_sale_opportunity_id_to_purchase_orders',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (404,'2020_04_02_151534_tenant_add_total_canceled_to_purchases',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (405,'2020_04_02_154134_tenant_contracts_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (406,'2020_04_02_154147_tenant_contract_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (407,'2020_04_02_154244_tenant_contract_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (408,'2020_04_03_111019_tenant_income_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (409,'2020_04_03_111139_tenant_income_reasons_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (410,'2020_04_03_111209_tenant_income_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (411,'2020_04_03_111703_tenant_income_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (412,'2020_04_03_124629_tenant_income_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (413,'2020_04_03_143703_tenant_insert_internal_to_soap_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (414,'2020_05_06_205001_create_status_orders_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (415,'2020_05_06_210451_tenant_add_status_orders_to_orders',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (416,'2020_05_07_123152_tenant_technical_services_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (417,'2020_05_07_164323_tenant_user_commissions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (418,'2020_05_12_131218_add_product_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (419,'2020_05_12_204311_tenant_add_cotizaction_finance_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (420,'2020_05_19_162014_tenant_add_contact_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (421,'2020_05_25_140825_tenant_add_column_plate_number_to_sales_note',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (422,'2020_05_30_132013_tenant_add_certificate_due_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (423,'2020_06_05_111820_tenant_add_line_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (424,'2020_06_10_102549_tenant_add_columns_technical_specifications_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (425,'2020_06_11_093739_tenant_add_columns_header_image_legend_footer_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (426,'2020_06_11_152155_tenant_add_purchase_orders',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (427,'2020_06_23_122822_tenant_drivers_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (428,'2020_06_23_122938_tenant_dispatchers_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (429,'2020_06_23_123012_tenant_order_forms_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (430,'2020_06_23_123126_tenant_order_form_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (431,'2020_06_24_173951_tenant_add_reference_order_form_id_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (432,'2020_07_03_124017_tenant_add_purchase_has_igv_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (433,'2020_07_08_163451_tenant_change_column_name_product_pdf_to_document_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (434,'2020_07_21_213555_tenant_add_columns_to_technical_services',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (435,'2020_07_23_153920_tenant_add_user_id_to_global_payments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (436,'2020_07_23_215548_tenant_add_img_firm_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (437,'2020_07_23_221614_tenant_add_qr_to_order_forms',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (438,'2020_07_30_101311_tenant_add_soap_shipping_response_to_summaries',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (439,'2020_07_30_105514_tenant_add_soap_shipping_response_to_voided',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (440,'2020_08_03_123426_tenant_add_secondary_license_plates_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (441,'2020_08_04_104159_tenant_add_initial_balance_to_bank_accounts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (442,'2020_08_04_123149_tenant_add_pending_amount_prepayment_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (443,'2020_08_05_104849_tenant_add_payment_method_type_id_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (444,'2020_08_05_125307_tenant_add_filename_to_purchases',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (445,'2020_08_06_101100_tenant_change_columns_delivery_date_date_of_due_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (446,'2020_08_06_120140_tenant_add_data_to_module_levels',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (447,'2020_08_20_151205_tenant_change_column_terms_condition_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (448,'2020_08_20_151856_tenant_change_column_terms_condition_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (449,'2020_08_20_172938_tenant_change_decimal_column_quantity_to_item_lots_group',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (450,'2020_08_26_142025_tenant_add_indexes_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (451,'2020_08_27_101335_tenant_add_indexes_to_item_lots',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (452,'2020_08_28_100623_tenant_change_type_column_terms_condition_to_contracts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (453,'2020_09_01_160245_tenant_add_warehouse_id_to_sale_note_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (454,'2020_09_02_103127_tenant_add_warehouse_id_to_order_note_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (455,'2020_09_02_124906_tenant_add_customer_id_to_establishments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (456,'2020_09_04_094513_tenant_add_quantity_to_item_sets',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (457,'2020_09_04_165849_tenant_active_data_to_cat_operation_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (458,'2020_09_07_110546_tenant_add_data_module_for_establishments_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (459,'2020_09_08_141513_tenant_add_regularize_shipping_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (460,'2020_09_08_142658_tenant_add_response_regularize_shipping_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (461,'2020_09_09_144419_tenant_modify_text_description_to_expense_method_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (462,'2020_09_09_151848_tenant_change_column_number_to_expenses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (463,'2020_09_09_160249_tenant_contract_state_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (464,'2020_09_09_161329_tenant_change_state_type_id_to_contracts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (465,'2020_09_11_105705_tenant_cash_transactions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (466,'2020_09_14_123539_tenant_technical_service_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (467,'2020_09_25_113553_tenant_add_data_rh_to_cat_document_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (468,'2020_09_29_110506_tenant_change_description_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (469,'2020_10_09_153402_tenant_add_default_document_type_03_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (470,'2020_10_13_095308_tenant_add_data_public_services_to_cat_document_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (471,'2020_10_13_111740_tenant_web_platforms_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (472,'2020_10_13_112548_tenant_add_destination_default_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (473,'2020_10_13_120454_tenant_add_web_platform_id_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (474,'2020_10_15_120313_tenant_active_transport_1004_to_cat_operation_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (475,'2020_10_15_173515_tenant_add_contact_phone_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (476,'2020_10_16_101845_teanant_add_data_to_module_levels',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (477,'2020_10_16_143042_tenant_add_columns_aditional_to_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (478,'2020_10_26_151606_tenant_change_columns_nullable_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (479,'2020_11_05_103808_tenant_add_seller_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (480,'2020_11_05_120923_tenant_add_payment_method_type_id_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (481,'2020_11_06_104926_tenant_add_soap_shipping_response_to_retentions',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (482,'2020_11_10_101728_tenant_change_column_name_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (483,'2020_11_12_143726_tenant_add_observation_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (484,'2020_11_12_164451_tenant_add_reference_sale_note_id_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (485,'2020_11_18_123948_tenant_add_soap_shipping_response_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (486,'2020_11_19_115728_tenant_devolution_reasons_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (487,'2020_11_19_115848_tenant_devolutions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (488,'2020_11_19_115944_tenant_devolution_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (489,'2020_11_25_115944_tenant_create_preprinted_format_templates_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (490,'2020_12_01_172908_tenant_add_reference_data_to_documents_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (491,'2020_12_03_141335_tenant_change_text_description_to_cat_document_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (492,'2020_12_31_103348_tenant_add_has_plastic_bag_taxes_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (493,'2021_01_05_145928_add_model_column_to_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (494,'2021_01_08_095018_add_barcode_column_to_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (495,'2021_01_08_103402_update_barcode_column_to_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (496,'2021_01_18_143008_change_lenght_to_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (497,'2021_01_19_111533_add_internal_code_column_to_persons_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (498,'2021_01_21_181843_add_terms_condition_sale_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (499,'2021_01_21_183856_add_terms_condition_column_to_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (500,'2021_01_26_102448_create_hotel_rates_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (501,'2021_01_26_152649_create_hotel_categories_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (502,'2021_01_26_161851_create_hotel_floors_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (503,'2021_01_26_171515_create_hotel_rooms_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (504,'2021_01_27_155235_create_hotel_room_rates_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (505,'2021_01_28_205134_create_hotel_rents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (506,'2021_01_31_163211_create_hotel_rent_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (507,'2021_02_01_102051_add_columns_to_hotel_rents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (508,'2021_02_01_112617_add_column_item_id_to_hotel_rooms_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (509,'2021_02_02_180153_add_arrears_column_to_hotel_rents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (510,'2021_02_04_215327_add_show_in_document_column_to_bank_accounts_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (511,'2021_02_08_111945_add_document_id_column_to_dispatches_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (512,'2021_02_10_153546_add_login_column_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (513,'2021_02_11_113008_change_destination_to_global_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (514,'2021_02_15_101110_add_favicon_column_to_companies_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (515,'2021_02_15_175921_add_navbar_column_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (516,'2021_02_19_165318_add_finances_column_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (517,'2021_02_25_165224_create_documentary_offices_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (518,'2021_02_25_180958_create_documentary_processes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (519,'2021_02_25_183124_create_documentary_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (520,'2021_02_26_102851_create_documentary_actions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (521,'2021_02_26_102900_create_documentary_files_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (522,'2021_03_01_120554_add_user_id_column_to_documentary_files_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (523,'2021_03_01_120931_create_documentary_file_offices_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (524,'2021_03_10_163436_add_extra_modules_to_modules_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (525,'2021_03_11_111743_add_logo_column_to_establishments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (526,'2021_03_19_204148_add_items_to_modules_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (527,'2021_03_19_221614_add_referential_information_to_quotations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (528,'2021_03_20_112202_change_order_items_to_modules_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (529,'2021_03_20_115648_add_seller_id_column_to_quotations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (530,'2021_03_20_132104_add_seller_id_column_to_contracts_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (531,'2021_03_21_165318_add_quotation_allow_seller_generate_sale_column_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (532,'2021_03_24_111209_tenant_payment_conditions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (533,'2021_03_24_111211_tenant_add_payment_condition_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (534,'2021_03_24_141211_tenant_document_fee_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (535,'2021_03_25_162827_add_purchase_order_column_to_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (536,'2021_03_31_212318_add_document_id_column_to_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (537,'2021_04_01_113520_add_levels_to_module_levels_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (538,'2021_04_05_115318_add_allow_edit_unit_price_to_seller_column_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (539,'2021_04_06_172009_create_item_warehouse_prices_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (540,'2021_04_08_175347_change_electronico_at_cat_document_type',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (541,'2021_04_17_163456_add_technical_service_id_to_cash_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (542,'2021_04_22_170915_add_template_pdf_to_establishments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (543,'2021_04_23_143433_add_edit_flag_to_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (544,'2021_04_27_105557_add_payment_type_to_payment_method',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (545,'2021_04_29_122333_add_visual_ticket_58_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (546,'2021_05_06_152551_add_due_date_to_sales_note',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (547,'2021_05_07_154207_add_soft_delete_to_document',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (548,'2021_05_07_154207_remove_soft_delete_to_document',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (549,'2021_05_12_163406_add_seller_can_create_porduct_to_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (550,'2021_05_13_120615_add_seller_can_view_balance_on_finance_to_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (551,'2021_05_27_124620_add_generate_option_to_sale_opportunities',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (552,'2021_06_03_122337_add_method_type_to_documents_fee',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (553,'2021_06_03_165209_add_serie_and_document_to_user',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (554,'2021_06_07_140759_change_format_number_to_expenses_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (555,'2021_06_09_124243_add_update_documents_by_dispaches_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (556,'2021_06_10_092525_add_pharmacy_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (557,'2021_06_15_094333_add_name_product_pdf_to_order_note_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (558,'2021_06_16_154233_add_send_auto_sunat_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (559,'2021_06_18_141048_add_modules_digemid',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (560,'2021_06_22_112855_add_fields_to_client',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (561,'2021_06_23_130016_create_digemid_catalog',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (562,'2021_06_28_125907_add_fields_to_documentary_office',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (563,'2021_06_28_142358_tenant_add_active_warehouse_prices_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (564,'2021_06_29_173743_tenant_add_allowance_charge_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (565,'2021_06_29_184419_remove_key_from_documentary',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (566,'2021_06_30_174147_create_adjust_to_docymentary_offices',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (567,'2021_06_30_175311_tenant_add_purchase_to_cash_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (568,'2021_07_01_171620_adjust_to_docymentary_procedure',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (569,'2021_07_02_133558_add_office_to_process',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (570,'2021_07_05_091229_change_data_to',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (571,'2021_07_05_094927_create_documentary_guides_number',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (572,'2021_07_05_125811_add_field_to_documentary_file',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (573,'2021_07_05_141048_add_modules_docymentary_requirements',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (574,'2021_07_05_213052_change_observation_from_file_offices',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (575,'2021_07_05_220241_add_requirements_to_documentary_file',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (576,'2021_07_06_114113_tenant_add_discount_stock_to_cat_transfer_reason_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (577,'2021_07_06_173358_tenant_add_dispatch_id_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (578,'2021_07_06_174510_add_name_pdf_to_sale_note_item',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (579,'2021_07_14_103324_add_data_affected_document',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (580,'2021_07_16_204722_add_phone_to_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (581,'2021_07_19_132554_add_days_to_client',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (582,'2021_08_03_115743_add_field_to_send_sale_note_to_other_site',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (583,'2021_08_05_143043_add_quotation_to_order_note',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (584,'2021_08_05_144042_tenant_add_search_item_by_series_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (585,'2021_08_06_094151_tenant_add_change_free_affectation_igv_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (586,'2021_08_09_115837_add_currency_to_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (587,'2021_08_09_131738_tenant_add_select_available_price_list_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (588,'2021_08_16_111909_create_colors_for_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (589,'2021_08_17_091109_add_more_propertys_to_item',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (590,'2021_08_20_153033_add_extra_data_of_product_to_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (591,'2021_08_23_105050_tenant_add_group_items_generate_document_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (592,'2021_08_23_172828_adjust_inventory_item_extra_data',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (593,'2021_08_24_153219_tenant_add_columns_unknown_error_to_summaries',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (594,'2021_08_25_155900_tenant_add_columns_integrated_query_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (595,'2021_09_08_165136_add_optional_emails_to_person',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (596,'2021_09_10_120216_create_email_send_log',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (597,'2021_09_10_143648_tenant_add_subtotal_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (598,'2021_09_13_171757_tenat_add_total_igv_free_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (599,'2021_09_15_115746_add_global_igv_to_purchase',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (600,'2021_09_15_170619_tenant_change_length_description_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (601,'2021_09_17_120309_add_url_apk_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (602,'2021_09_17_150025_transfer_accounts_payment',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (603,'2021_09_21_172056_add_file_to_format_templates',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (604,'2021_09_22_120834_add_fields_technical_services',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (605,'2021_09_22_120835_create_technical_service_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (606,'2021_09_24_114832_add_configuration_to_show_name_of_pdf',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (607,'2021_09_28_183535_add_name_pdf_to_quotation_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (608,'2021_09_29_123827_add_text_to_address_in_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (609,'2021_09_29_180607_tenant_add_set_address_by_establishment_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (610,'2021_09_30_135325_tenant_add_order_id_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (611,'2021_09_30_170149_tenant_add_permission_to_edit_cpe_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (612,'2021_09_30_171912_tenant_add_permission_edit_cpe_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (613,'2021_10_04_154837_tenant_change_nullable_packages_number_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (614,'2021_10_05_171912_add_configuration_module_to_user',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (615,'2021_10_05_174410_tenant_add_total_igv_free_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (616,'2021_10_06_132920_tenant_add_total_igv_free_to_order_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (617,'2021_10_07_144354_create_guide_file',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (618,'2021_10_11_110825_create_item_movement_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (619,'2021_10_12_111345_create_item_movement_rel_extra_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (620,'2021_10_13_113504_add_only_user_warehouse_item_to_config',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (621,'2021_10_18_110547_tenant_add_technical_service_id_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (622,'2021_10_18_110700_change_default_show_items_only_user_stablishment',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (623,'2021_10_18_110700_change_value_show_items_only_user_stablishment',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (624,'2021_10_18_110907_tenant_add_technical_service_id_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (625,'2021_10_18_150100_tenant_add_document_type_04_to_cat_document_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (626,'2021_10_18_150136_tenant_add_data_operation_type_0501_to_cat_operation_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (627,'2021_10_18_150154_tenant_cat_address_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (628,'2021_10_18_150215_tenant_add_address_type_id_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (629,'2021_10_18_150306_tenant_purchase_settlements_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (630,'2021_10_18_150324_tenant_purchase_settlement_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (631,'2021_10_18_153949_tenant_add_data_purchase_settlements_to_module_levels',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (632,'2021_10_18_174146_tenant_add_subtotal_to_purchase_settlements',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (633,'2021_10_19_150956_tenant_add_pending_amount_detraction_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (634,'2021_10_20_160503_tenant_add_retention_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (635,'2021_10_20_161035_tenant_add_data_retention_to_cat_charge_discount_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (636,'2021_10_20_174401_tenant_rename_pending_amount_detraction_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (637,'2021_10_21_093921_tenant_add_igv_retention_percentage_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (638,'2021_10_25_112316_tenant_add_subtotal_to_quotations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (639,'2021_10_25_142908_create_suscription_plans_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (640,'2021_10_25_154009_tenant_add_option_13_to_cat_note_credit_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (641,'2021_10_27_105710_tenant_add_subtotal_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (642,'2021_10_27_133333_add_parent_id_field_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (643,'2021_10_28_110438_alter_sale_notes_and_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (644,'2021_10_28_173041_tenant_add_name_product_xml_to_document_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (645,'2021_10_29_110421_tenant_add_name_product_pdf_to_xml_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (646,'2021_10_29_114826_change_value_alternate_server_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (647,'2021_11_09_113608_tenant_add_default_document_type_80_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (648,'2021_11_09_143629_tenant_add_search_item_by_barcode_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (649,'2021_11_09_173326_add_grade_and_section_touser_rel_suscription_plan',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (650,'2021_11_11_113443_tenant_general_payment_conditions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (651,'2021_11_11_114229_tenant_add_payment_condition_id_to_purchases',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (652,'2021_11_11_152521_tenant_purchase_fee_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (653,'2021_11_11_180653_tenant_add_recreate_documents_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (654,'2021_11_12_102347_add_grade_and_section_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (655,'2021_11_15_152956_additem_description_default_pdf_name_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (656,'2021_11_16_150657_add_user_id_to_inventories_transfer',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (657,'2021_11_25_105300_create_accounting_ledger_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (658,'2021_11_25_154025_add_auto_print_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (659,'2021_12_01_113756_add_show_services_on_pos',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (660,'2021_12_02_141408_tenant_bank_loans_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (661,'2021_12_08_211200_add_pos_history_and_cost_to_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (662,'2021_12_09_182924_add_flag_to_production_for_item',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (663,'2021_12_10_122529_add_zone_id_to_person',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (664,'2021_12_10_172959_add_name_to_mill',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (665,'2021_12_11_101624_add_data_to_inventory_transactions',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (666,'2021_12_11_114856_add_item_to_mill_item',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (667,'2021_12_11_170251_create_item_supplies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (668,'2021_12_15_112106_create_columns_to_show_by_user',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (669,'2021_12_16_144057_add_show_totals_on_c_p_e_list',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (670,'2021_12_21_134408_add_simplify_to_documentary',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (671,'2021_12_22_150753_add_guide_to_documentary_archive',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (672,'2021_12_25_163345_add_colors_to_documentary',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (673,'2021_12_31_105910_change_documentary_process_characters',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (674,'2022_01_05_170742_create_machine_for_production',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (675,'2022_01_07_124919_add_complete_to_documentary_files',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (676,'2022_01_11_140319_tenant_add_columns_send_pse_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (677,'2022_01_11_174033_tenant_add_columns_send_pse_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (678,'2022_01_12_180810_tenant_add_detraction_amount_rounded_int_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (679,'2022_01_13_141051_tenant_add_sale_notes_relateds_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (680,'2022_01_15_130321_add_old_quantity_to_item_lot_group',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (681,'2022_01_18_094821_tenant_add_data_to_inventory_transactions',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (682,'2022_01_18_120000_add_show_term_condition_pos_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (683,'2022_01_18_213317_tenant_create_cash_documents_credit',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (684,'2022_01_20_202655_tenant_add_unique_filename_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (685,'2022_01_22_001715_add_date_end_to_documentary_files',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (686,'2022_01_25_150315_add_apply_restaurant_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (687,'2022_01_25_152940_create_app_restaurant',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (688,'2022_01_26_175429_create_mi_tienda_pe',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (689,'2022_01_28_110155_tenant_add_related_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (690,'2022_01_29_141957_add_purchase_order_and_observation_to_purchase',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (691,'2022_01_29_191503_create_packaging_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (692,'2022_01_30_120653_create_configuration_to_mi_tienda_pe',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (693,'2022_02_02_203403_add_autogenerate_c_p_e_to_mi_tienda_pe',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (694,'2022_02_07_110836_add_ticket_template_pdf_to_establishments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (695,'2022_02_07_121152_add_is_custom_ticket_to_format_templates',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (696,'2022_02_07_142617_add_format_tickets_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (697,'2022_02_07_161128_tenant_add_data_to_promotions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (698,'2022_02_08_213251_tenant_add_restaurant_to_orders',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (699,'2022_02_11_162919_tenant_add_last_sale_price_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (700,'2022_02_14_222010_tenant_add_flasg_to_inventory_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (701,'2022_02_14_224820_tenant_add_barcode_to_item_unit_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (702,'2022_02_16_150615_tenant_add_show_logo_establishment_to_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (703,'2022_02_17_195824_add_colaborator_to_production_as_text',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (704,'2022_02_22_111659_tenant_add_columns_send_pse_to_voided',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (705,'2022_02_24_134926_tenant_add_columns_purchase_isc_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (706,'2022_02_24_201345_tenant_download_tray_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (707,'2022_02_25_172811_add_print_next_line_to_pdf_on_observation',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (708,'2022_02_27_120405_tenant_add_comments_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (709,'2022_03_03_110115_tenant_add_global_discount_type_id_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (710,'2022_03_04_003932_tenant_add_quotation_to_cash_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (711,'2022_03_08_112351_tenant_add_columns_send_pse_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (712,'2022_03_09_111107_tenant_add_restaurant_to_cash',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (713,'2022_03_10_094902_tenant_add_shipping_time_days_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (714,'2022_03_14_221802_tenant_add_type_to_download_tray',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (715,'2022_03_18_140238_tenant_add_total_igv_free_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (716,'2022_03_21_110605_create_restaurant_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (717,'2022_03_21_161851_add_configuration_new_valdiator_pagination',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (718,'2022_03_25_134555_tenant_add_customer_filter_by_seller_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (719,'2022_03_25_143916_tenant_add_index_seller_id_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (720,'2022_03_25_170158_tenant_add_validate_purchase_sale_unit_price_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (721,'2022_03_28_174245_add_barcode_column_to_persons_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (722,'2022_03_29_101534_add_quantity_to_restaurant_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (723,'2022_03_30_145732_tenant_add_nationality_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (724,'2022_03_31_132849_create_app_pos_garage',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (725,'2022_04_04_151234_add_commands_to_restaurant_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (726,'2022_04_06_111531_create_restaurant_roles_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (727,'2022_04_06_112454_add_restaurant_role_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (728,'2022_04_06_170626_tenant_add_unique_filename_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (729,'2022_04_11_143145_tenant_add_name_product_pdf_to_contract_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (730,'2022_04_11_152502_tenant_add_name_product_pdf_to_dispatch_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (731,'2022_04_12_111023_tenant_add_columns_igv_unit_price_purchases_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (732,'2022_04_12_121903_addindex_to_item_movement_rel_extra',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (733,'2022_04_12_162742_tenant_report_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (734,'2022_04_18_153331_tenant_add_payment_permissions_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (735,'2022_04_19_114422_tenant_add_set_global_purchase_currency_items_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (736,'2022_04_20_115118_tenant_add_subject_to_detraction_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (737,'2022_04_22_101547_tenant_change_type_observation_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (738,'2022_04_22_133909_tenant_add_set_unit_price_dispatch_related_record_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (739,'2022_04_25_151827_tenant_add_index_barcode_to_item_unit_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (740,'2022_04_26_175613_tenant_add_columns_restrict_voided_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (741,'2022_04_28_175537_tenant_add_order_form_external_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (742,'2022_04_29_140515_tenant_add_enabled_tips_pos_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (743,'2022_04_29_144947_tenant_tips_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (744,'2022_05_02_135252_tenant_add_fields_to_module_levels',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (745,'2022_05_02_152925_tenant_add_top_menu_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (746,'2022_05_02_170654_tenant_add_data_district_250307_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (747,'2022_05_03_143911_tenant_create_skins_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (748,'2022_05_03_171845_tenant_add_purchase_settlement_id_to_kardex',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (749,'2022_05_04_143113_tenant_add_payment_method_type_id_to_purchase_settlements',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (750,'2022_05_04_150922_tenant_change_type_column_quantity_to_item_supplies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (751,'2022_05_04_155103_tenant_workers_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (752,'2022_05_04_174017_tenant_add_unique_filename_to_summaries',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (753,'2022_05_05_110908_tenant_add_soap_type_id_to_production',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (754,'2022_05_05_115054_tenant_add_soap_type_id_to_mill',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (755,'2022_05_05_134703_tenant_add_soap_type_id_to_packaging',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (756,'2022_05_06_162034_add_fields_to_person',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (757,'2022_05_08_122934_tenant_add_route_path_to_module_levels',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (758,'2022_05_08_130054_tenant_update_data_to_skins',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (759,'2022_05_09_105130_tenant_payment_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (760,'2022_05_09_122150_tenant_add_defaults_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (761,'2022_05_09_141114_tenant_payment_link_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (762,'2022_05_09_143633_tenant_payment_links_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (763,'2022_05_09_212753_tenant_register_app_generate_link_to_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (764,'2022_05_10_113515_tenant_transaction_states_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (765,'2022_05_10_113608_tenant_transactions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (766,'2022_05_10_113734_tenant_transaction_queries_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (767,'2022_05_10_113800_tenant_client_error_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (768,'2022_05_10_113813_tenant_client_errors_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (769,'2022_05_10_163722_tenant_config_default_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (770,'2022_05_11_104030_tenant_add_soap_type_id_to_transactions',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (771,'2022_05_11_115321_tenant_add_query_transaction_to_payment_links',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (772,'2022_05_11_161435_tenant_change_nullable_payment_to_payment_links',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (773,'2022_05_12_180154_tenant_add_legend_forest_to_xml_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (774,'2022_05_13_162912_tenant_add_index_regularize_shipping_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (775,'2022_05_13_163802_tenant_purchase_settlement_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (776,'2022_05_13_172504_tenant_add_change_currency_item_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (777,'2022_05_17_164646_tenant_add_enabled_advanced_records_search_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (778,'2022_05_23_133515_tenant_add_decimal_quantity_unit_price_pdf_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (779,'2022_06_01_114042_tenant_add_separate_cash_transactions_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (780,'2022_06_01_161857_add_column_hotel_rate_id_to_hotel_rents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (781,'2022_06_01_164204_tenant_add_order_cash_income_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (782,'2022_06_02_160925_tenant_add_data_district_080915_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (783,'2022_06_03_135513_tenant_add_generate_order_note_from_quotation_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (784,'2022_06_06_144610_tenant_add_list_items_by_warehouse_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (785,'2022_06_16_143605_tenant_app_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (786,'2022_06_17_143817_tenant_add_payment_received_to_document_payments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (787,'2022_06_21_101957_tenant_add_columns_login_pse_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (788,'2022_06_21_165810_tenant_add_user_pse_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (789,'2022_06_23_141216_tenant_add_print_format_pdf_to_app_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (790,'2022_06_28_164504_tenant_add_date_of_issue_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (791,'2022_06_30_110007_tenant_add_columns_send_pse_to_summaries',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (792,'2022_06_30_172004_tenant_add_index_external_id_to_income',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (793,'2022_06_30_172247_tenant_add_filename_to_income',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (794,'2022_07_01_103451_tenant_add_filename_to_expenses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (795,'2022_07_01_172113_tenant_add_hide_pdf_view_documents_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (796,'2022_07_04_152229_tenant_add_ticket_single_shipment_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (797,'2022_07_04_153343_tenant_add_ticket_single_shipment_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (798,'2022_07_05_103856_tenant_add_purchase_permissions_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (799,'2022_07_06_113107_tenant_add_columns_theme_configuration_to_app_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (800,'2022_07_06_233507_tenant_add_columns_header_configuration_to_app_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (801,'2022_07_07_100534_tenant_add_module_app_2_generator_to_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (802,'2022_07_07_131216_tenant_app_modules_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (803,'2022_07_07_143039_tenant_app_module_user_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (804,'2022_07_08_101718_tenant_add_data_configuration_to_app_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (805,'2022_07_08_165430_tenant_add_indexes_columns_to_cash',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (806,'2022_07_11_180145_tenant_add_data_quotation_to_app_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (807,'2022_07_14_095450_tenant_add_app_logo_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (808,'2022_07_14_120321_tenant_add_affect_all_documents_to_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (809,'2022_07_14_143450_tenant_add_terms_condition_column_to_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (810,'2022_07_14_162144_tenant_add_terms_condition_column_to_dispatches_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (811,'2022_07_15_110508_tenant_add_text_filter_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (812,'2022_07_18_172843_tenant_add_app_mode_to_app_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (813,'2022_07_19_033009_tenant_add_dashboard_options_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (814,'2022_07_25_115003_tenant_add_quantity_sales_notes_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (815,'2022_07_30_133127_tenant_add_direct_print_to_app_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (816,'2022_08_05_110445_tenant_add_columns_whatsapp_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (817,'2022_08_05_174505_tenant_add_favorite_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (818,'2022_08_07_122150_tenant_add_folio_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (819,'2022_08_11_151009_tenant_add_data_district_080916_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (820,'2022_08_11_151307_tenant_add_data_district_080917_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (821,'2022_08_11_151330_tenant_add_data_district_080918_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (822,'2022_08_11_151345_tenant_add_data_district_080919_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (823,'2022_08_14_190940_tenant_add_additional_data_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (824,'2022_08_15_101024_tenant_add_data_default_various_clients_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (825,'2022_08_23_140943_tenant_add_personal_information_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (826,'2022_08_23_174028_tenant_user_default_document_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (827,'2022_08_24_155445_tenant_add_data_district_080914_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (828,'2022_08_24_160940_tenant_add_additional_data_to_document_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (829,'2022_08_24_165942_tenant_add_restrict_series_selection_seller_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (830,'2022_08_25_113149_tenant_system_activity_logs_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (831,'2022_08_25_154533_create_dispatch_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (832,'2022_08_29_015753_tenant_add_igv_31556_to_establishments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (833,'2022_08_30_104544_tenant_add_location_to_system_activity_logs',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (834,'2022_08_30_140345_tenant_rename_column_auth_transaction_type_to_system_activity_logs',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (835,'2022_08_30_175558_tenant_add_regex_password_user_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (836,'2022_08_31_105919_tenant_add_route_to_system_activity_logs',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (837,'2022_08_31_112916_tenant_system_activity_log_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (838,'2022_08_31_131222_tenant_add_foreign_transaction_type_to_system_activity_logs',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (839,'2022_08_31_142530_tenant_add_columns_remember_change_password_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (840,'2022_08_31_150743_tenant_add_last_password_update_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (841,'2022_09_01_173835_tenant_add_columns_point_system_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (842,'2022_09_02_100607_tenant_add_accumulated_points_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (843,'2022_09_02_130726_tenant_add_point_system_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (844,'2022_09_02_152858_tenant_add_columns_point_system_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (845,'2022_09_02_172003_tenant_add_show_complete_name_pos_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (846,'2022_09_03_190940_tenant_add_additional_data_to_documents_error',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (847,'2022_09_06_165424_tenant_add_round_points_of_sale_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (848,'2022_09_07_101240_tenant_add_columns_point_system_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (849,'2022_09_08_102133_tenant_add_enable_categories_products_view_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (850,'2022_09_08_113936_tenant_add_columns_restrict_seller_discount_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (851,'2022_09_08_151132_tenant_authorized_discount_users_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (852,'2022_09_09_110646_tenant_enabled_sales_agents_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (853,'2022_09_09_113545_tenant_agents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (854,'2022_09_09_144216_tenant_add_agent_id_to_documents_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (855,'2022_09_10_114510_tenant_add_inventory_review_to_inventory_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (856,'2022_09_14_170825_tenant_add_change_affectation_exonerated_igv_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (857,'2022_09_20_101814_tenant_add_data_login_lockout_to_system_activity_log_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (858,'2022_09_20_155523_tenant_add_request_email_to_system_activity_logs',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (859,'2022_09_27_104853_tenant_add_factory_code_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (860,'2022_09_27_115234_tenant_add_search_factory_code_items_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (861,'2022_09_28_101354_tenant_add_show_load_voucher_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (862,'2022_09_29_114005_tenant_add_force_send_by_summary_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (863,'2022_09_29_115507_tenant_add_permission_force_send_by_summary_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (864,'2022_10_04_141907_tenant_change_description_value_to_module_levels',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (865,'2022_10_05_152438_tenant_add_environment_to_restaurant_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (866,'2022_10_05_162751_tenant_add_index_changed_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (867,'2022_10_12_141349_tenant_add_real_system_stock_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (868,'2022_10_14_172645_tenant_add_register_series_invoice_xml_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (869,'2022_10_16_101354_tenant_add_enable_discount_by_customer_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (870,'2022_10_16_110607_tenant_add_discount_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (871,'2022_10_20_123539_tenant_add_image_default_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (872,'2022_10_21_190940_tenant_add_additional_data_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (873,'2022_10_21_210941_tenant_add_additional_data_to_dispatch_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (874,'2022_10_21_213010_tenant_add_config_to_restaurant_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (875,'2022_10_22_113308_tenant_create_waiters_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (876,'2022_10_22_174921_tenant_add_quantity_mesas_and_and_env4_to_environment_restaurant',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (877,'2022_11_04_142103_tenant_add_enabled_dispatch_ticket_pdf_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (878,'2022_11_04_173318_tenant_add_dispatch_ticket_pdf_to_document_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (879,'2022_11_10_132533_tenant_add_item_lot_group_id_to_purchase_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (880,'2022_11_14_162628_tenant_add_validate_stock_add_item_to_inventory_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (881,'2022_11_16_000856_create_restaurant_table_envs_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (882,'2022_11_16_000857_add_data_restaurant_table_envs',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (883,'2022_11_16_235623_create_restaurant_tables_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (884,'2022_11_17_154241_tenant_add_direct_send_documents_whatsapp_to_app_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (885,'2022_11_27_235623_create_restaurant_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (886,'2022_11_28_007706_tenant_guides_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (887,'2022_11_28_017707_tenant_guide_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (888,'2022_11_28_101718_tenant_add_guide_id_to_inventories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (889,'2022_11_28_111428_tenant_add_series_and_number_to_inventories_transfer',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (890,'2022_11_29_113028_tenant_create_number_series_to_guides',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (891,'2022_11_30_103028_tenant_add_api_sunat_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (892,'2022_12_01_013028_tenant_add_ticket_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (893,'2022_12_01_112321_tenant_update_data_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (894,'2022_12_02_023125_tenant_add_number_mtc_to_dispatchers',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (895,'2022_12_04_113118_tenant_add_sunat_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (896,'2022_12_06_105443_tenant_add_name_product_pdf_to_purchase_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (897,'2022_12_07_112335_add_show_price_in_barcode_ticket_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (898,'2022_12_12_225021_tenant_update_roles_restaurant_mozo',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (899,'2022_12_13_131847_tenant_change_column_inventory_transaction_id_to_guides',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (900,'2022_12_13_153125_tenant_add_unit_type_to_cat_unit_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (901,'2022_12_16_153346_tenant_add_price_selected_add_product_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (902,'2022_12_20_160732_tenant_add_additional_data_to_order_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (903,'2022_12_21_113016_tenant_edit_unit_type_to_cat_unit_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (904,'2022_12_22_101455_tenant_pdf_footer_images_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (905,'2022_12_23_132614_tenant_add_locked_create_establishments_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (906,'2022_12_23_173228_tenant_add_restrict_sales_limit_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (907,'2022_12_26_112720_add_data_district050511_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (908,'2022_12_27_012101_tenant_transports_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (909,'2022_12_27_152518_tenant_add_restrict_sale_items_cpe_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (910,'2022_12_27_154226_tenant_add_restrict_sale_cpe_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (911,'2022_12_28_174959_tenant_add_show_convert_cpe_pos_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (912,'2022_12_28_270513_tenant_update_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (913,'2022_12_29_120310_tenant_update_to_drivers',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (914,'2022_12_29_132101_tenant_origin_addresses_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (915,'2022_12_30_120731_tenant_add_index_is_credit_to_payment_method_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (916,'2022_12_31_100513_tenant_add_transport_data_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (917,'2023_01_14_013028_tenant_change_length_ticket_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (918,'2023_01_15_122101_tenant_dispatch_addresses_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (919,'2023_01_16_110612_tenant_add_receiver_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (920,'2023_01_16_150612_tenant_change_customer_id_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (921,'2023_02_06_133012_tenant_add_index_barcode_to_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (922,'2023_02_09_144759_create_inventory_transfer_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (923,'2023_02_24_163815_tenant_add_order_note_advanced_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (924,'2023_03_08_113249_tenant_hotel_rent_item_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (925,'2023_03_09_132241_tenant_add_subtotal_to_order_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (926,'2023_03_15_151737_tenant_change_nullable_email_to_establishments',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (927,'2023_03_15_154740_tenant_remove_validation_email_establishments_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (928,'2023_05_16_151245_tenant_add_select_establishment_bank_account_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (929,'2023_05_16_155258_tenant_add_establishment_id_to_bank_accounts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (930,'2023_07_31_122800_tenant_add_title_web_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (931,'2023_09_05_162336_tenant_add_is_multi_user_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (932,'2023_09_07_151856_tenant_add_sire_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (933,'2023_09_08_153856_tenant_add_multi_user_id_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (934,'2023_09_11_161249_tenant_add_change_values_preview_document_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (935,'2023_09_22_142406_tenant_add_session_lifetime_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (936,'2023_09_29_114956_tenant_add_permission_edit_item_prices_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (937,'2023_10_24_155929_tenant_add_search_items_main_form_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (938,'2023_10_30_110551_tenant_add_show_all_item_details_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (939,'2023_10_31_110244_tenant_add_description_to_document_item_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (940,'2023_10_31_164120_tenant_show_item_description_pack_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (941,'2023_11_02_153214_tenant_weighted_average_costs_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (942,'2023_11_08_114144_tenant_add_show_weighted_cost_purchase_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (943,'2023_11_28_170507_tenant_add_active_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (944,'2023_12_05_162639_tenant_add_indexes_to_item_movement',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (945,'2024_01_16_200524_tenant__add_two_skins_to_skins_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (946,'2024_01_29_174039_tenant_add_field_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (947,'2024_02_20_100139_tenant_add_field_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (948,'2024_03_19_111304_tenant_add_field_conditional_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (949,'2024_03_21_110027_tenant_add_qr_chat_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (950,'2024_04_05_095135_tenant_add_config_wsapp_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (951,'2024_04_16_105842_add_permission_list_product_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (952,'2024_05_01_125617_tenant_add_code_to_dispatch_addresses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (953,'2024_07_04_093139_tenant_add_gre_to_app_modules',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (954,'2024_07_04_161647_tenant_add_establishment_code_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (955,'2024_07_04_161907_tenant_add_establishment_code_to_person_addresses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (956,'2024_07_16_155645_tenant_add_is_migrated_address_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (957,'2024_08_07_232850_tenant_qr_api_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (958,'2024_08_09_152515_tenant_temporary_kardex_records_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (959,'2024_08_17_102705_tenant_add_enabled_printsend_command_to_restaurant_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (960,'2024_08_27_103556_tenant_add_show_seller_in_pdf_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (961,'2024_09_02_172407_tenant_add_enabled_pos_waiter_to_restaurant_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (962,'2024_09_06_123006_tenant_add_contracts_state_anulado',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (963,'2024_09_13_105728_tenant_add_column_certificate_qz_tray_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (964,'2024_09_16_152432_tenant_add_tuc_to_transports',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (965,'2024_09_16_153054_tenant_add_secondary_drivers_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (966,'2024_09_16_153331_tenant_add_enabled_price_items_dispatch_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (967,'2024_09_20_154547_tenant_add_establishment_id_to_origin_addresses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (968,'2024_09_30_151344_tenant_add_restaurant_pin_to_users',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (969,'2024_10_18_092548_create_restaurant_item_order_statuses_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (970,'2024_10_24_164327_tenant_add_note_to_restaurant_item_order_statuses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (971,'2024_10_25_100038_tenant_add_opening_date_to_restaurant_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (972,'2024_11_05_164040_tenant_add_legend_footer_sale_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (973,'2024_11_14_115821_tenant_add_payer_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (974,'2024_11_19_121819_tenant_add_enabled_close_table_to_restaurant_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (975,'2024_12_05_000001_tenant_create_template_columns_config_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (976,'2024_12_16_141248_update_modules_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (977,'2024_12_16_141337_update_modules_levels_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (978,'2025_01_10_131258_tenant_add_exact_discount_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (979,'2025_01_14_104330_tenant_add_link_tiktok_to_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (980,'2025_01_14_143150_tenant_add_customised_links_to_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (981,'2025_01_16_092629_tenant_add_image_to_categories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (982,'2025_01_16_150618_tenant_add_password_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (983,'2025_01_25_000001_create_restaurant_preparation_areas_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (984,'2025_01_25_000002_add_preparation_area_id_to_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (985,'2025_01_27_163103_tenant_add_establishment_id_to_hotel_floors',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (986,'2025_01_29_145920_tenant_add_establishment_id_to_hotel_categories',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (987,'2025_01_29_150632_tenant_add_establishment_id_to_hotel_rates',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (988,'2025_01_29_151323_tenant_add_establishment_id_to_hotel_rooms',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (989,'2025_01_31_144652_tenant_update_establecimiento_id_in_hotel_floors_and_categories_and_rates_and_rooms',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (990,'2025_02_05_113336_tenant_add_data_persons_to_hotel_rents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (991,'2025_02_05_170359_tenant_add_hotel_data_persons_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (992,'2025_02_06_093610_tenant_add_hotel_data_persons_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (993,'2025_02_07_112452_tenant_add_establishment_id_to_hotel_rents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (994,'2025_02_12_155006_create_cash_document_payments_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (995,'2025_02_18_160500_tenant_add_has_transport_driver_01_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (996,'2025_02_26_102417_create_hotel_rent_orders_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (997,'2025_02_27_111401_tenant_add_hotel_rent_order_id_to_hotel_rent_items',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (998,'2025_03_13_124158_add_icon_id_to_module_levels_tenant',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (999,'2025_03_25_170121_add_logo_dark_to_companies_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1000,'2025_04_16_111715_add_is_transport_m1l_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1001,'2025_04_16_112117_update_is_transport_m1l_nullable_in_dispatches_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1002,'2025_04_16_143806_tenant_add_reference_documents_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1003,'2025_05_07_112859_tenant_add_column_available_detraction_for_amount_minor_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1004,'2025_06_02_162706_tenant_change_district080918_to_districts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1005,'2025_06_09_161203_add_product_default_image_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1006,'2025_07_02_160022_create_pending_account_commissions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1007,'2025_07_10_121505_tenant_create_sale_note_fee_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1008,'2025_07_16_142842_tenant_add_payment_condition_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1009,'2025_07_17_124425_add_is_itinerant_to_documents_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1010,'2025_07_18_180056_add_construction_contracts_to_cat_detraction_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1011,'2025_08_04_104323_add_from_guest_register_to_users_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1012,'2025_08_04_104558_add_from_guest_register_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1013,'2025_08_04_114949_add_was_verified_guest_user_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1014,'2025_08_07_152128_tenant_add_available_cash_report_seller_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1015,'2025_08_08_110626_create_pse_providers_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1016,'2025_08_08_110633_add_pse_provider_id_to_companies_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1017,'2025_08_09_094424_add_secret_login_time_to_users_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1018,'2025_08_22_130447_tenant_change_is_itinerant_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1019,'2025_08_26_120044_tenant_color_eccomerce_to_configurations_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1020,'2025_08_26_132759_tenant_restaurant_tip_factor_to_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1021,'2025_09_29_091911_tenant_change_description_gret_to_cat_document_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1022,'2025_10_02_093143_tenant_field_mtccode_to_companies',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1023,'2025_10_02_105747_tenant_create_consigneds_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1024,'2025_10_02_110406_tenant_add_has_consigned_to_person_addresses',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1025,'2025_10_02_110744_tenant_add_consigned_id_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1026,'2025_10_02_110843_tenant_add_consigned_id_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1027,'2025_10_02_111513_tenant_add_consigned_ubigeo_to_documents',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1028,'2025_10_02_111604_tenant_add_consigned_ubigeo_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1029,'2025_10_02_111707_tenant_add_enable_consigned_to_configuration',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1030,'2025_10_03_115332_create_buyers_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1031,'2025_10_03_123433_tenant_add_buyer_id_to_dispatches',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1032,'2025_10_20_112720_tenant_change_integer_string_column_number_to_buyers',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1033,'2025_10_23_125525_add_top_menu_extra_columns_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1034,'2025_10_24_131727_tenant_change_data_to_payment_method_types',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1035,'2025_10_27_164933_add_spot_url_to_promotions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1036,'2025_10_27_175500_make_item_id_nullable_in_promotions_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1037,'2025_11_05_135419_add_enable_print_group_comands_to_restaurant_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1038,'2025_11_06_124454_add_restaurant_favorite_to_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1039,'2025_11_13_175012_tenant_add_is_agent_retention_to_persons',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1040,'2025_11_14_105805_tenant_create_configuration_taps',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1041,'2025_11_14_111405_tenant_create_plates',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1042,'2025_11_21_093945_add_preferences_to_configuration_ecommerce_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1043,'2025_11_24_000001_create_supplies_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1044,'2025_11_24_000002_create_restaurant_item_supplies_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1045,'2025_11_24_000003_create_purchase_supplies_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1046,'2025_11_24_173349_tenant_create_tag_templates',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1047,'2025_11_25_111048_tenant_create_tag_template_fields',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1048,'2025_11_28_164112_add_order_status_to_restaurant_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1049,'2025_12_01_113753_add_pharmacy_to_business_turns_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1050,'2025_12_01_172738_add_exonerated_unaffected_to_company_accounts',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1051,'2025_12_03_114900_add_price_labels_to_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1052,'2025_12_03_172701_add_enabled_close_table_mozo_to_restaurant_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1053,'2025_12_08_000001_create_modifier_groups_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1054,'2025_12_08_000002_create_item_modifier_group_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1055,'2025_12_11_155929_create_restaurant_table_groups_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1056,'2025_12_11_162118_add_group_relationships_to_restaurant_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1057,'2025_12_11_162148_add_is_active_to_restaurant_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1058,'2025_12_13_000000_alter_restaurant_table_envs_id_auto_increment',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1059,'2025_12_17_105250_add_fields_to_restaurant_table_envs',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1060,'2025_12_17_201319_add_is_paid_to_restaurant_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1061,'2025_12_18_000000_add_delivery_field_to_restaurant_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1062,'2025_12_18_120952_update_cat_unit_types_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1063,'2025_12_18_173031_add_original_environment_in_restaurant_tables',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1064,'2025_12_23_115528_update_preferences_add_full_width_banner',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1065,'2025_12_31_112040_create_restaurant_stock_products_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1066,'2026_01_05_111735_add_sidebar_mode_to_tenant_configurations',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1067,'2026_01_08_134836_update_visual_column_in_configurations_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1068,'2026_01_16_121140_add_is_dish_to_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1069,'2026_01_20_012517_tenant_add_seo_social_to_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1070,'2026_01_24_005145_create_configuration_pixels_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1071,'2026_02_12_031804_add_policies_to_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1072,'2026_02_13_060016_optimize_seo_columns_configuration_ecommerce',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1073,'2026_02_19_234821_add_google_verification_to_configuration_ecommerce_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1074,'2026_03_05_045830_tenant_add_slug_to_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1075,'2026_03_12_000001_add_unique_index_to_item_warehouse_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1076,'2026_03_12_100000_add_performance_indexes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1077,'2026_03_14_000001_add_smart_stock_to_item_warehouse_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1078,'2026_03_14_000002_create_logistic_orders_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1079,'2026_03_14_000003_create_logistic_order_items_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1080,'2026_03_14_000004_create_stock_movements_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1081,'2026_03_14_000005_create_shipping_guides_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1082,'2026_03_14_000006_add_logistic_fields_to_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1083,'2026_03_14_000007_add_warehouse_to_users_type_enum',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1084,'2026_03_14_000008_add_delivery_type_to_sale_notes',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1085,'2026_03_14_000009_add_warehouse_notes_to_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1086,'2026_03_14_000010_create_courier_companies_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1087,'2026_03_14_000011_add_shipping_fields_to_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1088,'2026_03_14_000012_add_shipping_district_to_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1089,'2026_03_14_000013_add_preferred_carrier_to_sale_notes_table',0);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1090,'2026_03_15_000001_add_logistic_module_levels_tenant',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1091,'2026_03_15_000002_create_logistic_returns_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1092,'2026_03_15_000003_add_shipping_cost_to_sale_notes_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1093,'2026_03_15_000004_update_shipping_fields_sale_notes',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1094,'2026_03_15_000007_add_warehouse_to_users_and_sale_notes',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1095,'2026_03_15_000001_update_shipping_guides_add_sale_note',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1096,'2026_03_15_000001_add_anulado_to_logistic_status_enum',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1097,'2026_03_16_000004_add_google_id_to_persons_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1098,'2026_03_16_000005_add_google_oauth_to_configuration_ecommerce',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1099,'2026_03_16_000001_add_comment_to_items_rating_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1100,'2026_03_16_000001_create_stock_notifications_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1101,'2026_03_16_000002_create_coupons_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1102,'2026_03_16_000006_add_newsletter_popup_to_configuration_ecommerce',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1103,'2026_03_16_000007_add_person_points_to_orders_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1104,'2026_03_15_000005_update_shipping_guides_add_sale_note',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1105,'2026_03_15_000006_add_anulado_to_logistic_status_enum',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1106,'2026_03_16_000008_add_comment_to_items_rating_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1107,'2026_03_21_000001_add_business_type_to_configurations_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1108,'2026_03_22_000001_add_has_variants_to_items_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1109,'2026_03_22_000002_create_item_options_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1110,'2026_03_22_000003_create_item_option_values_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1111,'2026_03_22_000004_create_item_variants_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1112,'2026_03_22_000005_create_item_variant_value_map_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1113,'2026_03_22_000006_create_item_variant_warehouse_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1114,'2026_03_22_000007_add_variant_id_to_logistic_order_items_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1115,'2026_03_22_000001_add_cancelado_to_status_orders',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1116,'2026_03_22_000010_add_has_variants_to_items_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1117,'2026_03_23_000001_add_whatsapp_api_fields_to_configuration_ecommerce',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1118,'2026_03_23_000002_create_sales_channels_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1119,'2026_03_23_000003_add_channel_fields_to_orders_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1120,'2026_03_23_000004_update_status_orders_descriptions',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1121,'2026_03_23_000005_seed_default_sales_channels',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1122,'2026_03_23_000006_create_discount_rules_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1123,'2026_03_24_000001_create_features_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1124,'2026_03_24_000002_create_plan_features_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1125,'2026_03_24_100001_add_two_factor_to_users_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1126,'2026_03_24_000001_create_abandoned_carts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1127,'2026_03_24_000002_add_performance_indexes',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1128,'2026_03_24_000004_add_culqi_preauth_to_orders_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1129,'2026_03_24_000005_add_api_fields_to_courier_companies_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1130,'2026_03_24_000006_add_reminder_sent_at_to_abandoned_carts_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1132,'2026_03_28_000001_create_themes_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1133,'2026_03_28_000002_create_domain_verifications_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1134,'2026_03_28_000003_create_theme_installations_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1135,'2026_03_28_000004_create_ecommerce_modes_and_business_types_tables',13);
