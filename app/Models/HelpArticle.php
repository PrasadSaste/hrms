<?php

namespace App\Models;

use App\Support\Markdown;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One page of the Self Assistance guide.
 *
 * A guide is four things: what the screen is for, what the words on it mean,
 * how to do the handful of jobs people actually come to it for, and what to try
 * when it does not behave. Each of those is optional, so a short guide is a
 * summary and nothing more.
 */
class HelpArticle extends Model
{
    protected $fillable = [
        'slug',
        'group',
        'title',
        'icon',
        'summary',
        'body',
        'route_name',
        'permission',
        'fields',
        'tasks',
        'troubleshooting',
        'position',
        'is_published',
        'updated_by',
    ];

    /** The order the groups appear in, matching the order of the main menu. */
    public const GROUPS = [
        'Getting started',
        'My workspace',
        'People',
        'Attendance & leave',
        'Payroll',
        'Insights',
        'Administration',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'tasks' => 'array',
            'troubleshooting' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Guides in menu order: by group, then by position, then by title. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('title');
    }

    /**
     * The guides one person may read.
     *
     * A guide carrying a permission is only listed for people who hold it,
     * because a guide to a screen someone cannot open is just a dead end.
     */
    public function scopeReadableBy(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('permission');

            if (! $user) {
                return;
            }

            $held = $user->getAllPermissions()->pluck('name')->all();

            // A super admin passes every gate, so they see the whole guide.
            if ($user->hasRole(Roles::SUPER_ADMIN)) {
                $q->orWhereNotNull('permission');

                return;
            }

            if ($held) {
                $q->orWhereIn('permission', $held);
            }
        });
    }

    /** Every readable guide, keyed by group in menu order. */
    public static function grouped(?User $user): Collection
    {
        return static::query()
            ->published()
            ->readableBy($user)
            ->ordered()
            ->get()
            ->groupBy('group')
            ->sortBy(fn ($articles, $group) => array_search($group, self::GROUPS, true) === false
                ? count(self::GROUPS)
                : array_search($group, self::GROUPS, true));
    }

    /** The guide for a route, if one has been written. */
    public static function forRoute(?string $routeName, ?User $user): ?self
    {
        if (! $routeName) {
            return null;
        }

        return static::query()
            ->published()
            ->readableBy($user)
            ->where('route_name', $routeName)
            ->first();
    }

    public function bodyHtml(): string
    {
        return Markdown::html($this->body);
    }

    /** The right-hand rail: only the sections this guide actually has. */
    public function sections(): array
    {
        return array_filter([
            'overview' => filled($this->body) ? 'What is this page?' : null,
            'fields' => filled($this->fields) ? 'What the fields mean' : null,
            'tasks' => filled($this->tasks) ? 'How do I?' : null,
            'troubleshooting' => filled($this->troubleshooting) ? 'Troubleshooting' : null,
        ]);
    }

    /** Everything a reader might search against, as one string. */
    public function searchableText(): string
    {
        $rows = collect($this->fields ?? [])
            ->concat($this->tasks ?? [])
            ->concat($this->troubleshooting ?? [])
            ->flatMap(fn ($row) => array_values((array) $row));

        return Str::lower(implode(' ', array_filter([
            $this->title,
            $this->group,
            $this->summary,
            $this->body,
            ...$rows->all(),
        ])));
    }
}
