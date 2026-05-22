#!/bin/bash
# =============================================================
# GMS Panel - Sunucu Kurulum Scripti
# Alma Linux 9 - Nginx + PHP (7.4-8.4) + MariaDB 11.4
# Kullanim: bash gms-kur.sh
# Github: https://github.com/GMS-Panel/gms-panel
# =============================================================

set -e

KIRMIZI='\033[0;31m'
YESIL='\033[0;32m'
SARI='\033[1;33m'
MAVI='\033[0;34m'
CYAN='\033[0;36m'
SIFIRLA='\033[0m'

GITHUB_RAW="https://raw.githubusercontent.com/GMS-Panel/gms-panel/main"

log()     { echo -e "${YESIL}[GMS]${SIFIRLA} $1"; }
uyari()   { echo -e "${SARI}[UYARI]${SIFIRLA} $1"; }
hata()    { echo -e "${KIRMIZI}[HATA]${SIFIRLA} $1"; exit 1; }
baslik()  { echo -e "\n${MAVI}========== $1 ==========${SIFIRLA}"; }
tamam()   { echo -e "  ${YESIL}[OK]${SIFIRLA}  $1"; }
bilgi()   { echo -e "  ${SARI}[!!]${SIFIRLA}  $1"; }
olumsuz() { echo -e "${KIRMIZI}[XX]${SIFIRLA}  $1"; }

