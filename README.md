# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.1.7  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

GitHub Releases bölümündeki `Warext-Studios-XenForo-Portfolyo-Sistemi-1.1.7.zip` paketini XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden mevcut sürümün üzerine yükleyin.

1.1.7 için manuel SQL import gerekmez. Mevcut kurulumlarda yorum ekleri için gereken `attach_count` alanı eklenti yükseltmesi sırasında otomatik oluşturulur.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Kapak, çoklu galeri görseli ve izinli gruplarda GLB 3D model yüklenebilir.

## Yorum sistemi

Portfolyo yorum alanı normal XenForo konu mesajı ve hızlı cevap altyapısına bağlanır. Görünümü taklit eden ayrı bir editör üretilmez.

- Yorum listesi `block block--messages`; her yorum `message message--post js-post` yapısını kullanır.
- Kullanıcı postbiti XenForo `message_macros::user_info` ile oluşturulur.
- Hızlı cevap formu normal konulardaki gibi `block js-quickReply` ve `attachment-manager quick-reply` handler'larını kullanır.
- Editör gövdesi doğrudan XenForo `quick_reply_macros::body` makrosundan gelir.
- `attachmentData` XenForo `XF:Attachment` repository'si tarafından hazırlanır. Bu nedenle **Dosya ekle**, yükleme ilerlemesi, ek silme ve editöre küçük/tam görsel ekleme kontrolleri XenForo'nun kendi attachment manager sistemiyle çalışır.
- Yorum ekleri `wrxt_portfolio_comment` content type ve `Warext\Portfolio\Attachment\Comment` handler'ı üzerinden XenForo attachment tablolarına bağlanır.
- Gönderimde `attachment_hash` doğrulanır ve yalnızca giriş yapan kullanıcının bu Portfolyo yorumu için yüklediği geçici ekler yeni yoruma ilişkilendirilir.
- Önizleme XenForo `XF:BbCodePreview` üzerinden çalışır ve henüz gönderilmemiş geçici ekleri de kullanır.
- Gönderilmiş yorumların ekleri XenForo `message_macros::attachments` ile gösterilir; mesaj içine `[ATTACH]` ile yerleştirilen ekler ikinci kez bağımsız listelenmez.
- Yorum metni backend'de `EditorPlugin::fromInput('message')` üzerinden BB code olarak alınır ve XenForo BB code renderer ile gösterilir.
- **Raporla** ve **Sil** işlemleri yorumun message action bar alanındadır.

Bu nedenle aktif tema veya XenForo'nun normal konu editörünü uyumlu biçimde değiştiren eklentiler, Portfolyo hızlı cevap alanında da aynı çekirdek DOM ve makro altyapısını görür.

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

## 1.1.7

- Normal XenForo konu hızlı cevap formundaki `attachment-manager quick-reply` zinciri Portfolyo'ya bağlandı.
- `quick_reply_macros::body` artık gerçek XenForo `attachmentData` verisi alır; **Dosya ekle** kontrolü sahte değil, çekirdek attachment manager tarafından oluşturulur.
- `wrxt_portfolio_comment` için XenForo attachment content type ve handler eklendi.
- Yorum gönderiminde geçici ekler güvenli biçimde yoruma ilişkilendirilir.
- Önizleme geçici ekleri destekler.
- Gönderilmiş ekler normal `message_macros::attachments` yapısında gösterilir.
- Eski kurulumlar için `attach_count` migration'ı otomatik çalışır.
- 1.1.6'daki native editör, postbit, BB code, raporlama ve güvenlik davranışları korunur.
- Manuel SQL gerekmez.
