package com.qa365.mobile

import androidx.compose.foundation.background
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import org.json.JSONArray
import org.json.JSONObject

// ═══════════════════════════════════════════════════════════════════
// DASHBOARD MODULE — Premium BI-grade dashboard
// ═══════════════════════════════════════════════════════════════════
@Composable
fun MainDashboardModule(data: JSONObject, onDetailSelected: (String, JSONObject) -> Unit) {
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

        // ── Hero Welcome Card with gradient ──
        val profileName = profile.optString("name", "Usuario")
        val avgScore = overview.optDouble("average_score", 0.0)

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = Color.Transparent),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        brush = Brush.linearGradient(
                            colors = listOf(Color(0xFF6366F1), Color(0xFF818CF8), Color(0xFFA78BFA))
                        ),
                        shape = RoundedCornerShape(20.dp)
                    )
                    .padding(24.dp)
            ) {
                Column {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.Top
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "¡Hola, $profileName! 👋",
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(
                                text = if (isAgent) "Vista de Asesor" else "Vista Ejecutiva",
                                fontSize = 13.sp,
                                color = Color.White.copy(alpha = 0.7f)
                            )
                        }
                        // Score Badge
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = String.format("%.1f%%", avgScore),
                                fontSize = 36.sp,
                                fontWeight = FontWeight.Black,
                                color = Color.White
                            )
                            Text(
                                text = "Nota Actual",
                                fontSize = 11.sp,
                                color = Color.White.copy(alpha = 0.6f)
                            )
                        }
                    }

                    // Agent league badge
                    if (league != null) {
                        Spacer(modifier = Modifier.height(12.dp))
                        Row(
                            modifier = Modifier
                                .clip(RoundedCornerShape(12.dp))
                                .background(Color.White.copy(alpha = 0.15f))
                                .padding(horizontal = 14.dp, vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text("🏆", fontSize = 16.sp)
                            Text(
                                text = league.optString("name", "Sin liga"),
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))
                    // Quick stats row
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

        // ── Metric Cards ──
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard(
                title = "Nota Promedio",
                value = String.format("%.1f%%", overview.optDouble("average_score", 0.0)),
                subtitle = "Periodo actual",
                color = Green,
                modifier = Modifier.weight(1f)
            )
            MetricCard(
                title = "Disputadas",
                value = summary.optString("disputed", "0"),
                subtitle = "En revisión",
                color = Rose,
                modifier = Modifier.weight(1f)
            )
        }

        Spacer(modifier = Modifier.height(20.dp))

        // ── Feedback Tracking ──
        SectionHeader(title = "Seguimiento de Feedback")
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                FeedbackStatRow("📊 Publicadas", feedbackSummary.optString("published", "0"))
                FeedbackStatRow("👁 Vistas", feedbackSummary.optString("viewed", "0"))
                FeedbackStatRow("✅ Aceptadas", feedbackSummary.optString("accepted", "0"))
                FeedbackStatRow("⚡ Disputadas", feedbackSummary.optString("disputed", "0"))
                FeedbackStatRow("⏳ Pendientes", feedbackSummary.optString("pending_response", "0"))
            }
        }

        // ── Charts ──
        val trendArray = data.optJSONArray("quality_trend")
        if (trendArray != null && trendArray.length() > 0) {
            Spacer(modifier = Modifier.height(24.dp))
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
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    TrendLineChart(points = trendPoints)
                }
            }
        }

        val defectsArray = data.optJSONArray("top_defects")
        if (defectsArray != null && defectsArray.length() > 0) {
            Spacer(modifier = Modifier.height(24.dp))
            SectionHeader(title = "Hallazgos Principales")

            val defectPoints = mutableListOf<ChartPoint>()
            for (i in 0 until defectsArray.length()) {
                val defect = defectsArray.optJSONObject(i) ?: continue
                val isCritical = defect.optBoolean("is_critical", false)
                defectPoints.add(
                    ChartPoint(
                        label = defect.optString("label", "Criterio"),
                        value = defect.optDouble("count", 0.0),
                        color = if (isCritical) Rose else Amber
                    )
                )
            }
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    TopDefectsBarChart(defects = defectPoints)
                }
            }
        }

        // ── Recent evaluations quick list ──
        val evaluationsArray = data.optJSONArray("evaluations")
        if (evaluationsArray != null && evaluationsArray.length() > 0) {
            Spacer(modifier = Modifier.height(24.dp))
            SectionHeader(title = "Últimas Evaluaciones")
            for (i in 0 until minOf(evaluationsArray.length(), 4)) {
                val eval = evaluationsArray.optJSONObject(i) ?: continue
                DetailCard(
                    title = eval.optString("campaign", "Sin campaña"),
                    scoreValue = eval.optString("score_label", "—"),
                    scoreColor = getScoreColor(eval.optDouble("score", -1.0)),
                    description = "${eval.optString("agent", "—")} | ${eval.optString("status_label", "—")}",
                    chips = listOf(
                        if (eval.optJSONObject("feedback_response")?.optBoolean("responded") == true) "✅ Respondido" else "⏳ Pendiente"
                    ),
                    onClick = { onDetailSelected("evaluation", eval) }
                )
            }
        }

        Spacer(modifier = Modifier.height(24.dp))
    }
}

