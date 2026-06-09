package com.qa365.mobile

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.lazy.LazyColumn
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
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import org.json.JSONArray
import org.json.JSONObject

@Composable
fun DashboardScreen(
    data: JSONObject?,
    activeTab: String,
    token: String?,
    serverUrl: String,
    themeMode: String,
    pendingEvaluationId: String? = null,
    onClearPendingEvaluation: () -> Unit = {},
    onThemeChanged: (String) -> Unit,
    onTabSelected: (String) -> Unit,
    onLogout: () -> Unit,
    onRefresh: () -> Unit,
    onFiltersChanged: (Map<String, String>) -> Unit = {}
) {
    // Premium navigation stack using mutableStateListOf
    val navStack = remember { mutableStateListOf<Pair<String, JSONObject>>() }
    val coroutineScope = rememberCoroutineScope()

    var notificationsList by remember { mutableStateOf<JSONArray?>(null) }
    var unreadCount by remember { mutableStateOf(0) }
    var isLoadingNotifications by remember { mutableStateOf(false) }

    val loadNotifications: () -> Unit = {
        if (token != null) {
            coroutineScope.launch {
                try {
                    isLoadingNotifications = true
                    val res = Api.request(serverUrl, "/api/mobile/notifications", "GET", null, token)
                    notificationsList = res.optJSONArray("data")
                    unreadCount = res.optJSONObject("meta")?.optInt("unread_count", 0) ?: 0
                } catch (e: Exception) {
                    android.util.Log.e("DashboardScreen", "Error loading notifications: ${e.message}")
                } finally {
                    isLoadingNotifications = false
                }
            }
        }
    }

    // Clear navigation stack when switching tabs to avoid leaks or confusing back behaviors
    LaunchedEffect(activeTab) {
        navStack.clear()
        if (activeTab == "notifications") {
            loadNotifications()
        }
    }

    // Handle incoming deep link evaluations
    LaunchedEffect(pendingEvaluationId) {
        if (pendingEvaluationId != null) {
            val placeholder = JSONObject().apply {
                put("id", pendingEvaluationId.toIntOrNull() ?: -1)
            }
            navStack.add(Pair("evaluation", placeholder))
            onClearPendingEvaluation()
        }
    }

    LaunchedEffect(token) {
        while (!token.isNullOrBlank()) {
            delay(15_000)
            onRefresh()
        }
    }

    // Poll for notifications count every 30s to update the badge
    LaunchedEffect(token) {
        while (!token.isNullOrBlank()) {
            try {
                val res = Api.request(serverUrl, "/api/mobile/notifications?per_page=1", "GET", null, token)
                unreadCount = res.optJSONObject("meta")?.optInt("unread_count", 0) ?: 0
            } catch (e: Exception) {
                // ignore
            }
            delay(30_000)
        }
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
                onRefresh()
                if (activeTab == "notifications") {
                    loadNotifications()
                }
            }
        )
        return
    }

    Scaffold(
        bottomBar = {
            MobileBottomNav(activeTab = activeTab, unreadCount = unreadCount, onTabSelected = onTabSelected)
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
                    "dashboard" -> MainDashboardModule(data, onNavigate, onFiltersChanged)
                    "transcripts" -> TranscriptsModule(data, onNavigate)
                    "evaluations" -> EvaluationsModule(data, onNavigate)
                    "notifications" -> NotificationsModule(
                        notificationsList = notificationsList,
                        isLoading = isLoadingNotifications,
                        unreadCount = unreadCount,
                        token = token,
                        serverUrl = serverUrl,
                        onNavigate = onNavigate,
                        onReload = loadNotifications
                    )
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
private fun MobileBottomNav(activeTab: String, unreadCount: Int, onTabSelected: (String) -> Unit) {
    val items = listOf(
        NavItem("dashboard", "Inicio", Icons.Default.Home),
        NavItem("transcripts", "Audio", Icons.Default.Audiotrack),
        NavItem("evaluations", "Evals", Icons.Default.Assessment),
        NavItem("notifications", "Notif.", Icons.Default.Notifications),
        NavItem("more", "Perfil", Icons.Default.Person)
    )

    Surface(
        modifier = Modifier.navigationBarsPadding(),
        tonalElevation = 0.dp,
        shadowElevation = 10.dp,
        color = MaterialTheme.colorScheme.background.copy(alpha = 0.98f)
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 14.dp, end = 14.dp, top = 8.dp, bottom = 6.dp)
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
                    .height(72.dp)
                    .padding(horizontal = 8.dp, vertical = 6.dp),
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
                            .padding(horizontal = 2.dp, vertical = 4.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Box(contentAlignment = Alignment.TopEnd) {
                            Icon(
                                imageVector = item.icon,
                                contentDescription = item.label,
                                tint = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.48f),
                                modifier = Modifier.size(if (selected) 22.dp else 20.dp)
                            )
                            if (item.id == "notifications" && unreadCount > 0) {
                                Box(
                                    modifier = Modifier
                                        .offset(x = 6.dp, y = (-4).dp)
                                        .size(16.dp)
                                        .background(Color(0xFFEF4444), shape = CircleShape),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text(
                                        text = if (unreadCount > 99) "99+" else unreadCount.toString(),
                                        color = Color.White,
                                        fontSize = 9.sp,
                                        fontWeight = FontWeight.Bold
                                    )
                                }
                            }
                        }
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

@Composable
fun NotificationsModule(
    notificationsList: JSONArray?,
    isLoading: Boolean,
    unreadCount: Int,
    token: String?,
    serverUrl: String,
    onNavigate: (String, JSONObject) -> Unit,
    onReload: () -> Unit
) {
    val coroutineScope = rememberCoroutineScope()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Notificaciones",
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onBackground
            )
            
            if (unreadCount > 0) {
                TextButton(
                    onClick = {
                        if (token != null) {
                            coroutineScope.launch {
                                try {
                                    Api.request(serverUrl, "/api/mobile/notifications/read-all", "POST", null, token)
                                    onReload()
                                } catch (e: Exception) {
                                    // ignore
                                }
                            }
                        }
                    }
                ) {
                    Text("Marcar todo leído", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        if (isLoading && notificationsList == null) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(strokeWidth = 2.dp)
            }
        } else if (notificationsList == null || notificationsList.length() == 0) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(32.dp),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(
                        imageVector = Icons.Default.NotificationsOff,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.25f),
                        modifier = Modifier.size(48.dp)
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    Text(
                        text = "No tienes notificaciones",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Medium,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                    )
                }
            }
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                items(notificationsList.length()) { index ->
                    val notification = notificationsList.optJSONObject(index) ?: return@items
                    val id = notification.optString("id")
                    val isUnread = notification.optString("read_at", "null") == "null"
                    val nData = notification.optJSONObject("data") ?: JSONObject()
                    val title = nData.optString("title", "Notificación")
                    val body = nData.optString("body", "")
                    val dateStr = notification.optString("created_at", "").take(16).replace("T", " ")
                    val evalId = nData.optInt("evaluation_id", -1)

                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable {
                                // Navigate immediately, mark as read in background
                                if (evalId > 0) {
                                    onNavigate("evaluation", JSONObject().put("id", evalId))
                                }
                                if (token != null && isUnread) {
                                    coroutineScope.launch {
                                        try {
                                            Api.request(serverUrl, "/api/mobile/notifications/$id/read", "POST", null, token)
                                        } catch (_: Exception) {}
                                    }
                                }
                            }
                            .border(
                                width = 1.dp,
                                color = if (isUnread) MaterialTheme.colorScheme.primary.copy(alpha = 0.25f) else MaterialTheme.colorScheme.outline.copy(alpha = 0.12f),
                                shape = RoundedCornerShape(12.dp)
                            ),
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (isUnread) MaterialTheme.colorScheme.primary.copy(alpha = 0.03f) else MaterialTheme.colorScheme.surface
                        )
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(14.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            // Left Icon
                            Box(
                                modifier = Modifier
                                    .size(38.dp)
                                    .background(
                                        color = if (isUnread) MaterialTheme.colorScheme.primary.copy(alpha = 0.1f) else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.05f),
                                        shape = CircleShape
                                    ),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Assessment,
                                    contentDescription = null,
                                    tint = if (isUnread) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                                    modifier = Modifier.size(20.dp)
                                )
                            }

                            Spacer(modifier = Modifier.width(12.dp))

                            // Details
                            Column(modifier = Modifier.weight(1f)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        text = title,
                                        fontSize = 14.sp,
                                        fontWeight = if (isUnread) FontWeight.Bold else FontWeight.SemiBold,
                                        color = MaterialTheme.colorScheme.onBackground
                                    )
                                    if (isUnread) {
                                        Box(
                                            modifier = Modifier
                                                .size(8.dp)
                                                .background(MaterialTheme.colorScheme.primary, shape = CircleShape)
                                        )
                                    }
                                }
                                Spacer(modifier = Modifier.height(3.dp))
                                Text(
                                    text = body,
                                    fontSize = 12.sp,
                                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f),
                                    lineHeight = 16.sp
                                )
                                Spacer(modifier = Modifier.height(6.dp))
                                Text(
                                    text = dateStr,
                                    fontSize = 10.sp,
                                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.4f)
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
