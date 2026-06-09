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
fun MainDashboardModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit, onFiltersChanged: (Map<String, String>) -> Unit = {}) {
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
    val generatedAt = data.optString("generated_at", "")

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
                Pair("evolutivos", Pair("Evolutivos", Icons.Default.ShowChart)),
                Pair("campaigns", Pair("Campañas", Icons.Default.Campaign)),
                Pair("feedback", Pair("Feedback", Icons.Default.Chat)),
                Pair("ranking", Pair("Ranking", Icons.Default.Star)),
                Pair("operacion", Pair("Operación", Icons.Default.Speed)),
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

        // Filters (only for non-agent roles)
        if (!isAgent) {
        val filterOptions = data.optJSONObject("filter_options")
        val currentFilters = data.optJSONObject("filters")
        var showFilters by remember { mutableStateOf(false) }
        var filterStartDate by remember { mutableStateOf(currentFilters?.optString("start_date", "") ?: "") }
        var filterEndDate by remember { mutableStateOf(currentFilters?.optString("end_date", "") ?: "") }
        var filterCampaignId by remember { mutableStateOf(currentFilters?.optString("campaign_id", "") ?: "") }
        var filterParentCampaignId by remember { mutableStateOf(currentFilters?.optString("parent_campaign_id", "") ?: "") }
        var filterSupervisorId by remember { mutableStateOf(currentFilters?.optString("supervisor_id", "") ?: "") }
        var filterAgentId by remember { mutableStateOf(currentFilters?.optString("agent_id", "") ?: "") }

        val hasActiveFilters = filterStartDate.isNotEmpty() || filterEndDate.isNotEmpty() ||
            filterCampaignId.isNotEmpty() || filterParentCampaignId.isNotEmpty() ||
            filterSupervisorId.isNotEmpty() || filterAgentId.isNotEmpty()

        // Filter toggle button
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                IconButton(onClick = { showFilters = !showFilters }) {
                    Icon(
                        imageVector = Icons.Default.FilterList,
                        contentDescription = "Filtros",
                        tint = if (hasActiveFilters) MaterialTheme.colorScheme.primary
                               else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                    )
                }
                if (hasActiveFilters) {
                    TextButton(onClick = {
                        filterStartDate = ""
                        filterEndDate = ""
                        filterCampaignId = ""
                        filterParentCampaignId = ""
                        filterSupervisorId = ""
                        filterAgentId = ""
                        onFiltersChanged(emptyMap())
                    }) {
                        Text("Limpiar filtros", fontSize = 12.sp)
                    }
                }
            }
        }

        // Filter panel
        if (showFilters) {
            Card(
                modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f))
            ) {
                Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    // Date range
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(
                            value = filterStartDate,
                            onValueChange = { filterStartDate = it },
                            label = { Text("Desde", fontSize = 12.sp) },
                            modifier = Modifier.weight(1f),
                            singleLine = true,
                            textStyle = MaterialTheme.typography.bodySmall
                        )
                        OutlinedTextField(
                            value = filterEndDate,
                            onValueChange = { filterEndDate = it },
                            label = { Text("Hasta", fontSize = 12.sp) },
                            modifier = Modifier.weight(1f),
                            singleLine = true,
                            textStyle = MaterialTheme.typography.bodySmall
                        )
                    }

                    // Campaign filters
                    val parentCampaigns = filterOptions?.optJSONArray("parent_campaigns")
                    val subcampaigns = filterOptions?.optJSONArray("subcampaigns")
                    val supervisors = filterOptions?.optJSONArray("supervisors")
                    val agents = filterOptions?.optJSONArray("agents")

                    if (parentCampaigns != null && parentCampaigns.length() > 0) {
                        FilterDropdown(
                            label = "Campaña",
                            selectedId = filterParentCampaignId,
                            options = (0 until parentCampaigns.length()).map {
                                val c = parentCampaigns.getJSONObject(it)
                                Pair(c.optString("id"), c.optString("name"))
                            },
                            onSelected = { filterParentCampaignId = it; filterCampaignId = "" }
                        )
                    }

                    if (subcampaigns != null && subcampaigns.length() > 0) {
                        val filteredSubs = (0 until subcampaigns.length())
                            .map { subcampaigns.getJSONObject(it) }
                            .filter { filterParentCampaignId.isEmpty() || it.optString("parent_id") == filterParentCampaignId }
                        if (filteredSubs.isNotEmpty()) {
                            FilterDropdown(
                                label = "Subcampaña",
                                selectedId = filterCampaignId,
                                options = filteredSubs.map { Pair(it.optString("id"), it.optString("name")) },
                                onSelected = { filterCampaignId = it }
                            )
                        }
                    }

                    if (supervisors != null && supervisors.length() > 0) {
                        FilterDropdown(
                            label = "Supervisor",
                            selectedId = filterSupervisorId,
                            options = (0 until supervisors.length()).map {
                                val s = supervisors.getJSONObject(it)
                                Pair(s.optString("id"), s.optString("name"))
                            },
                            onSelected = { filterSupervisorId = it }
                        )
                    }

                    if (agents != null && agents.length() > 0) {
                        FilterDropdown(
                            label = "Agente",
                            selectedId = filterAgentId,
                            options = (0 until agents.length()).map {
                                val a = agents.getJSONObject(it)
                                Pair(a.optString("id"), a.optString("name"))
                            },
                            onSelected = { filterAgentId = it }
                        )
                    }

                    // Apply button
                    Button(
                        onClick = {
                            val filters = mutableMapOf<String, String>()
                            if (filterStartDate.isNotEmpty()) filters["start_date"] = filterStartDate
                            if (filterEndDate.isNotEmpty()) filters["end_date"] = filterEndDate
                            if (filterCampaignId.isNotEmpty()) filters["campaign_id"] = filterCampaignId
                            else if (filterParentCampaignId.isNotEmpty()) filters["parent_campaign_id"] = filterParentCampaignId
                            if (filterSupervisorId.isNotEmpty()) filters["supervisor_id"] = filterSupervisorId
                            if (filterAgentId.isNotEmpty()) filters["agent_id"] = filterAgentId
                            onFiltersChanged(filters)
                            showFilters = false
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Text("Aplicar filtros")
                    }
                }
            }
        }

        } // end if (!isAgent)

        Spacer(modifier = Modifier.height(12.dp))

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

                    val trendPoints = jsonArrayToComboPoints(trendArray, "count", "avg_score", Blue)
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
                            ComboBarLineChart(
                                points = trendPoints,
                                barLabel = "Evaluaciones",
                                lineLabel = "Calidad",
                                lineSuffix = "%"
                            )
                            val insight = trendPoints.lastOrNull { it.insight.isNotBlank() }?.insight
                            if (!insight.isNullOrBlank()) {
                                Spacer(modifier = Modifier.height(10.dp))
                                InsightCallout(text = insight)
                            }
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
            "evolutivos" -> DashboardEvolutivosModule(data)
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
                SectionHeader(title = "Seguimiento de Feedback", subtitle = "Estado de respuestas y disputas de la operación")
                Spacer(modifier = Modifier.height(10.dp))
                
                val pubVal = feedbackSummary.optDouble("published", 0.0)
                val viewVal = feedbackSummary.optDouble("viewed", 0.0)
                val respVal = feedbackSummary.optDouble("responded", 0.0)
                val accVal = feedbackSummary.optDouble("accepted", 0.0)
                val dispVal = feedbackSummary.optDouble("disputed", 0.0)

                val readRatio = if (pubVal > 0) (viewVal / pubVal * 100.0) else 0.0
                val respRatio = if (pubVal > 0) (respVal / pubVal * 100.0) else 0.0
                val accRatio = if (respVal > 0) (accVal / respVal * 100.0) else 0.0

                // Render metrics cards
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    DashboardMiniMetric(
                        title = "Tasa de Conformidad",
                        value = String.format("%.1f%%", accRatio),
                        subtitle = "sobre respuestas",
                        icon = Icons.Default.CheckCircle,
                        color = Green,
                        modifier = Modifier.weight(1f)
                    )
                    DashboardMiniMetric(
                        title = "Tasa de Respuesta",
                        value = String.format("%.1f%%", respRatio),
                        subtitle = "sobre publicados",
                        icon = Icons.Default.Chat,
                        color = Violet,
                        modifier = Modifier.weight(1f)
                    )
                }

                Spacer(modifier = Modifier.height(14.dp))

                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .border(
                            width = 1.dp,
                            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.22f),
                            shape = RoundedCornerShape(16.dp)
                        ),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text("Estado de Conversión", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                        Spacer(modifier = Modifier.height(12.dp))
                        
                        ProgressLine(label = "Feedback Visto (Vistas / Publicadas)", score = readRatio, color = Cyan)
                        Spacer(modifier = Modifier.height(10.dp))
                        ProgressLine(label = "Feedback Respondido (Respuestas / Publicadas)", score = respRatio, color = Violet)
                        Spacer(modifier = Modifier.height(10.dp))
                        ProgressLine(label = "Aceptación de Agentes (Aceptadas / Respuestas)", score = accRatio, color = Green)
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .border(
                            width = 1.dp,
                            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.22f),
                            shape = RoundedCornerShape(16.dp)
                        ),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text("Resumen Operativo", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                        Spacer(modifier = Modifier.height(8.dp))
                        FeedbackStatRow("Publicadas", feedbackSummary.optString("published", "0"), Icons.Default.Publish)
                        FeedbackStatRow("Vistas", feedbackSummary.optString("viewed", "0"), Icons.Default.Visibility)
                        FeedbackStatRow("Aceptadas", feedbackSummary.optString("accepted", "0"), Icons.Default.CheckCircle)
                        FeedbackStatRow("Disputadas", feedbackSummary.optString("disputed", "0"), Icons.Default.Warning)
                        FeedbackStatRow("Pendientes de Firma", feedbackSummary.optString("pending_response", "0"), Icons.Default.Schedule)
                    }
                }

                val feedbackSeries = data.optJSONObject("charts")?.optJSONObject("feedback")
                if (feedbackSeries != null) {
                    Spacer(modifier = Modifier.height(16.dp))
                    DashboardPeriodChartCard(
                        title = "Evolutivo de Feedback",
                        subtitle = "Evaluaciones publicadas vs feedback cerrado",
                        series = feedbackSeries,
                        barMetric = "total",
                        lineMetric = "done_pct",
                        barLabel = "Evaluaciones",
                        lineLabel = "Firma %",
                        lineSuffix = "%",
                        color = Green,
                        defaultPeriod = "week"
                    )
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
            "operacion" -> DashboardOperationsModule(data, onNavigate)
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
private fun DashboardEvolutivosModule(data: JSONObject) {
    val charts = data.optJSONObject("charts") ?: JSONObject()
    val qualitySeries = charts.optJSONObject("quality") ?: JSONObject()
    val mpSeries = charts.optJSONObject("malas_practicas") ?: JSONObject()
    val feedbackSeries = charts.optJSONObject("feedback") ?: JSONObject()
    val defectsArray = data.optJSONArray("top_defects")

    Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
        SectionHeader(title = "Evolutivos", subtitle = "Día, semana y mes en una sola lectura")
        DashboardPeriodChartCard(
            title = "Calidad",
            subtitle = "Evaluaciones y nota promedio",
            series = qualitySeries,
            barMetric = "count",
            lineMetric = "avg_score",
            barLabel = "Evaluaciones",
            lineLabel = "Calidad",
            lineSuffix = "%",
            color = Blue
        )
        DashboardPeriodChartCard(
            title = "Malas prácticas",
            subtitle = "Incidencias y participación",
            series = mpSeries,
            barMetric = "count",
            lineMetric = "percentage",
            barLabel = "MP",
            lineLabel = "% con MP",
            lineSuffix = "%",
            color = Rose
        )
        DashboardPeriodChartCard(
            title = "Feedback respondido",
            subtitle = "Evaluaciones y feedback visto",
            series = feedbackSeries,
            barMetric = "total",
            lineMetric = "done_pct",
            barLabel = "Evaluaciones",
            lineLabel = "Visto",
            lineSuffix = "%",
            color = Green,
            defaultPeriod = "week"
        )

        if (defectsArray != null && defectsArray.length() > 0) {
            val defectPoints = jsonArrayToPoints(
                array = defectsArray,
                metric = "count",
                fallbackColor = Rose,
                maxLabelLength = 28
            )
            SectionHeader(title = "Criterios con más fallos")
            ChartSurface {
                TopDefectsBarChart(defects = defectPoints)
            }
        }
    }
}