# =============================================================
# SISTEM KONTROLU
# =============================================================
sistem_kontrol() {
    clear
    echo -e "${MAVI}"
    echo "  ██████╗ ███╗   ███╗███████╗"
    echo " ██╔════╝ ████╗ ████║██╔════╝"
    echo " ██║  ███╗██╔████╔██║███████╗"
    echo " ██║   ██║██║╚██╔╝██║╚════██║"
    echo " ╚██████╔╝██║ ╚═╝ ██║███████║"
    echo "  ╚═════╝ ╚═╝     ╚═╝╚══════╝"
    echo -e "${SIFIRLA}"
    echo " GMS Panel - Sunucu Kurulum Scripti"
    echo " Alma Linux 9 | Nginx | PHP 7.4-8.4 | MariaDB 11.4"
    echo " https://github.com/GMS-Panel/gms-panel"
    echo ""

    baslik "SISTEM KONTROLU"
    echo ""

    HATA_VAR=0
    UYARI_VAR=0

    # Root kontrolu
    if [ "$EUID" -eq 0 ]; then
        tamam "Root kullanici"
    else
        olumsuz "Root kullanici degil!"
        HATA_VAR=1
    fi

    # Alma Linux kontrolu
    if grep -q "AlmaLinux 9" /etc/os-release; then
        ALMA_VER=$(grep "VERSION_ID" /etc/os-release | cut -d'"' -f2)
        tamam "Alma Linux ${ALMA_VER}"
    elif grep -q "AlmaLinux" /etc/os-release; then
        bilgi "Alma Linux mevcut ama versiyon 9 degil"
        UYARI_VAR=1
    else
        olumsuz "Bu script sadece Alma Linux icin tasarlanmistir!"
        HATA_VAR=1
    fi

    # Internet baglantisi
    if curl -s --connect-timeout 5 https://google.com > /dev/null 2>&1; then
        tamam "Internet baglantisi"
    else
        olumsuz "Internet baglantisi yok!"
        HATA_VAR=1
    fi

    # GitHub erisimi
    if curl -s --connect-timeout 5 https://raw.githubusercontent.com > /dev/null 2>&1; then
        tamam "GitHub erisimi"
    else
        olumsuz "GitHub'a erisim yok!"
        HATA_VAR=1
    fi

    # Disk alani
    DISK_BOSTA=$(df / | awk 'NR==2 {print int($4/1024/1024)}')
    if [ "$DISK_BOSTA" -ge 10 ]; then
        tamam "Disk alani: ${DISK_BOSTA}GB bos"
    elif [ "$DISK_BOSTA" -ge 5 ]; then
        bilgi "Disk alani: ${DISK_BOSTA}GB bos (min 10GB onerilir)"
        UYARI_VAR=1
    else
        olumsuz "Disk alani: ${DISK_BOSTA}GB bos (cok az!)"
        HATA_VAR=1
    fi

    # RAM
    RAM_MB=$(free -m | awk 'NR==2 {print $2}')
    if [ "$RAM_MB" -ge 2048 ]; then
        tamam "RAM: ${RAM_MB}MB"
    elif [ "$RAM_MB" -ge 1024 ]; then
        bilgi "RAM: ${RAM_MB}MB (min 2GB onerilir)"
        UYARI_VAR=1
    else
        olumsuz "RAM: ${RAM_MB}MB (cok az!)"
        UYARI_VAR=1
    fi

    # SELinux
    SELINUX=$(getenforce 2>/dev/null || echo "Bilinmiyor")
    if [ "$SELINUX" = "Disabled" ]; then
        tamam "SELinux: Devre disi"
    else
        bilgi "SELinux: ${SELINUX} (kurulum devre disi birakacak)"
        UYARI_VAR=1
    fi

    # Firewall
    if systemctl is-active firewalld > /dev/null 2>&1; then
        bilgi "Firewall: Aktif (ayarlar guncellenecek)"
    else
        bilgi "Firewall: Devre disi (kurulum aktif edecek)"
    fi

    # Mevcut servisler
    if systemctl is-active nginx > /dev/null 2>&1; then
        bilgi "Nginx: Zaten kurulu (yeniden yapilandirilacak)"
        UYARI_VAR=1
    else
        tamam "Nginx: Kurulu degil"
    fi

    if systemctl is-active mariadb > /dev/null 2>&1; then
        bilgi "MariaDB: Zaten kurulu (yeniden yapilandirilacak)"
        UYARI_VAR=1
    else
        tamam "MariaDB: Kurulu degil"
    fi

    if rpm -q php > /dev/null 2>&1; then
        bilgi "PHP: Sistemde mevcut (cakisma olabilir)"
        UYARI_VAR=1
    else
        tamam "PHP: Kurulu degil"
    fi

    # Hostname
    HOSTNAME=$(hostname)
    if [ "$HOSTNAME" != "localhost" ] && [ "$HOSTNAME" != "localhost.localdomain" ]; then
        tamam "Hostname: ${HOSTNAME}"
    else
        bilgi "Hostname: ${HOSTNAME} (degistirmeniz onerilir)"
        UYARI_VAR=1
    fi

    tamam "Kernel: $(uname -r)"

    echo ""
    echo -e "${CYAN}========================================${SIFIRLA}"

    if [ "$HATA_VAR" -eq 1 ]; then
        echo ""
        olumsuz "Kritik hatalar tespit edildi, kurulum devam edemez!"
        echo ""
        exit 1
    fi

    if [ "$UYARI_VAR" -eq 1 ]; then
        echo ""
        bilgi "Uyarilar var ama kurulum devam edebilir."
        echo ""
        read -p "Devam etmek istiyor musunuz? (e/h): " ONAY
        if [ "$ONAY" != "e" ] && [ "$ONAY" != "E" ]; then
            echo "Kurulum iptal edildi."
            exit 0
        fi
    else
        echo ""
        tamam "Tum kontroller basarili!"
        echo ""
        sleep 2
    fi
}

# =============================================================
# BILGI ALMA
# =============================================================
bilgi_al() {
    baslik "KURULUM BILGILERI"
    echo ""
    read -p "Sunucu ana domain (ornek: gms.tr): " ANA_DOMAIN
    read -p "Panel domain (ornek: panel.gms.tr): " PANEL_DOMAIN
    read -s -p "MariaDB root sifresi: " DB_ROOT_SIFRE
    echo ""
    read -p "SSL sertifikasi alinsin mi? (e/h): " SSL_SECIM
    if [ "$SSL_SECIM" = "e" ] || [ "$SSL_SECIM" = "E" ]; then
        read -p "Eposta adresi (SSL icin): " EPOSTA
    fi
    echo ""
    log "Kurulum basliyor..."
    echo ""
}

# =============================================================
# 1 - SISTEM GUNCELLEME
# =============================================================
sistem_guncelle() {
    baslik "1/10 - Sistem Guncelleme"
    dnf update -y
    dnf install -y wget curl vim git net-tools bash-completion
    log "Sistem guncellendi."
}

