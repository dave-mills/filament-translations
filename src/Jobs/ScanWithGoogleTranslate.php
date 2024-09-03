<?php

namespace TomatoPHP\FilamentTranslations\Jobs;

use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;
use TomatoPHP\FilamentTranslations\Models\Translation;

class ScanWithGoogleTranslate implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public function __construct(
		public Authenticatable $user,
		public string $language = "en"
	) {
	}

	/**
	 * Execute the job.
	 */
	public function handle(): void
	{
		$translator = new GoogleTranslate($this->language);
        $defaultLocale = config('filament-translations.default_local') ?? config('app.fallback_locale');

		Translation::chunk(200, function (Collection $translations) use ($translator, $defaultLocale) {
			foreach ($translations as $translation) {

                // skip if translation already exists (and is not identical to the default locale)
                if($translation->text[$this->language] && $translation->text[$this->language] !== $translation->text[$defaultLocale]) {
                    continue;
                }

				$textToTranslate = $translation->text[$defaultLocale] ?? $translation['key'];
				$translation->setTranslation($this->language, $translator->translate($textToTranslate));
				$translation->save();
			}
		});

		Notification::make()
			->title(trans('filament-translations::translation.google_scan_notifications_done'))
			->success()
            ->broadcast($this->user)
			->sendToDatabase($this->user);

        event(new DatabaseNotificationsSent($this->user));



	}
}
