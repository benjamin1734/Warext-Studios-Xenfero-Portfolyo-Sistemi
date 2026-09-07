# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için üyelerin görsel ve 3D çalışmalarını güvenli biçimde yayınlayabildiği kapsamlı portfolyo sistemidir.

**Sürüm:** 1.0.1  
**Geliştirici:** Warext Studios  
**Add-on ID:** `Warext/Portfolio`

## Kurulum ZIP'i

**[Warext Studios | XenForo Portfolyo Sistemi 1.0.1 - Kurulum ZIP'ini indir](https://github.com/benjamin1734/Warext-Studios-Xenfero-Portfolyo-Sistemi/releases/download/v1.0.1/Warext-Studios-XenForo-Portfolyo-Sistemi-1.0.1.zip)**

Bu dosya XenForo Admin CP üzerinden doğrudan kurulmak için hazırlanmıştır.

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

1. Releases bölümündeki `Warext-Studios-XenForo-Portfolyo-Sistemi-1.0.1.zip` dosyasını indirin.
2. XenForo Admin CP → **Add-ons → Install/upgrade from archive** bölümünü açın.
3. ZIP dosyasını seçip kurulumu başlatın.
4. Kurulumdan sonra kullanıcı grubu ve Admin CP izinlerini düzenleyin.
5. ClamAV ile Imagick/GD servislerinin kullanılabilir olduğunu doğrulayın.

## 1.0.1

- XenForo `xf_cron_entry.entry_id` 25 karakter sınırını aşan cron kimlikleri düzeltildi.
- Kurulum sırasında görülen `Please enter a value using 25 characters or fewer` hatası giderildi.
- XenForo çekirdek 25 karakter ID alanları yeniden doğrulandı.
