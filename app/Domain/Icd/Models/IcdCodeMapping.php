<?php

namespace App\Domain\Icd\Models;

use Database\Factories\IcdCodeMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcdCodeMapping extends Model
{
    /** @use HasFactory<IcdCodeMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'code_source',
        'version_source',
        'code_cible',
        'version_cible',
    ];
}
