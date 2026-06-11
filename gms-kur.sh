#!/bin/bash
# =============================================================
# GMS Panel - Sunucu Kurulum Scripti v2.5
# Alma Linux 9 Minimal - Nginx + PHP (7.4-8.4) + MariaDB 11.4
# Panel erisim: http://SUNUCU_IP:8090
# Github: https://github.com/GMS-Panel/gms-panel
# =============================================================

KIRMIZI='\033[0;31m'
YESIL='\033[0;32m'
SARI='\033[1;33m'
MAVI='\033[0;34m'
CYAN='\033[0;36m'
SIFIRLA='\033[0m'

GMS_SYS_USER="gmssys"
GMS_PANEL_PORT="8090"
GITHUB_REPO="https://github.com/GMS-Panel/gms-panel.git"
GITHUB_RAW="https://raw.githubusercontent.com/GMS-Panel/gms-panel/main"

PANEL_KULLANICI=""
PANEL_SIFRE=""
DB_ROOT_SIFRE=""

log()     { echo -e "${YESIL}[GMS]${SIFIRLA} $1"; }
uyari()   { echo -e "${SARI}[UYARI]${SIFIRLA} $1"; }
hata()    { echo -e "${KIRMIZI}[HATA]${SIFIRLA} $1"; }
baslik()  { echo -e "\n${MAVI}========================================${SIFIRLA}\n ${1}\n${MAVI}========================================${SIFIRLA}"; }
tamam()   { echo -e "  ${YESIL}[OK]${SIFIRLA}  $1"; }
olumsuz() { echo -e "  ${KIRMIZI}[XX]${SIFIRLA}  $1"; }
bilgi()   { echo -e "  ${SARI}[!!]${SIFIRLA}  $1"; }
duzelt()  { echo -e "       ${CYAN}->${SIFIRLA} $1"; }

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
    echo " GMS Panel Kurulum Scripti v2.5"
    echo " https://github.com/GMS-Panel/gms-panel"
    echo ""
}