# =============================================================
# 2 - FIREWALL
# =============================================================
firewall_kur() {
    baslik "2/10 - Firewall"
    systemctl enable --now firewalld
    firewall-cmd --permanent --add-service=ssh
    firewall-cmd --permanent --add-service=http
    firewall-cmd --permanent --add-service=https
    firewall-cmd --permanent --add-service=ftp
    firewall-cmd --permanent --add-service=smtp
    firewall-cmd --permanent --add-service=imaps
    firewall-cmd --permanent --add-port=3306/tcp
    firewall-cmd --permanent --remove-service=cockpit 2>/dev/null || true
    firewall-cmd --permanent --remove-service=dhcpv6-client 2>/dev/null || true
    firewall-cmd --reload
    log "Firewall ayarlandi."
}

# =============================================================
# 3 - NGINX
# =============================================================
nginx_kur() {
    baslik "3/10 - Nginx"
    dnf install -y nginx
    systemctl enable --now nginx

    if ! grep -q "charset utf-8" /etc/nginx/nginx.conf; then
        sed -i 's/keepalive_timeout   65;/keepalive_timeout   65;\n    charset utf-8;/' /etc/nginx/nginx.conf
    fi

    cat > /etc/nginx/conf.d/00-default.conf << 'EOF'
server {
    listen 80 default_server;
    server_name _;
    return 444;
}
EOF

    nginx -t
    systemctl reload nginx
    log "Nginx kuruldu."
}

# =============================================================
# 4 - PHP
# =============================================================
php_kur() {
    baslik "4/10 - PHP (7.4, 8.0, 8.1, 8.2, 8.3, 8.4)"
    dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm

    for VER in 74 80 81 82 83 84; do
        log "PHP ${VER} kuruluyor..."
        dnf install -y php${VER} php${VER}-php-fpm php${VER}-php-mysqlnd \
            php${VER}-php-curl php${VER}-php-gd php${VER}-php-mbstring \
            php${VER}-php-xml php${VER}-php-zip php${VER}-php-json \
            php${VER}-php-opcache php${VER}-php-intl 2>/dev/null || uyari "PHP ${VER} kurulamadi."

        CONF="/etc/opt/remi/php${VER}/php-fpm.d/www.conf"
        if [ -f "$CONF" ]; then
            sed -i 's/listen.acl_users = apache$/listen.acl_users = apache,nginx/' $CONF
        fi

        systemctl enable --now php${VER}-php-fpm 2>/dev/null || true
    done

    log "Tum PHP versiyonlari kuruldu."
}

# =============================================================
# 5 - MARIADB
# =============================================================
mariadb_kur() {
    baslik "5/10 - MariaDB 11.4"
    curl -LsS https://downloads.mariadb.com/MariaDB/mariadb_repo_setup | bash -s -- --mariadb-server-version="mariadb-11.4"
    dnf install -y MariaDB-server MariaDB-client
    systemctl enable --now mariadb
    mysql_upgrade 2>/dev/null || true
    systemctl restart mariadb

    mysql -u root << SQLEOF
ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_ROOT_SIFRE}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
SQLEOF

    log "MariaDB kuruldu."
}

# =============================================================
# 6 - PHPMYADMIN
# =============================================================
phpmyadmin_kur() {
    baslik "6/10 - phpMyAdmin"
    dnf install -y phpmyadmin
    log "phpMyAdmin kuruldu."
}

# =============================================================
# 7 - KLASOR YAPISI
# =============================================================
klasor_olustur() {
    baslik "7/10 - Klasor Yapisi"

    useradd -d /home/gmsadmin -m -s /bin/bash gmsadmin 2>/dev/null || uyari "gmsadmin zaten var."
    mkdir -p /home/gmsadmin/public_html /home/gmsadmin/logs
    chown -R gmsadmin:gmsadmin /home/gmsadmin
    chmod 755 /home/gmsadmin /home/gmsadmin/public_html

    useradd -d /home/panel -m -s /bin/bash panel 2>/dev/null || uyari "panel zaten var."
    mkdir -p /home/panel/public_html /home/panel/logs
    chown -R panel:panel /home/panel
    chmod 755 /home/panel /home/panel/public_html

    log "Klasor yapisi olusturuldu."
}

