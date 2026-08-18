<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'media_type_id',
        'report_code',
        'file_path',
        'link_url',
        'status',
        'total_score',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    protected $appends = [
        'category',
    ];

    public function getCategoryAttribute(): string
    {
        return match (true) {
            $this->total_score >= 68 => 'Kategori 1',
            $this->total_score >= 40 => 'Kategori 2',
            $this->total_score >= 20 => 'Kategori 3',
            default => 'Tidak memenuhi kategori',
        };
    }

    /**
     * Relasi: Setiap Laporan dimiliki oleh satu User (Pelapor).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi: Setiap Laporan merujuk pada satu Jenis Media.
     */
    public function mediaType()
    {
        return $this->belongsTo(MediaType::class);
    }

    /**
     * Relasi: Satu Laporan memiliki banyak Jawaban Kriteria.
     */
    public function answers()
    {
        return $this->hasMany(ReportAnswer::class);
    }
}