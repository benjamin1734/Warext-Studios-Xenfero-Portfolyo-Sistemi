# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.0.6  
**Add-on ID:** `Warext/Portfolio`

## Kurulum / yükseltme

Güncel paketi GitHub Releases bölümünden indirin ve XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden yükleyin.

## Kullanıcı tarafı

- Navbar **Portfolyo** → `/portfolyo/`: tüm yayınlanmış çalışmaların genel vitrini.
- Kullanıcı menüsü **Portfolyom** → `/portfolyo/mine`
- Kullanıcı menüsü **Yeni çalışma** → `/portfolyo/add`
- Kullanıcı menüsü **Kaydedilenler** → `/portfolyo/saved`
- Diğer üyelerin profilindeki **Portfolyo** butonu yalnızca o üyenin yayınlanmış çalışmalarını gösterir.

Genel vitrin ve yayınlanmış çalışma sayfaları herkese açıktır. Oluşturma, düzenleme, yorum, beğeni, kaydetme, takip ve raporlama işlemleri kullanıcı grubu izinleriyle yönetilir.

## Admin CP

**Portfolyo Sistemi** bağımsız ana kategori olarak görünür. Portfolyo Yönetimi, Moderasyon ve Raporlar, Güvenlik Merkezi, Karantina, Engellenen Dosyalar, Güvenlik Olayları, Denetim Kayıtları, SHA-256 Engelleme Listesi ve Portfolyo Ayarları bu bölüm altındadır.

## 1.0.6

- Public route tanımları XenForo 2.3 route formatına göre düzeltildi.
- `calisma` ve `kullanici` sub-route formatlarına eksik literal segmentler eklendi.
- Public route section context değeri gerçek navbar navigation ID'si olan `wrxtPortfolioNav` ile eşitlendi.
- `/portfolyo/`, `/portfolyo/add`, `/portfolyo/mine`, `/portfolyo/saved`, çalışma ve kullanıcı portfolyo sayfalarının aynı route ailesinde doğru çözülmesi sağlandı.
