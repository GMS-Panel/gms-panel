#!/bin/bash
# =============================================================
# GMS Panel - Sunucu Kurulum Scripti v2.0
# Alma Linux 9 - Nginx + PHP (7.4-8.4) + MariaDB 11.4
# Kullanim: bash gms-kur.sh
# Github: https://github.com/GMS-Panel/gms-panel
# =============================================================

KIRMIZI='\033[0;31m'
YESIL='\033[0;32m'
SARI='\033[1;33m'
MAVI='\033[0;34m'
CYAN='\033[0;36m'
SIFIRLA='\033[0m'

GITHUB_RAW="https://raw.githubusercontent.com/GMS-Panel/gms-panel/main"

log()     { echo -e "${YESIL}[GMS]${SIFIRLA} $1"; }
uyari()   { echo -e "${SARI}[UYARI]${SIFIRLA} $1"; }
hata()    { echo -e "${KIRMIZI}[HATA]${SIFIRLA} $1"; }
baslik()  { echo -e "\n${MAVI}========================================${SIFIRLA}\n ${1}\n${MAVI}========================================${SIFIRLA}"; }
tamam()   { echo -e "  ${YESIL}[OK]${SIFIRLA}  $1"; }
olumsuz() { echo -e "  ${KIRMIZI}[XX]${SIFIRLA}  $1"; }
bilgi()   { echo -e "  ${SARI}[!!]${SIFIRLA}  $1"; }
duzelt()  { echo -e "       ${CYAN}→${SIFIRLA} $1"; }

logo() {
    clear
    echo -e "${MAVI}"
    echo "  ██████╗ ███╗   ███╗███████╗"
    echo " ██╔════╝ ████╗ ████║██╔════╝"
    echo " ██║  ███╗██╔████╔██║███████╗"
    echo " ██║   ██║██║╚██╔╝██║╚════██║"
    echo " ╚██████╔╝██║ ╚═╝ ██║███████║"
    echo "  ╚═════╝ ╚═╝     ╚═╝╚══════╝"
    echo -e "${SIFIRLA}"
    echo " GMS Panel Kurulum Scripti v2.0"
    echo " https://github.com/GMS-Panel/gms-panel"
    echo ""
}

# =============================================================
# DNS KALICI AYAR
# =============================================================
dns_ayarla() {
    # Aktif baglanti adini bul
    AKTIF_CON=$(nmcli -t -f NAME con show --active | head -1)

    if [ -z "$AKTIF_CON" ]; then
        uyari "Aktif network baglantisi bulunamadi, DNS manuel ayarlaniyor..."
        echo "nameserver 8.8.8.8" > /etc/resolv.conf
        echo "nameserver 8.8.4.4" >> /etc/resolv.conf
        return
    fi

    # DNS'i NetworkManager uzerinden kalici ayarla
    nmcli con mod "$AKTIF_CON" ipv4.dns "8.8.8.8 8.8.4.4"
    nmcli con up "$AKTIF_CON" > /dev/null 2>&1

    # resolv.conf'un ezilmesini engelle
    echo "nameserver 8.8.8.8" > /etc/resolv.conf
    echo "nameserver 8.8.4.4" >> /etc/resolv.conf
    chattr +i /etc/resolv.conf

    tamam "DNS kalici olarak ayarlandi (8.8.8.8 / 8.8.4.4)"
}

