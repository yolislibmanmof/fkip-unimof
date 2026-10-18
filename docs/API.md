# Dokumentasi API FKIP UNIMOF

Dokumen ini menjelaskan endpoint API yang tersedia pada situs FKIP UNIMOF. Ganti `https://fkip.unimof.ac.id` dengan alamat situs yang digunakan jika berbeda.

## Informasi umum

| Parameter | Nilai |
|---|---|
| Base URL | `https://fkip.unimof.ac.id/api/` |
| Format respons | JSON |
| Autentikasi | Hanya endpoint readiness yang memerlukan token |

## Pemeriksaan layanan

### Health check

**Endpoint:** `GET /api/health.php`  
**Metode yang didukung:** `GET`, `HEAD`

Memastikan PHP dapat merespons. Endpoint ini **tidak memeriksa koneksi database**.

Contoh respons `200 OK`:

```json
{
  "status": "ok",
  "service": "fkip-unimof",
  "timestamp": "2026-10-18T10:00:00Z"
}