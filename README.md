# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.1.4  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

GitHub Releases bölümündeki `Warext-Studios-XenForo-Portfolyo-Sistemi-1.1.4.zip` paketini XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden mevcut sürümün üzerine yükleyin.

1.1.4 için manuel SQL import gerekmez.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Çalışma açıklaması ve yorum yazma alanları XenForo dahili zengin metin editörünü kullanır.
- Kapak, çoklu galeri görseli ve izinli gruplarda GLB 3D model yüklenebilir.

## Yorum sistemi

1.1.4 ile yorum görünümü XenForo konu görünümündeki yapıya daha yakın hale getirildi.

- Yorum başlığı ayrı bloktur.
- Her yorum bağımsız `message message--post` kartıdır; ortak tek kutunun parçası gibi görünmez.
- Her kartta sol kullanıcı paneli, sağ mesaj alanı, tarih, kalıcı yorum numarası ve mesaj aksiyonları bulunur.
- Yorumlar arasında ayrı kart boşluğu vardır.
- Hızlı cevap alanı yorum listesinden ayrıdır ve XenForo'nun `block js-quickReply → block-container → block-body → message--quickReply` yapısını kullanır.
- Düz textarea kullanılmaz; XenForo `<xf:editor>` kullanılır.
- Backend `EditorPlugin::fromInput('message')` üzerinden BB code alır.
- Yorum içeriği XenForo BB code renderer ile gösterilir.
- Kullanıcı yetkisine göre **Raporla** ve **Sil** işlemleri mesaj aksiyon çubuğunda görünür.
- Yorum raporları çalışma raporundan ayrı hedef olarak moderasyona aktarılır.

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

**Şimdi işle / yeniden dene** butonu güvenlik/işleme pipeline'ını aynı HTTP isteğinde çalıştırır. ClamAV servis/bağlantı hatasında, yalnızca yapısal doğrulamayı geçmiş dosyalarda **ClamAV olmadan devam** kullanılabilir.

### Manuel yayın onayı

Dosyaların teknik kontrolleri bittikten sonra çalışma **Admin CP → Portfolyo Sistemi → Portfolyo Moderasyonu** alanına geçer. Burada **Önizle**, **Onayla ve yayınla** ve **Reddet** işlemleri bulunur.

## 1.1.4

- Yorumların ve hızlı cevap editörünün aynı `block-container` içinde birleşmesi kaldırıldı.
- Her yorum bağımsız XenForo mesaj kartına dönüştürüldü.
- Hızlı cevap editörü ayrı `block js-quickReply` yapısına taşındı.
- `message.less` ve `bb_code.less` entegrasyonu korunuyor.
- 1.1.2'de eklenen BB code, yorum raporlama ve yetki kontrolleri korunuyor.
- Manuel SQL gerekmez.
