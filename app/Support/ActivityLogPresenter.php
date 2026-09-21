<?php

namespace App\Support;

use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Turns a raw Spatie activity row into the words, icons, and field-by-field
 * comparisons the Activity Logs resource shows.
 *
 * Everything the timeline and the detail page print goes through here, so the
 * two can never disagree about what an event means or — more importantly —
 * which values are safe to show. Blade templates only lay the result out.
 */
final class ActivityLogPresenter
{
    /** Model events written by Spatie's LogsActivity trait. */
    public const CRUD_EVENTS = ['created', 'updated', 'deleted', 'restored'];

    /** Authentication events written by the App\Listeners\*AdminLogin listeners. */
    public const AUTH_EVENTS = ['login', 'logout', 'login_failed', 'login_throttled'];

    /** Both count as a failed sign-in: a throttled attempt is still a rejected one. */
    public const LOGIN_FAILURE_EVENTS = ['login_failed', 'login_throttled'];

    /**
     * Longer values are cut short in the list and expandable on demand, so one
     * long description cannot push every other change off the screen.
     */
    public const TRUNCATE_AT = 80;

    /**
     * `tone` picks the icon colours in resources/views/filament/activity-logs;
     * `color` is the Filament badge colour on the detail page.
     */
    private const EVENTS = [
        'created' => ['label' => 'Created', 'verb' => 'created', 'tone' => 'green', 'color' => 'success', 'icon' => Heroicon::OutlinedPlus],
        'updated' => ['label' => 'Updated', 'verb' => 'updated', 'tone' => 'amber', 'color' => 'warning', 'icon' => Heroicon::OutlinedPencilSquare],
        'deleted' => ['label' => 'Deleted', 'verb' => 'deleted', 'tone' => 'red', 'color' => 'danger', 'icon' => Heroicon::OutlinedTrash],
        'restored' => ['label' => 'Restored', 'verb' => 'restored', 'tone' => 'blue', 'color' => 'info', 'icon' => Heroicon::OutlinedArrowUturnLeft],
        'login' => ['label' => 'Login', 'verb' => 'signed in to the admin panel', 'tone' => 'green', 'color' => 'success', 'icon' => Heroicon::OutlinedArrowLeftEndOnRectangle],
        'logout' => ['label' => 'Logout', 'verb' => 'signed out of the admin panel', 'tone' => 'gray', 'color' => 'gray', 'icon' => Heroicon::OutlinedArrowRightStartOnRectangle],
        'login_failed' => ['label' => 'Failed login', 'verb' => 'failed to sign in', 'tone' => 'red', 'color' => 'danger', 'icon' => Heroicon::OutlinedExclamationTriangle],
        'login_throttled' => ['label' => 'Login throttled', 'verb' => 'was locked out after too many failed sign-in attempts', 'tone' => 'orange', 'color' => 'orange', 'icon' => Heroicon::OutlinedLockClosed],
    ];

    /**
     * Any key containing one of these is treated as a credential and its value
     * is never rendered — not in the change list, not in the metadata, and not
     * nested inside an array value either.
     */
    private const SENSITIVE_FRAGMENTS = [
        'password', 'passwd', 'secret', 'token', 'session', 'credential',
        'api_key', 'apikey', 'private_key', 'two_factor', 'recovery_code',
    ];

    /**
     * Short words matched as whole snake_case segments only, because as plain
     * substrings they would hide innocent fields ("pin" is inside "shipping").
     */
    private const SENSITIVE_SEGMENTS = ['otp', 'pin', 'cvv', 'cvc', 'ssn'];

    /** Rendered in their own places on the detail page, so left out of "Other metadata". */
    private const CHANGE_KEYS = ['attributes', 'old'];

    private const IP_KEYS = ['ip_address', 'ip'];

    private const USER_AGENT_KEYS = ['user_agent'];

