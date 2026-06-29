<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentUpload extends Model
{
    protected $fillable = [
        'appointment_id',
        'file_path',
        'file_type',
    ];

    protected $appends = [
        'image_url',
        'image_path',
    ];

    public function getImageUrlAttribute()
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function getImagePathAttribute()
    {
        return $this->file_path;
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}