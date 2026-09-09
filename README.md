# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.0.5  
**Add-on ID:** `Warext/Portfolio`

## İndirme ve kurulum

Güncel kurulum paketini GitHub **Releases** bölümünden indirin:

https://github.com/benjamin1734/Warext-Studios-Xenfero-Portfolyo-Sistemi/releases/latest

XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden ZIP dosyasını yükleyin.

## Kullanıcı tarafı

Ana forum navigasyonundaki **Portfolyo** bağlantısı genel topluluk vitrinidir. `/portfolyo/` adresinde yalnızca yayınlanmış kullanıcı çalışmalarının listesi, filtreleri ve sıralaması bulunur. Kişisel yönetim butonları bu sayfada gösterilmez.

Kişisel portfolyo işlemleri kullanıcı adı/avatar menüsünden yapılır:

- **Portfolyom** → `/portfolyo/mine`
- **Yeni çalışma** → `/portfolyo/add`
- **Kaydedilenler** → `/portfolyo/saved`

Diğer üyelerin profillerindeki **Portfolyo** butonu o üyenin herkese açık yayınlanmış çalışmalarını gösterir.

Yayınlanmış portfolyo vitrini, kullanıcı portfolyo sayfaları ve yayınlanmış çalışma detayları genel erişime açıktır. İçerik oluşturma, düzenleme, kaydetme, yorum, takip ve raporlama işlemleri kullanıcı grubu izinlerine bağlıdır.

## Admin CP

Admin CP sol menüsünde **Portfolyo Sistemi** bağımsız bir ana kategori olarak görünür. Altında Portfolyo Yönetimi, Moderasyon ve Raporlar, Güvenlik Merkezi, Karantina, Engellenen Dosyalar, Güvenlik Olayları, Denetim Kayıtları, SHA-256 Engelleme Listesi ve Portfolyo Ayarları bulunur.

## Gereksinimler

- XenForo 2.3.0+
- PHP 8.0+
- PHP ZIP desteği
- ClamAV / `clamd`
- Imagick veya GD

## 1.0.5

- Navbar Portfolyo bağlantısı tamamen genel topluluk vitrini haline getirildi.
- Genel vitrin ve yayınlanmış çalışma görüntülemedeki gereksiz `view` izin engeli kaldırıldı.
- Kişisel Portfolyom / Yeni çalışma / Kaydedilenler işlemleri kullanıcı hesabı menüsüne taşındı.
- Genel portfolyo listesindeki kişisel işlem butonları kaldırıldı.
- Varsayılan XenForo grup izinlerinin kurulum ve yükseltmede gerçekten uygulanması sağlandı.
- Mevcut kurulumlarda Registered ve Administrative dahil varsayılan grupların eksik Portfolyo izinleri yükseltme sırasında otomatik tamamlanır.
