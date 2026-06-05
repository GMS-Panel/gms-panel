#!/bin/bash
# =============================================================
# GMS Panel - Hesap Silme Scripti
# Kullanim: hesap-sil.sh kullaniciadi
# Ornek: hesap-sil.sh ahmet
# =============================================================

KULLANICI=$1

if [ -z "$KULLANICI" ]; then
    echo "Kullanim: hesap-sil.sh kullaniciadi"
    exit 1
fi

# Kullanici var mi kontrol et
if ! id "$KULLANICI" > /dev/null 2>&1; then
    echo "[HATA] Kullanici bulunamadi: $KULLANICI"
    exit 1
fi

echo "[GMS] Hesap siliniyor: $KULLANICI"

# PHP-FPM pool sil
for VER in 74 80 81 82 83 84; do
    CONF="/etc/opt/remi/php${VER}/php-fpm.d/${KULLANICI}.conf"
    if [ -f "$CONF" ]; then
        rm -f "$CONF"
        systemctl restart php${VER}-php-fpm 2>/dev/null
        echo "[GMS] PHP ${VER} pool silindi."
    fi
done

# Nginx config sil
NGINX_CONF=$(find /etc/nginx/conf.d/ -name "*.conf" -exec grep -l "/home/${KULLANICI}/" {} \; 2>/dev/null)
if [ -n "$NGINX_CONF" ]; then
    echo "$NGINX_CONF" | xargs rm -f
    nginx -t && systemctl reload nginx 2>/dev/null
    echo "[GMS] Nginx config silindi."
fi

# Linux kullanicisini ve home klasorunu sil
userdel -r "$KULLANICI" 2>/dev/null
echo "[GMS] Tamamlandi: $KULLANICI silindi."
