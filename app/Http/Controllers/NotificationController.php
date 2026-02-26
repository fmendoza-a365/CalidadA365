<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Obtener las últimas notificaciones no leídas y leídas
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $unread = $user->unreadNotifications()->take(10)->get();
        $read = $user->readNotifications()->take(5)->get();
        
        return response()->json([
            'unread' => $unread->map(function($n) {
                return [
                    'id' => $n->id,
                    'data' => $n->data,
                    'created_at' => $n->created_at->diffForHumans(),
                    'read_at' => null,
                ];
            }),
            'read' => $read->map(function($n) {
                return [
                    'id' => $n->id,
                    'data' => $n->data,
                    'created_at' => $n->created_at->diffForHumans(),
                    'read_at' => $n->read_at->format('d/m/Y H:i'),
                ];
            }),
            'unread_count' => $user->unreadNotifications()->count()
        ]);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        
        return response()->json(['success' => true]);
    }

    /**
     * Marcar todas como leídas
     */
    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        
        return response()->json(['success' => true]);
    }
}
