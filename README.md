# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.1.5  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

GitHub Releases bölümündeki `Warext-Studios-XenForo-Portfolyo-Sistemi-1.1.5.zip` paketini XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden mevcut sürümün üzerine yükleyin.

1.1.5 için manuel SQL import gerekmez.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Çalışma açıklaması ve yorum yazma alanları XenForo dahili zengin metin editörünü kullanır.
- Kapak, çoklu galeri görseli ve izinli gruplarda GLB 3D model yüklenebilir.

## Yorum sistemi

1.1.5 ile yorum alanı XenForo `thread_view` / `post_macros` düzenine daha doğrudan bağlandı.

- Mesaj listesi `block block--messages` yapısını kullanır.
- Her yorum gerçek `message message--post js-post` kartı olarak render edilir.
- Kullanıcı paneli elle taklit edilmez; XenForo `message_macros::user_info` makrosu kullanılır. Böylece avatar, kullanıcı adı, ünvan, banner/rol görünümü aktif temanın normal konu mesajlarıyla aynı altyapıdan gelir.
- Mesaj meta alanı `message-attribution message-attribution--split`, içerik `message-content js-messageContent`, alt işlemler gerçek `message-actionBar actionBar` yapısını kullanır.
- **Raporla** ve **Sil** sol iç işlem grubunda gösterilir; Sil işleminde XenForo onayı kullanılır.
- Hızlı cevap, yorum listesinden bağımsız `block js-quickReply → block-container → block-body → message--quickReply` yapısındadır.
- Düz textarea kullanılmaz; XenForo `<xf:editor>` kullanılır.
- Backend `EditorPlugin::fromInput('message')` üzerinden BB code alır ve BB code renderer ile gösterir.
- `message.less` ve `bb_code.less` doğrudan yüklenir; aktif temanın XenForo konu mesajı görünümü Portfolyo yorumlarına uygulanır.
- Desteklenmeyen forum butonları sahte olarak eklenmez; mevcut Portfolyo yorum altyapısındaki raporlama/silme işlevleri gerçek controller işlemlerine bağlıdır.

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

## 1.1.5

- Yorum listesi normal XenForo `block--messages` kapsayıcısına geçirildi.
- Elle hazırlanmış kullanıcı paneli kaldırıldı; `message_macros::user_info` kullanılmaya başlandı.
- Tarih/meta satırı, içerik gövdesi ve mesaj işlem alanı normal konu mesajı sınıf hiyerarşisine geçirildi.
- Raporla/Sil aksiyonları normal mesajlarda olduğu gibi iç aksiyon grubuna taşındı.
- Hızlı cevap yorum listesinden bağımsız XenForo quick-reply bloğu olarak korunuyor.
- 1.1.2–1.1.4 BB code, editör, raporlama ve güvenlik davranışları korunur.
- Manuel SQL gerekmez.