@Composable
private fun DashboardOperationsModule(data: JSONObject, onNavigate: (String, JSONObject) -> Unit) {
    val audio = data.optJSONObject("audio_productivity") ?: JSONObject()
    val summary = audio.optJSONObject("summary") ?: JSONObject()
    val monitors = audio.optJSONArray("by_monitor") ?: JSONArray()
    val recent = audio.optJSONArray("recent") ?: JSONArray()
    val modules = data.optJSONObject("modules") ?: JSONObject()
    val transcriptSummary = modules.optJSONObject("transcripts")?.optJSONObject("summary") ?: JSONObject()
    val evaluationSummary = modules.optJSONObject("evaluations")?.optJSONObject("summary") ?: JSONObject()

    Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
        SectionHeader(title = "Operación", subtitle = "Ritmo de carga, IA y revisión")

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            DashboardMiniMetric(
                title = "Audios",
                value = summary.optString("total_audio", "0"),
                subtitle = "periodo",
                icon = Icons.Default.Audiotrack,
                color = Cyan,
                modifier = Modifier.weight(1f)
            )
            DashboardMiniMetric(
                title = "Monitores",
                value = summary.optString("monitors", "0"),
                subtitle = "con cargas",
                icon = Icons.Default.Groups,
                color = Violet,
                modifier = Modifier.weight(1f)
            )
        }

        ChartSurface {
            OperationMetricRow("Prom. entre cargas", summary.optString("avg_gap_label", "Sin datos"), Icons.Default.Schedule)
            OperationMetricRow("Carga a transcripción", summary.optString("avg_transcription_label", "Sin datos"), Icons.Default.GraphicEq)
            OperationMetricRow("Carga a IA", summary.optString("avg_ai_label", "Sin datos"), Icons.Default.AutoAwesome)
            OperationMetricRow("Carga a revisión", summary.optString("avg_review_label", "Sin datos"), Icons.Default.RateReview)
        }

        SectionHeader(title = "Ritmo por monitor")
        ChartSurface {
            if (monitors.length() == 0) {
                EmptyInlineText("No hay audios cargados en el periodo.")
            } else {
                for (i in 0 until minOf(monitors.length(), 5)) {
                    val monitor = monitors.optJSONObject(i) ?: continue
                    MonitorPerformanceRow(monitor)
                    if (i < minOf(monitors.length(), 5) - 1) {
                        HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.16f))
                    }
                }
            }
        }

        SectionHeader(title = "Últimas cargas")
        if (recent.length() == 0) {
            ChartSurface { EmptyInlineText("Aún no hay cargas recientes para mostrar.") }
        } else {
            for (i in 0 until minOf(recent.length(), 4)) {
                val item = recent.optJSONObject(i) ?: continue
                DetailCard(
                    title = item.optString("campaign", "Sin campaña"),
                    scoreValue = item.optString("since_previous_label", "Sin datos"),
                    scoreColor = Cyan,
                    description = "${item.optString("monitor", "Sin monitor")} | ${item.optString("file_name", "Audio")}",
                    chips = listOf(
                        "IA ${item.optString("upload_to_ai_label", "Sin datos")}",
                        item.optString("status", "estado")
                    ),
                    onClick = { onNavigate("transcript_list", data) }
                )
            }
        }

        ChartSurface {
            OperationMetricRow("Transcripciones procesando", transcriptSummary.optString("processing", "0"), Icons.Default.Sync)
            OperationMetricRow("Transcripciones fallidas", transcriptSummary.optString("failed", "0"), Icons.Default.Report)
            OperationMetricRow("Evaluaciones pendientes", evaluationSummary.optString("pending_monitor", "0"), Icons.Default.PendingActions)
            OperationMetricRow("Evaluaciones críticas", evaluationSummary.optString("critical", "0"), Icons.Default.Warning)
        }
    }
}

