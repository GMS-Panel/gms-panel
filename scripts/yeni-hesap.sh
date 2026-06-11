#!/bin/bash
# GMS Panel - Yeni Hosting Hesabi Olustur
# Kullanim: yeni-hesap.sh kullaniciadi domain.com php_versiyonu email ssl(0/1) sifre
# Ornek   : yeni-hesap.sh ahmet ahmet.com 83 ahmet@ahmet.com 1 Sifre123
# PHP ver : 74, 80, 81, 82, 83, 84

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

KULLANICI=$1
DOMAIN=$2
PHP=$3
EMAIL=${4:-""}
SSL_KUR=${5:-"0"}
SIFRE=${6:-""}

YASAK="gmssys root nginx apache mysql mariadb ftp mail www admin administrator test nobody daemon bin sys"

# Parametre kontrolu
if [ -z "$KULLANICI" ] || [ -z "$DOMAIN" ] || [ -z "$PHP" ]; then
    echo "Kullanim: yeni-hesap.sh kullaniciadi domain.com php_versiyonu [email] [ssl]"
    exit 1
fi

# Gecersiz PHP versiyonu
if ! echo "74 80 81 82 83 84" | grep -qw "$PHP"; then
    echo "HATA: Gecersiz PHP versiyonu. Gecerli: 74 80 81 82 83 84"
    exit 1
fi

# Yasak kullanici adi kontrolu
for yasak in $YASAK; do
    if [ "$KULLANICI" = "$yasak" ]; then
        echo "HATA: Gecersiz kullanici adi. Lutfen baska bir kullanici adi deneyin."
        exit 1
    fi
done

# Kullanici zaten var mi
if id "$KULLANICI" &>/dev/null; then
    echo "HATA: Bu kullanici zaten mevcut."
    exit 1
fi

# Domain zaten tanimli mi
if [ -f "/etc/nginx/conf.d/${DOMAIN}.conf" ]; then
    echo "HATA: Bu domain zaten tanimli."
    exit 1
fi

echo "[GMS] Hesap olusturuluyor: $KULLANICI / $DOMAIN / PHP $PHP"

# Linux kullanicisi olustur
/usr/sbin/useradd -d /home/$KULLANICI -m -s /bin/bash $KULLANICI
mkdir -p /home/$KULLANICI/public_html /home/$KULLANICI/logs
chown -R $KULLANICI:$KULLANICI /home/$KULLANICI
chmod 755 /home/$KULLANICI /home/$KULLANICI/public_html

# Sifre ata: verilmisse kullan, yoksa guvenli rastgele sifre olustur
if [ -n "$SIFRE" ]; then
    echo "${KULLANICI}:${SIFRE}" | /usr/sbin/chpasswd
else
    SIFRE=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9!@#' | head -c 16)
    echo "${KULLANICI}:${SIFRE}" | /usr/sbin/chpasswd
fi
echo "  Sifre     : $SIFRE"

# Dosya yoneticisi icin gmssys'e ACL yazma izni ver
setfacl -R -m u:gmssys:rwx /home/$KULLANICI/
setfacl -R -d -m u:gmssys:rwx /home/$KULLANICI/

# PHP-FPM pool
cat > /etc/opt/remi/php${PHP}/php-fpm.d/${KULLANICI}.conf << POOL
[${KULLANICI}]
user = ${KULLANICI}
group = ${KULLANICI}
listen = /var/opt/remi/php${PHP}/run/php-fpm/${KULLANICI}.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660
pm = dynamic
pm.max_children = 5
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
POOL

# Pool conf izinleri: gmssys okuyabilsin (php_settings.php icin)
chown root:gmssys /etc/opt/remi/php${PHP}/php-fpm.d/${KULLANICI}.conf
chmod 640 /etc/opt/remi/php${PHP}/php-fpm.d/${KULLANICI}.conf

# reload: graceful, panel ayakta kalir (restart yerine)
/usr/bin/systemctl reload php${PHP}-php-fpm

# Nginx vhost (HTTP)
cat > /etc/nginx/conf.d/${DOMAIN}.conf << NGINX
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    root /home/${KULLANICI}/public_html;
    index index.php index.html;
    access_log /home/${KULLANICI}/logs/access.log;
    error_log  /home/${KULLANICI}/logs/error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/opt/remi/php${PHP}/run/php-fpm/${KULLANICI}.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX

/usr/sbin/nginx -t && /usr/bin/systemctl reload nginx

# Varsayilan site sablonunu kopyala (index.html + logo)
SITE_DIR="/home/gmssys/gms-panel/site"
if [ -f "${SITE_DIR}/index.html" ]; then
    cp "${SITE_DIR}/index.html" /home/${KULLANICI}/public_html/index.html
    chown ${KULLANICI}:${KULLANICI} /home/${KULLANICI}/public_html/index.html
fi
if [ -f "${SITE_DIR}/logo.png" ]; then
    cp "${SITE_DIR}/logo.png" /home/${KULLANICI}/public_html/logo.png
    chown ${KULLANICI}:${KULLANICI} /home/${KULLANICI}/public_html/logo.png
fi

# MySQL kullanicisi ve veritabani yetkisi
DB_CONF="/etc/gms/db.conf"
if [ -f "$DB_CONF" ]; then
    DB_ROOT=$(grep "^DB_ROOT=" "$DB_CONF" | cut -d'=' -f2-)
    MYSQL_SIFRE=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 16)

    mysql -u root -p"${DB_ROOT}" 2>/dev/null << SQLEOF
CREATE USER IF NOT EXISTS '${KULLANICI}'@'localhost' IDENTIFIED BY '${MYSQL_SIFRE}';
GRANT ALL PRIVILEGES ON \`${KULLANICI}\\_%\`.* TO '${KULLANICI}'@'localhost';
FLUSH PRIVILEGES;
SQLEOF

    # Hesap bilgilerini kaydet (EMAIL dahil)
    cat > /etc/gms/users/${KULLANICI}.conf << CONF
KULLANICI=${KULLANICI}
DOMAIN=${DOMAIN}
PHP=${PHP}
EMAIL=${EMAIL}
MYSQL_USER=${KULLANICI}
MYSQL_SIFRE=${MYSQL_SIFRE}
OLUSTURMA=$(date '+%d.%m.%Y %H:%M')
CONF
    chmod 640 /etc/gms/users/${KULLANICI}.conf
    chown root:gmssys /etc/gms/users/${KULLANICI}.conf
fi

echo "[GMS] Tamamlandi!"
echo "  Kullanici : $KULLANICI"
echo "  Domain    : $DOMAIN"
echo "  PHP       : $PHP"
echo "  Email     : ${EMAIL:-'-'}"
echo "  Klasor    : /home/$KULLANICI/public_html"

# SSL sertifikasi kur (Let's Encrypt)
if [ "$SSL_KUR" = "1" ]; then
    echo ""
    echo "[GMS] SSL sertifikasi olusturuluyor..."
    SSL_EMAIL="${EMAIL:-admin@${DOMAIN}}"
    /usr/bin/certbot --nginx \
        -d "${DOMAIN}" -d "www.${DOMAIN}" \
        --non-interactive \
        --agree-tos \
        -m "${SSL_EMAIL}" \
        --redirect 2>&1
    echo "[GMS] SSL islemi tamamlandi."
fi
