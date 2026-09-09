# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.0.7  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

Güncel paketi GitHub Releases bölümünden indirin ve XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden yükleyin.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: tüm yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Diğer üyelerin profilindeki **Portfolyo** butonu yalnızca o üyenin yayınlanmış çalışmalarını gösterir.

**Çalışmalarım** sayfasında yeni çalışma ekleme butonu bulunur. Kullanıcının henüz hiç portfolyo içeriği yoksa boş ekran yerine açıklayıcı bir boş durum alanı ve **İlk çalışmanı ekle** butonu gösterilir. Oluşturma izni kapalıysa bunun nedeni açıkça belirtilir.

Genel vitrin ve yayınlanmış çalışma sayfaları herkese açıktır. Oluşturma, düzenleme, yorum, beğeni, kaydetme, takip ve raporlama işlemleri kullanıcı grubu izinleriyle yönetilir.

## Admin CP

**Portfolyo Sistemi** bağımsız ana kategori olarak görünür. Portfolyo Yönetimi, Moderasyon ve Raporlar, Güvenlik Merkezi, Karantina, Engellenen Dosyalar, Güvenlik Olayları, Denetim Kayıtları, SHA-256 Engelleme Listesi ve Portfolyo Ayarları bu bölüm altındadır.

## 1.0.7

- **Çalışmalarım** sayfasına sağ üst **Yeni çalışma ekle** butonu eklendi.
- Hiç çalışma yokken boş ekran yerine **Henüz herhangi bir portfolyo içeriği eklemediniz** mesajı gösterilir.
- Boş durumda ayrıca **İlk çalışmanı ekle** çağrı butonu gösterilir.
- Kullanıcının oluşturma izni yoksa buton yerine izin durumunu açıklayan mesaj gösterilir.
