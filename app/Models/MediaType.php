<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Relasi: Satu Jenis Media bisa digunakan di banyak Laporan.
     */
    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}