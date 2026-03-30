<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ProductReview;
use App\Models\Tenant\Item;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = ProductReview::with('item:id,description')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json($reviews);
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'reviewer_name' => 'required|string|max:150',
            'reviewer_email' => 'nullable|email|max:200',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:2000',
        ]);

        $verified = false;
        if ($request->order_id) {
            $verified = \App\Models\Tenant\Order::where('id', $request->order_id)
                ->whereIn('status_order_id', [3, 4])->exists();
        }

        $review = ProductReview::create([
            'item_id' => $request->item_id,
            'person_id' => auth()->id(),
            'order_id' => $request->order_id,
            'reviewer_name' => $request->reviewer_name,
            'reviewer_email' => $request->reviewer_email,
            'rating' => $request->rating,
            'title' => $request->title,
            'body' => $request->body,
            'verified_purchase' => $verified,
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Reseña enviada para moderación']);
    }

    public function forProduct(int $itemId)
    {
        $reviews = ProductReview::approved()->forItem($itemId)
            ->orderByDesc('verified_purchase')->orderByDesc('created_at')
            ->limit(50)->get();
        $stats = ProductReview::averageForItem($itemId);
        return response()->json(['reviews' => $reviews, 'stats' => $stats]);
    }

    public function moderate(Request $request, int $id)
    {
        $request->validate(['status' => 'required|in:approved,rejected', 'admin_reply' => 'nullable|string|max:1000']);
        $review = ProductReview::findOrFail($id);
        $review->update([
            'status' => $request->status,
            'admin_reply' => $request->admin_reply,
            'approved_at' => $request->status === 'approved' ? now() : null,
        ]);
        return response()->json(['success' => true]);
    }
}
