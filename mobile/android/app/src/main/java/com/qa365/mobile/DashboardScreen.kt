package com.qa365.mobile

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
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
    var detailType by remember { mutableStateOf<String?>(null) }
    var detailData by remember { mutableStateOf<JSONObject?>(null) }

    if (detailType != null && detailData != null) {
        DetailScreen(
            type = detailType!!,
            data = detailData!!,
            token = token,
            serverUrl = serverUrl,
            onBack = {
                detailType = null
                detailData = null
            }
        )
        return
    }

    Scaffold(
        bottomBar = {
            NavigationBar(
                containerColor = MaterialTheme.colorScheme.surface,
                tonalElevation = 8.dp
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
                        label = { Text(item.label) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = MaterialTheme.colorScheme.primary,
                            selectedTextColor = MaterialTheme.colorScheme.primary,
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
                    CircularProgressIndicator()
                }
            } else {
                val onDetailSelected: (String, JSONObject) -> Unit = { type, itemData ->
                    detailType = type
                    detailData = itemData
                }

                when (activeTab) {
                    "dashboard" -> MainDashboardModule(data, onDetailSelected)
                    "transcripts" -> TranscriptsModule(data, onDetailSelected)
                    "evaluations" -> EvaluationsModule(data, onDetailSelected)
                    "campaigns" -> CampaignsModule(data, onDetailSelected)
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
