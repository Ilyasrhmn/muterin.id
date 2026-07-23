<?php

namespace App\Http\Controllers;

use App\Models\PlaceList;
use App\Models\SavedPlace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SavedPlaceController extends Controller
{
    public function index()
    {
        PlaceList::ensureDefaultsFor(auth()->user());

        return view('map.saved');
    }

    public function data()
    {
        $userId = auth()->id();
        PlaceList::ensureDefaultsFor(auth()->user());

        $lists = PlaceList::where('user_id', $userId)->withCount('places')->orderBy('id')->get();
        $places = SavedPlace::where('user_id', $userId)->with('list')->latest()->get();

        return response()->json([
            'lists' => $lists->map(fn (PlaceList $l) => [
                'id' => $l->id, 'name' => $l->name, 'icon' => $l->icon,
                'color' => $l->color, 'is_default' => $l->is_default, 'place_count' => $l->places_count,
            ]),
            'places' => $places->map(fn (SavedPlace $p) => $this->presentPlace($p)),
        ]);
    }

    public function storeList(Request $request)
    {
        $data = $this->validateList($request);
        $list = $request->user()->placeLists()->create($data + ['is_default' => false]);

        return response()->json($list, 201);
    }

    public function updateList(Request $request, PlaceList $list)
    {
        abort_unless($list->user_id === auth()->id(), 403);
        $list->update($this->validateList($request));

        return response()->json($list);
    }

    public function destroyList(PlaceList $list)
    {
        abort_unless($list->user_id === auth()->id(), 403);
        if ($list->is_default) {
            return response()->json(['error' => 'List bawaan tidak bisa dihapus.'], 422);
        }
        // Hapus foto tiap tempat sebelum cascade DB menghapus barisnya.
        foreach ($list->places()->whereNotNull('photo_path')->pluck('photo_path') as $path) {
            Storage::disk('public')->delete($path);
        }
        $list->delete(); // cascade delete saved_places

        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'place_list_id' => 'required|integer',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:4096',
        ]);
        $this->assertOwnsList($data['place_list_id']);

        $data['photo_path'] = $request->file('photo')?->store('places', 'public');
        unset($data['photo']);

        $place = $request->user()->savedPlaces()->create($data);

        return response()->json($this->presentPlace($place->load('list')), 201);
    }

    public function update(Request $request, SavedPlace $place)
    {
        abort_unless($place->user_id === auth()->id(), 403);
        $data = $request->validate([
            'place_list_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);
        $this->assertOwnsList($data['place_list_id']);
        $place->update($data);

        return response()->json($this->presentPlace($place->load('list')));
    }

    public function destroy(SavedPlace $place)
    {
        abort_unless($place->user_id === auth()->id(), 403);
        if ($place->photo_path) {
            Storage::disk('public')->delete($place->photo_path);
        }
        $place->delete();

        return response()->json(['ok' => true]);
    }

    private function validateList(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'icon' => ['required', Rule::in(PlaceList::ICONS)],
            'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);
    }

    private function assertOwnsList(int $listId): void
    {
        abort_unless(
            PlaceList::where('id', $listId)->where('user_id', auth()->id())->exists(),
            403,
        );
    }

    private function presentPlace(SavedPlace $p): array
    {
        return [
            'id' => $p->id,
            'place_list_id' => $p->place_list_id,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'title' => $p->title,
            'description' => $p->description,
            'photo_url' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            'list_name' => $p->list?->name,
            'list_icon' => $p->list?->icon,
            'list_color' => $p->list?->color,
        ];
    }
}
