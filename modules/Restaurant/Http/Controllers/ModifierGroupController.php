<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Restaurant\Models\ModifierGroup;
use App\Models\Tenant\Item;

class ModifierGroupController extends Controller
{
    /**
     * List all modifier groups
     */
    public function records()
    {
        $groups = ModifierGroup::orderBy('name')->get();
        return response()->json(['data' => $groups]);
    }

    /**
     * Render web index page for modifier groups
     */
    public function indexPage()
    {
        return view('restaurant::modifier_groups.index');
    }

    /**
     * Store a new modifier group
     */
    public function store(Request $request)
    {
        $data = $request->only(['name', 'selection_type', 'items', 'active']);
        $group = ModifierGroup::create($data);
        return response()->json(['data' => $group], 201);
    }

    /**
     * Show a single group
     */
    public function show($id)
    {
        $group = ModifierGroup::findOrFail($id);
        return response()->json(['data' => $group]);
    }

    /**
     * Update a group
     */
    public function update(Request $request, $id)
    {
        $group = ModifierGroup::findOrFail($id);
        $group->fill($request->only(['name', 'selection_type', 'items', 'active']));
        $group->save();
        return response()->json(['data' => $group]);
    }

    /**
     * Delete a group
     */
    public function destroy($id)
    {
        $group = ModifierGroup::findOrFail($id);
        $group->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Assign groups to an item (sync)
     * Accepts payload: { group_ids: [1,2], default_open_map: {groupId: true|false} }
     */
    public function assignToItem(Request $request, $itemId)
    {
        $item = Item::findOrFail($itemId);
        $groupIds = $request->input('group_ids', []);
        $defaultOpenMap = $request->input('default_open_map', []);

        $syncData = [];
        foreach ($groupIds as $gid) {
            $syncData[$gid] = ['default_open' => isset($defaultOpenMap[$gid]) ? (bool)$defaultOpenMap[$gid] : false];
        }

        $item->modifierGroups()->sync($syncData);

        return response()->json(['data' => $item->modifierGroups()->get()]);
    }

    /**
     * Get groups assigned to an item
     */
    public function groupsForItem($itemId)
    {
        $item = Item::findOrFail($itemId);
        return response()->json(['data' => $item->modifierGroups()->get()]);
    }
}
