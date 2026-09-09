# Warext Studios | XenForo Portfolyo Sistemi

XenForo 2.3+ için görsel ve 3D çalışmaların yayınlanabildiği portfolyo eklentisi.

**Güncel sürüm:** 1.0.4  
**Add-on ID:** `Warext/Portfolio`

## İndirme ve kurulum

Güncel kurulum paketini GitHub **Releases** bölümünden indirin:

https://github.com/benjamin1734/Warext-Studios-Xenfero-Portfolyo-Sistemi/releases/latest

XenForo Admin CP → **Add-ons → Install/upgrade from archive** üzerinden ZIP dosyasını yükleyin.

## Kullanıcı tarafı

Ana forum navigasyonunda **Portfolyo** bağlantısı bulunur. Portfolyo ana sayfası `/portfolyo/` adresidir.

Giriş yapmış kullanıcılar portfolyo sayfasının üst kısmında izinlerine göre şu işlemleri görür:

- **Yeni Çalışma** → `/portfolyo/add`
- **Çalışmalarım** → `/portfolyo/mine`
- **Kaydedilenler** → `/portfolyo/saved`

Üye profillerinde ayrıca **Portfolyo** butonu bulunur ve ilgili üyenin yayınlanmış çalışmalarını açar.

## Admin CP

Admin CP sol menüsünde **Portfolyo Sistemi** bağımsız bir ana kategori olarak görünür. Altında:

- Portfolyo Yönetimi
- Moderasyon ve Raporlar
- Güvenlik Merkezi
- Karantina
- Engellenen Dosyalar
- Güvenlik Olayları
- Denetim Kayıtları
- SHA-256 Engelleme Listesi
- Portfolyo Ayarları

Ayarlar doğrudan `admin.php?options/groups/wrxtPortfolioSettings/` adresinden de açılabilir.

## Gereksinimler

- XenForo 2.3.0+
- PHP 8.0+
- PHP ZIP desteği
- ClamAV / `clamd`
- Imagick veya GD

## 1.0.4

- Admin CP menüsü gerçek bağımsız **Portfolyo Sistemi** kategorisi olacak şekilde düzeltildi.
- Ham `admin_navigation.*`, `admin_permission.*`, `option.*` ve izin phrase anahtarlarının kullanıcıya görünmesi engellendi.
- Portfolyo ayarlarının Türkçe başlıkları düzenlendi.
- Kullanıcı işlemleri portfolyo ana sayfasının üst bölümüne taşındı.
- Ayar XML'i ile PHP servislerindeki option ID'leri eşitlendi.
