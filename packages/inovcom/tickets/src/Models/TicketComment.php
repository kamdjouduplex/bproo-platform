<?php

namespace InovCom\Tickets\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class TicketComment extends TenantModel
{
    public const TYPE_COMMENT = 'comment';
    public const TYPE_STATUS = 'status_change';
    public const TYPE_ASSIGN = 'assignment';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'comment_type',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isSystem(): bool
    {
        return $this->comment_type !== self::TYPE_COMMENT;
    }
}
