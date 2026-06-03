package com.qa365.mobile

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import org.json.JSONArray
import org.json.JSONObject

// ═══════════════════════════════════════════════════════════════════
// DASHBOARD MODULE — Web-aligned BI Dashboard
// ═══════════════════════════════════════════════════════════════════
@Composable
fun MainDashboardModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit) {
    val overview = data.optJSONObject("overview") ?: JSONObject()
    val summary = data.optJSONObject("summary") ?: JSONObject()
    val modules = data.optJSONObject("modules") ?: JSONObject()
    val feedbackModule = modules.optJSONObject("feedback") ?: JSONObject()
    val feedbackSummary = feedbackModule.optJSONObject("summary") ?: JSONObject()
    val profile = data.optJSONObject("profile") ?: JSONObject()
    val isAgent = profile.optString("primary_view", "executive") == "agent"
    val agentData = data.optJSONObject("agent")
    val league = agentData?.optJSONObject("league")

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp)
    ) {
        Spacer(modifier = Modifier.height(8.dp))

        // Hero Welcome Card matching web colors
        val profileName = profile.optString("name", "Usuario")
        val avgScore = overview.optDouble("average_score", 0.0)

        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(
                    width = 1.dp,
                    color = MaterialTheme.colorScheme.outline,
                    shape = RoundedCornerShape(16.dp)
                ),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        brush = Brush.linearGradient(
                            colors = listOf(
                                MaterialTheme.colorScheme.primary,
                                MaterialTheme.colorScheme.secondary
                            )
                        ),
                        shape = RoundedCornerShape(16.dp)
                    )
                    .padding(20.dp)
            ) {
                Column {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "Hola, $profileName",
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(
                                text = if (isAgent) "Vista de Asesor" else "Vista Ejecutiva",
                                fontSize = 12.sp,
                                color = Color.White.copy(alpha = 0.7f)
                            )
                        }
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = String.format("%.1f%%", avgScore),
                                fontSize = 32.sp,
                                fontWeight = FontWeight.Black,
                                color = Color.White
                            )
                            Text(
                                text = "Nota Promedio",
                                fontSize = 10.sp,
                                color = Color.White.copy(alpha = 0.6f)
                            )
                        }
                    }

                    // Agent league badge (no emojis, uses Star icon)
                    if (league != null) {
                        Spacer(modifier = Modifier.height(12.dp))
                        Row(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(Color.White.copy(alpha = 0.15f))
                                .padding(horizontal = 12.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.Star,
                                contentDescription = null,
                                tint = Color.White,
                                modifier = Modifier.size(14.dp)
                            )
                            Text(
                                text = league.optString("name", "Sin liga"),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Quick stats
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        DashboardQuickStat("Evaluaciones", overview.optString("total_evaluations", "0"), Icons.Default.Assessment)
                        DashboardQuickStat("Alertas", summary.optString("open_alerts", "0"), Icons.Default.Warning)
                        DashboardQuickStat("Críticas", summary.optString("critical_scores", "0"), Icons.Default.Error)
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(20.dp))

        // System Modules Grid Section
        SectionHeader(title = "Módulos de Gestión", subtitle = "Accesos rápidos de la operación")
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            ModuleShortcutCard(
                title = "Fichas de Calidad",
                icon = Icons.Default.Assignment,
                modifier = Modifier.weight(1f),
                onClick = { onNavigate("quality_form_list", data) }
            )
            ModuleShortcutCard(
                title = "Reportes IA",
                icon = Icons.Default.AutoAwesome,
                modifier = Modifier.weight(1f),
                onClick = { onNavigate("insight_list", data) }
            )
        }

        Spacer(modifier = Modifier.height(20.dp))

        // Metric Cards
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard(
                title = "Nota Promedio",
                value = String.format("%.1f%%", overview.optDouble("average_score", 0.0)),
                subtitle = "Periodo actual",
                color = getScoreColor(overview.optDouble("average_score", 0.0)),
                modifier = Modifier.weight(1f)
            )
            MetricCard(
                title = "Disputadas",
                value = summary.optString("disputed", "0"),
                subtitle = "En revisión",
                color = getScoreColor(0.0), // Red
                modifier = Modifier.weight(1f)
            )
        }

        Spacer(modifier = Modifier.height(20.dp))

        // Feedback Tracking
        SectionHeader(title = "Seguimiento de Feedback")
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(
                    width = 1.dp,
                    color = MaterialTheme.colorScheme.outline,
                    shape = RoundedCornerShape(12.dp)
                ),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                FeedbackStatRow("Publicadas", feedbackSummary.optString("published", "0"), Icons.Default.Publish)
                FeedbackStatRow("Vistas", feedbackSummary.optString("viewed", "0"), Icons.Default.Visibility)
                FeedbackStatRow("Aceptadas", feedbackSummary.optString("accepted", "0"), Icons.Default.CheckCircle)
                FeedbackStatRow("Disputadas", feedbackSummary.optString("disputed", "0"), Icons.Default.Warning)
                FeedbackStatRow("Pendientes", feedbackSummary.optString("pending_response", "0"), Icons.Default.Schedule)
            }
        }

        // Charts
        val trendArray = data.optJSONArray("quality_trend")
        if (trendArray != null && trendArray.length() > 0) {
            Spacer(modifier = Modifier.height(20.dp))
            SectionHeader(title = "Tendencia de Calidad")

            val trendPoints = mutableListOf<ChartPoint>()
            for (i in 0 until trendArray.length()) {
                val point = trendArray.optJSONObject(i) ?: continue
                val score = point.optDouble("avg_score", 0.0)
                trendPoints.add(
                    ChartPoint(
                        label = point.optString("label", "Día").takeLast(5),
                        value = score,
                        color = getScoreColor(score)
                    )
                )
            }
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .border(
                        width = 1.dp,
                        color = MaterialTheme.colorScheme.outline,
                        shape = RoundedCornerShape(12.dp)
                    ),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    TrendLineChart(points = trendPoints)
                }
            }
        }

        val defectsArray = data.optJSONArray("top_defects")
        if (defectsArray != null && defectsArray.length() > 0) {
            Spacer(modifier = Modifier.height(20.dp))
            SectionHeader(title = "Hallazgos Principales")

            val defectPoints = mutableListOf<ChartPoint>()
            for (i in 0 until defectsArray.length()) {
                val defect = defectsArray.optJSONObject(i) ?: continue
                val isCritical = defect.optBoolean("is_critical", false)
                defectPoints.add(
                    ChartPoint(
                        label = defect.optString("label", "Criterio"),
                        value = defect.optDouble("count", 0.0),
                        color = if (isCritical) Color(0xFFEF4444) else Color(0xFFF59E0B)
                    )
                )
            }
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .border(
                        width = 1.dp,
                        color = MaterialTheme.colorScheme.outline,
                        shape = RoundedCornerShape(12.dp)
                    ),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    TopDefectsBarChart(defects = defectPoints)
                }
            }
        }

        // Recent evaluations list
        val evaluationsArray = data.optJSONArray("evaluations")
        if (evaluationsArray != null && evaluationsArray.length() > 0) {
            Spacer(modifier = Modifier.height(20.dp))
            SectionHeader(title = "Últimas Evaluaciones")
            for (i in 0 until minOf(evaluationsArray.length(), 4)) {
                val eval = evaluationsArray.optJSONObject(i) ?: continue
                DetailCard(
                    title = eval.optString("campaign", "Sin campaña"),
                    scoreValue = eval.optString("score_label", "—"),
                    scoreColor = getScoreColor(eval.optDouble("score", -1.0)),
                    description = "${eval.optString("agent", "—")} | ${eval.optString("status_label", "—")}",
                    chips = listOf(
                        if (eval.optJSONObject("feedback_response")?.optBoolean("responded") == true) "Respondido" else "Pendiente"
                    ),
                    onClick = { onNavigate("evaluation", eval) }
                )
            }
        }

        Spacer(modifier = Modifier.height(24.dp))
    }
}

