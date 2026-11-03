<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected $table = 'employee_documents';

    protected $fillable = [
        'user_id',
        'document_type',
        'title',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'expiry_date',
        'uploaded_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'file_size' => 'integer',
    ];

    protected $appends = [
        'file_url',
    ];

    public function getFileUrlAttribute(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        $base = request()->getSchemeAndHttpHost() ?: config('app.url');
        return rtrim($base, '/') . '/api/' . ltrim($this->file_path, '/');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

