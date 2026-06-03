package com.qa365.mobile

import android.content.Context
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import org.json.JSONObject

import androidx.compose.foundation.isSystemInDarkTheme

class MainActivity : ComponentActivity() {
    private var token by mutableStateOf<String?>(null)
    private var serverUrl by mutableStateOf("https://qa365.com.pe")
    private var dashboardData by mutableStateOf<JSONObject?>(null)
    private var activeTab by mutableStateOf("dashboard")
    private var themeMode by mutableStateOf("system")

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        val prefs = getSharedPreferences("qa365_mobile", Context.MODE_PRIVATE)
        token = prefs.getString("token", null)
        serverUrl = prefs.getString("server_url", "https://qa365.com.pe") ?: "https://qa365.com.pe"
        themeMode = prefs.getString("theme_mode", "system") ?: "system"

        if (token != null) {
            loadDashboard()
        }

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
                            }
                        )
                    } else {
                        DashboardScreen(
                            data = dashboardData,
                            activeTab = activeTab,
                            token = token,
                            serverUrl = serverUrl,
                            themeMode = themeMode,
                            onThemeChanged = { newMode ->
                                themeMode = newMode
                                prefs.edit().putString("theme_mode", newMode).apply()
                            },
                            onTabSelected = { activeTab = it },
                            onLogout = {
                                token = null
                                prefs.edit().remove("token").apply()
                                dashboardData = null
                            },
                            onRefresh = { loadDashboard() }
                        )
                    }
                }
            }
        }
    }

    private fun loadDashboard() {
        CoroutineScope(Dispatchers.Main).launch {
            try {
                dashboardData = Api.request(serverUrl, "/api/mobile/dashboard", "GET", null, token)
            } catch (e: Exception) {
                // If 401, clear token
                if (e is ApiException && e.code == 401) {
                    token = null
                    getSharedPreferences("qa365_mobile", Context.MODE_PRIVATE).edit().remove("token").apply()
                }
            }
        }
    }
}
