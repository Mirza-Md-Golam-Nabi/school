<?php

namespace App\Filament\Concerns;

use App\Support\AcademicSession;

/**
 * For the per-class promotion pages: shows which session the students are
 * promoted from and to (e.g. "Session 2026-27 → 2027-28" for college), and
 * warns when that session is still running — between January and June a
 * college class's current students are mid-session, not ready to promote.
 */
trait DescribesPromotionSession
{
    public function getSubheading(): ?string
    {
        $class = $this->resolveClass();

        if (! $class || ! $this->year) {
            return null;
        }

        $subheading = 'Session '.$class->sessionLabel($this->year).' → '.$class->sessionLabel($this->year + 1);

        if ($this->year >= $class->currentSessionYear()) {
            $endsOn = AcademicSession::periodFor($this->year, $class)['end']->format('M Y');

            $subheading .= " · সতর্কতা: এই সেশন এখনো চলছে ({$endsOn} পর্যন্ত) — সাধারণত সেশন শেষে promote করা হয়।";
        }

        return $subheading;
    }
}
