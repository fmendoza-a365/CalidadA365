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
            ->hasMany(DashboardWidget::class)
            ->orderBy('order')
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
            'position_x' => 'integer|min:0',
            'position_y' => 'integer|min:0',
            'width' => 'integer|min:1|max:12',
            'height' => 'integer|min:1|max:12',
            'order' => 'integer|min:0',
        ]);

        $validated['user_id'] = auth()->id();

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
            'position_x' => 'sometimes|integer|min:0',
            'position_y' => 'sometimes|integer|min:0',
            'width' => 'sometimes|integer|min:1|max:12',
            'height' => 'sometimes|integer|min:1|max:12',
            'order' => 'sometimes|integer|min:0',
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
     * Bulk update widget positions (for drag-and-drop)
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|exists:dashboard_widgets,id',
            'widgets.*.position_x' => 'required|integer|min:0',
            'widgets.*.position_y' => 'required|integer|min:0',
            'widgets.*.width' => 'sometimes|integer|min:1|max:12',
            'widgets.*.height' => 'sometimes|integer|min:1|max:12',
        ]);

        foreach ($validated['widgets'] as $widgetData) {
            $widget = DashboardWidget::find($widgetData['id']);
            
            if ($widget && $widget->user_id === auth()->id()) {
                $widget->update([
                    'position_x' => $widgetData['position_x'],
                    'position_y' => $widgetData['position_y'],
                    'width' => $widgetData['width'] ?? $widget->width,
                    'height' => $widgetData['height'] ?? $widget->height,
                ]);
            }
        }

        return response()->json(['message' => 'Widgets updated successfully']);
    }
}
