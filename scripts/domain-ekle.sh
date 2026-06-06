#!/bin/bash
# GMS Panel - Domain Ekle
# Kullanim: domain-ekle.sh domain kullanici php_versiyonu
# Ornek   : domain-ekle.sh musteri.com ahmet 83

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

DOMAIN=$1
KULLANICI=$2
PHP=$3

if [ -z "$DOMAIN" ] || [ -z "$KULLANICI" ] || [ -z "$PHP" ]; then
    echo "Kullanim: domain-ekle.sh domain kullanici php_versiyonu"
    exit 1
fi

# Kullanici var mi
if ! id "$KULLANICI" &>/dev/null; then
    echo "HATA: $KULLANICI kullanicisi bulunamadi."
    exit 1
fi

# Domain zaten tanimli mi
if [ -f "/etc/nginx/conf.d/${DOMAIN}.conf" ]; then
    echo "HATA: Bu domain zaten tanimli."
    exit 1
fi

cat > /etc/nginx/conf.d/${DOMAIN}.conf << NGINX
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    root /home/${KULLANICI}/public_html;
    index index.php index.html;
    access_log /home/${KULLANICI}/logs/${DOMAIN}_access.log;
    error_log  /home/${KULLANICI}/logs/${DOMAIN}_error.log;

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
echo "[GMS] Domain eklendi: $DOMAIN -> /home/$KULLANICI/public_html"
