<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Garment;
use App\Models\Design;
use App\Models\OrderItem;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get comprehensive product and design performance metrics:
     * - Most viewed products
     * - Most viewed designs
     * - Most sold products (garments)
     * - Most sold designs
     * - High-level summary KPI stats
     */
    public function productPerformance()
    {
        try {
            // 1. Most Viewed Garments (Products)
            $mostViewedGarments = Garment::with(['category', 'design'])
                ->orderByDesc('views_count')
                ->limit(10)
                ->get()
                ->map(function ($g) {
                    return [
                        'id' => $g->id,
                        'name' => $g->name,
                        'image' => $g->image,
                        'category' => $g->category?->name ?? 'General',
                        'design' => $g->design?->name ?? 'Standard',
                        'price' => (float)$g->price,
                        'views_count' => (int)($g->views_count ?? 0),
                    ];
                });

            // 2. Most Viewed Designs
            $mostViewedDesigns = Design::with('category')
                ->orderByDesc('views_count')
                ->limit(10)
                ->get()
                ->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'name' => $d->name,
                        'image' => $d->image_url ?? $d->image,
                        'category' => $d->category?->name ?? 'General',
                        'views_count' => (int)($d->views_count ?? 0),
                    ];
                });

            // 3. Most Sold Products (Garments)
            $mostSoldGarments = OrderItem::select(
                    'garment_id',
                    'garment_name',
                    DB::raw('SUM(quantity) as total_sold_quantity'),
                    DB::raw('SUM(price * quantity) as total_revenue'),
                    DB::raw('COUNT(DISTINCT order_id) as total_orders')
                )
                ->whereHas('order', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->groupBy('garment_id', 'garment_name')
                ->orderByDesc('total_sold_quantity')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $garment = $item->garment_id ? Garment::with('category')->find($item->garment_id) : null;
                    return [
                        'garment_id' => $item->garment_id,
                        'name' => $garment?->name ?? $item->garment_name ?? 'Custom Product',
                        'image' => $garment?->image,
                        'category' => $garment?->category?->name ?? 'Stitching',
                        'price' => $garment ? (float)$garment->price : null,
                        'total_sold_quantity' => (int)$item->total_sold_quantity,
                        'total_revenue' => (float)$item->total_revenue,
                        'total_orders' => (int)$item->total_orders,
                    ];
                });

            // 4. Most Sold Designs
            $mostSoldDesigns = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('garments', 'order_items.garment_id', '=', 'garments.id')
                ->join('designs', 'garments.design_id', '=', 'designs.id')
                ->where('orders.status', '!=', 'cancelled')
                ->select(
                    'designs.id as design_id',
                    'designs.name as design_name',
                    'designs.image as design_image',
                    DB::raw('SUM(order_items.quantity) as total_sold_quantity'),
                    DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue'),
                    DB::raw('COUNT(DISTINCT order_items.order_id) as total_orders')
                )
                ->groupBy('designs.id', 'designs.name', 'designs.image')
                ->orderByDesc('total_sold_quantity')
                ->limit(10)
                ->get()
                ->map(function ($d) {
                    return [
                        'design_id' => $d->design_id,
                        'name' => $d->design_name,
                        'image' => $d->design_image ? (str_starts_with($d->design_image, 'http') ? $d->design_image : asset('storage/' . $d->design_image)) : null,
                        'total_sold_quantity' => (int)$d->total_sold_quantity,
                        'total_revenue' => (float)$d->total_revenue,
                        'total_orders' => (int)$d->total_orders,
                    ];
                });

            // 5. High level KPI totals
            $totalProductViews = (int) Garment::sum('views_count');
            $totalDesignViews = (int) Design::sum('views_count');
            
            $soldAggregate = OrderItem::whereHas('order', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->select(
                    DB::raw('COALESCE(SUM(quantity), 0) as total_units'),
                    DB::raw('COALESCE(SUM(price * quantity), 0) as total_sales')
                )
                ->first();

            return $this->successResponse([
                'summary' => [
                    'total_product_views' => $totalProductViews,
                    'total_design_views' => $totalDesignViews,
                    'total_units_sold' => (int)($soldAggregate->total_units ?? 0),
                    'total_sales_revenue' => (float)($soldAggregate->total_sales ?? 0),
                    'top_viewed_product' => $mostViewedGarments->first() ?? null,
                    'top_sold_product' => $mostSoldGarments->first() ?? null,
                    'top_sold_design' => $mostSoldDesigns->first() ?? null,
                ],
                'most_viewed_products' => $mostViewedGarments,
                'most_viewed_designs' => $mostViewedDesigns,
                'most_sold_products' => $mostSoldGarments,
                'most_sold_designs' => $mostSoldDesigns,
            ], 'Product performance analytics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve product performance analytics', 500, $e->getMessage());
        }
    }

    /**
     * Record a product (garment) view
     */
    public function recordView($id)
    {
        try {
            $garment = Garment::findOrFail($id);
            $garment->increment('views_count');
            if ($garment->design_id) {
                Design::where('id', $garment->design_id)->increment('views_count');
            }

            return $this->successResponse([
                'garment_id' => $garment->id,
                'views_count' => (int) $garment->views_count,
            ], 'View recorded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record product view', 500, $e->getMessage());
        }
    }
}
