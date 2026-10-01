# 📊 Dokumentasi API - FKIP UNIMOF

Dokumen ini berisi dokumentasi lengkap untuk semua endpoint API yang tersedia pada Website FKIP UNIMOF.

## Informasi Umum

| Parameter     | Nilai                              |
|---------------|------------------------------------|
| Base URL      | `https://fkip.unimof.ac.id/api/`   |
| Format        | JSON                               |
| Method        | POST                               |
| Content-Type  | `application/json`                 |

## Endpoint yang Tersedia

### 1. Download Counter

Meningkatkan jumlah unduhan untuk file tertentu di Download Center.

**URL:** `POST /api/download-counter.php`

**Request Body:**
```json
{
  "id": 123
}