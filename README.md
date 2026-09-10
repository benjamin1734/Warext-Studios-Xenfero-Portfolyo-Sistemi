# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.1.2  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

GitHub Releases bölümündeki `Warext-Studios-XenForo-Portfolyo-Sistemi-1.1.2.zip` paketini XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden mevcut sürümün üzerine yükleyin.

1.1.2 için manuel SQL import gerekmez. Yükseltme adımı yorum raporlarının hedef yorumu ayrı saklayabilmesi için gerekli `comment_id` alanını otomatik oluşturur.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Çalışma açıklaması ve yorum yazma alanları XenForo dahili zengin metin editörünü kullanır.
- Kapak, çoklu galeri görseli ve izinli gruplarda GLB 3D model yüklenebilir.

## Yorum sistemi

1.1.2 ile portfolyo yorumları XenForo konu altı mesaj görünümüne geçirildi.

- Düz `textarea` kaldırıldı; hızlı cevap alanı XenForo `<xf:editor>` bileşenini kullanır.
- Backend yorum metnini `EditorPlugin::fromInput('message')` ile alır ve BB code olarak saklar.
- Yorumlar avatar, kullanıcı adı, kullanıcı ünvanı, tarih, yorum bağlantısı ve mesaj aksiyon çubuğuyla gösterilir.
- Yorum içeriği XenForo BB code renderer ile render edilir.
- Yazım denetimi veya editöre entegre diğer XenForo eklentileri standart editör DOM yapısını gördüğü için portfolyo yorum alanıyla uyumlu çalışabilir.
- Kullanıcı yetkisine göre **Raporla** ve **Sil** işlemleri yorumun kendi mesaj aksiyon çubuğunda görünür.
- Yorum raporu çalışma raporundan ayrı hedef olarak kaydedilir ve Admin CP → Portfolyo Sistemi → Moderasyon ve Raporlar bölümünde ilgili yorum numarasıyla görünür.

## Yayın akışı

1. Dosya karantinaya alınır.
2. MIME, magic bytes, dosya yapısı, boyut ve SHA-256 doğrulanır.
3. ClamAV erişilebiliyorsa zararlı yazılım taraması yapılır.
4. JPG/PNG/WebP dosyaları güvenli WebP çıktısına dönüştürülür. İzole worker kullanılamazsa Imagick/GD fallback denenir.
5. GLB modeller güvenlik nedeniyle izole worker ile analiz edilir.
6. İşlenmiş çıktı blob deposuna alınır. Normal blob yayını başarısız olursa doğrulanmış direct-blob fallback kullanılabilir.
7. Tüm dosyalar teknik kontrolleri geçince çalışma **Portfolyo Moderasyonu** ekranına gider.
8. Yetkili **Onayla ve yayınla** veya **Reddet** işlemini uygular.

## Admin CP

**Portfolyo Sistemi** altında Portfolyo Yönetimi, Portfolyo Moderasyonu, Güvenlik Merkezi, Karantina / işlem kuyruğu, Engellenen Dosyalar, Güvenlik Olayları, Denetim Kayıtları, SHA-256 Engelleme Listesi ve Portfolyo Ayarları bulunur.

### Karantina / işlem kuyruğu

**Şimdi işle / yeniden dene** butonu güvenlik/işleme pipeline'ını aynı HTTP isteğinde çalıştırır. ClamAV servis/bağlantı hatasında, yalnızca yapısal doğrulamayı geçmiş dosyalarda **ClamAV olmadan devam** kullanılabilir. Zararlı, hash engelli veya yapısal doğrulamayı geçememiş dosyalarda bu işlem kullanılamaz.

### Manuel yayın onayı

Manuel yayın onayı Karantina ekranında yapılmaz. Dosyaların teknik kontrolleri tamamen bittikten sonra çalışma **Admin CP → Portfolyo Sistemi → Portfolyo Moderasyonu** alanına geçer. Burada **Önizle**, **Onayla ve yayınla** ve **Reddet** işlemleri bulunur.

## 1.1.2

- Portfolyo yorum alanı XenForo'nun konu mesajı görünümündeki `message` sınıflarına geçirildi.
- Yorum yazma alanındaki düz textarea kaldırıldı ve XenForo dahili zengin metin editörü eklendi.
- Yorum submit işlemi `EditorPlugin` üzerinden BB code uyumlu hale getirildi.
- Yorum içeriği artık BB code olarak render edilir; biçimlendirme düz metin gibi gösterilmez.
- Yorumlara avatar, kullanıcı profili, ünvan, tarih, kalıcı yorum anchor'ı ve mesaj aksiyon barı eklendi.
- Yorum bazında **Raporla** işlemi eklendi; raporlar çalışma raporlarından ayrı `comment_id` hedefiyle tutulur.
- Yorum silme yetkisi sahip/moderatör/yönetici kurallarına göre mesaj aksiyon alanında gösterilir.
- Release workflow'una yerel XenForo template modification anchor kontrolü eklendi; özel template üzerinde eşleşmeyen modification varsa paketleme durur.
- 1.1.2 şema yükseltmesi otomatik çalışır; manuel SQL gerekmez.

## 1.1.1

- `blob_publish_failed` nedeniyle görsellerin `processing / error` durumunda kalması için blob şeması self-repair eklendi.
- Eski 1.0.x sürümlerinden yükselen kurulumlarda eksik blob tablosu ve kritik blob kolonları kontrol edilip onarılıyor.
- Normal blob klasörüne yayınlama başarısız olursa hash doğrulanmış direct-blob fallback eklendi.
- Karantina ekranındaki **Şimdi işle / yeniden dene** ve **ClamAV olmadan devam** URL'leri gerçek zamanlı pipeline controller'ına bağlandı.
- Sürüm numaralandırması `1.1.1 → 1.1.2 ... 1.1.9 → 1.2.0` düzeninde ilerler.
