<?php

namespace App\Http\Controllers\Dashboard;

use Image;
use App\Models\Game;
use App\Models\Event;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\EventCategory as Category;

class EventsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index (Request $request)
    {
        if ($request->has('game') and $request->game) {
            $game  = Game::where('slug', $request->game)->firstOrFail();
            $query = $game->events()->latest();
        } else {
            $query = Event::latest();
        }

        $games      = Game::orderBy('name')->get();
        $events     = $query->with(['game', 'category'])->paginate(15);

        return view('dashboard.events.index', compact('games', 'events'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create ()
    {
        $games      = Game::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        return view('dashboard.events.create', compact('games', 'categories'));
    }

    public function finishCreate (Request $request)
    {
        $this->validateStep1($request);

        $game     = Game::findOrFail($request->game_id);
        $category = Category::findOrFail($request->event_category_id);

        if ($category->id == 1) {
            $data = $game->athletes;
        } else {
            $data = $game->teams;
        }

        return view('dashboard.events.finishCreate', compact(
            'game', 'category', 'data'
        ));
    }

    protected function validateStep1 (Request $request, $event = null)
    {
        $rules = [
            'title'             => 'required|string|min:3',
            'slug'              => 'required|string|min:3|unique:events,slug',
            'details'           => 'nullable|string|min:10',
            'game_id'           => 'required|numeric|exists:games,id',
            'event_category_id' => 'required|numeric|exists:event_categories,id',
            'live_at'           => 'required|date',
        ];

        if ($event and $event instanceof Event) {
            $rules['slug'] = [
                'required', 'string', 'min:3',
                Rule::unique('events')->ignore($event->id),
            ];
        }

        $this->validate($request, $rules);
    }

    protected function validateStep2 (Request $request)
    {
        $this->validate($request, [
            'data'   => 'required|array|min:2',

            'thumb' => [
                'nullable', 'file', 'image',
                Rule::dimensions()->minWidth(200)->minWidth(200)->ratio(1 / 1),
            ],

            'banner' => [
                'nullable', 'file', 'image',
                Rule::dimensions()->minWidth(600)->minWidth(200)->ratio(2 / 1),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store (Request $request)
    {
        $this->validateStep2($request);

        $event = new Event;

        $event->title             = $request->title;
        $event->slug              = $request->slug;
        $event->details           = $request->details;
        $event->game_id           = $request->game_id;
        $event->event_category_id = $request->event_category_id;
        $event->live_at           = $this->formatDate($request->live_at);

        if ($request->hasFile('thumb')) {
            $event->thumb = $this->upload($request->file('thumb'), 'thumbs', [
                    'width'  => 250,
                    'height' => 250,
            ]);
        }

        if ($request->hasFile('banner')) {
            $event->banner = $this->upload($request->file('banner'), 'banners', [
                'width'  => 800,
                'height' => 400,
            ]);
        }

        if (!$event->save()) {
            return redirect()->route('dashboard.events.create')->with(
                'global.error',
                'Something went wrong. Please try again later.'
            )->withInput();
        }

        if ($request->event_category_id == 1) {
            $event->athletes()->attach($request->data);
        } else {
            $event->teams()->attach($request->data);
        }

        return redirect()->route('dashboard.events.index')->with(
            'global.success',
            'Event created successfully.'
        );
    }

    protected function formatDate ($string)
    {
        return str_replace('T', ' ', $string) . ':00';
    }

    protected function upload ($file, $path, $dimensions = null)
    {
        $filename = time() . '.' . $file->getClientOriginalExtension();

        $image = Image::make($file);

        if ($dimensions and is_array($dimensions)) {
            $image->resize(
                $dimensions['width'], $dimensions['height']
            );
        }

        // echo public_path("uploads\\events\\{$path}\\{$filename}"); die();

        $image->save(
            public_path("uploads\\events\\{$path}\\{$filename}")
        );

        return $filename;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show ($slug)
    {
        $event    = $this->event($slug);
        $game     = $event->game;
        $category = $event->category;
        $data     = $category->id == 1 ? $event->athletes : $event->teams;

        return view('dashboard.events.show', compact('event', 'game', 'category', 'data'));
    }

    protected function event ($slug)
    {
        return Event::where('slug', $slug)->firstOrFail();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit ($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update (Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy ($slug)
    {
        if ($this->event($slug)->delete()) {
            return redirect()->route('dashboard.events.index')->with(
                'global.success',
                'Event deleted successfully'
            );
        }

        return back()->with(
            'global.error',
            'Something went wrong while deleting the event. Please try again later.'
        );
    }

    public function showEnd ($slug)
    {
        $event = $this->event($slug);

        if ($event->event_category_id == 1) {
            $data = $event->athletes;
        } else {
            $data = $event->teams;
        }

        return view('dashboard.events.end', compact('event', 'data'));
    }

    public function end (Request $request, $slug)
    {
        $event = $this->event($slug);

        $this->validate($request, [
            'winner_id' => 'required|numeric',
        ]);

        $event->winner_id = $request->winner_id;

        if (!$event->save()) {
            return back()->with(
                'global.error',
                'Something went wrong. Please try again later.'
            );
        }

        $this->makeTransactions($event);

        return back()->with(
            'global.success',
            'Event ended successfully'
        );
    }

    protected function makeTransactions (Event $event)
    {
        $data = [];

        $bets = $event->bets()->get();

        if (!$bets or !$bets->count()) {
            return;
        }

        foreach ($bets as $bet) {
            $data[ ] = [
                'user_id' => $bet->user_id,
                'bet_id'  => $bet->id,
                'amount'  => $bet->amount,
                'type'    => $bet->player_id == $event->winner_id ? 1 : 0,
                'details' => $bet->player_id == $event->winner_id ? 'Bet won' : 'Bet lost',
            ];
        }

        Transaction::insert($data);
    }
}
