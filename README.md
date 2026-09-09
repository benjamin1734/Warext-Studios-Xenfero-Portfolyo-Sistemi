# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.0.10  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

Güncel paketi GitHub Releases bölümünden indirin ve XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden mevcut sürümün üzerine yükseltin. 1.0.10 için manuel SQL gerekmez.

Kurulum için **Release Assets** altında bulunan `Warext-Studios-XenForo-Portfolyo-Sistemi-1.0.10.zip` dosyasını kullanın. `dist/` paketi ve XenForo `hashes.json` kaydı GitHub Actions tarafından aynı kaynaklardan otomatik oluşturulur.

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
4. Görseller WebP çıktısına yeniden işlenir. Öncelik izole PHP worker'dır; cPanel'de `proc_open` veya PHP CLI kullanılamıyorsa Imagick/GD yerel fallback devreye girer.
5. GLB 3D modeller güvenlik nedeniyle yalnızca izole worker ile analiz edilir.
6. Tüm teknik kontroller geçen çalışma **Portfolyo Moderasyonu** ekranına düşer.
7. Yetkili **Onayla ve yayınla** veya **Reddet** işlemini uygular.

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

### Karantina / işlem kuyruğu

**Şimdi işle / yeniden dene** butonu 1.0.10'dan itibaren yalnızca XenForo job kuyruğuna kayıt eklemez; seçilen dosyanın o anki teknik aşamasını doğrudan çalıştırır ve sonucu yöneticiye gösterir.

- ClamAV erişilemiyorsa neden ekranda görünür ve güvenli koşullarda **ClamAV olmadan devam** kullanılabilir.
- Görsel worker kullanılamıyorsa Imagick/GD fallback otomatik denenir.
- GLB dosyasında worker yoksa dosya yayınlanmaz; açık hata koduyla işlem kuyruğunda kalır.
- Dosya teknik kontrolleri bitirdiğinde çalışma otomatik olarak **Portfolyo Moderasyonu** aşamasına taşınır.

### Manuel yayın onayı

Manuel yayın onayı **Karantina** ekranında yapılmaz. Karantina yalnızca teknik güvenlik ve dosya işleme aşamasıdır.

Teknik kontroller tamamen bittikten sonra:

**Admin CP → Portfolyo Sistemi → Portfolyo Moderasyonu**

Burada çalışma için **Önizle**, **Onayla ve yayınla** ve **Reddet** işlemleri görünür. Onaylanan çalışma genel `/portfolyo/` vitrininde yayınlanır.

## 1.0.10

- `processing` aşamasında görünürde hiçbir şey olmamasına neden olan ikinci job kuyruğu bağımlılığı kaldırıldı; güvenlik taraması tamamlandıktan sonra dosya işleme aynı çalışma zincirinde başlatılıyor.
- Karantina **Şimdi işle / yeniden dene** işlemi gerçek zamanlı çalışacak şekilde ayrıldı; yalnızca kuyruğa ekleyip beklemiyor.
- cPanel'de `proc_open` veya PHP CLI kapalı olduğunda JPG/PNG/WebP dosyaları için Imagick/GD tabanlı kontrollü yerel WebP fallback eklendi.
- GLB güvenliği gevşetilmedi; 3D model analizi için izole worker zorunlu kalmaya devam ediyor.
- Karantina ekranına yayın akışını açıklayan yardım alanı eklendi.
- Portfolyo Moderasyonu ekranına manuel onayın burada yapıldığını açıklayan bilgi alanı eklendi.
- ClamAV olmadan devam işlemi başarılı olduğunda dosya doğrudan işleme alınarak moderasyona geçiş deneniyor.
- İşlem sonucu `scan_pending`, `processing_pending`, `blocked` veya moderasyona hazır şeklinde yöneticiye açık mesajla gösteriliyor.

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
