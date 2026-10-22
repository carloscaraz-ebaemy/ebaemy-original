<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Una guía de novedades que un usuario ya vio (o pidió no volver a ver).
 * Ver la migración create_user_feature_tours_table.
 */
class UserFeatureTour extends Model
{
    use UsesTenantConnection;

    protected $table = 'user_feature_tours';

    protected $fillable = ['user_id', 'tour_key', 'status'];
}
