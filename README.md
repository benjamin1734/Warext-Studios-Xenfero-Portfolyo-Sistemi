# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için üyelerin görsel ve 3D çalışmalarını güvenli biçimde yayınlayabildiği kapsamlı portfolyo sistemidir.

**Sürüm:** 1.0.0  
**Geliştirici:** Warext Studios  
**Add-on ID:** `Warext/Portfolio`

## Kurulum ZIP'i

**[Warext Studios | XenForo Portfolyo Sistemi 1.0.0 - Kurulum ZIP'ini indir](https://raw.githubusercontent.com/benjamin1734/Warext-Studios-Xenfero-Portfolyo-Sistemi/main/dist/Warext-Studios-XenForo-Portfolyo-Sistemi-1.0.0.zip)**

Bu dosya XenForo Admin CP üzerinden doğrudan kurulmak için hazırlanmıştır.

## Özellikler

- Görsel ve GLB tabanlı 3D portfolyo çalışmaları
- Kapak, galeri ve interaktif 3D model görüntüleme
- Taslak, güvenlik kontrolü, moderasyon ve yayınlama akışı
- Kategori, etiket ve kullanılan program bilgileri
- Üye profiline portfolyo entegrasyonu
- Beğeni, yorum, kaydetme, takip ve görüntülenme sistemi
- Kaydedilen çalışmalar sayfası
- Kategori ve içerik türüne göre filtreleme
- Yeni, eski, en beğenilen, en çok görüntülenen ve en çok yorumlanan sıralamaları
- XenForo bildirim merkezi entegrasyonu
- SEO ve paylaşım meta verileri
- Approval Queue entegrasyonu
- Telif, çalıntı çalışma, zararlı dosya şüphesi ve içerik raporlama sistemi
- Admin CP Güvenlik Merkezi
- Audit log ve yeniden tarama sistemi

## Dosya güvenliği

Yüklenen dosyalar doğrudan herkese açık dizinlere yazılmaz. Dosyalar önce özel karantina alanına alınır ve yayınlanmadan önce güvenlik zincirinden geçirilir.

- Uzantı, MIME ve magic-byte doğrulaması
- SHA-256 bütünlük kontrolü
- Tehlikeli çift uzantı ve yol manipülasyonu kontrolleri
- Hash blacklist desteği
- ClamAV taraması ve fail-closed davranış
- Görsellerde boyut ve megapiksel limitleri
- Görsellerin güvenli WebP biçimine yeniden kodlanması
- EXIF ve gereksiz metadata temizliği
- GLB yapı ve kaynak doğrulaması
- Harici URI, `file://`, traversal ve uzaktan kaynak engellemesi
- Vertex, triangle, mesh, node, texture, animation, skin ve joint limitleri
- Büyük PNG/WebP/GLB parçaları için düşük bellekli parser kontrolleri
- Sandbox WebGL2 3D viewer
- Yayındaki dosyalar için periyodik yeniden tarama
- Teknik güvenlik bloklarının moderasyon tarafından bypass edilememesi

## Depolama

- SHA-256 içerik adresli blob depolama
- Aynı güvenli içeriğin fiziksel olarak tekrar saklanmasını önleyen deduplication
- Referans sayacı ve otomatik onarım
- Gecikmeli garbage collection
- Atomic kapak/model değişimi
- Gelecekte farklı storage backend'lerine uyarlanabilecek storage abstraction

## Moderasyon

Yayınlanmış bir çalışmada yapılan yeni metin veya dosya değişiklikleri doğrudan ziyaretçilere gösterilmez. Değişiklikler ayrı bir bekleyen revizyon olarak tutulur ve moderasyon onayından sonra atomik olarak yayına alınır.

Yeni kapak veya model incelenirken mevcut güvenli yayın aktif kalır. Teknik olarak bloklanan bir aday dosya mevcut güvenli çalışmayı etkilemez; ancak aktif olarak kullanılan bir blob daha sonra zararlı bulunursa ilgili yayın güvenlik incelemesine alınır.

## Gereksinimler

- XenForo 2.3.0+
- PHP 8.0+
- PHP ZIP desteği
- PHP CLI erişimi
- ClamAV / `clamd`
- Görsel işleme için Imagick veya GD

## Kurulum

1. Yukarıdaki doğrudan kurulum ZIP'ini indirin.
2. XenForo Admin CP'ye giriş yapın.
3. **Add-ons → Install/upgrade from archive** bölümünü açın.
4. ZIP dosyasını seçip yükleyin.
5. Kurulum tamamlandıktan sonra kullanıcı grubu ve Admin CP izinlerini ihtiyacınıza göre düzenleyin.
6. ClamAV ile Imagick/GD servislerinin sunucuda kullanılabilir olduğunu doğrulayın.

Arşiv yükleme seçeneği kapalıysa XenForo yapılandırmasında add-on archive installer özelliğinin etkin olması gerekir.

## Paket yapısı

```text
upload/
├── js/warext/portfolio/
└── src/addons/Warext/Portfolio/
    ├── addon.json
    ├── hashes.json
    ├── Setup.php
    ├── _data/
    └── ...
```

`hashes.json`, kurulum paketindeki dosyaların SHA-256 manifestini içerir ve XenForo dosya bütünlüğü kontrolleriyle uyumludur.
