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
}
