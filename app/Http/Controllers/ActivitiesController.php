<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Event;
use Illuminate\Http\Request;

class ActivitiesController extends Controller
{
    private function parseDate($value): ?\DateTime
    {
        if (!$value) return null;
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if ($dt) return $dt;
        return \DateTime::createFromFormat('M-d-Y', $value) ?: null;
    }

    private function parseDateTime($date, $time): ?\DateTime
    {
        if (!$date || !$time) return null;
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $date.' '.$time);
        if ($dt) return $dt;
        $dt = \DateTime::createFromFormat('M-d-Y H:i', $date.' '.$time);
        if ($dt) return $dt;
        // Try with seconds
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $date.' '.$time);
        if ($dt) return $dt;
        return \DateTime::createFromFormat('M-d-Y H:i:s', $date.' '.$time) ?: null;
    }

    private function normalizeDateToYmd($value): ?string
    {
        $dt = $this->parseDate($value);
        return $dt ? $dt->format('Y-m-d') : null;
    }

    private function normalizeTimeToHi($value): ?string
    {
        if ($value === null || $value === '') return null;
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 5);
        }
        if (preg_match('/^\d{1}:\d{2}$/', $value)) {
            return '0'.$value;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        $dt = \DateTime::createFromFormat('H:i:s', $value)
            ?: \DateTime::createFromFormat('G:i:s', $value)
            ?: \DateTime::createFromFormat('H:i', $value)
            ?: \DateTime::createFromFormat('G:i', $value);
        return $dt ? $dt->format('H:i') : null;
    }

    public function indexForEvent($eventId)
    {
        // Fetch from DB and sort by date (MMM-dd-YYYY) then startTime
        $activities = Activity::query()
            ->where('event_id', $eventId)
            ->get()
            ->sortBy(function ($a) {
                $date = $this->parseDate($a->date ?? null);
                $dateKey = $date ? $date->format('Y-m-d') : '1970-01-01';
                $timeKey = $a->startTime ?? '';
                return $dateKey . ' ' . $timeKey;
            })
            ->values()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'event_id' => $a->event_id,
                    'title' => $a->title,
                    'date' => $a->date,
                    'startTime' => $this->normalizeTimeToHi($a->startTime),
                    'endTime' => $this->normalizeTimeToHi($a->endTime),
                    'location' => $a->location,
                ];
            })
            ->toArray();

        return response()->json($activities);
    }

    public function updateForEvent(Request $request, $eventId)
    {
        // Find current event for validation context
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        // Normalize empty string times to null to satisfy nullable|date_format rules
        $incomingActivities = $request->input('activities');
        if (is_array($incomingActivities)) {
            $normalized = [];
            foreach ($incomingActivities as $a) {
                $a = is_array($a) ? $a : [];
                $a['startTime'] = $this->normalizeTimeToHi($a['startTime'] ?? null);
                $a['endTime'] = $this->normalizeTimeToHi($a['endTime'] ?? null);
                $a['location'] = (isset($a['location']) && $a['location'] !== '') ? $a['location'] : null;
                $a['title'] = $a['title'] ?? '';
                $normalized[] = $a;
            }
            $request->merge(['activities' => $normalized]);
        }

        $validated = $request->validate([
            'activities' => 'nullable|array',
            'activities.*.title' => 'nullable|string|max:255',
            'activities.*.location' => 'nullable|string|max:255',
            'activities.*.date' => ['required', 'string', function ($attribute, $value, $fail) use ($event) {
                $actDate = $this->parseDate($value);
                if (!$actDate) {
                    $fail('The '.$attribute.' must be a valid date in MMM-DD-YYYY or YYYY-MM-DD format (e.g. May-28-2025 or 2025-05-28).');
                    return;
                }
                $eventStart = $this->parseDate($event->startDate ?? null);
                $eventEnd = $this->parseDate($event->endDate ?? null);
                if ($eventEnd) {
                    $eventEnd->setTime(23, 59, 59);
                }
                if ($eventStart && $eventEnd && ($actDate < $eventStart || $actDate > $eventEnd)) {
                    $fail('Each activity date must be within the event date range.');
                }
            }],
            'activities.*.startTime' => 'nullable|date_format:H:i',
            'activities.*.endTime' => ['nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                $parts = explode('.', $attribute);
                $index = $parts[1] ?? null;
                $start = $index !== null ? $request->input("activities.$index.startTime") : null;
                if ($start && $value && strtotime($value) <= strtotime($start)) {
                    $fail('Activity end time must be after start time.');
                }
            }],
        ]);

        // Enforce activity times within event window when event times exist
        $eventStartDT = (!empty($event->startDate) && !empty($event->startTime))
            ? $this->parseDateTime($event->startDate, $event->startTime)
            : null;
        $eventEndDT = (!empty($event->endDate) && !empty($event->endTime))
            ? $this->parseDateTime($event->endDate, $event->endTime)
            : null;

        foreach ($validated['activities'] ?? [] as $i => $act) {
            if ($eventStartDT && !empty($act['startTime'])) {
                $actStart = $this->parseDateTime(($act['date'] ?? ''), ($act['startTime'] ?? '00:00'));
                if ($actStart && $actStart < $eventStartDT) {
                    return back()->with('error', 'Activity '.($i+1).': start time must be on/after event start.');
                }
            }
            if ($eventEndDT && !empty($act['startTime'])) {
                $actStart = $this->parseDateTime(($act['date'] ?? ''), ($act['startTime'] ?? '00:00'));
                if ($actStart && $actStart > $eventEndDT) {
                    return back()->with('error', 'Activity '.($i+1).': start time must be on/before event end.');
                }
            }
            if ($eventEndDT && !empty($act['endTime'])) {
                $actEnd = $this->parseDateTime(($act['date'] ?? ''), ($act['endTime'] ?? '00:00'));
                if ($actEnd && $actEnd > $eventEndDT) {
                    return back()->with('error', 'Activity '.($i+1).': end time must be on/before event end.');
                }
            }
            if ($eventStartDT && !empty($act['endTime'])) {
                $actEnd = $this->parseDateTime(($act['date'] ?? ''), ($act['endTime'] ?? '00:00'));
                if ($actEnd && $actEnd < $eventStartDT) {
                    return back()->with('error', 'Activity '.($i+1).': end time must be on/after event start.');
                }
            }
        }

        // Remove existing activities for this event
        Activity::where('event_id', $eventId)->delete();

        // Insert the new activities
        foreach ($validated['activities'] ?? [] as $act) {
            Activity::create([
                'event_id' => $eventId,
                'title' => $act['title'] ?? '',
                'date' => $this->normalizeDateToYmd($act['date'] ?? null),
                'startTime' => $act['startTime'] ?? null,
                'endTime' => $act['endTime'] ?? null,
                'location' => $act['location'] ?? null,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Activities updated successfully.']);
    }
}
