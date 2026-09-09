# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.0.9  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

Güncel paketi GitHub Releases bölümünden indirin ve XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden mevcut sürümün üzerine yükseltin. 1.0.9 için manuel SQL gerekmez.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: yayınlanmış çalışmaların kart tabanlı genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Diğer üyelerin profilindeki **Portfolyo** butonu yalnızca o üyenin yayınlanmış çalışmalarını gösterir.
- Yeni çalışma ve düzenleme ekranlarında XenForo'nun dahili zengin metin editörü kullanılır.
- Kapak, çoklu galeri görseli ve izinli gruplarda GLB 3D model yüklenebilir.

## Yayın akışı

1. Dosya karantinaya alınır.
2. Uzantı, MIME, magic bytes, dosya yapısı, boyut ve SHA-256 kontrolleri yapılır.
3. ClamAV erişilebiliyorsa zararlı yazılım taraması yapılır.
4. Görseller güvenli WebP çıktısına yeniden işlenir; GLB modeller izole worker ile analiz edilir.
5. Teknik kontroller geçen çalışma **Portfolyo Moderasyonu** ekranına düşer.
6. Yetkili **Onayla ve yayınla** veya **Reddet** işlemini uygular.

ClamAV sunucu/cPanel tarafından kullanılamıyorsa dosya otomatik olarak güvenlik kontrolünü geçmiş sayılmaz. Güvenlik yetkilisi, yalnızca yapısal doğrulamayı geçmiş ve gerçek bir ClamAV servis/bağlantı hatasında bekleyen dosyada **ClamAV olmadan devam** işlemini açıkça seçebilir. Enfekte, hash engelli veya yapısal doğrulamadan geçemeyen dosyalar bu işlemle aşılamaz.

## Admin CP

**Portfolyo Sistemi** altında:

- Portfolyo Yönetimi
- Portfolyo Moderasyonu
- Güvenlik Merkezi
- Karantina / işlem kuyruğu
- Engellenen Dosyalar
- Güvenlik Olayları
- Denetim Kayıtları
- SHA-256 Engelleme Listesi
- Portfolyo Ayarları

Güvenlik Merkezi ClamAV erişimini, `proc_open` worker durumunu ve kullanılabilir görsel motorunu gösterir. Karantina ekranından bekleyen işler yeniden tetiklenebilir.

## 1.0.9

- Admin CP **Portfolyo Ayarları** bağlantısındaki 404 sorunu giderildi. Eksik XenForo option-group metadata'sı yükseltmede ve ayar ekranı açılırken kendini onarıyor.
- Teknik kontrolden geçen çalışmalar için bağımsız **Portfolyo Moderasyonu** kuyruğu eklendi.
- Moderasyon ekranına **Önizle**, **Onayla ve yayınla** ve **Reddet** işlemleri eklendi.
- Karantina ekranı salt okunur olmaktan çıkarıldı; durum, doğrulama, tarama, işleme ve hata nedeni ayrıntılı gösteriliyor.
- Karantinaya **İşlemi yeniden dene** ve yalnızca güvenli koşullarda kullanılabilen **ClamAV olmadan devam** işlemleri eklendi.
- İşleme kuyruğundaki yanlış option ID (`wrxtPortfolioProcessingRetryMinutes`) düzeltilerek gerçek `wrxtPfProcRetryMins` seçeneğine bağlandı.
- Genel portfolyo vitrini kart/grid yapısına geçirildi; kapak önizlemeleri, kategori/tür ve istatistikler eklendi.
- Çalışma detay sayfası medya, açıklama, bilgi paneli, etiketler, galeri, 3D model ve etkileşim alanlarıyla yeniden tasarlandı.
- Kullanıcı portfolyosu, Kaydedilenler ve Çalışmalarım ekranları aynı görsel sisteme geçirildi.
- Oluşturma/düzenleme şablonları doğrudan XenForo editörü ve medya alanlarını içeriyor; önceki kırılgan kendi-template modifikasyonları kaldırıldı.
- Onay bekleyen sahipler için durum metinleri Türkçeleştirildi ve teknik süreç daha anlaşılır hale getirildi.
