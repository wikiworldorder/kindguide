<?php namespace App\Models;
// generated from /resources/views/vendor/survloop/admin/db/export-laravel-model-gen.blade.php

use Illuminate\Database\Eloquent\Model;

class KGAuditSect extends Model
{
    protected $table      = 'KG_AuditSect';
    protected $primaryKey = 'AudSecID';
    public $timestamps    = true;
    protected $fillable   = 
    [    
		'AudSecCertificationID', 
		'AudSecOrder', 
		'AudSecName', 
    ];
    
    // END SurvLoop auto-generated portion of Model
    
}
