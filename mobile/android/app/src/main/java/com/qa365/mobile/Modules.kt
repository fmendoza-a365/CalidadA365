package com.qa365.mobile

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
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
    val transcriptSummary = modules.optJSONObject("transcripts")?.optJSONObject("summary") ?: JSONObject()
    val evaluationSummary = modules.optJSONObject("evaluations")?.optJSONObject("summary") ?: JSONObject()
    val formSummary = modules.optJSONObject("quality_forms")?.optJSONObject("summary") ?: JSONObject()
    val insightSummary = modules.optJSONObject("insights")?.optJSONObject("summary") ?: JSONObject()
    val profile = data.optJSONObject("profile") ?: JSONObject()
    val isAgent = profile.optString("primary_view", "executive") == "agent"
    val agentData = data.optJSONObject("agent")
    val league = agentData?.optJSONObject("league")

    val alertsArray = data.optJSONArray("alerts")
    val evaluationsArray = data.optJSONArray("evaluations")
    val campaignsArray = data.optJSONArray("campaigns")
    val rankingArray = data.optJSONArray("ranking")
    val trendArray = data.optJSONArray("quality_trend")
    val defectsArray = data.optJSONArray("top_defects")

    var selectedSubTab by remember { mutableStateOf("resumen") }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp)
    ) {
        Spacer(modifier = Modifier.height(8.dp))

        // Horizontal scrollable tab selector
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            val tabs = listOf(
                Pair("resumen", Pair("Resumen", Icons.Default.Assessment)),
                Pair("campaigns", Pair("Campañas", Icons.Default.Campaign)),
                Pair("feedback", Pair("Feedback", Icons.Default.Chat)),
                Pair("ranking", Pair("Ranking", Icons.Default.Star)),
                Pair("alerts", Pair("Alertas", Icons.Default.Warning))
            )
            tabs.forEach { (id, info) ->
                val (label, icon) = info
                val isSelected = selectedSubTab == id
                val containerColor = if (isSelected) {
                    MaterialTheme.colorScheme.primary
                } else {
                    MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
                }
                val contentColor = if (isSelected) {
                    Color.White
                } else {
                    MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                }
                val borderStroke = if (isSelected) {
                    null
                } else {
                    BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f))
                }

                Box(
                    modifier = Modifier
                        .height(38.dp)
                        .clip(RoundedCornerShape(20.dp))
                        .background(containerColor)
                        .then(
                            if (borderStroke != null) {
                                Modifier.border(borderStroke, RoundedCornerShape(20.dp))
                            } else {
                                Modifier
                            }
                        )
                        .clickable { selectedSubTab = id }
                        .padding(horizontal = 14.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Icon(
                            imageVector = icon,
                            contentDescription = label,
                            modifier = Modifier.size(16.dp),
                            tint = contentColor
                        )
                        Text(
                            text = label,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = contentColor
                        )
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Render tab contents
        when (selectedSubTab) {
            "resumen" -> {
                DashboardExecutiveHero(
                    profile = profile,
                    isAgent = isAgent,
                    overview = overview,
                    summary = summary,
                    feedbackSummary = feedbackSummary,
                    transcriptSummary = transcriptSummary,
                    league = league
                )

                Spacer(modifier = Modifier.height(16.dp))

                DashboardKpiGrid(
                    overview = overview,
                    summary = summary,
                    feedbackSummary = feedbackSummary,
                    transcriptSummary = transcriptSummary
                )

                Spacer(modifier = Modifier.height(18.dp))

                DashboardOperationalPanel(
                    feedbackSummary = feedbackSummary,
                    transcriptSummary = transcriptSummary,
                    evaluationSummary = evaluationSummary
                )

                if (trendArray != null && trendArray.length() > 0) {
                    Spacer(modifier = Modifier.height(18.dp))
                    SectionHeader(title = "Tendencia de calidad", subtitle = "Últimos días con evaluaciones disponibles")

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

                if (campaignsArray != null && campaignsArray.length() > 0) {
                    Spacer(modifier = Modifier.height(18.dp))
                    DashboardCampaignSnapshot(campaignsArray)
                }

                Spacer(modifier = Modifier.height(18.dp))
                DashboardSignalPanel(
                    isAgent = isAgent,
                    overview = overview,
                    summary = summary,
                    feedbackSummary = feedbackSummary,
                    transcriptSummary = transcriptSummary,
                    defectsArray = defectsArray
                )

                Spacer(modifier = Modifier.height(18.dp))
                DashboardModuleGrid(
                    transcriptSummary = transcriptSummary,
                    evaluationSummary = evaluationSummary,
                    formSummary = formSummary,
                    insightSummary = insightSummary,
                    feedbackSummary = feedbackSummary,
                    onNavigate = onNavigate,
                    data = data
                )

                if (alertsArray != null && alertsArray.length() > 0) {
                    Spacer(modifier = Modifier.height(18.dp))
                    SectionHeader(title = "Alertas recientes", subtitle = "Riesgos que requieren atención")
                    for (i in 0 until minOf(alertsArray.length(), 3)) {
                        val alert = alertsArray.optJSONObject(i) ?: continue
                        AlertPreviewCard(alert = alert, evaluationsArray = evaluationsArray, onNavigate = onNavigate)
                    }
                }

                if (evaluationsArray != null && evaluationsArray.length() > 0) {
                    Spacer(modifier = Modifier.height(18.dp))
                    SectionHeader(title = if (isAgent) "Mis últimas evaluaciones" else "Últimas evaluaciones")
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
            }
            "campaigns" -> {
                if (campaignsArray != null && campaignsArray.length() > 0) {
                    SectionHeader(title = "Desempeño por Campaña", subtitle = "Promedios de calidad por operación")
                    Spacer(modifier = Modifier.height(8.dp))
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp)),
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
                    ) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            for (i in 0 until campaignsArray.length()) {
                                val camp = campaignsArray.optJSONObject(i) ?: continue
                                val name = camp.optString("label", "Campaña")
                                val avg = camp.optDouble("avg_score", 0.0)
                                val count = camp.optInt("count", 0)
                                
                                ProgressLine(
                                    label = "$name ($count ev.)",
                                    score = avg,
                                    color = getScoreColor(avg)
                                )
                            }
                        }
                    }
                } else {
                    Box(modifier = Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) {
                        Text("No hay datos de campañas disponibles", color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
                    }
                }

                if (defectsArray != null && defectsArray.length() > 0) {
                    Spacer(modifier = Modifier.height(20.dp))
                    SectionHeader(title = "Hallazgos Principales", subtitle = "Criterios con menor adherencia")

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
            }
            "feedback" -> {
                SectionHeader(title = "Seguimiento de Feedback", subtitle = "Estado de respuestas y disputas")
                Spacer(modifier = Modifier.height(8.dp))
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
            }
            "ranking" -> {
                if (rankingArray != null && rankingArray.length() > 0) {
                    SectionHeader(title = "Competencia Mensual", subtitle = "Clasificación de asesores por calidad")
                    Spacer(modifier = Modifier.height(8.dp))
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
                            val count = rankingArray.length()
                            for (i in 0 until count) {
                                val rItem = rankingArray.optJSONObject(i) ?: continue
                                val rName = rItem.optString("label", "Asesor")
                                val rScore = rItem.optString("score_label", "0.0%")
                                val rLevel = rItem.optString("level", "Plata")
                                
                                RankingRow(
                                    name = rName,
                                    scoreLabel = rScore,
                                    level = rLevel,
                                    position = i + 1
                                )
                                
                                if (i < count - 1) {
                                    HorizontalDivider(
                                        modifier = Modifier.padding(vertical = 12.dp),
                                        color = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
                                    )
                                }
                            }
                        }
                    }
                } else {
                    Box(modifier = Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) {
                        Text("No hay datos de ranking disponibles", color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
                    }
                }
            }
            "alerts" -> {
                if (alertsArray != null && alertsArray.length() > 0) {
                    SectionHeader(title = "Alertas Operativas", subtitle = "Incidencias críticas detectadas")
                    Spacer(modifier = Modifier.height(8.dp))
                    Column(
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        for (i in 0 until alertsArray.length()) {
                            val alert = alertsArray.optJSONObject(i) ?: continue
                            val title = alert.optString("title", "Alerta")
                            val desc = alert.optString("description", "")
                            val severity = alert.optString("severity", "info")
                            val created = alert.optString("created_at", "").take(10)
                            val evalId = alert.optInt("evaluation_id", -1)
                            
                            val fullEval = if (evalId > 0 && evaluationsArray != null) {
                                var found: org.json.JSONObject? = null
                                for (idx in 0 until evaluationsArray.length()) {
                                    val ev = evaluationsArray.optJSONObject(idx)
                                    if (ev != null && ev.optInt("id") == evalId) {
                                        found = ev
                                        break
                                    }
                                }
                                found
                            } else null
                            
                            AlertRowCard(
                                title = title,
                                description = desc,
                                severity = severity,
                                dateLabel = created,
                                onClick = {
                                    if (fullEval != null) {
                                        onNavigate("evaluation", fullEval)
                                    }
                                }
                            )
                        }
                    }
                } else {
                    Box(modifier = Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) {
                        Text("No hay alertas operativas críticas", color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(24.dp))
    }
}

@Composable
private fun DashboardExecutiveHero(
    profile: JSONObject,
    isAgent: Boolean,
    overview: JSONObject,
    summary: JSONObject,
    feedbackSummary: JSONObject,
    transcriptSummary: JSONObject,
    league: JSONObject?
) {
    val avgScore = overview.optDouble("average_score", 0.0)
    val scoreColor = getScoreColor(avgScore)
    val profileName = profile.optString("name", "Usuario")
    val published = feedbackSummary.optDouble("published", 0.0).coerceAtLeast(0.0)
    val responded = feedbackSummary.optDouble("responded", 0.0).coerceAtLeast(0.0)
    val responsePct = if (published > 0) (responded / published * 100.0) else 0.0
    val statusText = when {
        avgScore < 70.0 -> "Riesgo alto"
        avgScore < 85.0 -> "En seguimiento"
        else -> "Operación saludable"
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(
                width = 1.dp,
                color = scoreColor.copy(alpha = 0.24f),
                shape = RoundedCornerShape(22.dp)
            ),
        shape = RoundedCornerShape(22.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(18.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    LabelChip(
                        text = if (isAgent) "Vista de asesor" else "Vista ejecutiva",
                        color = MaterialTheme.colorScheme.primary,
                        icon = if (isAgent) Icons.Default.Person else Icons.Default.Business
                    )
                    Spacer(modifier = Modifier.height(10.dp))
                    Text(
                        text = profileName,
                        fontSize = 25.sp,
                        fontWeight = FontWeight.Black,
                        color = MaterialTheme.colorScheme.onSurface,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = if (isAgent) {
                            "Tus resultados, feedback y próximas revisiones."
                        } else {
                            "Calidad, feedback, audios, alertas y operación."
                        },
                        fontSize = 13.sp,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.62f),
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                }
                Box(
                    modifier = Modifier
                        .size(78.dp)
                        .clip(CircleShape)
                        .background(scoreColor.copy(alpha = 0.12f)),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            text = String.format("%.0f", avgScore),
                            fontSize = 26.sp,
                            fontWeight = FontWeight.Black,
                            color = scoreColor
                        )
                        Text(
                            text = "%",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = scoreColor
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))
            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.16f))
            Spacer(modifier = Modifier.height(14.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                HeroSignal(
                    label = "Estado",
                    value = statusText,
                    color = scoreColor,
                    modifier = Modifier.weight(1f)
                )
                HeroSignal(
                    label = "Feedback",
                    value = String.format("%.0f%%", responsePct),
                    color = if (responsePct >= 80.0) Green else Amber,
                    modifier = Modifier.weight(1f)
                )
            }

            Spacer(modifier = Modifier.height(10.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                HeroSignal(
                    label = "Audios",
                    value = transcriptSummary.optString("audio", "0"),
                    color = Cyan,
                    modifier = Modifier.weight(1f)
                )
                HeroSignal(
                    label = "Alertas",
                    value = summary.optString("open_alerts", "0"),
                    color = if (summary.optInt("open_alerts", 0) > 0) Rose else Green,
                    modifier = Modifier.weight(1f)
                )
            }

            if (league != null) {
                Spacer(modifier = Modifier.height(12.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f))
                        .padding(horizontal = 12.dp, vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Star,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.primary,
                            modifier = Modifier.size(16.dp)
                        )
                        Text(
                            text = "Liga ${league.optString("name", "Asesor")}",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.primary
                        )
                    }
                    Text(
                        text = league.optString("score_label", String.format("%.1f%%", avgScore)),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Black,
                        color = MaterialTheme.colorScheme.primary
                    )
                }
            }
        }
    }
}

