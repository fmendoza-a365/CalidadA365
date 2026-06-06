package com.qa365.mobile

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
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
            MobileBottomNav(activeTab = activeTab, onTabSelected = onTabSelected)
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

@Composable
private fun MobileBottomNav(activeTab: String, onTabSelected: (String) -> Unit) {
    val items = listOf(
        NavItem("dashboard", "Inicio", Icons.Default.Home),
        NavItem("transcripts", "Audio", Icons.Default.Audiotrack),
        NavItem("evaluations", "Evals", Icons.Default.Assessment),
        NavItem("campaigns", "Camp.", Icons.Default.Campaign),
        NavItem("more", "Perfil", Icons.Default.Person)
    )

    Surface(
        tonalElevation = 0.dp,
        shadowElevation = 10.dp,
        color = MaterialTheme.colorScheme.background.copy(alpha = 0.98f)
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 10.dp)
                .border(
                    width = 1.dp,
                    color = MaterialTheme.colorScheme.outline.copy(alpha = 0.22f),
                    shape = RoundedCornerShape(24.dp)
                ),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(66.dp)
                    .padding(horizontal = 8.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                items.forEach { item ->
                    val selected = activeTab == item.id
                    Column(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxHeight()
                            .clip(RoundedCornerShape(18.dp))
                            .background(if (selected) MaterialTheme.colorScheme.primary.copy(alpha = 0.12f) else Color.Transparent)
                            .clickable { onTabSelected(item.id) }
                            .padding(horizontal = 2.dp, vertical = 6.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Icon(
                            imageVector = item.icon,
                            contentDescription = item.label,
                            tint = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.48f),
                            modifier = Modifier.size(if (selected) 22.dp else 20.dp)
                        )
                        Spacer(modifier = Modifier.height(3.dp))
                        Text(
                            text = item.label,
                            fontSize = 10.sp,
                            fontWeight = if (selected) FontWeight.Black else FontWeight.SemiBold,
                            color = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.52f),
                            maxLines = 1,
                            overflow = TextOverflow.Clip
                        )
                    }
                }
            }
        }
    }
}
