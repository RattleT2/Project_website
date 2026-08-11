<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #1a56db; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h2 style="margin: 0;">Laporan Media Kominfo</h2>
        <p style="margin: 5px 0 0; opacity: 0.9;">Kabupaten Banjar</p>
    </div>

    <div style="background: #fff; padding: 30px; border: 1px solid #e5e7eb; border-top: none;">
        <p>Halo <strong>{{ $userName }}</strong>,</p>

        <p>Status laporan Anda telah diperbarui oleh admin:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold; width: 150px;">ID Laporan</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">#{{ $reportId }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold;">Jenis Media</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $mediaType }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold;">Status Sebelumnya</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $oldStatus }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background: #dcfce7; font-weight: bold;">Status Baru</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background: #dcfce7;"><strong>{{ $newStatus }}</strong></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold;">Total Skor</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $totalScore }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold;">Kategori</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb;">{{ $category }}</td>
            </tr>
        </table>

        <p>Silakan login ke sistem untuk melihat detail laporan Anda.</p>

        <p style="margin-top: 30px; color: #6b7280; font-size: 13px;">
            Email ini dikirim otomatis oleh Sistem Laporan Media Kabupaten Banjar.<br>
            Mohon tidak membalas email ini.
        </p>
    </div>
</body>
</html>