@Composable
private fun HeroSignal(label: String, value: String, color: Color, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(14.dp))
            .background(color.copy(alpha = 0.08f))
            .padding(horizontal = 12.dp, vertical = 10.dp)
    ) {
        Text(
            text = label,
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold,
            color = color,
            maxLines = 1
        )
        Spacer(modifier = Modifier.height(4.dp))
        Text(
            text = value,
            fontSize = 16.sp,
            fontWeight = FontWeight.Black,
            color = MaterialTheme.colorScheme.onSurface,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
private fun DashboardKpiGrid(
    overview: JSONObject,
    summary: JSONObject,
    feedbackSummary: JSONObject,
    transcriptSummary: JSONObject
) {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            DashboardMiniMetric(
                title = "Evaluaciones",
                value = overview.optString("total_evaluations", "0"),
                subtitle = "visibles",
                icon = Icons.Default.Assessment,
                color = Blue,
                modifier = Modifier.weight(1f)
            )
            DashboardMiniMetric(
                title = "Pend. monitor",
                value = summary.optString("pending_reviews", summary.optString("monitor_pending", "0")),
                subtitle = "por revisar",
                icon = Icons.Default.RateReview,
                color = Amber,
                modifier = Modifier.weight(1f)
            )
        }
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            DashboardMiniMetric(
                title = "Feedback",
                value = feedbackSummary.optString("pending_response", "0"),
                subtitle = "sin respuesta",
                icon = Icons.Default.Chat,
                color = Violet,
                modifier = Modifier.weight(1f)
            )
            DashboardMiniMetric(
                title = "Audios IA",
                value = transcriptSummary.optString("processing", "0"),
                subtitle = "procesando",
                icon = Icons.Default.Audiotrack,
                color = Cyan,
                modifier = Modifier.weight(1f)
            )
        }
    }
}