# =============================================================
# DNS KALICI AYAR
# =============================================================
dns_ayarla() {
    AKTIF_CON=$(nmcli -t -f NAME con show --active 2>/dev/null | head -1)
    if [ -z "$AKTIF_CON" ]; then
        uyari "Aktif network baglantisi bulunamadi, DNS manuel ayarlaniyor..."
        echo "nameserver 8.8.8.8" > /etc/resolv.conf
        echo "nameserver 8.8.4.4" >> /etc/resolv.conf
        return
    fi
    nmcli con mod "$AKTIF_CON" ipv4.dns "8.8.8.8 8.8.4.4"
    nmcli con up "$AKTIF_CON" > /dev/null 2>&1
    chattr -i /etc/resolv.conf 2>/dev/null || true
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

    if [ "$EUID" -eq 0 ]; then
        tamam "Root kullanici"
    else
        olumsuz "Root kullanici degil!"
        duzelt "Cozum: sudo bash gms-kur.sh"
        KRITIK_HATA=1
    fi

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

    dns_ayarla

    if curl -s --connect-timeout 5 https://google.com > /dev/null 2>&1; then
        tamam "Internet baglantisi"
    else
        olumsuz "Internet baglantisi yok!"
        duzelt "Cozum: ip addr show ve ip route komutlarini kontrol edin."
        KRITIK_HATA=1
    fi

    if curl -s --connect-timeout 5 https://raw.githubusercontent.com > /dev/null 2>&1; then
        tamam "GitHub erisimi"
    else
        olumsuz "GitHub'a erisim yok!"
        duzelt "Cozum: Sunucunuzun disariya erisimi olmali."
        KRITIK_HATA=1
    fi

    DISK_GB=$(df / | awk 'NR==2 {print int($4/1024/1024)}')
    DISK_TOPLAM=$(df / | awk 'NR==2 {print int($2/1024/1024)}')
    if [ "$DISK_GB" -ge 10 ]; then
        tamam "Disk: ${DISK_GB}GB bos / ${DISK_TOPLAM}GB toplam"
    elif [ "$DISK_GB" -ge 5 ]; then
        bilgi "Disk: ${DISK_GB}GB bos - Minimum 10GB onerilir, devam edilebilir."
    else
        olumsuz "Disk: ${DISK_GB}GB bos - Yetersiz!"
        duzelt "Cozum: En az 10GB bos disk alani olmalidir."
        KRITIK_HATA=1
    fi

    RAM_MB=$(free -m | awk 'NR==2 {print $2}')
    if [ "$RAM_MB" -ge 2048 ]; then
        tamam "RAM: ${RAM_MB}MB"
    elif [ "$RAM_MB" -ge 1024 ]; then
        bilgi "RAM: ${RAM_MB}MB - Minimum 2GB onerilir, devam edilebilir."
    else
        olumsuz "RAM: ${RAM_MB}MB - Yetersiz!"
        duzelt "Cozum: En az 1GB RAM olmalidir."
        KRITIK_HATA=1
    fi

    SELINUX=$(getenforce 2>/dev/null || echo "Bilinmiyor")
    if [ "$SELINUX" = "Disabled" ]; then
        tamam "SELinux: Devre disi"
    else
        bilgi "SELinux: ${SELINUX} - Otomatik devre disi birakilacak"
        setenforce 0 2>/dev/null || true
        sed -i 's/^SELINUX=.*/SELINUX=disabled/' /etc/selinux/config 2>/dev/null || true
        tamam "SELinux: Devre disi birakildi"
    fi

    if systemctl is-active firewalld > /dev/null 2>&1; then
        bilgi "Firewall: Aktif - Kurulum portlari ayarlayacak"
    else
        bilgi "Firewall: Devre disi - Kurulum aktif edip ayarlayacak"
    fi

    TZ=$(timedatectl show --property=Timezone --value 2>/dev/null || echo "Bilinmiyor")
    if [ "$TZ" = "Europe/Istanbul" ]; then
        tamam "Timezone: ${TZ}"
    else
        bilgi "Timezone: ${TZ} - Europe/Istanbul olarak ayarlanacak"
        timedatectl set-timezone Europe/Istanbul 2>/dev/null || true
        tamam "Timezone: Europe/Istanbul olarak ayarlandi"
    fi

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
    echo " Panele giris icin kullanici adi ve sifre belirleyin."
    echo ""

    while true; do
        read -p " Panel yonetici kullanici adi: " PANEL_KULLANICI
        if [ -z "$PANEL_KULLANICI" ]; then
            olumsuz "Kullanici adi bos birakilamaz!"
        elif echo "$PANEL_KULLANICI" | grep -qP '[^a-z0-9_]'; then
            olumsuz "Sadece kucuk harf, rakam ve alt cizgi kullanin!"
        elif [ ${#PANEL_KULLANICI} -lt 3 ]; then
            olumsuz "Kullanici adi en az 3 karakter olmalidir!"
        else
            break
        fi
    done

    while true; do
        read -s -p " Panel yonetici sifresi: " PANEL_SIFRE
        echo ""
        read -s -p " Sifre tekrar: " PANEL_SIFRE2
        echo ""
        if [ ${#PANEL_SIFRE} -lt 8 ]; then
            olumsuz "Sifre en az 8 karakter olmalidir!"
        elif [ "$PANEL_SIFRE" != "$PANEL_SIFRE2" ]; then
            olumsuz "Sifreler eslesmiyor!"
        else
            break
        fi
    done

    while true; do
        read -s -p " MariaDB root sifresi: " DB_ROOT_SIFRE
        echo ""
        read -s -p " MariaDB root sifresi tekrar: " DB_ROOT_SIFRE2
        echo ""
        if [ ${#DB_ROOT_SIFRE} -lt 8 ]; then
            olumsuz "Sifre en az 8 karakter olmalidir!"
        elif [ "$DB_ROOT_SIFRE" != "$DB_ROOT_SIFRE2" ]; then
            olumsuz "Sifreler eslesmiyor!"
        else
            break
        fi
    done

    SUNUCU_IP=$(hostname -I | awk '{print $1}')

    echo ""
    echo -e " ${CYAN}Kurulum ozeti:${SIFIRLA}"
    echo "   Panel Kullanici : ${PANEL_KULLANICI}"
    echo "   Panel Adresi    : http://${SUNUCU_IP}:${GMS_PANEL_PORT}"
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
    baslik "1/9 - Sistem Guncelleme"
    dnf update -y -q
    dnf install -y -q wget curl vim nano git net-tools bash-completion openssl
    log "Sistem guncellendi."
}

# =============================================================
# 2 - FIREWALL
# =============================================================
firewall_kur() {
    baslik "2/9 - Firewall"
    systemctl enable --now firewalld
    firewall-cmd --permanent --add-service=ssh
    firewall-cmd --permanent --add-service=http
    firewall-cmd --permanent --add-service=https
    firewall-cmd --permanent --add-service=ftp
    firewall-cmd --permanent --add-port=${GMS_PANEL_PORT}/tcp
    firewall-cmd --permanent --remove-service=cockpit 2>/dev/null || true
    firewall-cmd --permanent --remove-service=dhcpv6-client 2>/dev/null || true
    firewall-cmd --reload
    tamam "Panel portu ${GMS_PANEL_PORT} acildi."
    log "Firewall tamamlandi."
}

# =============================================================
# 3 - FAIL2BAN
# =============================================================
fail2ban_kur() {
    baslik "3/9 - Fail2ban"
    dnf install -y -q fail2ban

    cat > /etc/fail2ban/jail.local << 'EOF'
[sshd]
enabled = true
port = ssh
maxretry = 5
findtime = 300
bantime = 3600

[nginx-http-auth]
enabled = true
port = http,https
maxretry = 5
findtime = 300
bantime = 3600
EOF

    systemctl enable --now fail2ban
    log "Fail2ban kuruldu ve ayarlandi."
}

# =============================================================
# 4 - NGINX
# =============================================================
nginx_kur() {
    baslik "4/9 - Nginx"
    dnf install -y -q nginx
    systemctl enable --now nginx

    if ! grep -q "charset utf-8" /etc/nginx/nginx.conf; then
        sed -i 's/keepalive_timeout   65;/keepalive_timeout   65;\n    charset utf-8;/' /etc/nginx/nginx.conf
    fi

    [ -f /etc/nginx/conf.d/php-fpm.conf ] && mv /etc/nginx/conf.d/php-fpm.conf /etc/nginx/conf.d/php-fpm.conf.bak
    [ -f /etc/nginx/default.d/php.conf ] && mv /etc/nginx/default.d/php.conf /etc/nginx/default.d/php.conf.bak
    [ -f /etc/nginx/default.d/phpMyAdmin.conf ] && mv /etc/nginx/default.d/phpMyAdmin.conf /etc/nginx/default.d/phpMyAdmin.conf.bak

    # Direkt IP erisimine 444 don (gizli kapat)
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
# 5 - PHP
# =============================================================
php_kur() {
    baslik "5/9 - PHP (7.4, 8.0, 8.1, 8.2, 8.3, 8.4)"
    dnf install -y -q https://rpms.remirepo.net/enterprise/remi-release-9.rpm

    for VER in 74 80 81 82 83 84; do
        log "PHP ${VER} kuruluyor..."
        dnf install -y php${VER} php${VER}-php-fpm php${VER}-php-mysqlnd \
            php${VER}-php-curl php${VER}-php-gd php${VER}-php-mbstring \
            php${VER}-php-xml php${VER}-php-zip php${VER}-php-json \
            php${VER}-php-opcache php${VER}-php-intl 2>/dev/null || uyari "PHP ${VER} kurulamadi."

        CONF="/etc/opt/remi/php${VER}/php-fpm.d/www.conf"
        if [ -f "$CONF" ]; then
            sed -i 's/^;listen.owner.*/listen.owner = nginx/' "$CONF"
            sed -i 's/^;listen.group.*/listen.group = nginx/' "$CONF"
            sed -i 's/^;listen.mode.*/listen.mode = 0660/' "$CONF"
            sed -i 's/^listen.acl_users/#listen.acl_users/' "$CONF"
        fi
        systemctl enable --now php${VER}-php-fpm 2>/dev/null || true
        tamam "PHP ${VER} kuruldu."
    done

    log "Tum PHP versiyonlari kuruldu."
}

# =============================================================
# 6 - MARIADB
# =============================================================
mariadb_kur() {
    baslik "6/9 - MariaDB 11.4"
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

    log "MariaDB kuruldu ve guvence altina alindi."
}

# =============================================================
# 7 - CERTBOT (Let's Encrypt)
# =============================================================
certbot_kur() {
    baslik "7/9 - Certbot (Let's Encrypt)"

    # EPEL kurulu degilse kur (certbot icin gerekli)
    if ! rpm -q epel-release &>/dev/null; then
        log "EPEL deposu ekleniyor..."
        dnf install -y -q epel-release
    fi

    dnf install -y -q certbot python3-certbot-nginx
    tamam "Certbot kuruldu: $(certbot --version 2>&1)"
}

# =============================================================
# 8 - PHPMYADMIN
# =============================================================
phpmyadmin_kur() {
    baslik "8/9 - phpMyAdmin"
    dnf install -y -q phpmyadmin

    PMA_SECRET=$(openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 32)

    cat > /etc/phpMyAdmin/config.inc.php << EOF
<?php
\$cfg['blowfish_secret'] = '${PMA_SECRET}';
\$i = 0;
\$i++;

// GMS Panel - Signon kimlik dogrulama
\$cfg['Servers'][\$i]['auth_type']       = 'signon';
\$cfg['Servers'][\$i]['SignonSession']   = 'GMS_PMA_Session';
\$cfg['Servers'][\$i]['SignonURL']       = '/pma-giris.php';
\$cfg['Servers'][\$i]['host']            = 'localhost';
\$cfg['Servers'][\$i]['compress']        = false;
\$cfg['Servers'][\$i]['AllowNoPassword'] = false;

\$cfg['UploadDir']    = '';
\$cfg['SaveDir']      = '';
\$cfg['DefaultLang']  = 'tr';
\$cfg['ServerDefault'] = 1;
\$cfg['CheckConfigurationPermissions'] = false;
EOF

    chmod 640 /etc/phpMyAdmin/config.inc.php
    chown root:${GMS_SYS_USER} /etc/phpMyAdmin/config.inc.php

    log "phpMyAdmin kuruldu (signon modu aktif)."
}

# =============================================================
# 8 - GMSSYS KULLANICISI, PHP-FPM POOL VE SUDOERS
# =============================================================
gmssys_olustur() {
    baslik "8/9 - Sistem Kullanicisi ve Yetkilendirme"

    # gmssys sistem kullanicisi (login yok, sadece servis icin)
    log "${GMS_SYS_USER} sistem kullanicisi olusturuluyor..."
    useradd -r -m -d /home/${GMS_SYS_USER} -s /sbin/nologin ${GMS_SYS_USER} 2>/dev/null || true
    mkdir -p /home/${GMS_SYS_USER}/gms-panel
    mkdir -p /home/${GMS_SYS_USER}/logs
    chown -R ${GMS_SYS_USER}:${GMS_SYS_USER} /home/${GMS_SYS_USER}
    # phpMyAdmin config dosyasini okuyabilmesi icin apache grubuna ekle (SSO icin zorunlu)
    usermod -a -G apache ${GMS_SYS_USER}
    # Yedekleme dizini
    mkdir -p /var/gms/backups
    chown ${GMS_SYS_USER}:${GMS_SYS_USER} /var/gms/backups
    chmod 750 /var/gms/backups
    tamam "${GMS_SYS_USER} kullanicisi olusturuldu."

    # Panel PHP-FPM pool (gmssys olarak calisir - sudo icin kritik)
    log "Panel PHP-FPM pool olusturuluyor..."
    cat > /etc/opt/remi/php83/php-fpm.d/gms-panel.conf << EOF
[gms-panel]
user = ${GMS_SYS_USER}
group = ${GMS_SYS_USER}
listen = /var/opt/remi/php83/run/php-fpm/gms-panel.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 5
; sudo icin PATH ve HOME gerekli
env[PATH] = /usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
env[HOME] = /home/${GMS_SYS_USER}
EOF
    systemctl restart php83-php-fpm
    tamam "Panel PHP-FPM pool hazir (${GMS_SYS_USER} / gms-panel.sock)"

    # Sudoers
    log "Sudoers ayarlaniyor..."
    cat > /etc/sudoers.d/gms-panel << EOF
# GMS Panel - ${GMS_SYS_USER} yetkileri
Defaults:${GMS_SYS_USER} !requiretty
Defaults:${GMS_SYS_USER} env_keep += "PATH HOME"

${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/local/bin/yeni-hesap.sh
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/local/bin/hesap-sil.sh
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/local/bin/domain-ekle.sh
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/local/bin/domain-sil.sh
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/firewall-cmd
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/fail2ban-client
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl start fail2ban
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl stop fail2ban
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl enable fail2ban
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/certbot
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/sbin/userdel
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/sbin/nginx
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl restart php74-php-fpm
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl restart php80-php-fpm
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl restart php81-php-fpm
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl restart php82-php-fpm
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl restart php83-php-fpm
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl restart php84-php-fpm
${GMS_SYS_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload nginx
EOF
    chmod 440 /etc/sudoers.d/gms-panel
    visudo -c > /dev/null 2>&1 && tamam "Sudoers ayarlandi." || uyari "Sudoers dogrulama hatasi!"

    log "Sistem kullanicisi ve yetkilendirme tamamlandi."
}

# =============================================================
# 9 - PANEL KURULUMU (Dosyalar, Scriptler, Nginx, Auth)
# =============================================================
# NOT: panel_kur() fonksiyonu 9. adim olarak tanimlanmistir
panel_kur() {
    baslik "9/9 - Panel Kurulumu"

    PANEL_DIR="/home/${GMS_SYS_USER}/gms-panel"
    SUNUCU_IP=$(hostname -I | awk '{print $1}')

    # --- Yardimci scriptler ---
    log "Yardimci scriptler olusturuluyor..."

    cat > /usr/local/bin/yeni-hesap.sh << 'SCRIPT'
#!/bin/bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

KULLANICI=$1
DOMAIN=$2
PHP=$3

YASAK="gmssys root nginx apache mysql mariadb ftp mail www admin administrator test nobody daemon bin sys"

if [ -z "$KULLANICI" ] || [ -z "$DOMAIN" ] || [ -z "$PHP" ]; then
    echo "Kullanim: yeni-hesap.sh kullaniciadi domain.com php_versiyonu"
    exit 1
fi

for yasak in $YASAK; do
    [ "$KULLANICI" = "$yasak" ] && echo "HATA: Gecersiz kullanici adi." && exit 1
done

id "$KULLANICI" &>/dev/null && echo "HATA: Bu kullanici zaten mevcut." && exit 1

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
listen.mode = 0660
pm = dynamic
pm.max_children = 5
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
POOL

/usr/bin/systemctl restart php${PHP}-php-fpm

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

DB_CONF="/etc/gms/db.conf"
if [ -f "$DB_CONF" ]; then
    DB_ROOT=$(grep "^DB_ROOT=" "$DB_CONF" | cut -d'=' -f2-)
    MYSQL_SIFRE=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 16)
    mysql -u root -p"${DB_ROOT}" 2>/dev/null << SQLEOF
CREATE USER IF NOT EXISTS '${KULLANICI}'@'localhost' IDENTIFIED BY '${MYSQL_SIFRE}';
GRANT ALL PRIVILEGES ON \`${KULLANICI}\\_%\`.* TO '${KULLANICI}'@'localhost';
FLUSH PRIVILEGES;
SQLEOF

    cat > /etc/gms/users/${KULLANICI}.conf << CONF
KULLANICI=${KULLANICI}
DOMAIN=${DOMAIN}
PHP=${PHP}
MYSQL_USER=${KULLANICI}
MYSQL_SIFRE=${MYSQL_SIFRE}
OLUSTURMA=$(date '+%d.%m.%Y %H:%M')
CONF
    chmod 640 /etc/gms/users/${KULLANICI}.conf
    chown root:gmssys /etc/gms/users/${KULLANICI}.conf
fi

echo "[GMS] Tamamlandi: $KULLANICI / $DOMAIN"
SCRIPT
    chmod +x /usr/local/bin/yeni-hesap.sh
    tamam "yeni-hesap.sh olusturuldu."

    cat > /usr/local/bin/hesap-sil.sh << 'SCRIPT'
#!/bin/bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

KULLANICI=$1
[ -z "$KULLANICI" ] && echo "Kullanim: hesap-sil.sh kullaniciadi" && exit 1

echo "[GMS] Hesap siliniyor: $KULLANICI"

for VER in 74 80 81 82 83 84; do
    CONF="/etc/opt/remi/php${VER}/php-fpm.d/${KULLANICI}.conf"
    if [ -f "$CONF" ]; then
        rm -f "$CONF"
        /usr/bin/systemctl restart php${VER}-php-fpm 2>/dev/null
    fi
done

find /etc/nginx/conf.d/ -name "*.conf" \
    -exec grep -l "/home/${KULLANICI}/" {} \; | xargs rm -f 2>/dev/null
/usr/sbin/nginx -t && /usr/bin/systemctl reload nginx 2>/dev/null

DB_CONF="/etc/gms/db.conf"
if [ -f "$DB_CONF" ]; then
    DB_ROOT=$(grep "^DB_ROOT=" "$DB_CONF" | cut -d'=' -f2-)
    mysql -u root -p"${DB_ROOT}" 2>/dev/null << SQLEOF
DROP USER IF EXISTS '${KULLANICI}'@'localhost';
FLUSH PRIVILEGES;
SQLEOF
fi

/usr/sbin/userdel -r "$KULLANICI" 2>/dev/null
rm -f /etc/gms/users/${KULLANICI}.conf

echo "[GMS] $KULLANICI silindi."
SCRIPT
    chmod +x /usr/local/bin/hesap-sil.sh
    tamam "hesap-sil.sh olusturuldu."

    cat > /usr/local/bin/domain-ekle.sh << 'SCRIPT'
#!/bin/bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

DOMAIN=$1
KULLANICI=$2
PHP=$3

[ -z "$DOMAIN" ] || [ -z "$KULLANICI" ] || [ -z "$PHP" ] && \
    echo "Kullanim: domain-ekle.sh domain kullanici php_versiyonu" && exit 1

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
echo "[GMS] Domain eklendi: $DOMAIN -> $KULLANICI"
SCRIPT
    chmod +x /usr/local/bin/domain-ekle.sh
    tamam "domain-ekle.sh olusturuldu."

    cat > /usr/local/bin/domain-sil.sh << 'SCRIPT'
#!/bin/bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

DOMAIN=$1
[ -z "$DOMAIN" ] && echo "Kullanim: domain-sil.sh domain" && exit 1

rm -f /etc/nginx/conf.d/${DOMAIN}.conf
/usr/sbin/nginx -t && /usr/bin/systemctl reload nginx
echo "[GMS] Domain silindi: $DOMAIN"
SCRIPT
    chmod +x /usr/local/bin/domain-sil.sh
    tamam "domain-sil.sh olusturuldu."

    # --- Panel dosyalari (GitHub tarball) ---
    log "Panel dosyalari indiriliyor..."
    TARBALL_URL="https://github.com/GMS-Panel/gms-panel/archive/refs/heads/main.tar.gz"
    TMP_TAR="/tmp/gms-panel.tar.gz"

    if curl -fsSL "${TARBALL_URL}" -o "${TMP_TAR}"; then
        mkdir -p "${PANEL_DIR}"
        # Tarball icerigini gecici dizine acar, sonra dogru yere tasir
        tar -xzf "${TMP_TAR}" -C /tmp/
        # GitHub main branch'i gms-panel-main/ olarak acar
        cp -rf /tmp/gms-panel-main/. "${PANEL_DIR}/"
        rm -rf /tmp/gms-panel-main "${TMP_TAR}"
        tamam "Panel dosyalari indirildi."
    else
        hata "Panel dosyalari indirilemedi. Internet baglantisini kontrol edin."
        exit 1
    fi

    chown -R ${GMS_SYS_USER}:${GMS_SYS_USER} "${PANEL_DIR}"

    # phpMyAdmin signon dosyasi (panel web root'unda olmali)
    cat > "${PANEL_DIR}/panel/pma-giris.php" << 'PHPEOF'
<?php
require_once __DIR__ . '/auth.php';

$mysql_user = '';
$mysql_pass = '';

if (is_admin()) {
    $db_conf = parse_ini_file('/etc/gms/db.conf') ?: [];
    $mysql_user = 'root';
    $mysql_pass = $db_conf['DB_ROOT'] ?? '';
} elseif (is_user()) {
    $hesap    = $_SESSION['gms_hesap'] ?? '';
    $usr_conf = "/etc/gms/users/{$hesap}.conf";
    if ($hesap && file_exists($usr_conf)) {
        $conf        = parse_ini_file($usr_conf) ?: [];
        $mysql_user  = $conf['MYSQL_USER']  ?? '';
        $mysql_pass  = $conf['MYSQL_SIFRE'] ?? '';
    }
}

if (empty($mysql_user)) {
    header('Location: index.php');
    exit;
}

session_write_close();
session_name('GMS_PMA_Session');
session_start();
$_SESSION['PMA_single_signon_user']     = $mysql_user;
$_SESSION['PMA_single_signon_password'] = $mysql_pass;
$_SESSION['PMA_single_signon_host']     = 'localhost';
session_write_close();

header('Location: /phpmyadmin/');
exit;
PHPEOF
    chown ${GMS_SYS_USER}:${GMS_SYS_USER} "${PANEL_DIR}/panel/pma-giris.php"
    tamam "phpMyAdmin signon dosyasi olusturuldu."

    # --- /etc/gms yapisi ---
    mkdir -p /etc/gms/users
    chmod 750 /etc/gms
    chown root:${GMS_SYS_USER} /etc/gms
    chmod 750 /etc/gms/users
    chown root:${GMS_SYS_USER} /etc/gms/users

    # auth.conf
    PANEL_HASH=$(php83 -r "echo password_hash('${PANEL_SIFRE}', PASSWORD_DEFAULT);" 2>/dev/null)
    cat > /etc/gms/auth.conf << EOF
USER=${PANEL_KULLANICI}
HASH=${PANEL_HASH}
EOF
    chmod 640 /etc/gms/auth.conf
    chown root:${GMS_SYS_USER} /etc/gms/auth.conf
    tamam "Panel kimlik dogrulama ayarlandi."

    # db.conf (yardimci scriptler icin MariaDB root sifresi)
    cat > /etc/gms/db.conf << EOF
DB_ROOT=${DB_ROOT_SIFRE}
EOF
    chmod 640 /etc/gms/db.conf
    chown root:${GMS_SYS_USER} /etc/gms/db.conf
    tamam "Veritabani yapilandirmasi kaydedildi."

    # --- Nginx panel vhost (port 8090) ---
    log "Panel nginx vhost ayarlaniyor (port ${GMS_PANEL_PORT})..."
    mkdir -p "${PANEL_DIR}/logs"
    chown ${GMS_SYS_USER}:${GMS_SYS_USER} "${PANEL_DIR}/logs"

    cat > /etc/nginx/conf.d/gms-panel.conf << EOF
server {
    listen ${GMS_PANEL_PORT};
    server_name _;
    root ${PANEL_DIR}/panel;
    index index.php index.html;
    access_log ${PANEL_DIR}/logs/access.log;
    error_log  ${PANEL_DIR}/logs/error.log;

    # phpMyAdmin
    location /phpmyadmin {
        alias /usr/share/phpMyAdmin;
        index index.php;
        location ~ \.php\$ {
            fastcgi_pass unix:/var/opt/remi/php83/run/php-fpm/gms-panel.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$request_filename;
            include fastcgi_params;
        }
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/opt/remi/php83/run/php-fpm/gms-panel.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

    nginx -t && systemctl reload nginx
    tamam "Panel nginx vhost hazir (port ${GMS_PANEL_PORT})."

    log "Panel kurulumu tamamlandi."
}

# =============================================================
# OZET
# =============================================================
ozet_goster() {
    SUNUCU_IP=$(hostname -I | awk '{print $1}')
    clear
    echo -e "${YESIL}"
    echo "  ╔═══════════════════════════════════════════╗"
    echo "  ║         KURULUM TAMAMLANDI!               ║"
    echo "  ╚═══════════════════════════════════════════╝"
    echo -e "${SIFIRLA}"
    echo -e " ${CYAN}Panel Erisim:${SIFIRLA}"
    echo "   Adres      : http://${SUNUCU_IP}:${GMS_PANEL_PORT}"
    echo "   Kullanici  : ${PANEL_KULLANICI}"
    echo "   Sifre      : ${PANEL_SIFRE}"
    echo ""
    echo -e " ${CYAN}phpMyAdmin:${SIFIRLA}"
    echo "   Adres      : http://${SUNUCU_IP}:${GMS_PANEL_PORT}/phpmyadmin"
    echo "   (Panel uzerinden otomatik giris yapilir)"
    echo ""
    echo -e " ${CYAN}Veritabani:${SIFIRLA}"
    echo "   MariaDB root sifresi /etc/gms/db.conf icinde sakli"
    echo ""
    echo -e " ${CYAN}Kurulu Servisler:${SIFIRLA}"
    echo "   PHP      : 7.4, 8.0, 8.1, 8.2, 8.3, 8.4"
    echo "   MariaDB  : 11.4"
    echo "   Nginx    : $(nginx -v 2>&1 | grep -oP '[\d.]+')"
    echo "   Firewall : Aktif (port ${GMS_PANEL_PORT} acik)"
    echo "   Fail2ban : Aktif"
    echo ""
    echo -e " ${CYAN}Panel Guncelleme (deploy):${SIFIRLA}"
    echo "   curl -fsSL https://github.com/GMS-Panel/gms-panel/archive/refs/heads/main.tar.gz | tar -xz -C /tmp/ && cp -rf /tmp/gms-panel-main/. /home/${GMS_SYS_USER}/gms-panel/ && rm -rf /tmp/gms-panel-main"
    echo ""
    echo -e " ${SARI}[!!] Panel sifrenizi guvenli bir yere kaydedin!${SIFIRLA}"
    echo ""
}

# =============================================================
# ANA AKIS
# =============================================================
sistem_kontrol
bilgi_al
sistem_guncelle
firewall_kur
fail2ban_kur
nginx_kur
php_kur
mariadb_kur
certbot_kur
phpmyadmin_kur
gmssys_olustur
panel_kur
ozet_goster
