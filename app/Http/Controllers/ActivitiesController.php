<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ActivitiesController extends Controller
{
    private string $dbPath;
    private array $jsonData;

    public function __construct()
    {
        $this->dbPath = base_path('db.json');
        $this->jsonData = json_decode(File::get($this->dbPath), true);
    }

    private function writeJson(array $data): void
    {
        File::put($this->dbPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function indexForEvent($eventId)
    {
        $activities = collect($this->jsonData['activities'] ?? [])
            ->where('event_id', $eventId)
            ->sortBy(function ($a) {
                // Sort by date (M-d-Y) then startTime
                $date = \DateTime::createFromFormat('M-d-Y', $a['date'] ?? 'Jan-01-1970');
                $dateKey = $date ? $date->format('Y-m-d') : '1970-01-01';
                $timeKey = $a['startTime'] ?? '';
                return $dateKey . ' ' . $timeKey;
            })
            ->values()
            ->toArray();

        return response()->json($activities);
    }

    public function updateForEvent(Request $request, $eventId)
    {
        // Find current event for validation context
        $event = collect($this->jsonData['events'] ?? [])->firstWhere('id', $eventId);
        if (!$event) {
            return back()->with('error', 'Event not found.');
        }

        $validated = $request->validate([
            'activities' => 'nullable|array',
            'activities.*.title' => 'nullable|string|max:255',
            'activities.*.location' => 'nullable|string|max:255',
            'activities.*.date' => ['required', 'string', function ($attribute, $value, $fail) use ($event) {
                if (!preg_match('/^[A-Za-z]{3}-\\d{2}-\\d{4}$/', $value)) {
                    $fail('The '.$attribute.' must be in MMM-DD-YYYY format (e.g. May-28-2025)');
                    return;
                }
                $eventStart = \DateTime::createFromFormat('M-d-Y', $event['startDate'] ?? null);
                $eventEnd = \DateTime::createFromFormat('M-d-Y', $event['endDate'] ?? null);
                $actDate = \DateTime::createFromFormat('M-d-Y', $value);
                if ($eventStart && $eventEnd && $actDate && ($actDate < $eventStart || $actDate > $eventEnd)) {
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
        $eventStartDT = (!empty($event['startDate']) && !empty($event['startTime']))
            ? \DateTime::createFromFormat('M-d-Y H:i', $event['startDate'].' '.($event['startTime'] ?? '00:00'))
            : null;
        $eventEndDT = (!empty($event['endDate']) && !empty($event['endTime']))
            ? \DateTime::createFromFormat('M-d-Y H:i', $event['endDate'].' '.($event['endTime'] ?? '00:00'))
            : null;

        foreach ($validated['activities'] ?? [] as $i => $act) {
            if ($eventStartDT && !empty($act['startTime'])) {
                $actStart = \DateTime::createFromFormat('M-d-Y H:i', ($act['date'] ?? '').' '.($act['startTime'] ?? '00:00'));
                if ($actStart && $actStart < $eventStartDT) {
                    return back()->with('error', 'Activity '.($i+1).': start time must be on/after event start.');
                }
            }
            if ($eventEndDT && !empty($act['startTime'])) {
                $actStart = \DateTime::createFromFormat('M-d-Y H:i', ($act['date'] ?? '').' '.($act['startTime'] ?? '00:00'));
                if ($actStart && $actStart > $eventEndDT) {
                    return back()->with('error', 'Activity '.($i+1).': start time must be on/before event end.');
                }
            }
            if ($eventEndDT && !empty($act['endTime'])) {
                $actEnd = \DateTime::createFromFormat('M-d-Y H:i', ($act['date'] ?? '').' '.($act['endTime'] ?? '00:00'));
                if ($actEnd && $actEnd > $eventEndDT) {
                    return back()->with('error', 'Activity '.($i+1).': end time must be on/before event end.');
                }
            }
            if ($eventStartDT && !empty($act['endTime'])) {
                $actEnd = \DateTime::createFromFormat('M-d-Y H:i', ($act['date'] ?? '').' '.($act['endTime'] ?? '00:00'));
                if ($actEnd && $actEnd < $eventStartDT) {
                    return back()->with('error', 'Activity '.($i+1).': end time must be on/after event start.');
                }
            }
        }

        // Remove existing activities for this event
        $this->jsonData['activities'] = collect($this->jsonData['activities'] ?? [])
            ->filter(fn ($a) => ($a['event_id'] ?? null) !== $eventId)
            ->values()
            ->toArray();

        // Insert the new activities
        foreach ($validated['activities'] ?? [] as $act) {
            $this->jsonData['activities'][] = [
                'id' => $eventId.'-'.substr(md5(uniqid(rand(), true)), 0, 4),
                'event_id' => $eventId,
                'title' => $act['title'] ?? '',
                'date' => $act['date'] ?? null,
                'startTime' => $act['startTime'] ?? null,
                'endTime' => $act['endTime'] ?? null,
                'location' => $act['location'] ?? null,
            ];
        }

        $this->writeJson($this->jsonData);

        return redirect()->back()->with('success', 'Activities updated successfully.');
    }
}
