<?php

namespace App\Http\Controllers;

use App\Models\DashboardWidget;
use Illuminate\Http\Request;

class DashboardWidgetController extends Controller
{
    /**
     * Get all widgets for the authenticated user
     */
    public function index()
    {
        $widgets = auth()->user()
            ->dashboardWidgets()
            ->orderBy('sort_order')
            ->get();

        return response()->json($widgets);
    }

    /**
     * Store a new widget
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'widget_type' => 'required|string|in:stats_card,line_chart,bar_chart,table,pie_chart',
            'title' => 'required|string|max:255',
            'config' => 'nullable|array',
            'width' => 'nullable|string|in:sm,md,lg,full',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['width'] = $validated['width'] ?? 'sm';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $widget = DashboardWidget::create($validated);

        return response()->json($widget, 201);
    }

    /**
     * Update an existing widget
     */
    public function update(Request $request, DashboardWidget $widget)
    {
        // Ensure user owns this widget
        if ($widget->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'config' => 'sometimes|array',
            'width' => 'sometimes|string|in:sm,md,lg,full',
            'sort_order' => 'sometimes|integer',
        ]);

        $widget->update($validated);

        return response()->json($widget);
    }

    /**
     * Delete a widget
     */
    public function destroy(DashboardWidget $widget)
    {
        // Ensure user owns this widget
        if ($widget->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $widget->delete();

        return response()->json(['message' => 'Widget deleted successfully']);
    }

    /**
     * Bulk update widget positions (reordering)
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|exists:dashboard_widgets,id',
            'widgets.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['widgets'] as $widgetData) {
            $widget = DashboardWidget::find($widgetData['id']);
            if ($widget && $widget->user_id === auth()->id()) {
                $widget->update(['sort_order' => $widgetData['sort_order']]);
            }
        }

        return response()->json(['message' => 'Widgets updated successfully']);
    }
}
