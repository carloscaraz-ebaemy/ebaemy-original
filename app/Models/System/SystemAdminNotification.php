<?php

namespace App\Models\System;

use App\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificación dirigida al SuperAdmin (panel /admin).
 *
 * Se crea desde puntos clave del flujo (registro de seller, lead/order
 * en marketplace, etc.). La campanita del topbar del SuperAdmin las
 * lista y permite marcar como leídas. Polling cada 60s refresca el
 * contador.
 */
class SystemAdminNotification extends Model
{
    use UsesSystemConnection;

    protected $table = 'system_admin_notifications';

    protected $fillable = [
        'type',
        'title',
        'body',
        'icon',
        'link',
        'related_type',
        'related_id',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /** Helper para crear notificaciones desde cualquier punto del código. */
    public static function notify(
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?string $icon = null,
        ?string $relatedType = null,
        $relatedId = null
    ): self {
        return self::create([
            'type'         => $type,
            'title'        => $title,
            'body'         => $body,
            'icon'         => $icon,
            'link'         => $link,
            'related_type' => $relatedType,
            'related_id'   => $relatedId ? (int) $relatedId : null,
            'is_read'      => false,
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