    /**
     * Attributes tried, in order, when naming the affected record ("Order #105 · ORD-2026-0001").
     * `image_path` comes last so records whose only identity is a file
     * (product images, banners) still get a title.
     */
    private const SUBJECT_TITLE_KEYS = ['name', 'title', 'order_number', 'email', 'sku', 'image_path'];

    private const HIDDEN_VALUE = 'Hidden';

    private const EMPTY_VALUE = '—';

    public function __construct(
        private readonly Activity $activity,
    ) {}

    public static function for(Activity $activity): self
    {
        return new self($activity);
    }

    // --- Event -----------------------------------------------------------

    public function event(): string
    {
        return (string) $this->activity->event;
    }

    public function isKnownEvent(): bool
    {
        return array_key_exists($this->event(), self::EVENTS);
    }

    public function eventLabel(): string
    {
        return self::eventLabelFor($this->event());
    }

    /** Colour family for the timeline icon: green, amber, red, blue, orange, or gray. */
    public function eventTone(): string
    {
        return self::EVENTS[$this->event()]['tone'] ?? 'gray';
    }

    public function eventIcon(): Heroicon
    {
        return self::EVENTS[$this->event()]['icon'] ?? Heroicon::OutlinedClock;
    }

    /**
     * Filament badge colour. Orange is not one of the panel's registered colour
     * names, so it is handed over as a palette.
     *
     * @return string|array<int, string>
     */
    public function eventColor(): string|array
    {
        $color = self::EVENTS[$this->event()]['color'] ?? 'gray';

        return $color === 'orange' ? Color::Orange : $color;
    }

    public static function eventLabelFor(?string $event): string
    {
        if (blank($event)) {
            return 'Other';
        }

        return self::EVENTS[$event]['label'] ?? Str::headline($event);
    }

    /**
     * Filter options: every event this app is known to write, plus anything
     * else that has turned up in the table.
     *
     * @param  iterable<int, string|null>  $recordedEvents
     * @return array<string, string>
     */
    public static function eventOptions(iterable $recordedEvents = []): array
    {
        return collect(array_keys(self::EVENTS))
            ->merge($recordedEvents)
            ->filter(fn ($event): bool => filled($event))
            ->unique()
            ->mapWithKeys(fn (string $event): array => [$event => self::eventLabelFor($event)])
            ->all();
    }

    // --- Actor -----------------------------------------------------------

    /**
     * Who did it: the causer's name, their first and last name, their email,
     * and failing all of those "Guest" for an anonymous sign-in attempt or
     * "System" for anything the application did on its own.
     */
    public function actorName(): string
    {
        $causer = $this->causer();

        if ($causer) {
            $name = $this->scalarAttribute($causer, 'name');

            if (filled($name)) {
                return $name;
            }

            $fullName = trim(implode(' ', array_filter([
                $this->scalarAttribute($causer, 'first_name'),
                $this->scalarAttribute($causer, 'last_name'),
            ])));

            if ($fullName !== '') {
                return $fullName;
            }

            $email = $this->scalarAttribute($causer, 'email');

            if (filled($email)) {
                return $email;
            }
        }

        // The causer row is gone (e.g. a deleted admin). Saying "System" here
        // would misattribute a person's action to the application.
        if (filled($this->activity->causer_id)) {
            return self::modelLabelFor($this->activity->causer_type).' #'.$this->activity->causer_id;
        }

        return $this->isAnonymousAuthAttempt() ? 'Guest' : 'System';
    }

    public function actorEmail(): ?string
    {
        $causer = $this->causer();

        return $causer ? $this->scalarAttribute($causer, 'email') : null;
    }

    public function isAnonymousAuthAttempt(): bool
    {
        return blank($this->activity->causer_id)
            && in_array($this->event(), self::AUTH_EVENTS, true);
    }

    // --- Subject ---------------------------------------------------------

    /** "Product #42", or null when the activity is not about a record. */
    public function subjectLabel(): ?string
    {
        if (blank($this->activity->subject_type)) {
            return null;
        }

        return trim(self::modelLabelFor($this->activity->subject_type).' #'.$this->activity->subject_id);
    }