@Composable
private fun DashboardMiniMetric(
    title: String,
    value: String,
    subtitle: String,
    icon: ImageVector,
    color: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.24f),
                shape = RoundedCornerShape(16.dp)
            ),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = title,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.58f),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = color,
                    modifier = Modifier.size(16.dp)
                )
            }
            Spacer(modifier = Modifier.height(9.dp))
            Text(
                text = value,
                fontSize = 25.sp,
                fontWeight = FontWeight.Black,
                color = color,
                maxLines = 1
            )
            Text(
                text = subtitle,
                fontSize = 10.sp,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.48f),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@Composable
private fun DashboardOperationalPanel(
    feedbackSummary: JSONObject,
    transcriptSummary: JSONObject,
    evaluationSummary: JSONObject
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.24f),
                shape = RoundedCornerShape(18.dp)
            ),
        shape = RoundedCornerShape(18.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(Icons.Default.Dashboard, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                Text(
                    text = "Pulso operativo",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )
            }
            Spacer(modifier = Modifier.height(14.dp))
            DashboardProgressMetric(
                label = "Feedback respondido",
                done = feedbackSummary.optDouble("responded", 0.0),
                total = feedbackSummary.optDouble("published", 0.0),
                color = Green
            )
            DashboardProgressMetric(
                label = "Evaluaciones publicadas",
                done = evaluationSummary.optDouble("published", 0.0),
                total = evaluationSummary.optDouble("total", 0.0),
                color = Blue
            )
            DashboardProgressMetric(
                label = "Audios completados",
                done = (transcriptSummary.optDouble("audio", 0.0) - transcriptSummary.optDouble("processing", 0.0) - transcriptSummary.optDouble("failed", 0.0)).coerceAtLeast(0.0),
                total = transcriptSummary.optDouble("audio", 0.0),
                color = Cyan
            )
        }
    }
}

