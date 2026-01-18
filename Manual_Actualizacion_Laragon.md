# 📦 Manual de Actualización del Proyecto en Entorno Local (Laragon)

Este documento describe el proceso completo para **actualizar el proyecto a la versión 7.1**, incluyendo requisitos de entorno, cambio de rama, actualización de dependencias y comandos de mantenimiento.

---

## 🧩 Requisitos del Entorno

Antes de comenzar, asegúrate de contar con las siguientes versiones instaladas:

### **PHP**
- Versión mínima: **8.2**
- Extensiones requeridas:
  - `openssl`
  - `mbstring`
  - `pdo`
  - `pdo_mysql`
  - `tokenizer`
  - `xml`
  - `ctype`
  - `json`
  - `bcmath`
  - `fileinfo`

### **Node.js**
- Versión mínima recomendada: **18.x o superior**
- Administrador de paquetes: **npm** o **yarn**

### **Composer**
- Versión: **2.x o superior**

---

## 🪄 Cambio de Rama a `v7.1`

1. Abre la terminal en el directorio raíz de tu proyecto (por ejemplo: `C:\laragon\www\pro7`).
2. Verifica las ramas remotas:
   ```bash
   git fetch --all
   git branch -a
   ```
3. Cambia a la rama `v7.1`:
   ```bash
   git checkout -b v7.1 origin/v7.1
   ```
4. Si tienes cambios locales pendientes, guárdalos antes de cambiar:
   ```bash
   git stash
   git checkout -b v7.1 origin/v7.1
   git stash pop
   ```

---

## 📍 Detener servicios

Antes de comenzar, asegúrate de detener los servicios innecesarios para evitar bloqueos:

1. Abre **Laragon**.
2. Detén todos los servicios con el botón **"Stop All"**.

---

## ⚙️ Actualizar dependencias del proyecto

1. Abre una **terminal de Laragon** (símbolo de CMD o PowerShell con el entorno cargado).
2. Navega hasta la carpeta raíz de tu proyecto:
   ```bash
   cd C:\laragon\www\pro7
   ```

3. Borra las carpetas y archivos de dependencias antiguas:
   ```bash
   rmdir /s /q vendor node_modules
   del composer.lock package-lock.json
   ```

4. Instala nuevamente las dependencias de **Composer**:
   ```bash
   composer install
   ```

5. Si tu proyecto usa **npm o yarn** (por ejemplo, para el panel frontend):
   ```bash
   npm install
   ```
   o, si prefieres usar **yarn**:
   ```bash
   yarn install
   ```

---

## 🔒 Otorgar permisos y preparar directorios

En Windows, no necesitas `chmod`, pero asegúrate de que existan los directorios necesarios:

```bash
mkdir bootstrap\cache
mkdir storage\framework\cache
mkdir storage\framework\sessions
mkdir storage\framework\testing
mkdir storage\framework\views
```

> 💡 Si Windows pregunta si quieres crear las carpetas, confirma “Sí”.

---

## 🧹 Limpiar y optimizar autoload

Ejecuta el siguiente comando para regenerar los autoloads de Composer:

```bash
composer dump-autoload
```

Luego limpia y optimiza el entorno de Laravel:

```bash
php artisan optimize:clear
```

---

## 🗄️ Actualizar base de datos local (MySQL en Laragon)

1. Abre **HeidiSQL** o una terminal MySQL:
   ```bash
   mysql -u root -p
   ```

2. Conéctate a tu base de datos principal:
   ```sql
   USE pro7;
   ```

3. Limpia los usuarios de tenancy antiguos (opcional, si trabajas con multi-tenant):

```sql
DELIMITER $$

CREATE PROCEDURE drop_tenancy_users()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE username VARCHAR(100);
    DECLARE userhost VARCHAR(100);
    DECLARE cur CURSOR FOR
        SELECT User, Host FROM mysql.user WHERE User LIKE 'tenancy_%';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO username, userhost;
        IF done THEN
            LEAVE read_loop;
        END IF;
        SET @s = CONCAT('DROP USER IF EXISTS `', username, '`@`', userhost, '`;');
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;

    CLOSE cur;
END$$

DELIMITER ;
```

Ejecuta la función:
```sql
CALL drop_tenancy_users();
```

Luego bórrala:
```sql
DROP PROCEDURE drop_tenancy_users;
```

Y actualiza privilegios:
```sql
FLUSH PRIVILEGES;
```

---

## 🔁 Actualizar configuración tenancy

Desde la terminal del proyecto, ejecuta:

```bash
php artisan passwords
php artisan tenancy:recreate
```

---

## 🚀 Optimizar y limpiar cachés

```bash
php artisan optimize:clear
```

---

## 📦 Revisión final

1. Reinicia los servicios en **Laragon**:
   - Apache
   - MySQL

2. Abre tu navegador y entra a tu dominio local, por ejemplo:
   ```
   http://pro7.test
   ```

3. Verifica que el sistema cargue correctamente y las dependencias estén actualizadas.

---

## 🎯 Resultado final

Tu entorno local está actualizado con PHP 8.2, dependencias nuevas, base de datos limpia y servicios funcionando.