@Composable
fun DashboardQuickStat(label: String, value: String, icon: ImageVector) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = Color.White.copy(alpha = 0.7f),
            modifier = Modifier.size(18.dp)
        )
        Spacer(modifier = Modifier.height(4.dp))
        Text(value, fontSize = 20.sp, fontWeight = FontWeight.Black, color = Color.White)
        Text(label, fontSize = 10.sp, color = Color.White.copy(alpha = 0.6f))
    }
}

@Composable
fun ModuleShortcutCard(title: String, icon: ImageVector, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.outline,
                shape = RoundedCornerShape(12.dp)
            )
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(RoundedCornerShape(8.dp))
                    .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.1f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(18.dp)
                )
            }
            Text(
                text = title,
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@Composable
fun FeedbackStatRow(label: String, value: String, icon: ImageVector) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                modifier = Modifier.size(16.dp)
            )
            Text(
                text = label,
                fontSize = 13.sp,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.8f)
            )
        }
        Text(
            text = value,
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )
    }
}

// ═══════════════════════════════════════════════════════════════════
// TRANSCRIPTS MODULE
// ═══════════════════════════════════════════════════════════════════
@Composable
fun TranscriptsModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("transcripts") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Transcripciones", "Carga, estado y resultado de audios")

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard("Total", summary.optString("total", "0"), "Interacciones", MaterialTheme.colorScheme.primary, Modifier.weight(1f))
            MetricCard("Procesando", summary.optString("processing", "0"), "En cola IA", Color(0xFFF59E0B), Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(20.dp))
        SectionHeader("Últimas Transcripciones")

        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = item.optString("campaign", "Sin campaña"),
                scoreValue = item.optString("score_label", "Sin nota"),
                scoreColor = getScoreColor(item.optDouble("score", -1.0)),
                description = "${item.optString("agent", "Sin asesor")} | ${item.optString("file_name", "Interacción")}",
                chips = listOf(
                    item.optString("transcription_status", "Pendiente"),
                    item.optString("duration_label", "00:00")
                ),
                onClick = { onNavigate("transcript", item) }
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// EVALUATIONS MODULE
// ═══════════════════════════════════════════════════════════════════
@Composable
fun EvaluationsModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("evaluations") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Evaluaciones", "Notas, revisión y respuesta del asesor")

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard("Total", summary.optString("total", "0"), "Visibles", MaterialTheme.colorScheme.primary, Modifier.weight(1f))
            MetricCard("Pendientes", summary.optString("pending_monitor", "0"), "Por revisar", Color(0xFFF59E0B), Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(20.dp))
        SectionHeader("Últimas Evaluaciones")

        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = item.optString("campaign", "Sin campaña"),
                scoreValue = item.optString("score_label", "0%"),
                scoreColor = getScoreColor(item.optDouble("score", -1.0)),
                description = "${item.optString("agent", "Sin asesor")} | ${item.optString("status_label", "Sin estado")}",
                chips = listOf(
                    if (item.optJSONObject("feedback_response")?.optBoolean("responded") == true) "Respondido" else "Pendiente"
                ),
                onClick = { onNavigate("evaluation", item) }
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// CAMPAIGNS MODULE
// ═══════════════════════════════════════════════════════════════════
@Composable
fun CampaignsModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("campaigns") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Campañas", "Avance por operación")

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard("Activas", summary.optString("active", "0"), "En operación", Color(0xFF10B981), Modifier.weight(1f))
            MetricCard("Total", summary.optString("total", "0"), "Asignadas", MaterialTheme.colorScheme.primary, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(20.dp))
        SectionHeader("Campañas Visibles")

        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            val isActive = item.optBoolean("active")
            DetailCard(
                title = item.optString("name", "Campaña"),
                scoreValue = item.optString("score_label", "0%"),
                scoreColor = getScoreColor(item.optDouble("average_score", 0.0)),
                description = "${item.optString("evaluations", "0")} evals | ${item.optString("interactions", "0")} interacciones",
                chips = listOf(if (isActive) "Activa" else "Inactiva"),
                onClick = { onNavigate("campaign", item) }
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// PROFILE MODULE — Premium profile view
// ═══════════════════════════════════════════════════════════════════
@Composable
fun ProfileModule(data: JSONObject, themeMode: String, onThemeChanged: (String) -> Unit, onLogout: () -> Unit, onRefresh: () -> Unit) {
    val profile = data.optJSONObject("profile") ?: JSONObject()
    val name = profile.optString("name", "Usuario")
    val paternal = profile.optString("paternal_surname", "")
    val maternal = profile.optString("maternal_surname", "")
    val fullName = profile.optString("full_name", "$name $paternal $maternal".trim()).ifEmpty { "Usuario QA365" }
    val username = profile.optString("username", "")
    val email = profile.optString("email", "")
    val personalEmail = profile.optString("personal_email", "")
    val personalPhone = profile.optString("personal_phone", "")
    val companyPhone = profile.optString("company_phone", "")
    val birthdate = profile.optString("birthdate", "")
    val gender = profile.optString("gender", "")
    val address = profile.optString("address", "")
    val department = profile.optString("department", "")
    val province = profile.optString("province", "")
    val district = profile.optString("district", "")
    val avatarUrl = profile.optString("avatar_url", "")

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
    ) {
        // Profile Header with gradient background matching brand
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(
                            MaterialTheme.colorScheme.primary,
                            MaterialTheme.colorScheme.secondary,
                            MaterialTheme.colorScheme.background
                        )
                    )
                )
                .padding(top = 32.dp, bottom = 24.dp),
            contentAlignment = Alignment.Center
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                // Premium Avatar
                val hasCustomAvatar = avatarUrl.isNotEmpty() && !avatarUrl.contains("ui-avatars.com")
                Box(
                    modifier = Modifier
                        .size(120.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.2f)),
                    contentAlignment = Alignment.Center
                ) {
                    Box(
                        modifier = Modifier
                            .size(112.dp)
                            .clip(CircleShape)
                            .then(
                                if (hasCustomAvatar) Modifier.background(Color.Transparent)
                                else Modifier.background(
                                    Brush.linearGradient(
                                        colors = listOf(
                                            MaterialTheme.colorScheme.primary,
                                            MaterialTheme.colorScheme.secondary
                                        )
                                    )
                                )
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        if (hasCustomAvatar) {
                            AsyncImage(
                                model = avatarUrl,
                                contentDescription = "Foto de perfil",
                                modifier = Modifier.size(112.dp).clip(CircleShape),
                                contentScale = ContentScale.Crop
                            )
                        } else {
                            val initials = if (fullName.isNotEmpty()) {
                                val parts = fullName.split(" ").filter { it.isNotEmpty() }
                                if (parts.size >= 2) "${parts[0][0]}${parts[1][0]}".uppercase()
                                else if (parts.size == 1) "${parts[0][0]}".uppercase()
                                else "QA"
                            } else "QA"
                            Text(
                                text = initials,
                                fontSize = 36.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                Text(
                    text = fullName,
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )

                if (username.isNotEmpty()) {
                    Spacer(modifier = Modifier.height(2.dp))
                    Text(
                        text = "@$username",
                        fontSize = 14.sp,
                        color = Color.White.copy(alpha = 0.7f)
                    )
                }

                Spacer(modifier = Modifier.height(12.dp))

                val rolesArray = profile.optJSONArray("roles")
                val mainRole = if (rolesArray != null && rolesArray.length() > 0) rolesArray.optString(0, "Usuario") else "Usuario"
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(Color.White.copy(alpha = 0.2f))
                        .padding(horizontal = 14.dp, vertical = 5.dp)
                ) {
                    Text(
                        text = mainRole.uppercase(),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                        letterSpacing = 1.sp
                    )
                }
            }
        }

        // Info Cards
        Column(modifier = Modifier.padding(horizontal = 16.dp)) {
            ProfileInfoCard(
                title = "Datos de la Cuenta",
                icon = Icons.Default.AccountCircle,
                items = listOf(
                    "Email Corporativo" to email.ifEmpty { "—" },
                    "Rol del Sistema" to (if (profile.optJSONArray("roles") != null && profile.optJSONArray("roles")!!.length() > 0) profile.optJSONArray("roles")!!.optString(0, "Usuario") else "Usuario"),
                    "Usuario" to username.ifEmpty { "—" }
                )
            )

            ProfileInfoCard(
                title = "Información de Contacto",
                icon = Icons.Default.Phone,
                items = listOf(
                    "Teléfono Corporativo" to companyPhone.ifEmpty { "No registrado" },
                    "Teléfono Personal" to personalPhone.ifEmpty { "No registrado" },
                    "Email Personal" to personalEmail.ifEmpty { "No registrado" }
                )
            )

            val genderLabel = when (gender.lowercase()) {
                "male", "m" -> "Masculino"
                "female", "f" -> "Femenino"
                "" -> "No registrado"
                else -> gender
            }
            val location = listOf(district, province, department).filter { it.isNotEmpty() }.joinToString(", ")

            ProfileInfoCard(
                title = "Información Personal",
                icon = Icons.Default.Person,
                items = listOf(
                    "Cumpleaños" to birthdate.ifEmpty { "No registrado" },
                    "Género" to genderLabel,
                    "Dirección" to address.ifEmpty { "No registrado" },
                    "Ubicación" to location.ifEmpty { "No registrado" }
                )
            )

            Spacer(modifier = Modifier.height(6.dp))

            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 6.dp)
                    .border(
                        width = 1.dp,
                        color = MaterialTheme.colorScheme.outline,
                        shape = RoundedCornerShape(12.dp)
                    ),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Palette,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.primary,
                            modifier = Modifier.size(18.dp)
                        )
                        Text(
                            text = "Tema de la Aplicación",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.primary
                        )
                    }
                    Spacer(modifier = Modifier.height(12.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        val modes = listOf("light" to "Claro", "dark" to "Oscuro", "system" to "Sistema")
                        modes.forEach { (modeKey, modeName) ->
                            val selected = themeMode == modeKey
                            val containerColor = if (selected) MaterialTheme.colorScheme.primary else Color.Transparent
                            val contentColor = if (selected) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurface
                            val borderStroke = if (selected) null else BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
                            
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .height(36.dp)
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(containerColor)
                                    .then(if (borderStroke != null) Modifier.border(borderStroke, RoundedCornerShape(8.dp)) else Modifier)
                                    .clickable { onThemeChanged(modeKey) },
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = modeName,
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Medium,
                                    color = contentColor
                                )
                            }
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(20.dp))

            Button(
                onClick = onRefresh,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(48.dp),
                shape = RoundedCornerShape(10.dp),
                colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary)
            ) {
                Icon(Icons.Default.Sync, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(modifier = Modifier.width(6.dp))
                Text("Sincronizar Datos", fontSize = 14.sp, fontWeight = FontWeight.Bold)
            }

            Spacer(modifier = Modifier.height(10.dp))

            OutlinedButton(
                onClick = onLogout,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(48.dp),
                shape = RoundedCornerShape(10.dp),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.error.copy(alpha = 0.5f))
            ) {
                Icon(Icons.Default.Logout, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(modifier = Modifier.width(6.dp))
                Text("Cerrar Sesión", fontSize = 14.sp, fontWeight = FontWeight.Bold)
            }

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

@Composable
fun ProfileInfoCard(title: String, icon: ImageVector, items: List<Pair<String, String>>) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 6.dp)
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.outline,
                shape = RoundedCornerShape(12.dp)
            ),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(18.dp)
                )
                Text(
                    text = title,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
            }
            Spacer(modifier = Modifier.height(12.dp))
            items.forEachIndexed { index, (label, value) ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 5.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(label, fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f))
                    Text(
                        text = value,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurface,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.widthIn(max = 180.dp)
                    )
                }
                if (index < items.size - 1) {
                    Divider(color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.05f))
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// [NEW] QUALITY FORMS MODULE
// ═══════════════════════════════════════════════════════════════════
@Composable
fun QualityFormsModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("quality_forms") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Fichas de Calidad", "Formatos y criterios de evaluación activos")

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard("Total Fichas", summary.optString("total", "0"), "Registradas", MaterialTheme.colorScheme.primary, Modifier.weight(1f))
            MetricCard("Con Contexto", summary.optString("with_context", "0"), "Guiadas por IA", Color(0xFF10B981), Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(20.dp))
        SectionHeader("Formatos Disponibles")

        if (items.length() == 0) {
            Text(
                text = "No hay fichas de calidad asignadas.",
                modifier = Modifier.fillMaxWidth().padding(vertical = 32.dp),
                textAlign = TextAlign.Center,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
            )
        }

        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            val hasContext = item.optBoolean("has_context")
            DetailCard(
                title = item.optString("name", "Formato de Calidad"),
                scoreValue = "V${item.optInt("versions", 1)}",
                scoreColor = MaterialTheme.colorScheme.primary,
                description = "Campaña: ${item.optString("campaign", "General")} | Estado: ${item.optString("latest_status", "Activa")}",
                chips = if (hasContext) listOf("Contexto IA") else emptyList(),
                onClick = { onNavigate("quality_form", item) }
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// [NEW] INSIGHTS MODULE
// ═══════════════════════════════════════════════════════════════════
@Composable
fun InsightsModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("insights") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Insights de IA", "Reportes y análisis automáticos de calidad")

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard("Total Reportes", summary.optString("total", "0"), "Generados", MaterialTheme.colorScheme.primary, Modifier.weight(1f))
            MetricCard("Últimos 30 días", summary.optString("last_30_days", "0"), "Frecuencia mensual", Color(0xFF10B981), Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(20.dp))
        SectionHeader("Reportes de Insights")

        if (items.length() == 0) {
            Text(
                text = "No se han generado reportes de insights aún.",
                modifier = Modifier.fillMaxWidth().padding(vertical = 32.dp),
                textAlign = TextAlign.Center,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
            )
        }

        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = "Insight ${item.optString("type", "Campaña").replaceFirstChar { it.uppercase() }}",
                scoreValue = "${item.optInt("findings", 0)} hallazgos",
                scoreColor = Color(0xFFEF4444),
                description = "Campaña: ${item.optString("campaign", "—")} | Rango: ${item.optString("date_range", "—")}",
                chips = listOf(item.optString("summary", "Ver detalles")),
                onClick = { onNavigate("insight", item) }
            )
        }
    }
}