# =============================================================
# 8 - GITHUB DAN DOSYALARI CEK
# =============================================================
dosyalari_cek() {
    baslik "8/10 - GitHub'dan Dosyalar Cekiliyor"

    log "Ana site dosyasi indiriliyor..."
    wget -q "${GITHUB_RAW}/web/index.html" -O /home/gmsadmin/public_html/index.html
    chown gmsadmin:gmsadmin /home/gmsadmin/public_html/index.html

    log "Panel dosyasi indiriliyor..."
    wget -q "${GITHUB_RAW}/web/panel/index.php" -O /home/panel/public_html/index.php
    chown panel:panel /home/panel/public_html/index.php

    log "yeni-hesap.sh indiriliyor..."
    wget -q "${GITHUB_RAW}/scripts/yeni-hesap.sh" -O /usr/local/bin/yeni-hesap.sh
    chmod +x /usr/local/bin/yeni-hesap.sh

    log "Dosyalar indirildi."
}

# =============================================================
# 9 - NGINX VHOST
# =============================================================
nginx_vhost() {
    baslik "9/10 - Nginx Vhost"

    cat > /etc/nginx/conf.d/${ANA_DOMAIN}.conf << EOF
server {
    listen 80;
    server_name ${ANA_DOMAIN} www.${ANA_DOMAIN};
    root /home/gmsadmin/public_html;
    index index.php index.html;
    access_log /home/gmsadmin/logs/access.log;
    error_log /home/gmsadmin/logs/error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/opt/remi/php83/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

    cat > /etc/nginx/conf.d/${PANEL_DOMAIN}.conf << EOF
server {
    listen 80;
    server_name ${PANEL_DOMAIN};
    root /home/panel/public_html;
    index index.php index.html;
    access_log /home/panel/logs/access.log;
    error_log /home/panel/logs/error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /phpmyadmin {
        alias /usr/share/phpMyAdmin;
        index index.php;

        location ~ \.php$ {
            fastcgi_pass unix:/var/opt/remi/php83/run/php-fpm/www.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$request_filename;
            include fastcgi_params;
        }
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/opt/remi/php83/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

    nginx -t
    systemctl reload nginx
    log "Nginx vhost ayarlandi."
}

# =============================================================
# 10 - SSL
# =============================================================
ssl_kur() {
    baslik "10/10 - SSL Sertifikasi"
    if [ "$SSL_SECIM" = "e" ] || [ "$SSL_SECIM" = "E" ]; then
        dnf install -y certbot python3-certbot-nginx
        systemctl enable --now certbot-renew.timer
        certbot --nginx -d ${ANA_DOMAIN} --non-interactive --agree-tos -m ${EPOSTA} || uyari "${ANA_DOMAIN} SSL alinamadi."
        certbot --nginx -d ${PANEL_DOMAIN} --non-interactive --agree-tos -m ${EPOSTA} || uyari "${PANEL_DOMAIN} SSL alinamadi."
    else
        uyari "SSL atlandi. Sonradan: certbot --nginx -d ${ANA_DOMAIN}"
    fi
}

# =============================================================
# OZET
# =============================================================
ozet_goster() {
    clear
    echo -e "${YESIL}"
    echo "  ╔═══════════════════════════════════════════╗"
    echo "  ║         KURULUM TAMAMLANDI!               ║"
    echo "  ╚═══════════════════════════════════════════╝"
    echo -e "${SIFIRLA}"
    echo -e " ${CYAN}Adresler:${SIFIRLA}"
    echo "   Ana Site   : https://${ANA_DOMAIN}"
    echo "   Panel      : https://${PANEL_DOMAIN}"
    echo "   phpMyAdmin : https://${PANEL_DOMAIN}/phpmyadmin"
    echo ""
    echo -e " ${CYAN}Veritabani:${SIFIRLA}"
    echo "   Kullanici  : root"
    echo "   Sifre      : ${DB_ROOT_SIFRE}"
    echo ""
    echo -e " ${CYAN}Servisler:${SIFIRLA}"
    echo "   PHP        : 7.4, 8.0, 8.1, 8.2, 8.3, 8.4"
    echo "   MariaDB    : 11.4"
    echo "   Nginx      : $(nginx -v 2>&1 | cut -d'/' -f2)"
    echo "   Firewall   : Aktif"
    echo ""
    echo -e " ${SARI}[!!] Bu bilgileri guvenli bir yere kaydedin!${SIFIRLA}"
    echo ""
}

# =============================================================
# ANA AKIS
# =============================================================
sistem_kontrol
bilgi_al
sistem_guncelle
firewall_kur
nginx_kur
php_kur
mariadb_kur
phpmyadmin_kur
klasor_olustur
dosyalari_cek
nginx_vhost
ssl_kur
ozet_goster