@Composable
private fun DashboardPeriodChartCard(
    title: String,
    subtitle: String,
    series: JSONObject,
    barMetric: String,
    lineMetric: String,
    barLabel: String,
    lineLabel: String,
    lineSuffix: String,
    color: Color,
    defaultPeriod: String = "day"
) {
    var period by remember { mutableStateOf(defaultPeriod) }
    val data = series.optJSONArray(period) ?: JSONArray()
    val points = jsonArrayToComboPoints(data, barMetric, lineMetric, color)

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
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(title, fontSize = 15.sp, fontWeight = FontWeight.Black, color = MaterialTheme.colorScheme.onSurface)
                    Text(subtitle, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f))
                }
                PeriodSelector(period = period, onPeriodChanged = { period = it })
            }
            Spacer(modifier = Modifier.height(12.dp))
            if (points.isEmpty()) {
                EmptyInlineText("Sin datos para este periodo.")
            } else {
                ComboBarLineChart(
                    points = points,
                    barLabel = barLabel,
                    lineLabel = lineLabel,
                    lineSuffix = lineSuffix,
                    lineMaxValue = 100.0
                )

                val latestInsight = points.lastOrNull { it.insight.isNotBlank() }?.insight
                if (!latestInsight.isNullOrBlank()) {
                    Spacer(modifier = Modifier.height(10.dp))
                    InsightCallout(text = latestInsight)
                }
            }
        }
    }
}