# =============================================================
# SISTEM KONTROLU
# =============================================================
sistem_kontrol() {
    logo
    baslik "SISTEM KONTROLU"
    echo ""

    KRITIK_HATA=0

    # Root kontrolu (Duzeltilemeyen)
    if [ "$EUID" -eq 0 ]; then
        tamam "Root kullanici"
    else
        olumsuz "Root kullanici degil!"
        duzelt "Cozum: sudo bash gms-kur.sh"
        KRITIK_HATA=1
    fi

    # Alma Linux 9 kontrolu (Duzeltilemeyen)
    if grep -q "AlmaLinux 9" /etc/os-release 2>/dev/null; then
        ALMA_VER=$(grep "VERSION_ID" /etc/os-release | cut -d'"' -f2)
        tamam "Alma Linux ${ALMA_VER}"
    elif grep -q "AlmaLinux" /etc/os-release 2>/dev/null; then
        olumsuz "Alma Linux mevcut ama versiyon 9 degil!"
        duzelt "Cozum: Alma Linux 9 kurulu olmalidir."
        KRITIK_HATA=1
    else
        olumsuz "Alma Linux tespit edilemedi!"
        duzelt "Cozum: Bu script sadece Alma Linux 9 icin tasarlanmistir."
        KRITIK_HATA=1
    fi

    # DNS kalici ayarla (Internet testinden once)
    dns_ayarla

    # Internet baglantisi (Duzeltilemeyen)
    if curl -s --connect-timeout 5 https://google.com > /dev/null 2>&1; then
        tamam "Internet baglantisi"
    else
        olumsuz "Internet baglantisi yok!"
        duzelt "Cozum: ip addr show ve ip route komutlarini kontrol edin."
        KRITIK_HATA=1
    fi

    # GitHub erisimi (Duzeltilemeyen)
    if curl -s --connect-timeout 5 https://raw.githubusercontent.com > /dev/null 2>&1; then
        tamam "GitHub erisimi"
    else
        olumsuz "GitHub'a erisim yok!"
        duzelt "Cozum: Sunucunuzun disariya erisimi olmali."
        KRITIK_HATA=1
    fi

    # Disk alani - min 10GB (Duzeltilemeyen)
    DISK_GB=$(df / | awk 'NR==2 {print int($4/1024/1024)}')
    DISK_TOPLAM=$(df / | awk 'NR==2 {print int($2/1024/1024)}')
    if [ "$DISK_GB" -ge 10 ]; then
        tamam "Disk: ${DISK_GB}GB bos / ${DISK_TOPLAM}GB toplam"
    elif [ "$DISK_GB" -ge 5 ]; then
        bilgi "Disk: ${DISK_GB}GB bos - Minimum 10GB onerilir, devam edilebilir."
    else
        olumsuz "Disk: ${DISK_GB}GB bos - Cok az!"
        duzelt "Cozum: En az 10GB bos disk alani olmalidir."
        KRITIK_HATA=1
    fi

    # RAM - min 1GB (Duzeltilemeyen)
    RAM_MB=$(free -m | awk 'NR==2 {print $2}')
    if [ "$RAM_MB" -ge 2048 ]; then
        tamam "RAM: ${RAM_MB}MB"
    elif [ "$RAM_MB" -ge 1024 ]; then
        bilgi "RAM: ${RAM_MB}MB - Minimum 2GB onerilir, devam edilebilir."
    else
        olumsuz "RAM: ${RAM_MB}MB - Yetersiz!"
        duzelt "Cozum: VM ayarlarindan en az 1GB RAM tanimlayın."
        KRITIK_HATA=1
    fi

    # SELinux (Otomatik duzeltilir)
    SELINUX=$(getenforce 2>/dev/null || echo "Bilinmiyor")
    if [ "$SELINUX" = "Disabled" ]; then
        tamam "SELinux: Devre disi"
    else
        bilgi "SELinux: ${SELINUX} - Otomatik devre disi birakilacak"
        setenforce 0 2>/dev/null || true
        sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config 2>/dev/null || true
        tamam "SELinux: Devre disi birakildi"
    fi

    # Firewall (Otomatik duzeltilir - kurulum ayarlar)
    if systemctl is-active firewalld > /dev/null 2>&1; then
        bilgi "Firewall: Aktif - Kurulum portlari ayarlayacak"
    else
        bilgi "Firewall: Devre disi - Kurulum aktif edip ayarlayacak"
    fi

    # Hostname (Otomatik duzeltilir)
    MEVCUT_HOSTNAME=$(hostname)
    if [ "$MEVCUT_HOSTNAME" = "localhost" ] || [ "$MEVCUT_HOSTNAME" = "localhost.localdomain" ]; then
        bilgi "Hostname: ${MEVCUT_HOSTNAME} - Kurulumda guncellenecek"
    else
        tamam "Hostname: ${MEVCUT_HOSTNAME}"
    fi

    # Timezone (Otomatik duzeltilir)
    TZ=$(timedatectl show --property=Timezone --value 2>/dev/null || echo "Bilinmiyor")
    if [ "$TZ" = "Europe/Istanbul" ]; then
        tamam "Timezone: ${TZ}"
    else
        bilgi "Timezone: ${TZ} - Europe/Istanbul olarak ayarlanacak"
        timedatectl set-timezone Europe/Istanbul 2>/dev/null || true
        tamam "Timezone: Europe/Istanbul olarak ayarlandi"
    fi

    # Mevcut servis kontrolu (Bilgi amacli)
    systemctl is-active nginx > /dev/null 2>&1 && bilgi "Nginx zaten kurulu - Yeniden yapilandirilacak"
    systemctl is-active mariadb > /dev/null 2>&1 && bilgi "MariaDB zaten kurulu - Yeniden yapilandirilacak"
    rpm -q php > /dev/null 2>&1 && bilgi "PHP mevcut - Cakisma olabilir, kontrol edilecek"

    # Sonuc
    echo ""
    echo -e "${CYAN}========================================${SIFIRLA}"
    echo ""

    if [ "$KRITIK_HATA" -eq 1 ]; then
        hata "Kritik hatalar tespit edildi. Kurulum durduruluyor."
        echo ""
        echo " Yukaridaki hatalari duzeltip scripti tekrar calistirin."
        echo " Yardim icin: https://github.com/GMS-Panel/gms-panel"
        echo ""
        exit 1
    fi

    tamam "Sistem GMS Panel kurulumu icin hazir!"
    echo ""
    read -p " Kuruluma devam etmek istiyor musunuz? (e/h): " ONAY
    if [ "$ONAY" != "e" ] && [ "$ONAY" != "E" ]; then
        echo " Kurulum iptal edildi."
        exit 0
    fi
}