    public function subjectTypeLabel(): ?string
    {
        return blank($this->activity->subject_type)
            ? null
            : self::modelLabelFor($this->activity->subject_type);
    }

    /**
     * A human name for the affected record, read from the record itself or,
     * once it has been deleted, from the values the log kept.
     */
    public function subjectTitle(): ?string
    {
        $subject = $this->activity->relationLoaded('subject') ? $this->activity->subject : null;
        $properties = $this->properties();

        foreach (self::SUBJECT_TITLE_KEYS as $key) {
            $value = $subject instanceof Model
                ? $this->scalarAttribute($subject, $key)
                : null;

            $value ??= $this->scalarOrNull($properties['attributes'][$key] ?? null)
                ?? $this->scalarOrNull($properties['old'][$key] ?? null);

            if (filled($value)) {
                return Str::limit((string) $value, 60);
            }
        }

        return null;
    }

    /**
     * Short model name without its namespace: "App\Models\ProductVariant" and
     * a morph alias of it both come out as "Product Variant".
     */
    public static function modelLabelFor(?string $type): string
    {
        if (blank($type)) {
            return 'Record';
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        return Str::headline(class_basename($class));
    }

    // --- Sentence --------------------------------------------------------

    /**
     * The words after the actor's name, e.g. "updated" in "Admin updated
     * Product #42". For events this app does not know, the stored description
     * is the whole sentence.
     */
    public function action(): string
    {
        if (! $this->isKnownEvent()) {
            return filled($this->activity->description)
                ? (string) $this->activity->description
                : 'recorded an activity';
        }

        $verb = self::EVENTS[$this->event()]['verb'];

        if ($this->event() === 'login_failed' && filled($this->attemptedEmail())) {
            return $verb.' as';
        }

        return $verb;
    }

    /** The emphasised part after the verb: the record, or the email tried. */
    public function target(): ?string
    {
        if (in_array($this->event(), self::CRUD_EVENTS, true)) {
            return $this->subjectLabel() ?? 'a record';
        }

        if ($this->event() === 'login_failed') {
            return $this->attemptedEmail();
        }

        return null;
    }

    /** Plain one-line version of the sentence, for titles and the detail page. */
    public function sentence(): string
    {
        return collect([$this->actorName(), $this->action(), $this->target()])
            ->filter()
            ->implode(' ');
    }

    /**
     * The stored description, but only when it adds something. Spatie's model
     * events store the bare event name ("updated"), and the auth listeners'
     * descriptions repeat what the sentence already says.
     */
    public function extraDescription(): ?string
    {
        $description = trim((string) $this->activity->description);

        if (
            $description === ''
            || ! $this->isKnownEvent()
            || in_array($this->event(), self::AUTH_EVENTS, true)
            || strcasecmp($description, $this->event()) === 0
        ) {
            return null;
        }

        return $description;
    }

    public function attemptedEmail(): ?string
    {
        $email = $this->scalarOrNull($this->properties()['attempted_email'] ?? null);

        return filled($email) ? Str::limit((string) $email, 120) : null;
    }

    // --- Changes ---------------------------------------------------------

    /**
     * Which side of a change is worth showing: both for an update, only the
     * new values for a creation, only the previous ones for a deletion.
     */
    public function changeMode(): string
    {
        $properties = $this->properties();

        return match (true) {
            $this->event() === 'created' => 'new',
            $this->event() === 'deleted' => 'old',
            // A restore, or a custom log that only recorded the new values,
            // has nothing to compare against.
            empty($properties['old']) => 'new',
            default => 'compare',
        };
    }

    public function hasChanges(): bool
    {
        return $this->changes() !== [];
    }

    /**
     * One row per field that actually changed.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     sensitive: bool,
     *     old: array{display: string, full: string, truncated: bool},
     *     new: array{display: string, full: string, truncated: bool},
     * }>
     */
    public function changes(): array
    {
        $properties = $this->properties();
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        $new = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];
        $mode = $this->changeMode();

        $keys = match ($mode) {
            'new' => array_keys($new),
            'old' => array_keys($old ?: $new),
            default => array_values(array_unique([...array_keys($old), ...array_keys($new)])),
        };

        $rows = [];

        foreach ($keys as $key) {
            $key = (string) $key;
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($mode === 'old' && $old === []) {
                // Older rows kept a deleted record's values under "attributes".
                $oldValue = $newValue;
                $newValue = null;
            }

            $sensitive = self::isSensitiveKey($key);

            if ($mode === 'compare' && ! $sensitive && $this->sameValue($oldValue, $newValue)) {
                continue;
            }

            // A creation or deletion lists the record's meaningful values, so
            // fields that were simply empty are noise.
            if ($mode === 'new' && blank($newValue) && ! is_bool($newValue)) {
                continue;
            }

            if ($mode === 'old' && blank($oldValue) && ! is_bool($oldValue)) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => self::humanizeKey($key),
                'sensitive' => $sensitive,
                'old' => $this->valueCell($key, $oldValue, $sensitive),
                'new' => $this->valueCell($key, $newValue, $sensitive),
            ];
        }

