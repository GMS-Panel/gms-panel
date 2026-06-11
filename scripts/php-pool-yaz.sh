#!/bin/bash
# php-pool-yaz.sh - Pool conf dosyasina gecici dosyadan kopyalar
# Kullanim: php-pool-yaz.sh <hedef_conf> <kaynak_temp>
HEDEF="$1"
KAYNAK="$2"

# Guvenlik: hedef /etc/opt/remi/php* altinda olmali
if [[ ! "$HEDEF" =~ ^/etc/opt/remi/php[0-9]+/php-fpm\.d/[a-z0-9_-]+\.conf$ ]]; then
    echo "Gecersiz hedef yolu." >&2
    exit 1
fi

if [ ! -f "$KAYNAK" ]; then
    echo "Kaynak dosya bulunamadi." >&2
    exit 1
fi

cp "$KAYNAK" "$HEDEF"
chmod 640 "$HEDEF"
echo "OK"
