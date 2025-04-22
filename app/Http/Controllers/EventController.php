<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;

class EventController extends Controller
{
    public function show($id)
    {
        $json = File::get(base_path('db.json'));
        $data = json_decode($json, true);

        $event = collect($data['events'])->firstWhere('id', $id);
        $related = collect($data['events'])->where('id', '!=', $id)->take(5);

        return Inertia::render('Events/EventDetails', [
            'event' => $event,
            'relatedEvents' => $related,
            'categories' => $data['categories'] ?? [],
            'tags' => $data['tags'] ?? []
        ]);
    }

    public function update(Request $request, $id)
    {
        $json = File::get(base_path('db.json'));
        $data = json_decode($json, true);

        foreach ($data['events'] as &$event) {
            if ($event['id'] == $id) {
                $event['title'] = $request->input('title');
                $event['description'] = $request->input('description');
                $event['startDate'] = $request->input('startDate');
                $event['startTime'] = $request->input('startTime');
                $event['endDate'] = $request->input('endDate');
                $event['endTime'] = $request->input('endTime');
                $event['category_id'] = $request->input('category_id');
                $event['tags'] = $request->input('tags');
                $event['schedules'] = $request->input('schedules', []);
                break;
            }
        }

        File::put(base_path('db.json'), json_encode($data, JSON_PRETTY_PRINT));
        return back()->with('success', 'Event updated successfully.');
    }

}
