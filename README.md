# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.1.1  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

GitHub Releases bölümündeki `Warext-Studios-XenForo-Portfolyo-Sistemi-1.1.1.zip` paketini XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden mevcut sürümün üzerine yükleyin.

1.1.1 için manuel SQL import gerekmez. Yükseltme adımı eski 1.0.x kurulumlarında eksik kalmış olabilecek blob şemasını otomatik onarır ve `blob_publish_failed` durumunda takılmış dosyaları yeniden denenebilir hale getirir.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Açıklama alanları XenForo dahili zengin metin editörünü kullanır.
- Kapak, çoklu galeri görseli ve izinli gruplarda GLB 3D model yüklenebilir.

## Yayın akışı

1. Dosya karantinaya alınır.
2. MIME, magic bytes, dosya yapısı, boyut ve SHA-256 doğrulanır.
3. ClamAV erişilebiliyorsa zararlı yazılım taraması yapılır.
4. JPG/PNG/WebP dosyaları güvenli WebP çıktısına dönüştürülür. İzole worker kullanılamazsa Imagick/GD fallback denenir.
5. GLB modeller güvenlik nedeniyle izole worker ile analiz edilir.
6. İşlenmiş çıktı blob deposuna alınır. Normal blob yayını başarısız olursa 1.1.1 doğrulanmış direct-blob fallback kullanır.
7. Tüm dosyalar teknik kontrolleri geçince çalışma **Portfolyo Moderasyonu** ekranına gider.
8. Yetkili **Onayla ve yayınla** veya **Reddet** işlemini uygular.

## Admin CP

**Portfolyo Sistemi** altında Portfolyo Yönetimi, Portfolyo Moderasyonu, Güvenlik Merkezi, Karantina / işlem kuyruğu, Engellenen Dosyalar, Güvenlik Olayları, Denetim Kayıtları, SHA-256 Engelleme Listesi ve Portfolyo Ayarları bulunur.

### Karantina / işlem kuyruğu

**Şimdi işle / yeniden dene** butonu 1.1.1'de gerçekten aynı HTTP isteğinde güvenlik/işleme pipeline'ını çalıştırır. Önceki sürümde gerçek zamanlı controller hazırlanmış olmasına rağmen Karantina ekranındaki mevcut buton eski action'a bağlı kaldığı için işlem yeniden job kuyruğuna gönderiliyordu.

ClamAV servis/bağlantı hatasında, yalnızca yapısal doğrulamayı geçmiş dosyalarda **ClamAV olmadan devam** kullanılabilir. Zararlı, hash engelli veya yapısal doğrulamayı geçememiş dosyalarda bu işlem kullanılamaz.

### Manuel yayın onayı

Manuel yayın onayı Karantina ekranında yapılmaz. Dosyaların teknik kontrolleri tamamen bittikten sonra çalışma:

**Admin CP → Portfolyo Sistemi → Portfolyo Moderasyonu**

alanına geçer. Burada **Önizle**, **Onayla ve yayınla** ve **Reddet** işlemleri bulunur.

## 1.1.1

- `blob_publish_failed` nedeniyle görsellerin `processing / error` durumunda kalması için blob şeması self-repair eklendi.
- Eski 1.0.x sürümlerinden yükselen kurulumlarda eksik blob tablosu ve kritik blob kolonları kontrol edilip onarılıyor.
- Yükseltme sırasında eski `blob_publish_failed` kayıtlarının `next_processing_date` değeri sıfırlanarak yeniden işlenmeleri sağlanıyor.
- Normal blob klasörüne yayınlama başarısız olursa hash doğrulanmış direct-blob fallback eklendi; medya endpoint'leri yine dosyayı gösterebiliyor.
- Moderasyon ve yayın güvenlik kontrolleri hem standart blob hem doğrulanmış fallback depolamayı destekleyecek şekilde eşitlendi.
- Karantina ekranındaki mevcut **Şimdi işle / yeniden dene** ve **ClamAV olmadan devam** URL'leri gerçek zamanlı pipeline controller'ına bağlandı.
- İşlem hataları tek `blob_publish_failed` kodu altında tamamen gizlenmek yerine güvenli teknik alt hata kodlarıyla kayda alınıyor.
- Sürüm numaralandırması bundan sonra `1.1.1 → 1.1.2 ... 1.1.9 → 1.2.0` düzeninde ilerleyecek.