        return $rows;
    }

    // --- Request context -------------------------------------------------

    public function ipAddress(): ?string
    {
        return $this->firstProperty(self::IP_KEYS);
    }

    public function userAgent(): ?string
    {
        return $this->firstProperty(self::USER_AGENT_KEYS);
    }

    public function batchUuid(): ?string
    {
        return filled($this->activity->batch_uuid) ? (string) $this->activity->batch_uuid : null;
    }

    /**
     * Every other property the log carries, formatted and with credentials
     * masked, for the collapsible fallback section on the detail page.
     *
     * @return list<array{label: string, value: string}>
     */
    public function metadata(): array
    {
        $skip = [...self::CHANGE_KEYS, ...self::IP_KEYS, ...self::USER_AGENT_KEYS];
        $rows = [];

        foreach ($this->properties() as $key => $value) {
            $key = (string) $key;

            if (in_array($key, $skip, true)) {
                continue;
            }

            $rows[] = [
                'label' => self::humanizeKey($key),
                'value' => self::isSensitiveKey($key)
                    ? self::HIDDEN_VALUE
                    : self::formatValue($key, $value, pretty: true),
            ];
        }

        return $rows;
    }

    // --- Formatting ------------------------------------------------------

    /** "is_active" → "Is active", "category_id" → "Category ID". */
    public static function humanizeKey(string $key): string
    {
        $words = Str::of(Str::snake($key))
            ->replace(['_', '-', '.'], ' ')
            ->squish()
            ->explode(' ')
            ->map(fn (string $word): string => match (strtolower($word)) {
                'id' => 'ID',
                'ip' => 'IP',
                'sku' => 'SKU',
                'url' => 'URL',
                'uuid' => 'UUID',
                'or' => 'OR',
                default => strtolower($word),
            })
            ->implode(' ');

        return Str::ucfirst($words);
    }

    public static function isSensitiveKey(string|int $key): bool
    {
        $key = Str::snake(strtolower((string) $key));

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        $segments = preg_split('/[^a-z0-9]+/', $key) ?: [];

        return array_intersect($segments, self::SENSITIVE_SEGMENTS) !== [];
    }

    /**
     * Render any logged value as readable text. The result is plain text and
     * must still be escaped by the template that prints it.
     */
    public static function formatValue(string $key, mixed $value, bool $pretty = false): string
    {
        if (self::isSensitiveKey($key)) {
            return self::HIDDEN_VALUE;
        }

        if ($value === null || $value === '') {
            return self::EMPTY_VALUE;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return self::formatStructured(self::sanitize($value), $pretty);
        }

        if (self::isFlagKey($key) && in_array((string) $value, ['0', '1'], true)) {
            return $value == 1 ? 'Yes' : 'No';
        }

        if (is_numeric($value) && self::isMoneyKey($key)) {
            return '₱'.number_format((float) $value, 2);
        }

        if (is_string($value) && ($date = self::formatDate($value)) !== null) {
            return $date;
        }

        if (is_string($value) && self::isStatusKey($key) && preg_match('/^[a-z0-9_]+$/', $value)) {
            return Str::ucfirst(str_replace('_', ' ', $value));
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        }

        return (string) $value;
    }

    /**
     * Mask credential keys at every depth of an array value.
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = json_decode(json_encode($value) ?: 'null', true);
        }

        if (! is_array($value)) {
            return $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $clean[$key] = is_string($key) && self::isSensitiveKey($key)
                ? self::HIDDEN_VALUE
                : self::sanitize($item);
        }

        return $clean;
    }

    // --- Internals -------------------------------------------------------

    /**
     * @return array{display: string, full: string, truncated: bool}
     */
    private function valueCell(string $key, mixed $value, bool $sensitive): array
    {
        $full = $sensitive ? self::HIDDEN_VALUE : self::formatValue($key, $value);
        $display = Str::limit($full, self::TRUNCATE_AT);

        return [
            'display' => $display,
            'full' => $full,
            'truncated' => $display !== $full,
        ];
    }

    private function sameValue(mixed $old, mixed $new): bool
    {
        if (is_numeric($old) && is_numeric($new)) {
            return (float) $old === (float) $new;
        }

        return $old === $new;
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(): array
    {
        $properties = $this->activity->properties;

        if ($properties instanceof Collection) {
            return $properties->toArray();
        }

        return is_array($properties) ? $properties : [];
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstProperty(array $keys): ?string
    {
        $properties = $this->properties();

        foreach ($keys as $key) {
            $value = $this->scalarOrNull($properties[$key] ?? null);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function causer(): ?Model
    {
        if (blank($this->activity->causer_type)) {
            return null;
        }

        $causer = $this->activity->causer;

        return $causer instanceof Model ? $causer : null;
    }

    /**
     * Read an attribute (including accessors such as User::$name) without
     * tripping strict-mode missing-attribute exceptions on models that lack it.
     */
    private function scalarAttribute(Model $model, string $key): ?string
    {
        if (self::isSensitiveKey($key)) {
            return null;
        }

        try {
            $value = $model->getAttribute($key);
        } catch (Throwable) {
            return null;
        }

        $value = $this->scalarOrNull($value);

        return filled($value) ? (string) $value : null;
    }

    private function scalarOrNull(mixed $value): string|int|float|null
    {
        return is_scalar($value) && ! is_bool($value) ? $value : null;
    }

    private static function formatStructured(mixed $value, bool $pretty): string
    {
        if ($value === [] || $value === null) {
            return self::EMPTY_VALUE;
        }

        if (is_array($value) && array_is_list($value) && collect($value)->every(fn ($item): bool => is_scalar($item) || $item === null)) {
            return collect($value)
                ->map(fn ($item): string => $item === null ? self::EMPTY_VALUE : (is_bool($item) ? ($item ? 'Yes' : 'No') : (string) $item))
                ->implode(', ');
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0);

        return json_encode($value, $flags) ?: self::EMPTY_VALUE;
    }

    private static function formatDate(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }

        return strlen($value) === 10
            ? $date->format('M j, Y')
            : $date->format('M j, Y g:i A');
    }

    private static function isMoneyKey(string $key): bool
    {
        return in_array($key, ['price', 'subtotal', 'total', 'amount'], true)
            || Str::endsWith($key, ['_price', '_total', '_amount', '_subtotal']);
    }

    private static function isFlagKey(string $key): bool
    {
        return Str::startsWith($key, ['is_', 'has_', 'can_', 'should_']);
    }

    private static function isStatusKey(string $key): bool
    {
        return $key === 'status' || Str::endsWith($key, ['_status', '_method', '_reason']);
    }
}