@Composable
private fun DashboardProgressMetric(label: String, done: Double, total: Double, color: Color) {
    val pct = if (total > 0) (done / total).coerceIn(0.0, 1.0).toFloat() else 0f
    Column(modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = label,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.76f)
            )
            Text(
                text = "${done.toInt()}/${total.toInt()}",
                fontSize = 12.sp,
                fontWeight = FontWeight.Black,
                color = color
            )
        }
        Spacer(modifier = Modifier.height(6.dp))
        LinearProgressIndicator(
            progress = { pct },
            modifier = Modifier.fillMaxWidth().height(7.dp).clip(RoundedCornerShape(8.dp)),
            color = color,
            trackColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.55f)
        )
    }
}

@Composable
private fun DashboardCampaignSnapshot(campaignsArray: JSONArray) {
    SectionHeader(title = "Campañas", subtitle = "Promedio de calidad por operación")
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.24f),
                shape = RoundedCornerShape(16.dp)
            ),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            for (i in 0 until minOf(campaignsArray.length(), 4)) {
                val campaign = campaignsArray.optJSONObject(i) ?: continue
                val score = campaign.optDouble("avg_score", 0.0)
                ProgressLine(
                    label = "${campaign.optString("label", "Campaña")} (${campaign.optInt("count", 0)} ev.)",
                    score = score,
                    color = getScoreColor(score)
                )
            }
        }
    }
}

