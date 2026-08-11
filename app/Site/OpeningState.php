<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Location;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Whether a location is open at this moment, and the one line that says so.
 *
 * "Are you open?" is the question a local business loses the most customers to,
 * and until now this site answered it only with a seven-row table at the bottom
 * of a contact form — a visitor had to find the table, find today, and read a
 * clock. The `visit` block asks this instead, so the page says "Open now, until
 * 21:00" before anyone has to work it out. {@see PostFeed} answers the
 * neighbouring question (an ANNOUNCED closure, "shut Monday for the holiday")
 * through the notice bar; the two are deliberately separate, because a
 * one-off closure is editorial and this is arithmetic.
 *
 * A value object rather than a method on Location for the reason the app's
 * rules give: it is derived vocabulary, not persistence, and putting three
 * formatted strings on the model would make every `toArray()` carry them.
 *
 * Resolved at render time and never cached: the answer changes on a clock, the
 * public site has no full-page cache, and a stale "Open now" is worse than no
 * badge at all — it sends someone to a locked door.
 */
final readonly class OpeningState
{
    private function __construct(
        public bool $open,
        public string $label,
        public ?string $detail,
    ) {
        //
    }

    /**
     * Null when the location keeps no hours, which is a normal state — a
     * mobile nail technician or a by-appointment studio has none to publish,
     * and inventing "Closed" for them would be a lie the block renders in
     * bold. Callers skip the whole line rather than showing an empty one.
     */
    public static function for(Location $location): ?self
    {
        $hours = $location->opening_hours;

        if ($hours === null) {
            return null;
        }

        // The tenant's own clock, not the server's. A California salon
        // rendered from a UTC container is shut for the last eight hours of
        // every working day otherwise.
        $now = CarbonImmutable::now($location->timezone ?? config('app.timezone'));

        try {
            $open = $hours->isOpenAt($now);
            $edge = $open ? $hours->nextClose($now) : $hours->nextOpen($now);
        } catch (Throwable) {
            // spatie/opening-hours walks forward a bounded number of days and
            // throws when it finds nothing — a location with every day blank,
            // which is the same "nothing to say" case as no hours at all.
            return null;
        }

        return $open
            ? new self(true, __('Open now'), __('until :time', ['time' => self::clock($edge)]))
            : new self(false, __('Closed'), self::opensAt($now, $edge));
    }

    /**
     * When the doors open next, said the way a person would: no day name for
     * later today, "tomorrow" for tomorrow, and a weekday only once it is far
     * enough away to need one.
     */
    private static function opensAt(CarbonImmutable $now, DateTimeInterface $next): string
    {
        $opens = CarbonImmutable::instance($next)->setTimezone($now->getTimezone());
        $time = self::clock($opens);

        if ($opens->isSameDay($now)) {
            return __('opens :time', ['time' => $time]);
        }

        if ($opens->isSameDay($now->addDay())) {
            return __('opens tomorrow at :time', ['time' => $time]);
        }

        return __('opens :day at :time', ['day' => $opens->dayName, 'time' => $time]);
    }

    private static function clock(DateTimeInterface $moment): string
    {
        return $moment->format('H:i');
    }
}
