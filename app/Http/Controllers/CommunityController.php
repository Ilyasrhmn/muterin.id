<?php

namespace App\Http\Controllers;

use App\Models\CommunityPin;
use App\Services\CommunityPinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunityController extends Controller
{
    public function __construct(private CommunityPinService $service) {}

    public function index()
    {
        return view('map.community', [
            'pins' => $this->service->visiblePins(),
        ]);
    }

    public function data()
    {
        return response()->json([
            'pins' => $this->service->visiblePins()->map(fn (CommunityPin $p) => $this->present($p)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|in:sepi,gelap,rawan,rusak,banjir,momen',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'time_context' => 'required|in:siang,malam,kapanpun',
            'is_anonymous' => 'boolean',
            'photo' => 'nullable|image|max:10240',
        ]);

        $data['photo_path'] = $request->file('photo')?->store('community', 'public');
        $data['is_anonymous'] = $request->boolean('is_anonymous');
        unset($data['photo']);

        $pin = $request->user()->communityPins()->create($data);

        return response()->json($this->present($pin->load('user')), 201);
    }

    public function confirm(Request $request, CommunityPin $pin)
    {
        $data = $request->validate(['still_there' => 'required|boolean']);
        $count = $this->service->confirm($pin, $request->user(), $data['still_there']);

        return response()->json(['confirm_count' => $count]);
    }

    public function nearRoute(Request $request)
    {
        $data = $request->validate([
            'geometry' => 'required|array|min:2',
            'geometry.*' => 'required|array|size:2',
            'geometry.*.0' => 'required|numeric|between:-90,90',
            'geometry.*.1' => 'required|numeric|between:-180,180',
        ]);

        return response()->json([
            'pins' => $this->service->nearRoute($data['geometry'])->map(fn (CommunityPin $p) => $this->present($p)),
        ]);
    }

    public function destroy(CommunityPin $pin)
    {
        abort_unless($pin->user_id === auth()->id(), 403);
        if ($pin->photo_path) {
            Storage::disk('public')->delete($pin->photo_path);
        }
        $pin->delete();

        return response()->json(['ok' => true]);
    }

    private function present(CommunityPin $p): array
    {
        return [
            'id' => $p->id,
            'category' => $p->category,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'title' => $p->title,
            'description' => $p->description,
            'photo_url' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            'time_context' => $p->time_context,
            'confirm_count' => $p->confirm_count,
            'contributor' => $p->is_anonymous ? null : $p->user?->name,
            'is_mine' => $p->user_id === auth()->id(),
        ];
    }
}