@Composable
private fun DashboardSignalPanel(
    isAgent: Boolean,
    overview: JSONObject,
    summary: JSONObject,
    feedbackSummary: JSONObject,
    transcriptSummary: JSONObject,
    defectsArray: JSONArray?
) {
    val avgScore = overview.optDouble("average_score", 0.0)
    val pendingFeedback = feedbackSummary.optInt("pending_response", 0)
    val openAlerts = summary.optInt("open_alerts", 0)
    val processingAudios = transcriptSummary.optInt("processing", 0)
    val primaryDefect = firstDefectLabel(defectsArray)
    val recommendation = when {
        openAlerts > 0 -> "Priorizar las alertas críticas antes de revisar casos normales."
        pendingFeedback > 0 && isAgent -> "Responder el feedback pendiente para cerrar el ciclo de mejora."
        pendingFeedback > 0 -> "Hacer seguimiento a asesores con feedback publicado y sin respuesta."
        avgScore < 70.0 -> "Revisar evaluaciones críticas y reforzar calibración de criterios."
        processingAudios > 0 -> "Monitorear la cola de audios para evitar acumulación en IA."
        else -> "Mantener seguimiento preventivo y revisar tendencias por campaña."
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.primary.copy(alpha = 0.20f),
                shape = RoundedCornerShape(18.dp)
            ),
        shape = RoundedCornerShape(18.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primary.copy(alpha = 0.045f)),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(Icons.Default.AutoAwesome, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                Text(
                    text = "Lectura ejecutiva",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
            }
            Spacer(modifier = Modifier.height(10.dp))
            Text(
                text = recommendation,
                fontSize = 13.sp,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.82f),
                lineHeight = 18.sp
            )
            Spacer(modifier = Modifier.height(12.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                SignalChipBox(
                    label = "Fortaleza",
                    value = if (avgScore >= 85.0) "Calidad alta" else "Seguimiento activo",
                    color = if (avgScore >= 85.0) Green else Blue,
                    modifier = Modifier.weight(1f)
                )
                SignalChipBox(
                    label = "Foco",
                    value = primaryDefect,
                    color = if (openAlerts > 0 || avgScore < 70.0) Rose else Amber,
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

@Composable
private fun SignalChipBox(label: String, value: String, color: Color, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(color.copy(alpha = 0.09f))
            .padding(10.dp)
    ) {
        Text(text = label, fontSize = 10.sp, fontWeight = FontWeight.Black, color = color)
        Spacer(modifier = Modifier.height(3.dp))
        Text(
            text = value,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
private fun DashboardModuleGrid(
    transcriptSummary: JSONObject,
    evaluationSummary: JSONObject,
    formSummary: JSONObject,
    insightSummary: JSONObject,
    feedbackSummary: JSONObject,
    onNavigate: (String, JSONObject) -> Unit,
    data: JSONObject
) {
    SectionHeader(title = "Módulos", subtitle = "Accesos con lectura rápida")
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            DashboardModuleTile(
                title = "Audios",
                value = transcriptSummary.optString("audio", "0"),
                detail = "${transcriptSummary.optString("processing", "0")} procesando",
                icon = Icons.Default.Audiotrack,
                color = Cyan,
                modifier = Modifier.weight(1f),
                onClick = { onNavigate("transcript_list", data) }
            )
            DashboardModuleTile(
                title = "Evaluaciones",
                value = evaluationSummary.optString("total", "0"),
                detail = "${evaluationSummary.optString("critical", "0")} críticas",
                icon = Icons.Default.Assessment,
                color = Blue,
                modifier = Modifier.weight(1f),
                onClick = { onNavigate("evaluation_list", data) }
            )
        }
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            DashboardModuleTile(
                title = "Fichas",
                value = formSummary.optString("total", "0"),
                detail = "${formSummary.optString("with_context", "0")} con contexto",
                icon = Icons.Default.Assignment,
                color = Amber,
                modifier = Modifier.weight(1f),
                onClick = { onNavigate("quality_form_list", data) }
            )
            DashboardModuleTile(
                title = "Insights",
                value = insightSummary.optString("last_30_days", "0"),
                detail = "últimos 30 días",
                icon = Icons.Default.AutoAwesome,
                color = Violet,
                modifier = Modifier.weight(1f),
                onClick = { onNavigate("insight_list", data) }
            )
        }
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            DashboardModuleTile(
                title = "Feedback",
                value = feedbackSummary.optString("responded", "0"),
                detail = "${feedbackSummary.optString("pending_response", "0")} pendiente",
                icon = Icons.Default.Chat,
                color = Green,
                modifier = Modifier.weight(1f),
                onClick = { onNavigate("feedback_list", data) }
            )
            DashboardModuleTile(
                title = "Campañas",
                value = data.optJSONArray("campaigns")?.length()?.toString() ?: "0",
                detail = "con calidad agrupada",
                icon = Icons.Default.Campaign,
                color = Rose,
                modifier = Modifier.weight(1f),
                onClick = { onNavigate("campaign_list", data) }
            )
        }
    }
}

@Composable
private fun DashboardModuleTile(
    title: String,
    value: String,
    detail: String,
    icon: ImageVector,
    color: Color,
    modifier: Modifier = Modifier,
    onClick: () -> Unit
) {
    Card(
        modifier = modifier
            .border(
                width = 1.dp,
                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.22f),
                shape = RoundedCornerShape(16.dp)
            )
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(icon, contentDescription = null, tint = color, modifier = Modifier.size(18.dp))
                Text(text = value, fontSize = 21.sp, fontWeight = FontWeight.Black, color = color)
            }
            Spacer(modifier = Modifier.height(10.dp))
            Text(
                text = title,
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = detail,
                fontSize = 10.sp,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.52f),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@Composable
private fun AlertPreviewCard(
    alert: JSONObject,
    evaluationsArray: JSONArray?,
    onNavigate: (String, JSONObject) -> Unit
) {
    val severity = alert.optString("severity", "info")
    val color = when (severity.lowercase()) {
        "critical", "high", "error" -> Rose
        "warning", "medium" -> Amber
        else -> Blue
    }
    val evalId = alert.optInt("evaluation_id", -1)
    val fullEval = findEvaluationById(evaluationsArray, evalId)

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 5.dp)
            .border(
                width = 1.dp,
                color = color.copy(alpha = 0.24f),
                shape = RoundedCornerShape(14.dp)
            )
            .clickable(enabled = fullEval != null) {
                if (fullEval != null) {
                    onNavigate("evaluation", fullEval)
                }
            },
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(color.copy(alpha = 0.10f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Default.Warning, contentDescription = null, tint = color, modifier = Modifier.size(18.dp))
            }
            Spacer(modifier = Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = alert.optString("title", "Alerta"),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = alert.optString("description", "Requiere revisión."),
                    fontSize = 11.sp,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.58f),
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
    }
}