# =============================================================
# BILGI ALMA
# =============================================================
bilgi_al() {
    echo ""
    baslik "KURULUM BILGILERI"
    echo ""
    echo " Lutfen asagidaki bilgileri doldurun."
    echo " Bu bilgiler sisteminizin temel ayarlarini olusturacaktir."
    echo ""

    # Panel yonetici kullanici adi
    while true; do
        read -p " Panel yonetici kullanici adi (ornek: gmspanel): " PANEL_KULLANICI
        if [ -z "$PANEL_KULLANICI" ]; then
            olumsuz "Kullanici adi bos birakilamaz!"
        elif echo "$PANEL_KULLANICI" | grep -qP '[^a-z0-9_]'; then
            olumsuz "Sadece kucuk harf, rakam ve alt cizgi kullanin!"
        else
            break
        fi
    done

    # Panel yonetici sifresi
    while true; do
        read -s -p " Panel yonetici sifresi: " PANEL_SIFRE
        echo ""
        if [ ${#PANEL_SIFRE} -lt 8 ]; then
            olumsuz "Sifre en az 8 karakter olmalidir!"
        else
            break
        fi
    done

    # Ana domain
    while true; do
        read -p " Ana domain (ornek: firma.com): " ANA_DOMAIN
        [ -n "$ANA_DOMAIN" ] && break
        olumsuz "Domain bos birakilamaz!"
    done

    # Panel domain
    while true; do
        read -p " Panel domain (ornek: panel.firma.com): " PANEL_DOMAIN
        [ -n "$PANEL_DOMAIN" ] && break
        olumsuz "Panel domain bos birakilamaz!"
    done

    # MariaDB sifresi
    while true; do
        read -s -p " MariaDB root sifresi: " DB_ROOT_SIFRE
        echo ""
        if [ ${#DB_ROOT_SIFRE} -lt 8 ]; then
            olumsuz "Sifre en az 8 karakter olmalidir!"
        else
            break
        fi
    done

    # SSL
    read -p " SSL sertifikasi alinsin mi? (e/h): " SSL_SECIM
    if [ "$SSL_SECIM" = "e" ] || [ "$SSL_SECIM" = "E" ]; then
        while true; do
            read -p " Eposta adresi (SSL icin): " EPOSTA
            [ -n "$EPOSTA" ] && break
            olumsuz "Eposta bos birakilamaz!"
        done
    fi

    # Hostname guncelle
    hostnamectl set-hostname "$PANEL_DOMAIN" 2>/dev/null || true

    # Ozet
    echo ""
    echo -e " ${CYAN}Kurulum ozeti:${SIFIRLA}"
    echo "   Panel Kullanici : ${PANEL_KULLANICI}"
    echo "   Ana Domain      : ${ANA_DOMAIN}"
    echo "   Panel Domain    : ${PANEL_DOMAIN}"
    echo "   SSL             : ${SSL_SECIM}"
    echo ""
    read -p " Bilgiler dogru mu? Kuruluma baslayalim mi? (e/h): " BASLAT
    if [ "$BASLAT" != "e" ] && [ "$BASLAT" != "E" ]; then
        echo " Kurulum iptal edildi."
        exit 0
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
    dnf update -y -q
    dnf install -y -q wget curl vim nano git net-tools bash-completion
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
    dnf install -y -q nginx
    systemctl enable --now nginx

    # UTF-8 ekle
    if ! grep -q "charset utf-8" /etc/nginx/nginx.conf; then
        sed -i 's/keepalive_timeout   65;/keepalive_timeout   65;\n    charset utf-8;/' /etc/nginx/nginx.conf
    fi

    # IP ile direkt erisimi engelle
    cat > /etc/nginx/conf.d/00-default.conf << 'EOF'
server {
    listen 80 default_server;
    server_name _;
    return 444;
}
EOF

    nginx -t && systemctl reload nginx
    log "Nginx kuruldu."
}

# =============================================================
# 4 - PHP
# =============================================================
php_kur() {
    baslik "4/10 - PHP (7.4, 8.0, 8.1, 8.2, 8.3, 8.4)"
    dnf install -y -q https://rpms.remirepo.net/enterprise/remi-release-9.rpm

    for VER in 74 80 81 82 83 84; do
        log "PHP ${VER} kuruluyor..."
        dnf install -y -q php${VER} php${VER}-php-fpm php${VER}-php-mysqlnd \
            php${VER}-php-curl php${VER}-php-gd php${VER}-php-mbstring \
            php${VER}-php-xml php${VER}-php-zip php${VER}-php-json \
            php${VER}-php-opcache php${VER}-php-intl 2>/dev/null || uyari "PHP ${VER} kurulamadi."

        CONF="/etc/opt/remi/php${VER}/php-fpm.d/www.conf"
        if [ -f "$CONF" ]; then
            sed -i 's/listen.acl_users = apache$/listen.acl_users = apache,nginx/' "$CONF"
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
    curl -LsS https://downloads.mariadb.com/MariaDB/mariadb_repo_setup \
        | bash -s -- --mariadb-server-version="mariadb-11.4" > /dev/null 2>&1
    dnf install -y -q MariaDB-server MariaDB-client
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
    dnf install -y -q phpmyadmin
    log "phpMyAdmin kuruldu."
}

# =============================================================
# 7 - PANEL KULLANICISI VE KLASOR YAPISI
# =============================================================
klasor_olustur() {
    baslik "7/10 - Kullanici ve Klasor Yapisi"

    # Panel yonetici kullanicisi olustur
    useradd -d /home/${PANEL_KULLANICI} -m -s /bin/bash ${PANEL_KULLANICI} 2>/dev/null || true
    echo "${PANEL_KULLANICI}:${PANEL_SIFRE}" | chpasswd

    # Panel kullanicisina sudo yetkisi ver
    usermod -aG wheel ${PANEL_KULLANICI}

    # Panel klasor yapisi
    mkdir -p /home/${PANEL_KULLANICI}/gms-panel     # Panel web arayuzu
    mkdir -p /home/${PANEL_KULLANICI}/app            # Panel uygulama dosyalari
    mkdir -p /home/${PANEL_KULLANICI}/logs           # Panel loglari
    mkdir -p /home/${PANEL_KULLANICI}/config         # Panel ayarlari

    chown -R ${PANEL_KULLANICI}:${PANEL_KULLANICI} /home/${PANEL_KULLANICI}
    chmod 755 /home/${PANEL_KULLANICI}
    chmod 750 /home/${PANEL_KULLANICI}/config

    # Ana domain kullanicisi ve klasoru
    ANA_DOMAIN_KULLANICI=$(echo "$ANA_DOMAIN" | tr '.' '_')
    useradd -d /home/${ANA_DOMAIN_KULLANICI} -m -s /bin/bash ${ANA_DOMAIN_KULLANICI} 2>/dev/null || true
    mkdir -p /home/${ANA_DOMAIN_KULLANICI}/public_html
    mkdir -p /home/${ANA_DOMAIN_KULLANICI}/logs
    chown -R ${ANA_DOMAIN_KULLANICI}:${ANA_DOMAIN_KULLANICI} /home/${ANA_DOMAIN_KULLANICI}
    chmod 755 /home/${ANA_DOMAIN_KULLANICI} /home/${ANA_DOMAIN_KULLANICI}/public_html

    # Sistem ayarlarini kaydet
    cat > /home/${PANEL_KULLANICI}/config/settings.conf << EOF
# GMS Panel Sistem Ayarlari
# Olusturma tarihi: $(date '+%d.%m.%Y %H:%M')

PANEL_KULLANICI=${PANEL_KULLANICI}
ANA_DOMAIN=${ANA_DOMAIN}
PANEL_DOMAIN=${PANEL_DOMAIN}
KURULUM_TARIHI=$(date '+%d.%m.%Y %H:%M')
GMS_VERSIYON=2.0
EOF

    chown ${PANEL_KULLANICI}:${PANEL_KULLANICI} /home/${PANEL_KULLANICI}/config/settings.conf
    chmod 640 /home/${PANEL_KULLANICI}/config/settings.conf

    log "Kullanici ve klasor yapisi olusturuldu."
}

# =============================================================
# 8 - GITHUB DAN DOSYALARI CEK
# =============================================================
dosyalari_cek() {
    baslik "8/10 - Dosyalar Indiriliyor"

    # Panel web arayuzu
    log "Panel dosyalari indiriliyor..."
    wget -q "${GITHUB_RAW}/panel/index.php" \
        -O /home/${PANEL_KULLANICI}/gms-panel/index.php
    chown ${PANEL_KULLANICI}:${PANEL_KULLANICI} \
        /home/${PANEL_KULLANICI}/gms-panel/index.php

    # Ana site
    log "Ana site dosyasi indiriliyor..."
    ANA_DOMAIN_KULLANICI=$(echo "$ANA_DOMAIN" | tr '.' '_')
    wget -q "${GITHUB_RAW}/site/index.html" \
        -O /home/${ANA_DOMAIN_KULLANICI}/public_html/index.html
    chown ${ANA_DOMAIN_KULLANICI}:${ANA_DOMAIN_KULLANICI} \
        /home/${ANA_DOMAIN_KULLANICI}/public_html/index.html

    # yeni-hesap scripti
    log "yeni-hesap.sh indiriliyor..."
    wget -q "${GITHUB_RAW}/scripts/yeni-hesap.sh" \
        -O /usr/local/bin/yeni-hesap.sh
    chmod +x /usr/local/bin/yeni-hesap.sh

    log "Dosyalar indirildi."
}

# =============================================================
# 9 - NGINX VHOST
# =============================================================
nginx_vhost() {
    baslik "9/10 - Nginx Vhost"

    ANA_DOMAIN_KULLANICI=$(echo "$ANA_DOMAIN" | tr '.' '_')

    # Ana domain config
    cat > /etc/nginx/conf.d/${ANA_DOMAIN}.conf << EOF
server {
    listen 80;
    server_name ${ANA_DOMAIN} www.${ANA_DOMAIN};
    root /home/${ANA_DOMAIN_KULLANICI}/public_html;
    index index.php index.html;
    access_log /home/${ANA_DOMAIN_KULLANICI}/logs/access.log;
    error_log /home/${ANA_DOMAIN_KULLANICI}/logs/error.log;

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

    # Panel domain config
    cat > /etc/nginx/conf.d/${PANEL_DOMAIN}.conf << EOF
server {
    listen 80;
    server_name ${PANEL_DOMAIN};
    root /home/${PANEL_KULLANICI}/gms-panel;
    index index.php index.html;
    access_log /home/${PANEL_KULLANICI}/logs/access.log;
    error_log /home/${PANEL_KULLANICI}/logs/error.log;

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

    nginx -t && systemctl reload nginx
    log "Nginx vhost ayarlandi."
}

# =============================================================
# 10 - SSL
# =============================================================
ssl_kur() {
    baslik "10/10 - SSL Sertifikasi"
    if [ "$SSL_SECIM" = "e" ] || [ "$SSL_SECIM" = "E" ]; then
        dnf install -y -q certbot python3-certbot-nginx
        systemctl enable --now certbot-renew.timer
        certbot --nginx -d ${ANA_DOMAIN} --non-interactive --agree-tos \
            -m ${EPOSTA} || uyari "${ANA_DOMAIN} SSL alinamadi. DNS ayarlarini kontrol edin."
        certbot --nginx -d ${PANEL_DOMAIN} --non-interactive --agree-tos \
            -m ${EPOSTA} || uyari "${PANEL_DOMAIN} SSL alinamadi. DNS ayarlarini kontrol edin."
    else
        bilgi "SSL atlandi."
        duzelt "Sonradan almak icin: certbot --nginx -d ${ANA_DOMAIN}"
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
    echo -e " ${CYAN}Panel Giris Bilgileri:${SIFIRLA}"
    echo "   Kullanici  : ${PANEL_KULLANICI}"
    echo "   Sifre      : ${PANEL_SIFRE}"
    echo ""
    echo -e " ${CYAN}Veritabani Bilgileri:${SIFIRLA}"
    echo "   Kullanici  : root"
    echo "   Sifre      : ${DB_ROOT_SIFRE}"
    echo ""
    echo -e " ${CYAN}Kurulu Servisler:${SIFIRLA}"
    echo "   PHP        : 7.4, 8.0, 8.1, 8.2, 8.3, 8.4"
    echo "   MariaDB    : 11.4"
    echo "   Nginx      : $(nginx -v 2>&1 | cut -d'/' -f2)"
    echo "   Firewall   : Aktif"
    echo ""
    echo -e " ${CYAN}Ayar Dosyasi:${SIFIRLA}"
    echo "   /home/${PANEL_KULLANICI}/config/settings.conf"
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