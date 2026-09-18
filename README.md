# Warext Studios | XenForo Portfolio System

## Türkçe

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

## Destek

Sorularınız, hata bildirimleriniz, kurulum desteği ve Warext Studios XenForo eklentileriyle ilgili yardım için destek Discord sunucumuza katılabilirsiniz:

**Discord:** https://discord.gg/tgsV5XMcFS

---

## English

Warext Studios XenForo Portfolio System is a XenForo 2.3+ add-on for publishing visual work and 3D projects.

**Current version:** 1.1.7  
**Add-on ID:** `Warext/Portfolio`

## Installation / upgrade

Upload `Warext-Studios-XenForo-Portfolyo-Sistemi-1.1.7.zip` from GitHub Releases through XenForo Admin CP → **Add-ons → Install/upgrade from archive** over the existing version.

No manual SQL import is required for 1.1.7. On existing installations, the `attach_count` field required for comment attachments is created automatically during the add-on upgrade.

## User-facing pages

- Navbar **Portfolio** → `/portfolyo/`: public showcase of published work.
- User menu **My Portfolio** → `/portfolyo/mine`
- User menu **New Work** → `/portfolyo/add`
- User menu **Saved Items** → `/portfolyo/saved`
- Users can upload a cover image, multiple gallery images, and GLB 3D models when their group is allowed to do so.

## Comment system

Portfolio comments are connected directly to XenForo's normal thread-message and quick-reply infrastructure. The add-on does not create a separate editor that merely imitates XenForo.

- The comment list uses `block block--messages`, and every comment uses the `message message--post js-post` structure.
- User postbits are rendered with XenForo `message_macros::user_info`.
- The quick-reply form uses the same `block js-quickReply` and `attachment-manager quick-reply` handlers as normal threads.
- The editor body comes directly from XenForo's `quick_reply_macros::body` macro.
- `attachmentData` is prepared by XenForo's `XF:Attachment` repository. This means **Attach files**, upload progress, attachment removal, and small/full image insertion are handled by XenForo's own attachment manager.
- Comment attachments use the `wrxt_portfolio_comment` content type and `Warext\Portfolio\Attachment\Comment` handler and are stored through XenForo's attachment tables.
- On submit, `attachment_hash` is validated and only temporary files uploaded by the logged-in user for that specific Portfolio comment are associated with the new comment.
- Preview uses XenForo `XF:BbCodePreview` and supports temporary attachments that have not yet been submitted.
- Submitted comment attachments are displayed using `message_macros::attachments`; attachments already embedded through `[ATTACH]` are not listed a second time.
- Comment text is read as BBCode through `EditorPlugin::fromInput('message')` and rendered by XenForo's BBCode renderer.
- **Report** and **Delete** actions are located in the comment message action bar.

Because the same core DOM and macro infrastructure is used, active styles or compatible add-ons that modify XenForo's normal thread editor also see the same underlying structures in the Portfolio quick-reply area.

## Publishing pipeline

1. The uploaded file is quarantined.
2. MIME type, magic bytes, file structure, size, and SHA-256 are validated.
3. Malware scanning is performed when ClamAV is available.
4. JPG/PNG/WebP images are converted into safe WebP output; if the isolated worker is unavailable, Imagick/GD fallback processing is attempted.
5. GLB models are analyzed by an isolated worker.
6. Processed output is stored in the blob store; if normal blob publishing fails, a validated direct-blob fallback may be used.
7. After technical checks pass, the work is sent to **Portfolio Moderation**.
8. Authorized staff can **Approve and publish** or **Reject** the submission.

## Admin CP

The **Portfolio System** section includes Portfolio Management, Portfolio Moderation, Security Center, Quarantine / processing queue, Blocked Files, Security Events, Audit Logs, SHA-256 Block List, and Portfolio Settings.

## 1.1.7

- The `attachment-manager quick-reply` chain used by normal XenForo thread replies is connected to Portfolio comments.
- `quick_reply_macros::body` now receives real XenForo `attachmentData`; the **Attach files** control is created by the core attachment manager rather than a simulated UI.
- A XenForo attachment content type and handler were added for `wrxt_portfolio_comment`.
- Temporary attachments are securely associated with comments when submitted.
- Preview supports temporary attachments.
- Submitted attachments use the normal `message_macros::attachments` rendering flow.
- The `attach_count` migration runs automatically for older installations.
- Native editor, postbit, BBCode, reporting, and security behavior from 1.1.6 is retained.
- No manual SQL is required.

## Support

For questions, bug reports, installation support, and help with Warext Studios XenForo add-ons, you can join our support Discord server:

**Discord:** https://discord.gg/tgsV5XMcFS