@Composable
fun DashboardQuickStat(label: String, value: String, icon: ImageVector) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Icon(icon, contentDescription = null, tint = Color.White.copy(alpha = 0.7f), modifier = Modifier.size(18.dp))
        Spacer(modifier = Modifier.height(4.dp))
        Text(value, fontSize = 20.sp, fontWeight = FontWeight.Black, color = Color.White)
        Text(label, fontSize = 10.sp, color = Color.White.copy(alpha = 0.6f))
    }
}

@Composable
fun FeedbackStatRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(label, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.8f))
        Text(
            value,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )
    }
}

// ═══════════════════════════════════════════════════════════════════
// TRANSCRIPTS MODULE
// ═══════════════════════════════════════════════════════════════════
@Composable
fun TranscriptsModule(data: JSONObject, onDetailSelected: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("transcripts") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Transcripciones", "Carga, estado y resultado de audios.")

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard("Total", summary.optString("total", "0"), "Interacciones", Blue, Modifier.weight(1f))
            MetricCard("Procesando", summary.optString("processing", "0"), "En cola IA", Amber, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader("Últimas Transcripciones")

        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = item.optString("campaign", "Sin campaña"),
                scoreValue = item.optString("score_label", "Sin nota"),
                scoreColor = getScoreColor(item.optDouble("score", -1.0)),
                description = "${item.optString("agent", "Sin asesor")} | ${item.optString("file_name", "Interacción")}",
                chips = listOf(item.optString("transcription_status", "Pendiente"), item.optString("duration_label", "00:00")),
                onClick = { onDetailSelected("transcript", item) }
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// EVALUATIONS MODULE
// ═══════════════════════════════════════════════════════════════════
@Composable
fun EvaluationsModule(data: JSONObject, onDetailSelected: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("evaluations") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Evaluaciones", "Notas, revisión y respuesta del asesor.")

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard("Total", summary.optString("total", "0"), "Visibles", Blue, Modifier.weight(1f))
            MetricCard("Pendientes", summary.optString("pending_monitor", "0"), "Por revisar", Amber, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader("Últimas Evaluaciones")

        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = item.optString("campaign", "Sin campaña"),
                scoreValue = item.optString("score_label", "0%"),
                scoreColor = getScoreColor(item.optDouble("score", -1.0)),
                description = "${item.optString("agent", "Sin asesor")} | ${item.optString("status_label", "Sin estado")}",
                chips = listOf(if (item.optJSONObject("feedback_response")?.optBoolean("responded") == true) "✅ Respondido" else "⏳ Pendiente"),
                onClick = { onDetailSelected("evaluation", item) }
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// CAMPAIGNS MODULE
// ═══════════════════════════════════════════════════════════════════
@Composable
fun CampaignsModule(data: JSONObject, onDetailSelected: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("campaigns") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val items = module.optJSONArray("items") ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Campañas", "Avance por operación.")

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            MetricCard("Activas", summary.optString("active", "0"), "En operación", Green, Modifier.weight(1f))
            MetricCard("Total", summary.optString("total", "0"), "Asignadas", Blue, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeader("Campañas Visibles")

        for (i in 0 until items.length()) {
            val item = items.optJSONObject(i) ?: continue
            DetailCard(
                title = item.optString("name", "Campaña"),
                scoreValue = item.optString("score_label", "0%"),
                scoreColor = getScoreColor(item.optDouble("average_score", 0.0)),
                description = "${item.optString("evaluations", "0")} evals | ${item.optString("interactions", "0")} interacciones",
                chips = listOf(if (item.optBoolean("active")) "🟢 Activa" else "⚫ Inactiva"),
                onClick = { onDetailSelected("campaign", item) }
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// PROFILE MODULE — Premium profile view
// ═══════════════════════════════════════════════════════════════════
@Composable
fun ProfileModule(data: JSONObject, onLogout: () -> Unit, onRefresh: () -> Unit) {
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
        // ── Profile Header with gradient background ──
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(Color(0xFF6366F1), Color(0xFF818CF8), MaterialTheme.colorScheme.background)
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
                                        colors = listOf(Color(0xFF4F46E5), Color(0xFF6366F1))
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
                                fontSize = 38.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                Text(
                    text = fullName,
                    fontSize = 24.sp,
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
                        .clip(RoundedCornerShape(20.dp))
                        .background(Color.White.copy(alpha = 0.2f))
                        .padding(horizontal = 16.dp, vertical = 6.dp)
                ) {
                    Text(
                        text = mainRole.uppercase(),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                        letterSpacing = 1.sp
                    )
                }
            }
        }

        // ── Info Cards ──
        Column(modifier = Modifier.padding(horizontal = 16.dp)) {
            // Card 1: Account
            ProfileInfoCard(
                title = "Datos de la Cuenta",
                icon = Icons.Default.AccountCircle,
                items = listOf(
                    "Email Corporativo" to email.ifEmpty { "—" },
                    "Rol del Sistema" to (if (profile.optJSONArray("roles") != null && profile.optJSONArray("roles")!!.length() > 0) profile.optJSONArray("roles")!!.optString(0, "Usuario") else "Usuario"),
                    "Usuario" to username.ifEmpty { "—" }
                )
            )

            // Card 2: Contact
            ProfileInfoCard(
                title = "Información de Contacto",
                icon = Icons.Default.Phone,
                items = listOf(
                    "Teléfono Corporativo" to companyPhone.ifEmpty { "No registrado" },
                    "Teléfono Personal" to personalPhone.ifEmpty { "No registrado" },
                    "Email Personal" to personalEmail.ifEmpty { "No registrado" }
                )
            )

            // Card 3: Personal + Location
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

            Spacer(modifier = Modifier.height(24.dp))

            // Action Buttons
            Button(
                onClick = onRefresh,
                modifier = Modifier.fillMaxWidth().height(52.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary)
            ) {
                Icon(Icons.Default.Sync, contentDescription = null, modifier = Modifier.size(20.dp))
                Spacer(modifier = Modifier.width(8.dp))
                Text("Sincronizar Datos", fontSize = 15.sp, fontWeight = FontWeight.Bold)
            }

            Spacer(modifier = Modifier.height(12.dp))

            OutlinedButton(
                onClick = onLogout,
                modifier = Modifier.fillMaxWidth().height(52.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error)
            ) {
                Icon(Icons.Default.Logout, contentDescription = null, modifier = Modifier.size(20.dp))
                Spacer(modifier = Modifier.width(8.dp))
                Text("Cerrar Sesión", fontSize = 15.sp, fontWeight = FontWeight.Bold)
            }

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

@Composable
fun ProfileInfoCard(title: String, icon: ImageVector, items: List<Pair<String, String>>) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    icon,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(20.dp)
                )
                Text(
                    title,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
            }
            Spacer(modifier = Modifier.height(12.dp))
            items.forEachIndexed { index, (label, value) ->
                Row(
                    modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(label, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f))
                    Text(
                        value,
                        fontSize = 14.sp,
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
