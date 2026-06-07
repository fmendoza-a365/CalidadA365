package com.qa365.mobile

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import org.json.JSONObject

class MyFirebaseMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d("FCM_SERVICE", "Refreshed token: $token")
        
        // Save FCM token locally
        val prefs = getSharedPreferences("qa365_mobile", Context.MODE_PRIVATE)
        prefs.edit().putString("fcm_token", token).apply()

        // If user is already logged in, register this token on the backend
        val apiToken = prefs.getString("token", null)
        val serverUrl = prefs.getString("server_url", "https://qa365.com.pe") ?: "https://qa365.com.pe"
        if (!apiToken.isNullOrEmpty()) {
            CoroutineScope(Dispatchers.IO).launch {
                try {
                    val body = JSONObject().apply {
                        put("fcm_token", token)
                        put("platform", "android")
                    }
                    Api.request(serverUrl, "/api/mobile/devices/register", "POST", body, apiToken)
                    Log.d("FCM_SERVICE", "Registered refreshed FCM token with backend")
                } catch (e: Exception) {
                    Log.e("FCM_SERVICE", "Error registering refreshed token: ${e.message}")
                }
            }
        }
    }

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        Log.d("FCM_SERVICE", "Message received from: ${remoteMessage.from}")

        val title = remoteMessage.notification?.title ?: remoteMessage.data["title"] ?: "Nueva notificación"
        val body = remoteMessage.notification?.body ?: remoteMessage.data["body"] ?: ""
        val deepLink = remoteMessage.data["deep_link"] ?: "qa365://evaluations"

        sendNotification(title, body, deepLink)
    }

    private fun sendNotification(title: String, body: String, deepLink: String) {
        val channelId = "qa365_notifications"
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        // Create the notification channel (required for Android 8.0+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                "Notificaciones de Calidad QA365",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Canal para notificaciones de nuevas evaluaciones y feedback"
                enableLights(true)
                enableVibration(true)
            }
            notificationManager.createNotificationChannel(channel)
        }

        // Create intent to open MainActivity with the deep link Uri
        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(deepLink)).apply {
            setClass(this@MyFirebaseMessagingService, MainActivity::class.java)
            flags = Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }

        // Add flags for security & android requirements
        val pendingIntent = PendingIntent.getActivity(
            this,
            System.currentTimeMillis().toInt(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        // Try using the app icon, fallback to system info icon
        val iconRes = try {
            val appIcon = applicationInfo.icon
            if (appIcon != 0) appIcon else android.R.drawable.ic_dialog_info
        } catch (e: Exception) {
            android.R.drawable.ic_dialog_info
        }

        val builder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(iconRes)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setDefaults(NotificationCompat.DEFAULT_ALL)

        notificationManager.notify(System.currentTimeMillis().toInt(), builder.build())
    }
}
