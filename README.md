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
