
#==========================================================================================
# PEDIR DATOS AL USARIO
#==========================================================================================
read -p "Introduce el numero del servicio de los contenedores php-fpm y mariadb: " NUMBER_SERVICE
echo
read -s -p "Introduce la contraseña de root de la base de datos: " PASS
echo
#==========================================================================================
# VALIDAR ARCHIVO docker-compose.yml DEL APLICATIVO
#==========================================================================================

echo "RUN: Validando archivo docker-compose.yml..."
if [ -f "docker-compose.yml" ]; then
    FILE="docker-compose.yml"
    echo "SUCCESS: docker-compose.yml encontrado."
else
    echo "ERROR: docker-compose.yml no encontrado."
    exit 1
fi

#==========================================================================================
# ENCONTRAR EL NOMBRE DEL CONTENEDOR PHP
#==========================================================================================

echo "RUN: Buscando nombre del contenedor..."
CONTAINER_NAME_PHP=$(grep -A 5 "fpm_$NUMBER_SERVICE:" "$FILE" | grep "container_name:" | awk '{print $2}')

if [ -z "$CONTAINER_NAME_PHP" ]; then
    echo "ERROR: Nombre del contenedor no encontrado."
    exit 1
else
    echo "SUCCESS: Nombre del contenedor encontrado: $CONTAINER_NAME_PHP"
fi

#==========================================================================================
# ENCONTRAR EL NOMBRE DEL CONTENEDOR MYSQL
#==========================================================================================
echo "RUN: Buscando nombre del contenedor..."
CONTAINER_NAME_MYSQL=$(grep -A 5 "mariadb_$NUMBER_SERVICE:" "$FILE" | grep "container_name:" | awk '{print $2}')

if [ -z "$CONTAINER_NAME_MYSQL" ]; then
    echo "ERROR: Nombre del contenedor no encontrado."
    exit 1
else
    echo "SUCCESS: Nombre del contenedor encontrado: $CONTAINER_NAME_MYSQL"
fi


#==========================================================================================
# ACTUALIZAR IMAGENES
#==========================================================================================

echo "RUN: Actualizando imágenes..."

# Mapa de cambios (imagen_vieja → imagen_nueva)
declare -A MAP
MAP["rash07/php-fpm:2.0"]="rash07/php-fpm:8.2"
MAP["rash07/scheduling"]="rash07/scheduling:8.2"
MAP["rash07/php7.4-supervisor"]="rash07/supervisor-php:8.2"

for OLD in "${!MAP[@]}"; do
    NEW="${MAP[$OLD]}"
    sed -i "s|${OLD}\([^:]*\)\?$|${NEW}|g" "$FILE"
    echo "✔️  $OLD → $NEW"
done

#==========================================================================================
# DETENER CONTENEDORES
#==========================================================================================
echo "RUN: Deteniendo contenedores..."
docker compose down

#==========================================================================================
# ACTUALIZAR IMAGENES
#==========================================================================================
echo "RUN: Actualizando imágenes..."
docker compose up -d

#==========================================================================================
# BORRAR ARCHIVOS Y CARPETAS
#==========================================================================================
echo "RUN: Borrando archivos y carpetas..."
docker exec $CONTAINER_NAME_PHP rm -rf vendor
docker exec $CONTAINER_NAME_PHP rm -rf node_modules
docker exec $CONTAINER_NAME_PHP rm -rf composer.lock
docker exec $CONTAINER_NAME_PHP rm -rf package-lock.json

#==========================================================================================
# INSTALAR Y ACTUALIZAR PAQUETES PHP
#==========================================================================================
echo "RUN: Instalando y actualizando paquetes..."
docker exec $CONTAINER_NAME_PHP composer install

#==========================================================================================
# ACTUALIZAR PERMISOS
#==========================================================================================
echo "RUN: Actualizando permisos..."
docker exec $CONTAINER_NAME_PHP mkdir -p bootstrap/cache
docker exec $CONTAINER_NAME_PHP mkdir -p storage/framework/{cache,sessions,testing,views}
docker exec $CONTAINER_NAME_PHP chown -R www-data:www-data storage bootstrap/cache
docker exec $CONTAINER_NAME_PHP chmod -R 775 storage bootstrap/cache
docker exec $CONTAINER_NAME_PHP composer dump-autoload

#==========================================================================================
# BORRAR USUARIOS TENANCY
#==========================================================================================
USER="root"
SQL=$(cat <<'EOF'

USE mysql

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

CALL drop_tenancy_users();
DROP PROCEDURE drop_tenancy_users;
FLUSH PRIVILEGES;
EOF
)

docker exec -i "$CONTAINER_NAME_MYSQL" mysql -u"$USER" -p"$PASS" -e "$SQL"

#==========================================================================================
# RECREAR USUARIOS TENANCY
#==========================================================================================
echo "RUN: Recreando usuarios tenancy..."
docker exec $CONTAINER_NAME_PHP php artisan passwords
docker exec $CONTAINER_NAME_PHP php artisan tenancy:recreate
docker exec $CONTAINER_NAME_PHP php artisan optimize:clear
docker exec $CONTAINER_NAME_PHP chmod -R 777 vendor/mpdf
#==========================================================================================
echo "SUCCESS: Sistema actualizado correctamente."
