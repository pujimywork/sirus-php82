<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnomedCode extends Model
{
    protected $table = 'rsmst_snomed_codes';

    protected $primaryKey = 'snomed_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'snomed_code',
        'display_en',
        'display_id',
        'value_set',
    ];
}