@Composable
private fun PeriodSelector(period: String, onPeriodChanged: (String) -> Unit) {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.45f))
            .padding(3.dp),
        horizontalArrangement = Arrangement.spacedBy(2.dp)
    ) {
        listOf("day" to "D", "week" to "S", "month" to "M").forEach { (id, label) ->
            val selected = period == id
            Box(
                modifier = Modifier
                    .size(width = 30.dp, height = 26.dp)
                    .clip(RoundedCornerShape(9.dp))
                    .background(if (selected) MaterialTheme.colorScheme.primary else Color.Transparent)
                    .clickable { onPeriodChanged(id) },
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = label,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Black,
                    color = if (selected) Color.White else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.62f)
                )
            }
        }
    }
}

@Composable
private fun ChartSurface(content: @Composable ColumnScope.() -> Unit) {
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
        Column(modifier = Modifier.padding(16.dp), content = content)
    }
}

@Composable
private fun OperationMetricRow(label: String, value: String, icon: ImageVector) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(17.dp))
            Text(label, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.72f))
        }
        Text(value, fontSize = 13.sp, fontWeight = FontWeight.Black, color = MaterialTheme.colorScheme.onSurface)
    }
}

@Composable
private fun MonitorPerformanceRow(monitor: JSONObject) {
    Column(modifier = Modifier.fillMaxWidth().padding(vertical = 10.dp)) {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(
                text = monitor.optString("label", "Sin monitor"),
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f)
            )
            Text(
                text = "${monitor.optInt("audio_count", 0)} audios",
                fontSize = 12.sp,
                fontWeight = FontWeight.Black,
                color = Cyan
            )
        }
        Spacer(modifier = Modifier.height(6.dp))
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            SignalChipBox("Entre cargas", monitor.optString("avg_gap_label", "Sin datos"), Cyan, Modifier.weight(1f))
            SignalChipBox("A IA", monitor.optString("avg_ai_label", "Sin datos"), Blue, Modifier.weight(1f))
        }
    }
}

