# GMS Panel

**Alma Linux 9 için Açık Kaynak Web Hosting Yönetim Paneli**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Platform](https://img.shields.io/badge/Platform-Alma%20Linux%209-blue.svg)](https://almalinux.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%20--%208.4-purple.svg)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.4-orange.svg)](https://mariadb.org/)

---

## Nedir?

GMS Panel, Alma Linux 9 üzerine kurulu sunucularda web hosting altyapısını tek bir script ile kuran ve yöneten açık kaynak bir hosting yönetim panelidir.

cPanel, DirectAdmin gibi ücretli ve domain sınırlı sistemlere alternatif olarak geliştirilmiştir. Tamamen ücretsiz, sınırsız domain ve açık kaynaktır.

---

## Özellikler

### Mevcut (v2.0)
- Tek komutla tam otomatik kurulum
- Kurulum öncesi sistem kontrolü (kritik hatalar durdurur, düzeltilebilecekler otomatik düzeltilir)
- Nginx web sunucu
- PHP çoklu versiyon desteği (7.4, 8.0, 8.1, 8.2, 8.3, 8.4)
- Her hesap için izole PHP-FPM pool
- MariaDB 11.4 veritabanı sunucu
- phpMyAdmin veritabanı yönetim arayüzü
- Let's Encrypt SSL sertifikası (otomatik yenileme dahil)
- Firewall otomatik yapılandırma
- Her site için ayrı Linux kullanıcısı ve izolasyon
- Panel yönetici kullanıcısı (sudo yetkili)
- Sistem ayarları kayıt dosyası

### Geliştirme Aşamasında
- Panel web arayüzü (PHP tabanlı)
  - Sistem durum izleme (CPU, RAM, Disk)
  - Servis durumları (Nginx, PHP, MariaDB, Firewall)
  - Hesap yönetimi (ekle, sil, düzenle)
  - Domain yönetimi
  - SSL yönetimi
  - phpMyAdmin entegrasyonu
- Veritabanı yönetimi (panel üzerinden)
- FTP/SFTP hesap yönetimi
- E-posta hesap yönetimi
- Yedekleme sistemi (UrBackup entegrasyonu)
- Otomatik MariaDB yedekleme
- Çoklu sunucu desteği
- API desteği

---

## Gereksinimler

| Bileşen | Minimum | Önerilen |
|---------|---------|----------|
| İşletim Sistemi | Alma Linux 9.x Minimal | Alma Linux 9.x Minimal |
| CPU | 1 core | 2 core |
| RAM | 1 GB | 2 GB |
| Disk | 20 GB | 50 GB+ |
| Network | Statik IP | Statik IP |
| İnternet | Zorunlu | Zorunlu |

---

## Hızlı Kurulum

```bash
wget https://raw.githubusercontent.com/GMS-Panel/gms-panel/main/gms-kur.sh
bash gms-kur.sh
```

Detaylı kurulum talimatları için: [KurulumTalimati.txt](KurulumTalimati.txt)

---

## Kurulum Sonrası Yapı

```
/home/
├── PANEL_KULLANICI/          # Kurulumda belirlenir, sudo yetkili
│   ├── gms-panel/            # Panel web arayüzü (nginx buraya bakar)
│   ├── app/                  # Panel uygulama dosyaları
│   ├── logs/                 # Panel logları
│   └── config/
│       └── settings.conf     # Sistem ayarları
│
├── ANA_DOMAIN_KULLANICI/     # Otomatik oluşturulur (firma_com formatı)
│   ├── public_html/          # Ana site dosyaları
│   └── logs/                 # Site logları
│
├── MUSTERI1/                 # Panel üzerinden eklenir
│   ├── public_html/
│   └── logs/
│
└── MUSTERI2/                 # Panel üzerinden eklenir
    ├── public_html/
    └── logs/
```

---

## Repo Yapısı

```
gms-panel/
├── gms-kur.sh              # Ana kurulum scripti
├── KurulumTalimati.txt     # Adım adım kurulum rehberi
├── README.md               # Bu dosya
├── panel/
│   └── index.php           # Panel web arayüzü ana sayfası
├── site/
│   └── index.html          # Ana site varsayılan sayfası
└── scripts/
    └── yeni-hesap.sh       # Yeni hosting hesabı oluşturma scripti
```

---

## Yol Haritası

### v2.1
- [ ] Panel giriş sistemi (kullanıcı adı / şifre)
- [ ] Hesap yönetimi (ekle, sil, listele)
- [ ] Domain yönetimi
- [ ] Basit dosya yöneticisi

### v2.5
- [ ] SSL yönetimi panel üzerinden
- [ ] Veritabanı yönetimi panel üzerinden
- [ ] FTP hesap yönetimi
- [ ] E-posta hesap yönetimi

### v3.0
- [ ] Yedekleme sistemi
- [ ] Çoklu sunucu desteği
- [ ] API
- [ ] Müşteri portalı

---

## Katkı

Pull request ve issue'lar memnuniyetle karşılanır.

1. Fork edin
2. Feature branch oluşturun (`git checkout -b ozellik/yeni-ozellik`)
3. Commit edin (`git commit -m 'Yeni özellik eklendi'`)
4. Push edin (`git push origin ozellik/yeni-ozellik`)
5. Pull Request açın

---

## Lisans

MIT License - Özgürce kullanabilir, değiştirebilir ve dağıtabilirsiniz.

---

## İletişim

- GitHub Issues: [github.com/GMS-Panel/gms-panel/issues](https://github.com/GMS-Panel/gms-panel/issues)

---

*GMS Panel - Açık Kaynak Hosting Yönetim Paneli*
