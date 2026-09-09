# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için üyelerin görsel ve 3D çalışmalarını güvenli biçimde yayınlayabildiği kapsamlı portfolyo sistemidir.

**Sürüm:** 1.0.3  
**Geliştirici:** Warext Studios  
**Add-on ID:** `Warext/Portfolio`

## Kurulum ZIP'i

**[Warext Studios | XenForo Portfolyo Sistemi 1.0.3 - Kurulum ZIP'ini indir](https://github.com/benjamin1734/Warext-Studios-Xenfero-Portfolyo-Sistemi/releases/download/v1.0.3/Warext-Studios-XenForo-Portfolyo-Sistemi-1.0.3.zip)**

Bu dosya XenForo Admin CP üzerinden doğrudan kurulmak için hazırlanmıştır.

## Erişim noktaları

### Kullanıcı tarafı

- Ana portfolyo sayfası: `/portfolyo/`
- Çalışma ekleme: `/portfolyo/add`
- Çalışmalarım: `/portfolyo/mine`
- Kaydedilenler: `/portfolyo/saved`
- Üye profillerinde **Portfolyo** butonu
- Ana forum navigasyonunda **Portfolyo** menü bağlantısı

### Admin CP

Admin CP sol menüsünde **Portfolyo Sistemi** artık bağımsız bir ana kategori olarak görünür. Altında:

- Portfolyo Yönetimi
- Moderasyon ve Raporlar
- Güvenlik Merkezi
- Karantina
- Engellenen Dosyalar
- Güvenlik Olayları
- Denetim Kayıtları
- SHA-256 Engelleme Listesi
- Portfolyo Ayarları

Ayarlar ayrıca doğrudan `admin.php?options/groups/wrxtPortfolioSettings/` adresinden açılabilir.

## Özellikler

- Görsel ve GLB tabanlı 3D portfolyo çalışmaları
- Kapak, galeri ve interaktif 3D model görüntüleme
- Taslak, güvenlik kontrolü, moderasyon ve yayınlama akışı
- Kategori, etiket ve kullanılan program bilgileri
- Üye profiline portfolyo entegrasyonu
- Beğeni, yorum, kaydetme, takip ve görüntülenme sistemi
- Kategori ve içerik türüne göre filtreleme ve sıralama
- XenForo bildirim merkezi ve Approval Queue entegrasyonu
- Telif, çalıntı çalışma ve zararlı dosya şüphesi raporları
- Admin CP Güvenlik Merkezi, audit log ve yeniden tarama sistemi

## Dosya güvenliği

- Özel karantina alanı
- Uzantı, MIME ve magic-byte doğrulaması
- SHA-256 bütünlük kontrolü ve hash blacklist
- ClamAV taraması ve fail-closed davranış
- Görsellerin güvenli WebP biçimine yeniden kodlanması
- EXIF ve gereksiz metadata temizliği
- GLB yapı, URI ve kaynak doğrulaması
- Model karmaşıklık ve texture limitleri
- Düşük bellekli PNG/WebP/GLB parser kontrolleri
- Sandbox WebGL2 3D viewer
- Yayındaki dosyalar için periyodik yeniden tarama
- Teknik güvenlik bloklarının moderasyon tarafından bypass edilememesi

## Depolama ve yayınlama

- SHA-256 içerik adresli blob depolama
- Güvenli deduplication ve referans sayacı
- Garbage collection
- Atomic kapak/model değişimi
- Yayınlanmış içerik değişiklikleri için bekleyen revizyon ve moderasyon akışı

## Gereksinimler

- XenForo 2.3.0+
- PHP 8.0+
- PHP ZIP desteği
- PHP CLI erişimi
- ClamAV / `clamd`
- Imagick veya GD

## Kurulum

1. Releases bölümündeki `Warext-Studios-XenForo-Portfolyo-Sistemi-1.0.3.zip` dosyasını indirin.
2. XenForo Admin CP → **Add-ons → Install/upgrade from archive** bölümünü açın.
3. ZIP dosyasını seçip kurulumu başlatın.
4. Kurulumdan sonra kullanıcı grubu ve Admin CP izinlerini düzenleyin.
5. ClamAV ile Imagick/GD servislerinin kullanılabilir olduğunu doğrulayın.

## 1.0.2

- XenForo cron kimlikleri 25 karakter sınırına uygun hale getirildi.
- `public:wrxt_portfolio_list` template derleyici hatasına neden olan riskli child-element yapıları kaldırıldı.
- 22 master template XenForo 2.3 uyumlu güvenli tag yapısıyla yeniden düzenlendi.

## 1.0.3

- Admin CP sol menüsüne bağımsız **Portfolyo Sistemi** ana kategorisi eklendi.
- Yönetim, moderasyon, güvenlik, karantina, engellenen dosyalar, olaylar, audit, hash listesi ve ayarlar tek kategori altında toplandı.
- Portfolyo ayar grubuna doğrudan Admin CP menü bağlantısı eklendi.
- Forum ana navigasyonuna **Portfolyo** bağlantısı eklendi.
- XenForo 2.3 admin/navigation phrase anahtarları standart noktalı formata taşındı; eski phrase kayıtları yükseltme uyumluluğu için korundu.