private fun firstDefectLabel(defectsArray: JSONArray?): String {
    if (defectsArray == null || defectsArray.length() == 0) {
        return "Sin hallazgos críticos"
    }
    return defectsArray.optJSONObject(0)?.optString("label", "Criterios de calidad") ?: "Criterios de calidad"
}

private fun findEvaluationById(evaluationsArray: JSONArray?, evalId: Int): JSONObject? {
    if (evaluationsArray == null || evalId <= 0) {
        return null
    }
    for (index in 0 until evaluationsArray.length()) {
        val evaluation = evaluationsArray.optJSONObject(index) ?: continue
        if (evaluation.optInt("id") == evalId) {
            return evaluation
        }
    }
    return null
}

@Composable
fun DashboardQuickStat(label: String, value: String, icon: ImageVector) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(horizontal = 8.dp)
    ) {
        Box(
            modifier = Modifier
                .size(36.dp)
                .clip(CircleShape)
                .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(18.dp)
            )
        }
        Spacer(modifier = Modifier.height(6.dp))
        Text(
            text = value,
            fontSize = 18.sp,
            fontWeight = FontWeight.Black,
            color = MaterialTheme.colorScheme.onSurface
        )
        Text(
            text = label,
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium,
            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
        )
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

@Composable
fun FeedbackListModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit) {
    val module = data.optJSONObject("modules")?.optJSONObject("feedback") ?: JSONObject()
    val summary = module.optJSONObject("summary") ?: JSONObject()
    val evaluations = data.optJSONObject("modules")
        ?.optJSONObject("evaluations")
        ?.optJSONArray("items")
        ?: data.optJSONArray("evaluations")
        ?: JSONArray()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        SectionHeader("Feedback", "Seguimiento de respuestas de asesores")

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard("Publicado", summary.optString("published", "0"), "Visible para asesor", Blue, Modifier.weight(1f))
            MetricCard("Pendiente", summary.optString("pending_response", "0"), "Sin respuesta", Amber, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(12.dp))

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard("Aceptado", summary.optString("accepted", "0"), "Compromisos", Green, Modifier.weight(1f))
            MetricCard("Disputado", summary.optString("disputed", "0"), "En revisión", Rose, Modifier.weight(1f))
        }

        Spacer(modifier = Modifier.height(20.dp))
        SectionHeader("Evaluaciones con feedback")

        for (i in 0 until evaluations.length()) {
            val item = evaluations.optJSONObject(i) ?: continue
            val response = item.optJSONObject("feedback_response")
            val responded = response?.optBoolean("responded") == true
            val visible = item.optString("visible_to_agent_at", "").isNotEmpty()
            val chip = when {
                responded -> "Respondido"
                visible -> "Pendiente"
                else -> "No publicado"
            }
            DetailCard(
                title = item.optString("campaign", "Sin campaña"),
                scoreValue = item.optString("score_label", "0%"),
                scoreColor = getScoreColor(item.optDouble("score", -1.0)),
                description = "${item.optString("agent", "Sin asesor")} | ${item.optString("status_label", "Sin estado")}",
                chips = listOf(chip),
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

@Composable
fun RankingRow(name: String, scoreLabel: String, level: String, position: Int) {
    val positionColor = when (position) {
        1 -> Color(0xFFFBBF24) // Gold
        2 -> Color(0xFF9CA3AF) // Silver
        3 -> Color(0xFFD97706) // Bronze
        else -> MaterialTheme.colorScheme.onSurface.copy(alpha = 0.08f)
    }
    val positionTextColor = when (position) {
        1, 2, 3 -> Color.White
        else -> MaterialTheme.colorScheme.onSurface
    }
    
    val levelColor = when (level.lowercase()) {
        "superior", "excelente", "retador", "gran maestro", "maestro", "diamante", "esmeralda" -> Color(0xFF10B981) // Green
        "solido", "platino", "oro", "plata" -> Color(0xFF6366F1) // Indigo/Blue
        "en seguimiento", "bronce" -> Color(0xFFF59E0B) // Amber
        else -> Color(0xFFEF4444) // Red
    }

    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(28.dp)
                .clip(CircleShape)
                .background(positionColor),
            contentAlignment = Alignment.Center
        ) {
            Text(
                text = position.toString(),
                fontSize = 12.sp,
                fontWeight = FontWeight.Bold,
                color = positionTextColor
            )
        }
        Spacer(modifier = Modifier.width(12.dp))
        Text(
            text = name,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.weight(1f),
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
        Spacer(modifier = Modifier.width(8.dp))
        Box(
            modifier = Modifier
                .clip(RoundedCornerShape(6.dp))
                .background(levelColor.copy(alpha = 0.1f))
                .border(width = 0.5.dp, color = levelColor.copy(alpha = 0.3f), shape = RoundedCornerShape(6.dp))
                .padding(horizontal = 8.dp, vertical = 2.dp)
        ) {
            Text(
                text = level.uppercase(),
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold,
                color = levelColor
            )
        }
        Spacer(modifier = Modifier.width(12.dp))
        Text(
            text = scoreLabel,
            fontSize = 14.sp,
            fontWeight = FontWeight.Black,
            color = MaterialTheme.colorScheme.primary
        )
    }
}

@Composable
fun AlertRowCard(title: String, description: String, severity: String, dateLabel: String, onClick: () -> Unit) {
    val severityColor = when (severity.lowercase()) {
        "critical", "danger", "error" -> Color(0xFFEF4444)
        "warning", "alert" -> Color(0xFFF59E0B)
        else -> Color(0xFF3B82F6)
    }
    val severityName = when (severity.lowercase()) {
        "critical", "danger", "error" -> "Crítico"
        "warning", "alert" -> "Advertencia"
        else -> "Info"
    }
    
    Card(
        modifier = Modifier
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
        Column(modifier = Modifier.padding(14.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .background(severityColor.copy(alpha = 0.1f))
                        .border(width = 0.5.dp, color = severityColor.copy(alpha = 0.3f), shape = RoundedCornerShape(6.dp))
                        .padding(horizontal = 8.dp, vertical = 2.dp)
                ) {
                    Text(
                        text = severityName.uppercase(),
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold,
                        color = severityColor
                    )
                }
                Text(
                    text = dateLabel,
                    fontSize = 11.sp,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.4f)
                )
            }
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = title,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = description,
                fontSize = 13.sp,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                lineHeight = 18.sp
            )
        }
    }
}
