# GMS Panel

**Alma Linux 9 icin Acik Kaynak Web Hosting Yonetim Paneli**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

GMS Panel; Nginx, PHP (7.4-8.4) ve MariaDB 11.4 uzerine kurulu, Alma Linux 9 Minimal icin tasarlanmis hafif ve guvenli bir web hosting yonetim panelidir. cPanel ve DirectAdmin alternatifi olarak gelistirilmistir.

---

## Ozellikler

- Coklu PHP versiyonu destegi (7.4, 8.0, 8.1, 8.2, 8.3, 8.4) — her hesap ayri PHP-FPM pool
- Hosting hesabi yonetimi (olustur, duzenle, sil)
- Domain ve alt domain yonetimi
- SSL sertifika yonetimi (Let's Encrypt / Certbot)
- Firewall yonetimi (firewalld)
- phpMyAdmin entegrasyonu — panel uzerinden otomatik giris
- Alt kullanici yonetimi — her musteri kendi hesabini yonetir
- Fail2ban entegrasyonu
- Git ile kolay deploy workflow

---

## Mimari

| Bilesen | Deger |
|---|---|
| Panel erisim | `http://SUNUCU_IP:8090` |
| PHP-FPM (panel) | `gmssys` kullanicisi, `gms-panel.sock` |
| PHP-FPM (hosting) | Her hesap kendi kullanicisiyla ayri pool |
| Auth konfig | `/etc/gms/auth.conf` |
| Hesap konfigleri | `/etc/gms/users/kullaniciadi.conf` |
| DB konfig | `/etc/gms/db.conf` |
| Yardimci scriptler | `/usr/local/bin/` |

---

## Kurulum

Alma Linux 9 Minimal kurulu sunucuda root olarak:

```bash
curl -fsSL https://raw.githubusercontent.com/GMS-Panel/gms-panel/main/gms-kur.sh -o gms-kur.sh
bash gms-kur.sh
```

Kurulum sirasinda sorulacaklar:
- Panel yonetici kullanici adi
- Panel yonetici sifresi
- MariaDB root sifresi

Kurulum tamamlandiginda panel `http://SUNUCU_IP:8090` adresinden erisebilir.

---

## Panel Guncelleme (Deploy)

```bash
curl -fsSL https://github.com/GMS-Panel/gms-panel/archive/refs/heads/main.tar.gz -o /tmp/gms-panel.tar.gz
tar -xzf /tmp/gms-panel.tar.gz -C /tmp/
cp -rf /tmp/gms-panel-main/. /home/gmssys/gms-panel/
rm -rf /tmp/gms-panel-main /tmp/gms-panel.tar.gz
systemctl reload php83-php-fpm
```

---

## Klasor Yapisi

```
gms-panel/
├── gms-kur.sh          # Ana kurulum scripti
├── panel/              # PHP panel dosyalari
│   ├── auth.php        # Kimlik dogrulama ve rol yonetimi
│   ├── layout.php      # Sayfa sablonu
│   ├── index.php       # Dashboard
│   ├── login.php       # Giris sayfasi
│   ├── accounts.php    # Hosting hesaplari listesi
│   ├── new_account.php # Yeni hesap olustur
│   ├── edit_account.php# Hesap duzenle / sil
│   ├── domains.php     # Domain yonetimi
│   ├── ssl.php         # SSL sertifika yonetimi
│   ├── firewall.php    # Firewall yonetimi
│   ├── users.php       # Alt kullanici yonetimi
│   └── pma-giris.php   # phpMyAdmin signon
├── scripts/            # Sistem yonetim scriptleri
│   ├── yeni-hesap.sh   # Hosting hesabi olustur
│   ├── hesap-sil.sh    # Hosting hesabi sil
│   └── domain-ekle.sh  # Domain ekle
└── site/               # Varsayilan site sablonu
```

---

## Sistem Gereksinimleri

- Alma Linux 9 Minimal
- Minimum 1 GB RAM (2 GB onerilir)
- Minimum 10 GB disk alani
- Internet erisimi (kurulum icin)

---

## Guvenlik

- PHP-FPM panel `gmssys` sistem kullanicisi olarak calisir (root degil)
- Her hosting hesabi izole PHP-FPM pool ile calisir
- SELinux devre disi (kurulum sirasinda)
- Fail2ban ile SSH ve Nginx korumasi
- Direkt IP erisimi engellidir (444 doner)
- `/etc/gms/` dizini sadece root ve gmssys tarafindan okunabilir

---

## Rol Sistemi

**Admin:** Tum panel ozelliklerine erisim  
**Alt Kullanici:** Sadece kendi hosting hesabini yonetir (domain, SSL, phpMyAdmin)

---

## Versiyon Gecmisi

| Versiyon | Ozellikler |
|---|---|
| v2.1 | Temel panel, hesap yonetimi, domain, SSL, firewall |
| v2.5 | Rol sistemi, alt kullanici yonetimi, gmssys mimarisi, phpMyAdmin SSO |

---

## Lisans

MIT License — Detaylar icin [LICENSE](LICENSE) dosyasina bakin.

---

## Gelistirici Notu (Claude icin)

Bu bolum gelistirme durumunu takip etmek icindir.

### Mevcut Durum (v2.5)

Mimari: Panel PHP-FPM'i `gmssys` sistem kullanicisi olarak calisir. Bu kullanicinin sudo yetkisi var, boylece PHP uzerinden `yeni-hesap.sh` gibi scriptler root olarak calistirilabiliyor. Eski mimaride apache kullanicisi sudo calistiramiyordu — yeni mimari bu sorunu kaldira yola cozuyor.

**ONEMLI GIZLILIK:** `gmssys` kullanici adi disariya acilmamali. Commit mesajlarinda, kullaniciya gosterilen hata mesajlarinda ve herhangi bir kullanici-gorunur icelikte bu isim gecmemeli.

**Yasak kullanici adlari:** `gmssys root nginx apache mysql mariadb ftp mail www admin administrator test nobody daemon bin sys` — bu kullanicilarda hata mesaji: "Gecersiz kullanici adi. Lutfen baska bir kullanici adi deneyin."

### Kurulum Yontemi

Kurulum scripti (`gms-kur.sh`) her seyi hallediyor. On gereksinim sadece:
```
curl -O https://raw.githubusercontent.com/GMS-Panel/gms-panel/main/gms-kur.sh
bash gms-kur.sh
```
git, wget vb. script icinde kuruluyor.

### Tamamlanan Duzeltmeler (v2.5 son durum)

Sunucu: panel.gms.tr (213.159.2.133) — panel calisir durumda.

**Duzeltilen hatalar:**
- PHP-FPM baslamiyordu: `www.conf` icinde `#listen.acl_users` yorumu `#` ile yazilmisti, `;` olmasi gerekiyordu
- nginx 502: `/home/gmssys` dizin izni 700 idi, 755 yapildi
- ssl.php HTTP 500: `goto renew_end` etiketi tanimsizdi, `$yetki_ok` boolean ile duzeltildi
- phpMyAdmin SSO: `gmssys` apache grubuna eklendi (`usermod -a -G apache gmssys`), config dosyasi artik okunabiliyor
- users.php: `get_all_users()` ROLE alani olmayan hosting hesap configlerini de donuyordu, filtrelendi
- accounts.php: sistem kullanicilari (`gmssys` dahil) listede gizlendi
- Dashboard PHP servisleri: `servis_kurulu()` fonksiyonu eklendi (Remi servis dosyalari kurulu ama calismiyor = Durdu gosterir, bu dogru)

**Eklenen sayfalar:**
- `databases.php` — veritabani yonetimi (olustur/sil, admin/alt kullanici rol ayrimi)
- `backup.php` — hosting hesabi yedekleme (tar.gz, `/var/gms/backups/`)
- `php_settings.php` — hesap bazinda PHP ini ayarlari (memory_limit, upload_max_filesize vb.)
- `files.php` — dosya yoneticisi (dizin gezinme, yukle, sil, mkdir, inline editor, indir)

**Eklenen scriptler:**
- `scripts/php-pool-yaz.sh` — pool conf dosyasini sudo ile guvenli yazar (`/usr/local/bin/php-pool-yaz.sh`)
  - Sudoers: `gmssys ALL=(root) NOPASSWD: /usr/local/bin/php-pool-yaz.sh`

**Onemli sistem notu:**
- phpMyAdmin config: `/etc/phpMyAdmin/config.inc.php` — `root:apache 640` izinli
- `gmssys` apache grubunda olmali, aksi halde PMA config okunamaz ve SSO calismiyor
- Yedek dizini: `/var/gms/backups/` — `gmssys:gmssys 750`
- Pool conf izinleri: `root:gmssys 640` olmali, yoksa `php_settings.php` okuyamaz
  - Mevcut hesaplar icin: `find /etc/opt/remi -name '*.conf' -path '*/php-fpm.d/*' ! -name 'www.conf' -exec chown root:gmssys {} \; -exec chmod 640 {} \;`
  - Yeni hesaplar: `yeni-hesap.sh` otomatik olarak `chown root:gmssys 640` yapiyor
- Dosya yoneticisi icin ACL: `gmssys`'e hosting home dizinlerinde `rwx` yetkisi gerekli
  - Mevcut hesaplar icin: `setfacl -R -m u:gmssys:rwx /home/HESAP/ && setfacl -R -d -m u:gmssys:rwx /home/HESAP/`
  - Yeni hesaplar: `yeni-hesap.sh` otomatik olarak `setfacl` uyguluyor

### Sonraki Gelistirme Hedefleri

- Tum ozellikler tamamlandi. Yeni istege gore gelistirme yapilacak.
