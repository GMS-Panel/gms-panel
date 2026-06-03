#!/bin/bash
# =============================================================
# GMS Panel - Auth Yapılandırma Scripti
# Kurulum sonrası bir kez çalıştırılır.
# Kullanım: bash setup-auth.sh
# =============================================================

if [ "$EUID" -ne 0 ]; then
    echo "[HATA] Root yetkisi gerekli: sudo bash setup-auth.sh"
    exit 1
fi

# Panel kullanıcısını settings.conf'tan oku
SETTINGS_FILE=$(find /home/*/config/settings.conf 2>/dev/null | head -1)
if [ -z "$SETTINGS_FILE" ]; then
    echo "[HATA] settings.conf bulunamadı. Kurulum tamamlanmış mı?"
    exit 1
fi

PANEL_KULLANICI=$(grep "^PANEL_KULLANICI=" "$SETTINGS_FILE" | cut -d'=' -f2)
echo "[GMS] Panel kullanıcısı: $PANEL_KULLANICI"

# Şifreyi al
while true; do
    read -s -p " Panel şifresi (en az 8 karakter): " PANEL_SIFRE
    echo ""
    [ ${#PANEL_SIFRE} -ge 8 ] && break
    echo "[HATA] Şifre en az 8 karakter olmalı!"
done

# PHP ile hash oluştur
HASH=$(php -r "echo password_hash('${PANEL_SIFRE}', PASSWORD_DEFAULT);")

# auth.conf oluştur
mkdir -p /etc/gms
cat > /etc/gms/auth.conf << EOF
# GMS Panel Auth Yapılandırması
# Oluşturma tarihi: $(date '+%d.%m.%Y %H:%M')
USER=${PANEL_KULLANICI}
HASH=${HASH}
EOF

chmod 600 /etc/gms/auth.conf
chown root:root /etc/gms/auth.conf

echo "[GMS] auth.conf oluşturuldu: /etc/gms/auth.conf"
echo "[GMS] Panel artık kullanıma hazır!"
