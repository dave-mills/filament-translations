<?php

namespace TomatoPHP\FilamentTranslations\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;
use Spatie\TranslationLoader\LanguageLine;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Translation extends LanguageLine
{
    use HasFactory;
    use SoftDeletes;

    public $translatable = ['text'];

    /** @var array */
    public $guarded = ['id'];

    /** @var array */
    protected $casts = [
        'text' => 'array',
        'metadata' => 'array',
    ];

    protected $table = "language_lines";

    protected $fillable = [
        "group",
        "key",
        "text",
        "namespace"
    ];


    public static function getTranslatableLocales(): array
    {
        return config('filament-translations.locals');
    }

    public function getTranslation(string $locale, string $group = null): string
    {
        if ($group === '*' && !isset($this->text[$locale])) {
            $fallback = config('app.fallback_locale');

            return $this->text[$fallback] ?? $this->key;
        }
        return $this->text[$locale] ?? '';
    }

    public function setTranslation(string $locale, string $value): self
    {
        $this->text = array_merge($this->text ?? [], [$locale => $value]);

        return $this;
    }

    protected function getTranslatedLocales(): array
    {
        return array_keys($this->text);
    }

    public function isReviewed(): Attribute
    {
        return new Attribute(
            get: function (): bool {
                return array_key_exists('is_reviewed', $this->metadata ?? []) && $this->metadata['is_reviewed'] === true;
            },
            set: function ($value): void {
                $this->metadata = array_merge($this->metadata ?? [], ['is_reviewed' => $value]);
            }
        );
    }

    public function reviewedBy(): Attribute
    {
        return new Attribute(
            get: function(): ?int {
                return array_key_exists('reviewed_by', $this->metadata) ? $this->metadata['reviewed_by'] : null;
            },
            set: function($value): void {
                $this->metadata = array_merge($this->metadata ?? [], ['reviewed_by' => $value]);
            }
        );
    }
}
