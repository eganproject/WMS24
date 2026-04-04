<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\AssemblyRecipe;
use App\Models\AssemblyRecipeItem;
use App\Models\Item;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssemblyRecipeController extends Controller
{
    private function generateCode(): string
    {
        $prefix = 'P_RKT';
        $date = now()->format('Ymd');
        $latest = AssemblyRecipe::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();
        $seq = 1;
        if ($latest && preg_match('/^(?:' . preg_quote($prefix, '/') . ')-' . $date . '-(\d{4})$/', $latest->code, $m)) {
            $seq = ((int) $m[1]) + 1;
        }
        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
    }
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $recipes = AssemblyRecipe::query()
            ->with('finishedItem')
            ->when($search !== '', function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhereHas('finishedItem', function ($qi) use ($search) {
                      $qi->where('sku', 'LIKE', "%{$search}%")
                         ->orWhere('nama_barang', 'LIKE', "%{$search}%");
                  });
            })
            ->orderBy('code')
            ->paginate(10)
            ->appends(['search' => $search]);

        return view('admin.masterdata.assembly_recipes.index', compact('recipes', 'search'));
    }

    public function show(AssemblyRecipe $assemblyrecipe)
    {
        $assemblyrecipe->load('finishedItem', 'items.item');
        return view('admin.masterdata.assembly_recipes.show', [
            'recipe' => $assemblyrecipe,
        ]);
    }

    public function create()
    {
        $items = Item::orderBy('nama_barang')->get();
        $nextCode = $this->generateCode();
        return view('admin.masterdata.assembly_recipes.create', compact('items', 'nextCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
                'description' => 'nullable|string',
                'finished_item_id' => 'required|integer|exists:items,id',
                'output_quantity' => 'required|numeric|min:0.01',
                'is_active' => 'nullable|boolean',
                'components.item_id' => 'required|array|min:1',
                'components.item_id.*' => 'required|integer|exists:items,id',
                'components.quantity' => 'required|array|min:1',
                'components.quantity.*' => 'required|numeric|min:0.01',
            ]);
        DB::beginTransaction();
        try {
            $recipe = AssemblyRecipe::create([
                'code' => $this->generateCode(),
                'description' => $request->input('description'),
                'finished_item_id' => $request->input('finished_item_id'),
                'output_quantity' => $request->input('output_quantity', 1),
                // Respect checkbox behavior: use has() so unchecked => false
                'is_active' => $request->has('is_active'),
            ]);

            $itemIds = $request->input('components.item_id', []);
            $quantities = $request->input('components.quantity', []);

            foreach ($itemIds as $idx => $itemId) {
                $qty = $quantities[$idx] ?? null;
                if (!$itemId || !$qty) continue;
                AssemblyRecipeItem::create([
                    'assembly_recipe_id' => $recipe->id,
                    'item_id' => $itemId,
                    'quantity' => $qty,
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'assemblyrecipes',
                'description' => 'Menambahkan assembly recipe: ' . $recipe->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.masterdata.assemblyrecipes.index')->with('success', 'Assembly recipe berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan assembly recipe: ' . $e->getMessage());
        }
    }

    public function edit(AssemblyRecipe $assemblyrecipe)
    {
        $assemblyrecipe->load('items.item');
        $items = Item::orderBy('nama_barang')->get();
        return view('admin.masterdata.assembly_recipes.edit', [
            'recipe' => $assemblyrecipe,
            'items' => $items,
        ]);
    }

    public function update(Request $request, AssemblyRecipe $assemblyrecipe)
    {
        $request->validate([
                'code' => 'nullable|string|max:100|unique:assembly_recipes,code,' . $assemblyrecipe->id,
                'description' => 'nullable|string',
                'finished_item_id' => 'required|integer|exists:items,id',
                'output_quantity' => 'required|numeric|min:0.01',
                'is_active' => 'nullable|boolean',
                'components.item_id' => 'required|array|min:1',
                'components.item_id.*' => 'required|integer|exists:items,id',
                'components.quantity' => 'required|array|min:1',
                'components.quantity.*' => 'required|numeric|min:0.01',
            ]);
        DB::beginTransaction();
        try {
            $dataToUpdate = [
                'description' => $request->input('description'),
                'finished_item_id' => $request->input('finished_item_id'),
                'output_quantity' => $request->input('output_quantity', 1),
                'is_active' => $request->has('is_active'),
            ];
            if ($request->filled('code')) {
                $dataToUpdate['code'] = $request->input('code');
            }
            $assemblyrecipe->update($dataToUpdate);

            AssemblyRecipeItem::where('assembly_recipe_id', $assemblyrecipe->id)->delete();

            $itemIds = $request->input('components.item_id', []);
            $quantities = $request->input('components.quantity', []);
            foreach ($itemIds as $idx => $itemId) {
                $qty = $quantities[$idx] ?? null;
                if (!$itemId || !$qty) continue;
                AssemblyRecipeItem::create([
                    'assembly_recipe_id' => $assemblyrecipe->id,
                    'item_id' => $itemId,
                    'quantity' => $qty,
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'assemblyrecipes',
                'description' => 'Memperbarui assembly recipe: ' . $assemblyrecipe->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.masterdata.assemblyrecipes.index')->with('success', 'Assembly recipe berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui assembly recipe: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, AssemblyRecipe $assemblyrecipe)
    {
        DB::beginTransaction();
        try {
            AssemblyRecipeItem::where('assembly_recipe_id', $assemblyrecipe->id)->delete();
            $code = $assemblyrecipe->code;
            $assemblyrecipe->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'assemblyrecipes',
                'description' => 'Menghapus assembly recipe: ' . $code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.masterdata.assemblyrecipes.index')->with('success', 'Assembly recipe berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus assembly recipe: ' . $e->getMessage());
        }
    }
}