@Composable
private fun EmptyInlineText(text: String) {
    Box(modifier = Modifier.fillMaxWidth().padding(vertical = 24.dp), contentAlignment = Alignment.Center) {
        Text(text, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
    }
}

private fun jsonArrayToPoints(
    array: JSONArray?,
    metric: String,
    fallbackColor: Color,
    maxLabelLength: Int = 12
): List<ChartPoint> {
    if (array == null) {
        return emptyList()
    }

    val points = mutableListOf<ChartPoint>()
    for (i in 0 until array.length()) {
        val item = array.optJSONObject(i) ?: continue
        val value = item.optDouble(metric, 0.0)
        val rawLabel = item.optString("label", "Dato")
        val label = if (rawLabel.length > maxLabelLength) {
            rawLabel.take((maxLabelLength - 3).coerceAtLeast(1)) + "..."
        } else {
            rawLabel
        }

        val displayLabel = if (rawLabel.length == 10 && rawLabel[4] == '-' && rawLabel[7] == '-') {
            rawLabel.takeLast(5)
        } else {
            label
        }

        points.add(
            ChartPoint(
                label = displayLabel,
                value = value,
                color = if (metric == "avg_score" || metric == "done_pct") getScoreColor(value) else fallbackColor
            )
        )
    }

    return points
}

private fun jsonArrayToComboPoints(
    array: JSONArray?,
    barMetric: String,
    lineMetric: String,
    fallbackColor: Color,
    maxLabelLength: Int = 12
): List<ComboChartPoint> {
    if (array == null) {
        return emptyList()
    }

    val points = mutableListOf<ComboChartPoint>()
    for (i in 0 until array.length()) {
        val item = array.optJSONObject(i) ?: continue
        val rawLabel = item.optString("label", "Dato")
        val label = if (rawLabel.length > maxLabelLength) {
            rawLabel.take(maxLabelLength - 3) + "..."
        } else {
            rawLabel
        }
        val displayLabel = if (rawLabel.length == 10 && rawLabel[4] == '-' && rawLabel[7] == '-') {
            rawLabel.takeLast(5)
        } else {
            label
        }
        val lineValue = item.optDouble(lineMetric, 0.0)

        points.add(
            ComboChartPoint(
                label = displayLabel,
                barValue = item.optDouble(barMetric, 0.0),
                lineValue = lineValue,
                color = fallbackColor,
                insight = item.optString("insight", "")
            )
        )
    }

    return points
}

@Composable
private fun RealtimeStatusPill(label: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f))
            .padding(horizontal = 12.dp, vertical = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Box(
            modifier = Modifier
                .size(8.dp)
                .clip(CircleShape)
                .background(Green)
        )
        Text(
            text = label,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.68f),
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

private fun generatedAtLabel(value: String): String {
    if (value.isBlank()) {
        return "Actualizacion automatica cada 15s"
    }

    val compact = value
        .replace("T", " ")
        .substringBefore(".")
        .removeSuffix("Z")
        .take(16)

    return "Tiempo real cada 15s | actualizado $compact"
}

@Composable
private fun InsightCallout(text: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f))
            .padding(12.dp),
        verticalAlignment = Alignment.Top,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Icon(
            imageVector = Icons.Default.AutoAwesome,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.primary,
            modifier = Modifier.size(16.dp)
        )
        Text(
            text = text,
            fontSize = 12.sp,
            lineHeight = 17.sp,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.78f)
        )
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
    val fullName = profile.optString("full_name", "")
    val firstName = profile.optString("name", "Usuario")
    val paternalSurname = profile.optString("paternal_surname", "")
    val displayName = if (fullName.isNotBlank()) {
        val parts = fullName.trim().split("\\s+".toRegex())
        if (parts.size >= 2) "${parts[0]} ${parts[1]}" else parts[0]
    } else {
        if (paternalSurname.isNotBlank()) "$firstName $paternalSurname" else firstName
    }
    val avatarUrl = profile.optString("avatar_url", "")
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
                        text = displayName,
                        fontSize = 25.sp,
                        fontWeight = FontWeight.Black,
                        color = MaterialTheme.colorScheme.onSurface,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = if (isAgent) {
                            "Tus resultados, feedback y próximas revisiones."
                        } else {
                            "Datos visibles según tu jerarquía: calidad, evaluaciones, audio y feedback."
                        },
                        fontSize = 13.sp,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.62f),
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                }
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Profile photo
                    Box(
                        modifier = Modifier
                            .size(48.dp)
                            .clip(CircleShape)
                            .background(MaterialTheme.colorScheme.surfaceVariant),
                        contentAlignment = Alignment.Center
                    ) {
                        if (avatarUrl.isNotBlank()) {
                            AsyncImage(
                                model = avatarUrl,
                                contentDescription = "Foto de perfil",
                                modifier = Modifier.fillMaxSize().clip(CircleShape),
                                contentScale = ContentScale.Crop
                            )
                        } else {
                            Text(
                                text = displayName.take(2).uppercase(),
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                    // Quality score circle
                    Box(
                        modifier = Modifier
                            .size(64.dp)
                            .clip(CircleShape)
                            .background(scoreColor.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Row(
                            verticalAlignment = Alignment.Bottom,
                            horizontalArrangement = Arrangement.Center
                        ) {
                            Text(
                                text = String.format("%.0f", avgScore),
                                fontSize = 24.sp,
                                fontWeight = FontWeight.Black,
                                color = scoreColor
                            )
                            Text(
                                text = "%",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Black,
                                color = scoreColor,
                                modifier = Modifier.padding(bottom = 2.dp, start = 1.dp)
                            )
                        }
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
                    label = "Estado QA",
                    value = statusText,
                    hint = "calidad promedio",
                    color = scoreColor,
                    modifier = Modifier.weight(1f)
                )
                HeroSignal(
                    label = "Feedback visto",
                    value = String.format("%.0f%%", responsePct),
                    hint = "respondido/publicado",
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
                    label = "Audios cargados",
                    value = transcriptSummary.optString("audio", "0"),
                    hint = "interacciones audio",
                    color = Cyan,
                    modifier = Modifier.weight(1f)
                )
                HeroSignal(
                    label = "Alertas abiertas",
                    value = summary.optString("open_alerts", "0"),
                    hint = "riesgos activos",
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
private fun HeroSignal(label: String, value: String, color: Color, modifier: Modifier = Modifier, hint: String = "") {
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
        if (hint.isNotBlank()) {
            Spacer(modifier = Modifier.height(3.dp))
            Text(
                text = hint,
                fontSize = 9.sp,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.48f),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
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
                title = "Evals visibles",
                value = overview.optString("total_evaluations", "0"),
                subtitle = "según tu rol",
                icon = Icons.Default.Assessment,
                color = Blue,
                modifier = Modifier.weight(1f)
            )
            DashboardMiniMetric(
                title = "Rev. monitor",
                value = summary.optString("pending_reviews", summary.optString("monitor_pending", "0")),
                subtitle = "pendientes",
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
                title = "Feedback pend.",
                value = feedbackSummary.optString("pending_response", "0"),
                subtitle = "sin respuesta",
                icon = Icons.Default.Chat,
                color = Violet,
                modifier = Modifier.weight(1f)
            )
            DashboardMiniMetric(
                title = "Audio IA cola",
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
    
    val levelLower = level.lowercase()
    val levelColor = when {
        levelLower.contains("diamante") || levelLower.contains("esmeralda") || levelLower.contains("q1") -> Color(0xFF10B981) // Green
        levelLower.contains("oro") || levelLower.contains("q2") -> Color(0xFFF59E0B) // Amber/Gold
        levelLower.contains("plata") || levelLower.contains("q3") -> Color(0xFF6366F1) // Indigo
        levelLower.contains("bronce") || levelLower.contains("q4") -> Color(0xFFD97706) // Brown/Bronze
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

@Composable
private fun FilterDropdown(
    label: String,
    selectedId: String,
    options: List<Pair<String, String>>,
    onSelected: (String) -> Unit
) {
    var expanded by remember { mutableStateOf(false) }
    val selectedName = options.find { it.first == selectedId }?.second ?: label

    Box {
        OutlinedButton(
            onClick = { expanded = true },
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(10.dp),
            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
        ) {
            Text(
                text = if (selectedId.isNotEmpty()) selectedName else label,
                fontSize = 13.sp,
                color = if (selectedId.isNotEmpty()) MaterialTheme.colorScheme.onSurface
                        else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                modifier = Modifier.weight(1f),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Icon(Icons.Default.ArrowDropDown, contentDescription = null, modifier = Modifier.size(18.dp))
        }

        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            DropdownMenuItem(
                text = { Text(label, fontSize = 13.sp) },
                onClick = { onSelected(""); expanded = false }
            )
            options.forEach { (id, name) ->
                DropdownMenuItem(
                    text = { Text(name, fontSize = 13.sp) },
                    onClick = { onSelected(id); expanded = false }
                )
            }
        }
    }
}
