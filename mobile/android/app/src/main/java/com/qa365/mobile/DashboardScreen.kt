package com.qa365.mobile

import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import org.json.JSONObject

@Composable
fun DashboardScreen(
    data: JSONObject?,
    activeTab: String,
    token: String?,
    serverUrl: String,
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
        DetailScreen(
            type = currentType,
            data = currentData,
            token = token,
            serverUrl = serverUrl,
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
            NavigationBar(
                modifier = Modifier.border(
                    width = 1.dp,
                    color = MaterialTheme.colorScheme.outline.copy(alpha = 0.4f)
                ),
                containerColor = MaterialTheme.colorScheme.surface,
                tonalElevation = 0.dp
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
                        icon = { Icon(item.icon, contentDescription = item.label) },
                        label = { Text(item.label, fontSize = 11.sp) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = MaterialTheme.colorScheme.primary,
                            selectedTextColor = MaterialTheme.colorScheme.primary,
                            unselectedIconColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                            unselectedTextColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                            indicatorColor = MaterialTheme.colorScheme.primary.copy(alpha = 0.1f)
                        )
                    )
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
                    "more" -> ProfileModule(data, onLogout, onRefresh)
                    else -> Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text("Módulo en construcción: $activeTab")
                    }
                }
            }
        }
    }
}

data class NavItem(val id: String, val label: String, val icon: ImageVector)
