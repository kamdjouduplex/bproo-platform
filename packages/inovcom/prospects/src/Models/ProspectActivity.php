<?php

namespace InovCom\Prospects\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class ProspectActivity extends TenantModel
{
    public const TYPE_NOTE = 'note';

    public const TYPE_CALL = 'call';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_EMAIL = 'email';

    public const TYPE_STATUS = 'status';

    protected $fillable = [
        'prospect_id',
        'user_id',
        'type',
        'body',
        'from_status',
        'to_status',
    ];

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_NOTE => 'Note',
            self::TYPE_CALL => 'Appel',
            self::TYPE_MEETING => 'Rendez-vous',
            self::TYPE_EMAIL => 'E-mail',
            self::TYPE_STATUS => 'Statut',
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::typeOptions()[$type] ?? $type;
    }
}
