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
        'file_path',
        'link_url',
        'status',
        'total_score',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

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