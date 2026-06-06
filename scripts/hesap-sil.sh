#!/bin/bash
# GMS Panel - Hosting Hesabi Sil
# Kullanim: hesap-sil.sh kullaniciadi
# Ornek   : hesap-sil.sh ahmet

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

KULLANICI=$1

if [ -z "$KULLANICI" ]; then
    echo "Kullanim: hesap-sil.sh kullaniciadi"
    exit 1
fi

# Kullanici var mi
if ! id "$KULLANICI" &>/dev/null; then
    echo "HATA: $KULLANICI kullanicisi bulunamadi."
    exit 1
fi

echo "[GMS] Hesap siliniyor: $KULLANICI"

# PHP-FPM pool sil
for VER in 74 80 81 82 83 84; do
    CONF="/etc/opt/remi/php${VER}/php-fpm.d/${KULLANICI}.conf"
    if [ -f "$CONF" ]; then
        rm -f "$CONF"
        /usr/bin/systemctl restart php${VER}-php-fpm 2>/dev/null
    fi
done

# Nginx vhost sil (tum konfig dosyalarini tara)
find /etc/nginx/conf.d/ -name "*.conf" \
    -exec grep -l "/home/${KULLANICI}/" {} \; | xargs rm -f 2>/dev/null
/usr/sbin/nginx -t && /usr/bin/systemctl reload nginx 2>/dev/null

# MySQL kullanicisi ve veritabanlarini sil
DB_CONF="/etc/gms/db.conf"
if [ -f "$DB_CONF" ]; then
    DB_ROOT=$(grep "^DB_ROOT=" "$DB_CONF" | cut -d'=' -f2-)
    mysql -u root -p"${DB_ROOT}" 2>/dev/null << SQLEOF
DROP USER IF EXISTS '${KULLANICI}'@'localhost';
FLUSH PRIVILEGES;
SQLEOF
    # Not: kullaniciadi_ ile baslayan veritabanlari silinmez
    # Panel uzerinden manuel silinebilir
fi

# Linux kullanicisi ve ev dizinini sil
/usr/sbin/userdel -r "$KULLANICI" 2>/dev/null

# GMS hesap konfig dosyasini sil
rm -f /etc/gms/users/${KULLANICI}.conf

echo "[GMS] $KULLANICI hesabi ve tum dosyalari silindi."
