# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.1.6  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

GitHub Releases bölümündeki `Warext-Studios-XenForo-Portfolyo-Sistemi-1.1.6.zip` paketini XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden mevcut sürümün üzerine yükleyin.

1.1.6 için manuel SQL import gerekmez.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Kapak, çoklu galeri görseli ve izinli gruplarda GLB 3D model yüklenebilir.

## Yorum sistemi

1.1.6 ile Portfolyo hızlı cevap alanındaki özel editör HTML'i kaldırıldı. Form, XenForo'nun normal konularda kullandığı **`quick_reply_macros::body`** makrosunu doğrudan çağırır.

- Yorum listesi `block block--messages` ve her yorum `message message--post js-post` yapısını kullanır.
- Kullanıcı postbiti XenForo `message_macros::user_info` ile oluşturulur.
- Hızlı cevap formu `block js-quickReply` yapısındadır ve içeride XenForo `quick_reply_macros::body` kullanılır.
- Toolbar, editör gövdesi, avatar hücresi, form buton grubu ve önizleme butonu Portfolyo tarafından yeniden çizilmez; XenForo çekirdek makrosundan gelir.
- Yorum önizlemesi XenForo `XF:BbCodePreview` controller plugin'i üzerinden çalışır.
- Yorum gönderimi backend'de `EditorPlugin::fromInput('message')` üzerinden BB code olarak alınır.
- Yorum içeriği XenForo BB code renderer ile gösterilir.
- **Raporla** ve **Sil** işlemleri yorumun kendi message action bar alanındadır.

Bu yapı sayesinde normal konu hızlı cevap alanını değiştiren tema veya uyumlu XenForo editör eklentileri Portfolyo tarafında da aynı çekirdek quick-reply DOM'una uygulanabilir.

## Yayın akışı

1. Dosya karantinaya alınır.
2. MIME, magic bytes, dosya yapısı, boyut ve SHA-256 doğrulanır.
3. ClamAV erişilebiliyorsa zararlı yazılım taraması yapılır.
4. JPG/PNG/WebP güvenli WebP çıktısına dönüştürülür; izole worker yoksa Imagick/GD fallback denenir.
5. GLB modeller izole worker ile analiz edilir.
6. İşlenmiş çıktı blob deposuna alınır; normal blob yayını başarısız olursa doğrulanmış direct-blob fallback kullanılabilir.
7. Teknik kontroller tamamlanınca çalışma **Portfolyo Moderasyonu** ekranına gider.
8. Yetkili **Onayla ve yayınla** veya **Reddet** işlemini uygular.

## Admin CP

**Portfolyo Sistemi** altında Portfolyo Yönetimi, Portfolyo Moderasyonu, Güvenlik Merkezi, Karantina / işlem kuyruğu, Engellenen Dosyalar, Güvenlik Olayları, Denetim Kayıtları, SHA-256 Engelleme Listesi ve Portfolyo Ayarları bulunur.

## 1.1.6

- Portfolyo quick-reply alanındaki elle yazılmış avatar/editör/buton HTML'i kaldırıldı.
- XenForo'nun kendi `quick_reply_macros::body` makrosu kullanılmaya başlandı.
- Normal quick reply formuna uygun `quick-reply ajax-submit` JS init yapısı eklendi.
- XenForo native önizleme butonu için Portfolyo yorum önizleme endpoint'i eklendi.
- Önizleme `XF:BbCodePreview` üzerinden oluşturulur.
- 1.1.2–1.1.5 yorum BB code, raporlama, postbit ve güvenlik davranışları korunur.
- Manuel SQL gerekmez.
