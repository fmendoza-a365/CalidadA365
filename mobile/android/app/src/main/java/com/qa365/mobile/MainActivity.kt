package com.qa365.mobile

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.core.content.ContextCompat
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import org.json.JSONObject

class MainActivity : ComponentActivity() {
    private var token by mutableStateOf<String?>(null)
    private var serverUrl by mutableStateOf("https://qa365.com.pe")
    private var dashboardData by mutableStateOf<JSONObject?>(null)
    private var activeTab by mutableStateOf("dashboard")
    private var themeMode by mutableStateOf("system")
    private var pendingEvaluationId by mutableStateOf<String?>(null)
    private var dashboardFilters by mutableStateOf<Map<String, String>>(emptyMap())

    // ActivityResultLauncher for requesting push notification permissions on Android 13+
    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted: Boolean ->
        if (isGranted) {
            Log.d("MainActivity", "Notification permission granted.")
            registerFcmToken()
        } else {
            Log.d("MainActivity", "Notification permission denied.")
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        val prefs = getSharedPreferences("qa365_mobile", Context.MODE_PRIVATE)
        token = prefs.getString("token", null)
        serverUrl = prefs.getString("server_url", "https://qa365.com.pe") ?: "https://qa365.com.pe"
        themeMode = prefs.getString("theme_mode", "system") ?: "system"

        if (token != null) {
            loadDashboard()
            askNotificationPermission()
            registerFcmToken()
        }

        // Handle deep link if app is launched through it
        intent?.data?.let { handleDeepLink(it) }

        setContent {
            val isDark = when (themeMode) {
                "light" -> false
                "dark" -> true
                else -> isSystemInDarkTheme()
            }
            QA365Theme(darkTheme = isDark) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    if (token == null) {
                        LoginScreen(
                            initialServer = serverUrl,
                            onLoginSuccess = { newToken, newServer ->
                                token = newToken
                                serverUrl = newServer
                                prefs.edit()
                                    .putString("token", token)
                                    .putString("server_url", serverUrl)
                                    .apply()
                                loadDashboard()
                                askNotificationPermission()
                                registerFcmToken()
                            }
                        )
                    } else {
                        DashboardScreen(
                            data = dashboardData,
                            activeTab = activeTab,
                            token = token,
                            serverUrl = serverUrl,
                            themeMode = themeMode,
                            pendingEvaluationId = pendingEvaluationId,
                            onClearPendingEvaluation = { pendingEvaluationId = null },
                            onThemeChanged = { newMode ->
                                themeMode = newMode
                                prefs.edit().putString("theme_mode", newMode).apply()
                            },
                            onTabSelected = { activeTab = it },
                            onLogout = {
                                unregisterFcmToken()
                                token = null
                                prefs.edit().remove("token").apply()
                                dashboardData = null
                            },
                            onRefresh = { loadDashboard() },
                            onFiltersChanged = { filters -> loadDashboard(filters) }
                        )
                    }
                }
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        intent.data?.let { handleDeepLink(it) }
    }

    private fun handleDeepLink(uri: Uri) {
        Log.d("MainActivity", "Deep link received: $uri")
        if (uri.scheme == "qa365" && uri.host == "evaluations") {
            val pathSegments = uri.pathSegments
            if (pathSegments.isNotEmpty()) {
                val evalId = pathSegments[0]
                Log.d("MainActivity", "Parsed evaluation ID: $evalId")
                pendingEvaluationId = evalId
            }
        }
    }

    private fun askNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) !=
                PackageManager.PERMISSION_GRANTED
            ) {
                requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
    }

    private fun registerFcmToken() {
        val currentToken = token ?: return
        val currentServer = serverUrl
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (!task.isSuccessful) {
                Log.w("MainActivity", "Fetching FCM registration token failed", task.exception)
                return@addOnCompleteListener
            }
            val fcmToken = task.result
            Log.d("MainActivity", "Retrieved FCM Token: $fcmToken")
            
            // Save locally
            val prefs = getSharedPreferences("qa365_mobile", Context.MODE_PRIVATE)
            prefs.edit().putString("fcm_token", fcmToken).apply()

            // Call register endpoint on backend
            CoroutineScope(Dispatchers.IO).launch {
                try {
                    val body = JSONObject().apply {
                        put("fcm_token", fcmToken)
                        put("platform", "android")
                    }
                    Api.request(currentServer, "/api/mobile/devices/register", "POST", body, currentToken)
                    Log.d("MainActivity", "FCM token registered on backend successfully")
                } catch (e: Exception) {
                    Log.e("MainActivity", "FCM token registration failed: ${e.message}")
                }
            }
        }
    }

    private fun unregisterFcmToken() {
        val currentToken = token ?: return
        val currentServer = serverUrl
        val prefs = getSharedPreferences("qa365_mobile", Context.MODE_PRIVATE)
        val fcmToken = prefs.getString("fcm_token", null)
        if (!fcmToken.isNullOrEmpty()) {
            CoroutineScope(Dispatchers.IO).launch {
                try {
                    val body = JSONObject().apply {
                        put("fcm_token", fcmToken)
                    }
                    Api.request(currentServer, "/api/mobile/devices/unregister", "POST", body, currentToken)
                    Log.d("MainActivity", "FCM token unregistered from backend successfully")
                } catch (e: Exception) {
                    Log.e("MainActivity", "FCM token unregistration failed: ${e.message}")
                }
            }
        }
    }

    private fun loadDashboard(filters: Map<String, String> = dashboardFilters) {
        dashboardFilters = filters
        CoroutineScope(Dispatchers.Main).launch {
            try {
                val queryParams = filters.entries.joinToString("&") { "${it.key}=${it.value}" }
                val path = if (queryParams.isNotEmpty()) "/api/mobile/dashboard?$queryParams" else "/api/mobile/dashboard"
                dashboardData = Api.request(serverUrl, path, "GET", null, token)
            } catch (e: Exception) {
                if (e is ApiException && e.code == 401) {
                    token = null
                    getSharedPreferences("qa365_mobile", Context.MODE_PRIVATE).edit().remove("token").apply()
                }
            }
        }
    }
}
