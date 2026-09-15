<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DesktopRelease extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_YANKED = 'yanked';

    public const CHANNEL_STABLE = 'stable';

    public const CHANNEL_BETA = 'beta';

    protected $fillable = [
        'uuid',
        'product_key',
        'channel',
        'version',
        'status',
        'changelog',
        'package_disk',
        'package_path',
        'package_url',
        'package_sha256',
        'package_size',
        'min_version',
        'mandatory',
        'meta',
        'published_at',
        'yanked_at',
        'created_by',
    ];

    protected $casts = [
        'mandatory' => 'boolean',
        'package_size' => 'integer',
        'meta' => 'array',
        'published_at' => 'datetime',
        'yanked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $release) {
            if (empty($release->uuid)) {
                $release->uuid = (string) Str::uuid();
            }
            $release->product_key = strtolower((string) $release->product_key);
            $release->channel = strtolower((string) ($release->channel ?: self::CHANNEL_STABLE));
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isYanked(): bool
    {
        return $this->status === self::STATUS_YANKED;
    }

    public function hasPackage(): bool
    {
        return filled($this->package_path) || filled($this->package_url);
    }

    public function publish(): void
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => $this->published_at ?: now(),
            'yanked_at' => null,
        ]);
    }

    public function yank(?string $reason = null): void
    {
        $meta = $this->meta ?? [];
        if ($reason) {
            $meta['yank_reason'] = $reason;
        }

        $this->update([
            'status' => self::STATUS_YANKED,
            'yanked_at' => now(),
            'meta' => $meta,
        ]);
    }

    /**
     * Compare semver-ish versions. Returns 1 if $a > $b, -1 if $a < $b, 0 if equal.
     */
    public static function compareVersions(string $a, string $b): int
    {
        $norm = static fn (string $v) => ltrim(trim($v), 'vV');

        return version_compare($norm($a), $norm($b));
    }
}
