<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Resi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChannelController extends Controller
{
    public function data(Request $request)
    {
        $query = Channel::query()->orderBy('name');
        $search = trim((string) $request->input('q', ''));

        if ($search !== '') {
            $this->applyTextSearch($query, 'name', $search, $this->isExactSearch($request));
        }

        $recordsTotal = Channel::count();
        $recordsFiltered = (clone $query)->count();
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);

        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $query->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['name' => $this->cleanName((string) $request->input('name'))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:channels,name'],
        ]);

        $channel = Channel::create([
            'name' => $this->cleanName($validated['name']),
        ]);

        return response()->json([
            'message' => 'Channel berhasil dibuat',
            'channel' => $channel,
        ]);
    }

    public function update(Request $request, Channel $channel)
    {
        $request->merge(['name' => $this->cleanName((string) $request->input('name'))]);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('channels', 'name')->ignore($channel->id),
            ],
        ]);

        $channel->update([
            'name' => $this->cleanName($validated['name']),
        ]);

        return response()->json([
            'message' => 'Channel berhasil diperbarui',
            'channel' => $channel->fresh(),
        ]);
    }

    public function destroy(Channel $channel)
    {
        DB::transaction(function () use ($channel) {
            Resi::where('channel_id', $channel->id)->update(['channel_id' => null]);
            $channel->delete();
        });

        return response()->json([
            'message' => 'Channel berhasil dihapus. Relasi resi terkait dikosongkan.',
        ]);
    }

    private function cleanName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?: trim($name);
    }
}
