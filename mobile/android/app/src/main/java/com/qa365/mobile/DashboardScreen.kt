package com.qa365.mobile

import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import org.json.JSONObject

@Composable
fun DashboardScreen(
    data: JSONObject?,
    activeTab: String,
    token: String?,
    serverUrl: String,
    themeMode: String,
    onThemeChanged: (String) -> Unit,
    onTabSelected: (String) -> Unit,
    onLogout: () -> Unit,
    onRefresh: () -> Unit
) {
    // Premium navigation stack using mutableStateListOf
    val navStack = remember { mutableStateListOf<Pair<String, JSONObject>>() }

    // Clear navigation stack when switching tabs to avoid leaks or confusing back behaviors
    LaunchedEffect(activeTab) {
        navStack.clear()
    }

    if (navStack.isNotEmpty()) {
        val (currentType, currentData) = navStack.last()
        val profile = data?.optJSONObject("profile") ?: JSONObject()
        val isAgent = profile.optString("primary_view", "executive") == "agent"
        DetailScreen(
            type = currentType,
            data = currentData,
            token = token,
            serverUrl = serverUrl,
            isAgent = isAgent,
            onNavigate = { type, itemData ->
                navStack.add(Pair(type, itemData))
            },
            onBack = {
                navStack.removeLast()
            }
        )
        return
    }

    Scaffold(
        bottomBar = {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 10.dp)
            ) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .border(
                            width = 1.dp,
                            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.2f),
                            shape = RoundedCornerShape(24.dp)
                        ),
                    shape = RoundedCornerShape(24.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.95f)
                    ),
                    elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
                ) {
                    NavigationBar(
                        containerColor = Color.Transparent,
                        tonalElevation = 0.dp,
                        modifier = Modifier.height(72.dp)
                    ) {
                        val items = listOf(
                            NavItem("dashboard", "Inicio", Icons.Default.Home),
                            NavItem("transcripts", "Audios", Icons.Default.Audiotrack),
                            NavItem("evaluations", "Eval.", Icons.Default.Assessment),
                            NavItem("campaigns", "Campañas", Icons.Default.Campaign),
                            NavItem("more", "Perfil", Icons.Default.Person)
                        )

                        items.forEach { item ->
                            NavigationBarItem(
                                selected = activeTab == item.id,
                                onClick = { onTabSelected(item.id) },
                                icon = { Icon(item.icon, contentDescription = item.label, modifier = Modifier.size(20.dp)) },
                                label = { Text(item.label, fontSize = 10.sp, fontWeight = FontWeight.Medium) },
                                colors = NavigationBarItemDefaults.colors(
                                    selectedIconColor = MaterialTheme.colorScheme.primary,
                                    selectedTextColor = MaterialTheme.colorScheme.primary,
                                    unselectedIconColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                                    unselectedTextColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                                    indicatorColor = MaterialTheme.colorScheme.primary.copy(alpha = 0.12f)
                                )
                            )
                        }
                    }
                }
            }
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
        ) {
            if (data == null) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(strokeWidth = 3.dp)
                }
            } else {
                val onNavigate: (String, JSONObject) -> Unit = { type, itemData ->
                    navStack.add(Pair(type, itemData))
                }

                when (activeTab) {
                    "dashboard" -> MainDashboardModule(data, onNavigate)
                    "transcripts" -> TranscriptsModule(data, onNavigate)
                    "evaluations" -> EvaluationsModule(data, onNavigate)
                    "campaigns" -> CampaignsModule(data, onNavigate)
                    "more" -> ProfileModule(data, themeMode, onThemeChanged, onLogout, onRefresh)
                    else -> Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text("Módulo en construcción: $activeTab")
                    }
                }
            }
        }
    }
}

data class NavItem(val id: String, val label: String, val icon: ImageVector)
