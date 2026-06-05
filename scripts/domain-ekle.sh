#!/bin/bash
# =============================================================
# GMS Panel - Domain Ekleme Scripti
# Kullanim: domain-ekle.sh domain kullanici php_ver www(0/1)
# Ornek: domain-ekle.sh musteri.com ahmet 83 1
# PHP versiyonlari: 74, 80, 81, 82, 83, 84
# www parametresi: 1=www de ekle, 0=sadece domain
# =============================================================

DOMAIN=$1
KULLANICI=$2
PHP=$3
WWW=$4

if [ -z "$DOMAIN" ] || [ -z "$KULLANICI" ] || [ -z "$PHP" ]; then
    echo "Kullanim: domain-ekle.sh domain kullanici php_ver www(0/1)"
    echo "Ornek: domain-ekle.sh musteri.com ahmet 83 1"
    exit 1
fi

# Kullanici var mi kontrol et
if ! id "$KULLANICI" > /dev/null 2>&1; then
    echo "[HATA] Kullanici bulunamadi: $KULLANICI"
    exit 1
fi

# PHP versiyonu gecerli mi
if [ ! -f "/etc/opt/remi/php${PHP}/php-fpm.d/${KULLANICI}.conf" ]; then
    echo "[UYARI] PHP ${PHP} pool bulunamadi: ${KULLANICI}.conf"
fi

# Server name ayarla
SERVER_NAME="$DOMAIN"
[ "$WWW" = "1" ] && SERVER_NAME="$DOMAIN www.$DOMAIN"

echo "[GMS] Domain ekleniyor: $DOMAIN -> $KULLANICI (PHP $PHP)"

# Nginx config olustur
cat > /etc/nginx/conf.d/${DOMAIN}.conf << NGINX
server {
    listen 80;
    server_name ${SERVER_NAME};
    root /home/${KULLANICI}/public_html;
    index index.php index.html;
    access_log /home/${KULLANICI}/logs/${DOMAIN}_access.log;
    error_log /home/${KULLANICI}/logs/${DOMAIN}_error.log;

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

# Nginx test ve reload
if nginx -t 2>/dev/null; then
    systemctl reload nginx
    echo "[GMS] Tamamlandi: $DOMAIN eklendi."
else
    rm -f /etc/nginx/conf.d/${DOMAIN}.conf
    echo "[HATA] Nginx config hatasi, domain eklenemedi."
    exit 1
fi
