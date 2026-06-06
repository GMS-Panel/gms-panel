#!/bin/bash
# Kullanim: yeni-hesap.sh kullaniciadi domain.com php_versiyonu
# Ornek: yeni-hesap.sh ahmet ahmet.com 83
# PHP versiyonlari: 74, 80, 81, 82, 83, 84

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

KULLANICI=$1
DOMAIN=$2
PHP=$3

if [ -z "$KULLANICI" ] || [ -z "$DOMAIN" ] || [ -z "$PHP" ]; then
    echo "Kullanim: yeni-hesap.sh kullaniciadi domain.com php_versiyonu"
    echo "Ornek: yeni-hesap.sh ahmet ahmet.com 83"
    exit 1
fi

echo "[GMS] Hesap olusturuluyor: $KULLANICI / $DOMAIN / PHP $PHP"

/usr/sbin/useradd -d /home/$KULLANICI -m -s /bin/bash $KULLANICI
mkdir -p /home/$KULLANICI/public_html /home/$KULLANICI/logs
chown -R $KULLANICI:$KULLANICI /home/$KULLANICI
chmod 755 /home/$KULLANICI /home/$KULLANICI/public_html

cat > /etc/opt/remi/php${PHP}/php-fpm.d/${KULLANICI}.conf << POOL
[${KULLANICI}]
user = ${KULLANICI}
group = ${KULLANICI}
listen = /var/opt/remi/php${PHP}/run/php-fpm/${KULLANICI}.sock
listen.owner = nginx
listen.group = nginx
pm = dynamic
pm.max_children = 5
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 3
POOL

/usr/bin/systemctl restart php${PHP}-php-fpm

cat > /etc/nginx/conf.d/${DOMAIN}.conf << NGINX
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    root /home/${KULLANICI}/public_html;
    index index.php index.html;
    access_log /home/${KULLANICI}/logs/access.log;
    error_log /home/${KULLANICI}/logs/error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/opt/remi/php${PHP}/run/php-fpm/${KULLANICI}.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
NGINX

/usr/sbin/nginx -t && /usr/bin/systemctl reload nginx

echo "[GMS] Tamamlandi!"
echo "  Kullanici : $KULLANICI"
echo "  Domain    : $DOMAIN"
echo "  PHP       : $PHP"
echo "  Klasor    : /home/$KULLANICI/public_html"