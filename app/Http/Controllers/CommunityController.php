<?php

namespace App\Http\Controllers;

use App\Models\CommunityPin;
use App\Services\CommunityPinService;
use App\Services\ImagePhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunityController extends Controller
{
    private ?array $likedIds = null;

    private ?array $dislikedIds = null;

    private ?array $favoritedIds = null;

    public function __construct(
        private CommunityPinService $service,
        private ImagePhotoService $photoService,
    ) {}

    public function index()
    {
        return view('map.community', [
            'pins' => $this->service->visiblePins(),
        ]);
    }

    public function data()
    {
        $this->loadUserSets();

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

        $data['photo_path'] = $request->hasFile('photo')
            ? $this->photoService->storeCompressed($request->file('photo'), 'community')
            : null;
        $data['is_anonymous'] = $request->boolean('is_anonymous');
        unset($data['photo']);

        $pin = $request->user()->communityPins()->create($data);

        return response()->json($this->present($pin->load('user')), 201);
    }

    public function confirm(Request $request, CommunityPin $pin)
    {
        $data = $request->validate(['still_there' => 'required|boolean']);
        [$still, $gone] = $this->service->confirm($pin, $request->user(), $data['still_there']);

        // Vote user sendiri setelah toggle (null = baru saja dibatalkan) --
        // dipakai frontend buat nyalain/matiin warna ikon like/dislike.
        $myVote = $pin->confirmations()->where('user_id', $request->user()->id)->first()?->still_there;

        return response()->json([
            'still_count' => $still,
            'gone_count' => $gone,
            'is_liked' => $myVote === true,
            'is_disliked' => $myVote === false,
        ]);
    }

    public function favorite(Request $request, CommunityPin $pin)
    {
        $favorited = $this->service->toggleFavorite($pin, $request->user());

        return response()->json(['favorited' => $favorited]);
    }

    public function nearRoute(Request $request)
    {
        $data = $request->validate([
            'geometry' => 'required|array|min:2',
            'geometry.*' => 'required|array|size:2',
            'geometry.*.0' => 'required|numeric|between:-90,90',
            'geometry.*.1' => 'required|numeric|between:-180,180',
        ]);

        $this->loadUserSets();

        return response()->json([
            'pins' => $this->service->nearRoute($data['geometry'])->map(fn (CommunityPin $p) => $this->present($p)),
        ]);
    }

    // Ambil sekali per-request pin id yang di-like/difavoritkan user login,
    // dipakai present() lewat in_array() supaya nggak query per-pin (N+1).
    private function loadUserSets(): void
    {
        $this->likedIds = $this->service->likedPinIds(auth()->user());
        $this->dislikedIds = $this->service->dislikedPinIds(auth()->user());
        $this->favoritedIds = $this->service->favoritedPinIds(auth()->user());
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
            'still_count' => $p->still_count,
            'gone_count' => $p->gone_count,
            'contributor' => $p->is_anonymous ? null : $p->user?->name,
            'is_mine' => $p->user_id === auth()->id(),
            'is_liked' => in_array($p->id, $this->likedIds ?? [], true),
            'is_disliked' => in_array($p->id, $this->dislikedIds ?? [], true),
            'is_favorited' => in_array($p->id, $this->favoritedIds ?? [], true),
        ];
    }
}
