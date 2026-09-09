# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.0.8  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

Güncel paketi GitHub Releases bölümünden indirin ve XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden yükleyin.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: tüm yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Diğer üyelerin profilindeki **Portfolyo** butonu yalnızca o üyenin yayınlanmış çalışmalarını gösterir.

**Çalışmalarım** sayfasında yeni çalışma ekleme butonu bulunur. Kullanıcının henüz hiç portfolyo içeriği yoksa boş ekran yerine açıklayıcı bir boş durum alanı ve **İlk çalışmanı ekle** butonu gösterilir.

Yeni çalışma oluşturma ekranı tek akışta çalışır. Açıklama alanında XenForo'nun dahili zengin metin editörü kullanılır. Aynı formdan kapak görseli, birden fazla galeri görseli ve kullanıcı grubunun kotası izin veriyorsa `.glb` 3D model seçilebilir. Seçilen dosyalar mevcut karantina, güvenlik taraması, işleme ve kota sisteminden geçirilir.

Genel vitrin ve yayınlanmış çalışma sayfaları herkese açıktır. Oluşturma, düzenleme, yorum, beğeni, kaydetme, takip ve raporlama işlemleri kullanıcı grubu izinleriyle yönetilir.

## Admin CP

**Portfolyo Sistemi** bağımsız ana kategori olarak görünür. Portfolyo Yönetimi, Moderasyon ve Raporlar, Güvenlik Merkezi, Karantina, Engellenen Dosyalar, Güvenlik Olayları, Denetim Kayıtları, SHA-256 Engelleme Listesi ve Portfolyo Ayarları bu bölüm altındadır.

## 1.0.8

- Yeni çalışma ekranındaki düz açıklama alanı XenForo dahili zengin metin editörüne dönüştürüldü.
- İlk oluşturma ekranına **Kapak görseli** yükleme alanı eklendi.
- İlk oluşturma ekranına çoklu **Galeri görselleri** yükleme alanı eklendi.
- 3D model kullanımına izin verilen gruplar için `.glb` **3D model** yükleme alanı eklendi.
- İlk form `multipart/form-data` yükleme akışına geçirildi.
- Seçilen dosyaların portfolyo oluşturulduktan hemen sonra mevcut karantina/güvenlik kuyruğuna alınması sağlandı.
- XenForo editörü içeriği `EditorPlugin` üzerinden BBCode olarak güvenli şekilde alınır.
